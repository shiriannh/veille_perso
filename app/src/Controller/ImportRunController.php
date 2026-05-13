<?php

namespace App\Controller;

use App\Repository\ImportRunRepository;
use App\Repository\SourceRepository;
use App\Service\ArrayPaginator;
use App\Service\LesLibrairesImporter;
use App\Service\RssImporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/imports')]
class ImportRunController extends AbstractController
{
    #[Route('', name: 'app_import_run_index', methods: ['GET'])]
    public function index(
        Request $request,
        ImportRunRepository $importRunRepository,
        SourceRepository $sourceRepository,
        ArrayPaginator $arrayPaginator,
    ): Response
    {
        $sourceId = (string) $request->query->get('source', '');
        $source = ctype_digit($sourceId) && (int) $sourceId > 0 ? $sourceRepository->find((int) $sourceId) : null;
        $runs = $importRunRepository->findLatestFiltered($source);
        [$paginatedRuns, $pagination] = $arrayPaginator->paginate($request, $runs, '25');

        return $this->render('import_run/index.html.twig', [
            'runs' => $paginatedRuns,
            'pagination' => $pagination,
            'sources' => $sourceRepository->findAllOrdered(),
            'filters' => [
                'source' => $source?->getId(),
            ],
        ]);
    }

    #[Route('/import-connectors', name: 'app_import_run_import_connectors', methods: ['POST'])]
    public function importConnectors(Request $request, SourceRepository $sourceRepository, LesLibrairesImporter $lesLibrairesImporter): Response
    {
        if (!$this->isCsrfTokenValid('import_connectors', (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('app_import_run_index', $request->query->all());
        }

        $sources = $sourceRepository->findActiveLesLibrairesSources();

        if ($sources === []) {
            $this->addFlash('error', 'Aucune source connecteur active a importer.');

            return $this->redirectToRoute('app_import_run_index', $request->query->all());
        }

        $createdCount = 0;
        $skippedCount = 0;
        $errorCount = 0;
        $pagesVisited = 0;
        $candidatesCount = 0;
        $detailsOpened = 0;

        foreach ($sources as $source) {
            $run = $lesLibrairesImporter->import($source);
            $summary = $lesLibrairesImporter->lastSummary();
            $createdCount += $run->getCreatedCount();
            $skippedCount += $run->getSkippedCount();
            $pagesVisited += $summary['pagesVisited'];
            $candidatesCount += $summary['candidatesCount'];
            $detailsOpened += $summary['detailsOpened'];

            if ($run->getStatus()->value === 'failed') {
                ++$errorCount;
            }
        }

        $message = sprintf(
            'Connecteurs importes : %d source(s), %d page(s), %d candidat(s), %d fiche(s) ouverte(s), %d creee(s), %d ignoree(s).',
            count($sources),
            $pagesVisited,
            $candidatesCount,
            $detailsOpened,
            $createdCount,
            $skippedCount,
        );

        $this->addFlash($errorCount > 0 ? 'error' : 'success', $errorCount > 0 ? $message.' '.$errorCount.' erreur(s).' : $message);

        return $this->redirectToRoute('app_import_run_index', $request->query->all());
    }

    #[Route('/import-sources', name: 'app_import_run_import_sources', methods: ['POST'])]
    public function importSources(Request $request, SourceRepository $sourceRepository, RssImporter $rssImporter): Response
    {
        if (!$this->isCsrfTokenValid('import_sources', (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('app_import_run_index', $request->query->all());
        }

        $sources = $sourceRepository->findActiveRssSources();

        if ($sources === []) {
            $this->addFlash('error', 'Aucune source RSS active à importer.');

            return $this->redirectToRoute('app_import_run_index', $request->query->all());
        }

        $createdCount = 0;
        $skippedCount = 0;
        $errorCount = 0;

        foreach ($sources as $source) {
            $run = $rssImporter->import($source);
            $createdCount += $run->getCreatedCount();
            $skippedCount += $run->getSkippedCount();

            if ($run->getErrorMessage() !== null) {
                ++$errorCount;
            }
        }

        if ($errorCount > 0) {
            $this->addFlash('error', sprintf(
                'Import terminé avec %d erreur(s) : %d créée(s), %d ignorée(s).',
                $errorCount,
                $createdCount,
                $skippedCount,
            ));
        } else {
            $this->addFlash('success', sprintf(
                'Import terminé : %d source(s), %d créée(s), %d ignorée(s).',
                count($sources),
                $createdCount,
                $skippedCount,
            ));
        }

        return $this->redirectToRoute('app_import_run_index', $request->query->all());
    }
}
