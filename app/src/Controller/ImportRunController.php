<?php

namespace App\Controller;

use App\Repository\ImportRunRepository;
use App\Repository\SourceRepository;
use App\Service\RssImporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/imports')]
class ImportRunController extends AbstractController
{
    #[Route('', name: 'app_import_run_index', methods: ['GET'])]
    public function index(Request $request, ImportRunRepository $importRunRepository, SourceRepository $sourceRepository): Response
    {
        $source = $request->query->getInt('source') > 0 ? $sourceRepository->find($request->query->getInt('source')) : null;

        return $this->render('import_run/index.html.twig', [
            'runs' => $importRunRepository->findLatestFiltered($source),
            'sources' => $sourceRepository->findAllOrdered(),
            'filters' => [
                'source' => $source?->getId(),
            ],
        ]);
    }

    #[Route('/import-sources', name: 'app_import_run_import_sources', methods: ['POST'])]
    public function importSources(Request $request, SourceRepository $sourceRepository, RssImporter $rssImporter): Response
    {
        if (!$this->isCsrfTokenValid('import_sources', (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('app_import_run_index');
        }

        $sources = $sourceRepository->findActiveRssSources();

        if ($sources === []) {
            $this->addFlash('error', 'Aucune source RSS active à importer.');

            return $this->redirectToRoute('app_import_run_index');
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

        return $this->redirectToRoute('app_import_run_index');
    }
}
