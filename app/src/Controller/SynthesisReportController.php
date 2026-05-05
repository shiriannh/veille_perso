<?php

namespace App\Controller;

use App\Entity\SynthesisReport;
use App\Form\SynthesisReportGenerateType;
use App\Repository\SynthesisReportRepository;
use App\Service\ArrayPaginator;
use App\Service\SynthesisReportGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/syntheses')]
class SynthesisReportController extends AbstractController
{
    #[Route('', name: 'app_synthesis_report_index', methods: ['GET'])]
    public function index(Request $request, SynthesisReportRepository $synthesisReportRepository, ArrayPaginator $arrayPaginator): Response
    {
        $reports = $synthesisReportRepository->findLatest();
        [$paginatedReports, $pagination] = $arrayPaginator->paginate($request, $reports, '25');

        return $this->render('synthesis_report/index.html.twig', [
            'reports' => $paginatedReports,
            'pagination' => $pagination,
        ]);
    }

    #[Route('/new', name: 'app_synthesis_report_new', methods: ['GET', 'POST'])]
    public function new(Request $request, SynthesisReportGenerator $generator): Response
    {
        $form = $this->createForm(SynthesisReportGenerateType::class);
        $form->handleRequest($request);
        $previewEntries = [];

        if ($form->isSubmitted() && $form->isValid()) {
            $criteria = $this->criteriaFromForm($form->getData());

            if ($request->request->get('mode') === 'preview') {
                $previewEntries = $generator->preview($criteria);
                $this->addFlash('success', sprintf('Previsualisation : %d entree(s) seraient incluses.', count($previewEntries)));
            } else {
                $report = $generator->generate($criteria);

                if ($report->getEntries()->count() === 0) {
                    $this->addFlash('success', 'Synthese creee, aucune nouvelle entree pertinente a inclure.');
                } else {
                    $this->addFlash('success', sprintf('Synthese creee : %d entree(s) incluse(s).', $report->getEntries()->count()));
                }

                return $this->redirectToRoute('app_synthesis_report_show', ['id' => $report->getId()]);
            }
        }

        return $this->render('synthesis_report/new.html.twig', [
            'form' => $form,
            'preview_entries' => $previewEntries,
            'preview_groups' => $generator->groupByMediaType($previewEntries),
        ]);
    }

    #[Route('/{id}', name: 'app_synthesis_report_show', methods: ['GET'])]
    public function show(SynthesisReport $report, SynthesisReportGenerator $generator): Response
    {
        return $this->render('synthesis_report/show.html.twig', [
            'report' => $report,
            'groups' => $generator->groupByMediaType($report->getEntries()),
        ]);
    }

    #[Route('/{id}/regenerate', name: 'app_synthesis_report_regenerate', methods: ['POST'])]
    public function regenerate(Request $request, SynthesisReport $report, SynthesisReportGenerator $generator): Response
    {
        if (!$this->isCsrfTokenValid('regenerate_synthesis_report_'.$report->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide, regeneration annulee.');

            return $this->redirectToRoute('app_synthesis_report_show', ['id' => $report->getId()]);
        }

        $newReport = $generator->regenerate($report);
        $this->addFlash('success', sprintf('Synthese regeneree : %d entree(s) incluse(s).', $newReport->getEntries()->count()));

        return $this->redirectToRoute('app_synthesis_report_show', ['id' => $newReport->getId()]);
    }

    #[Route('/{id}/export/markdown', name: 'app_synthesis_report_export_markdown', methods: ['GET'])]
    public function exportMarkdown(SynthesisReport $report, SynthesisReportGenerator $generator): Response
    {
        $response = new Response($generator->exportMarkdown($report));
        $response->headers->set('Content-Type', 'text/markdown; charset=UTF-8');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $this->filename($report, 'md'),
        ));

        return $response;
    }

    #[Route('/{id}/export/html', name: 'app_synthesis_report_export_html', methods: ['GET'])]
    public function exportHtml(SynthesisReport $report, SynthesisReportGenerator $generator): Response
    {
        $response = new Response($generator->exportStandaloneHtml($report));
        $response->headers->set('Content-Type', 'text/html; charset=UTF-8');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $this->filename($report, 'html'),
        ));

        return $response;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function criteriaFromForm(array $data): array
    {
        return [
            'title' => $data['title'] ?? null,
            'notes' => $data['notes'] ?? null,
            'fromDate' => $data['fromDate'] ?? null,
            'toDate' => $data['toDate'] ?? null,
            'mediaTypes' => $data['mediaTypes'] ?? [],
            'includeMaybeRelevant' => (bool) ($data['includeMaybeRelevant'] ?? false),
            'maybeMinimumScore' => $data['maybeMinimumScore'] ?? null,
            'minimumInterestLevel' => $data['minimumInterestLevel'] ?? null,
            'excludeCommercial' => (bool) ($data['excludeCommercial'] ?? false),
        ];
    }

    private function filename(SynthesisReport $report, string $extension): string
    {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $report->getTitle()) ?? 'synthese');
        $slug = trim($slug, '-') ?: 'synthese';

        return $slug.'.'.$extension;
    }
}
