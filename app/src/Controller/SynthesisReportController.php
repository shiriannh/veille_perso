<?php

namespace App\Controller;

use App\Entity\SynthesisReport;
use App\Form\SynthesisReportGenerateType;
use App\Repository\SynthesisReportRepository;
use App\Service\SynthesisReportGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/syntheses')]
class SynthesisReportController extends AbstractController
{
    #[Route('', name: 'app_synthesis_report_index', methods: ['GET'])]
    public function index(SynthesisReportRepository $synthesisReportRepository): Response
    {
        return $this->render('synthesis_report/index.html.twig', [
            'reports' => $synthesisReportRepository->findLatest(),
        ]);
    }

    #[Route('/new', name: 'app_synthesis_report_new', methods: ['GET', 'POST'])]
    public function new(Request $request, SynthesisReportGenerator $generator): Response
    {
        $form = $this->createForm(SynthesisReportGenerateType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $report = $generator->generate(
                $form->get('title')->getData(),
                $form->get('notes')->getData(),
                (bool) $form->get('includeMaybeRelevant')->getData(),
            );

            if ($report->getEntries()->count() === 0) {
                $this->addFlash('success', 'Synthèse créée, aucune nouvelle entrée pertinente à inclure.');
            } else {
                $this->addFlash('success', sprintf('Synthèse créée : %d entrée(s) incluse(s).', $report->getEntries()->count()));
            }

            return $this->redirectToRoute('app_synthesis_report_show', ['id' => $report->getId()]);
        }

        return $this->render('synthesis_report/new.html.twig', [
            'form' => $form,
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
}
