<?php

namespace App\Controller;

use App\Entity\Source;
use App\Enum\FetchMode;
use App\Form\SourceCsvImportType;
use App\Form\SourceType;
use App\Repository\ImportRunRepository;
use App\Repository\SourceRepository;
use App\Service\DatabaseResetter;
use App\Service\ArrayPaginator;
use App\Service\LesLibrairesImporter;
use App\Service\ReferenceFieldSynchronizer;
use App\Service\RssFeedInspector;
use App\Service\RssImporter;
use App\Service\SourceCsvImporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/sources')]
class SourceController extends AbstractController
{
    #[Route('', name: 'app_source_index', methods: ['GET'])]
    public function index(
        Request $request,
        SourceRepository $sourceRepository,
        ImportRunRepository $importRunRepository,
        ArrayPaginator $arrayPaginator,
    ): Response
    {
        $activeRssOnly = $request->query->getBoolean('activeRssOnly');
        $lastImportError = $request->query->getBoolean('lastImportError');
        $sources = $sourceRepository->findFiltered($activeRssOnly, $lastImportError);
        [$paginatedSources, $pagination] = $arrayPaginator->paginate($request, $sources, '25');
        $lastImportRuns = [];

        foreach ($paginatedSources as $source) {
            $lastImportRuns[$source->getId()] = $importRunRepository->findLatestForSource($source, 1)[0] ?? null;
        }

        return $this->render('source/index.html.twig', [
            'sources' => $paginatedSources,
            'pagination' => $pagination,
            'last_import_runs' => $lastImportRuns,
            'filters' => [
                'activeRssOnly' => $activeRssOnly,
                'lastImportError' => $lastImportError,
            ],
        ]);
    }

    #[Route('/new', name: 'app_source_new', methods: ['GET', 'POST'])]
    public function new(Request $request, ReferenceFieldSynchronizer $referenceFieldSynchronizer, EntityManagerInterface $entityManager): Response
    {
        $source = new Source();
        $referenceFieldSynchronizer->syncSourceToReferences($source);
        $form = $this->createForm(SourceType::class, $source);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $referenceFieldSynchronizer->syncSourceFromReferences($source);
            $referenceFieldSynchronizer->syncSourceToReferences($source);
            $entityManager->persist($source);
            $entityManager->flush();

            $this->addFlash('success', 'Source créée.');

            return $this->redirectToRoute('app_source_index', $request->query->all());
        }

        return $this->render('source/new.html.twig', [
            'source' => $source,
            'form' => $form,
        ]);
    }

    #[Route('/import-csv', name: 'app_source_import_csv', methods: ['GET', 'POST'])]
    public function importCsv(Request $request, SourceCsvImporter $sourceCsvImporter): Response
    {
        $form = $this->createForm(SourceCsvImportType::class);
        $form->handleRequest($request);
        $errors = [];

        if ($form->isSubmitted() && $form->isValid()) {
            $simulate = (bool) $form->get('simulate')->getData();
            $duplicateRule = (string) $form->get('duplicateRule')->getData();
            $result = $sourceCsvImporter->import($form->get('file')->getData(), !$simulate, $duplicateRule);

            if (!$result->hasErrors()) {
                $this->addFlash('success', $simulate
                    ? sprintf('Simulation valide : %d source(s) pourraient etre importees.', $result->importedCount())
                    : sprintf('%d sources importees avec succes.', $result->importedCount()));

                return $this->redirectToRoute('app_source_index');
            }

            $errors = $result->errors();
            $request->getSession()->set('source_csv_errors', $errors);
            $this->addFlash('error', 'Import annule : le CSV contient des erreurs.');
        }

        return $this->render('source/import_csv.html.twig', [
            'form' => $form,
            'errors' => $errors,
        ]);
    }

    #[Route('/import-csv/template', name: 'app_source_import_csv_template', methods: ['GET'])]
    public function importCsvTemplate(): Response
    {
        $content = "name;type;url;isActive;fetchMode;feedUrl;notes\n"
            ."Actu SF;website;https://example.org;true;rss;https://example.org/feed.xml;Veille science-fiction\n"
            ."Site manuel;website;https://example.net;oui;manual;;A consulter ponctuellement\n";

        $response = new Response($content);
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'modele_sources.csv',
        ));

        return $response;
    }

    #[Route('/import-csv/error-report', name: 'app_source_import_csv_error_report', methods: ['GET'])]
    public function importCsvErrorReport(Request $request): Response
    {
        $errors = $request->getSession()->get('source_csv_errors', []);
        $content = "Rapport d'erreurs import CSV\n===========================\n\n";
        foreach ($errors as $error) {
            $content .= '- '.$error."\n";
        }

        $response = new Response($content);
        $response->headers->set('Content-Type', 'text/plain; charset=UTF-8');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'rapport_erreurs_sources.txt',
        ));

        return $response;
    }

    #[Route('/reset-database', name: 'app_source_reset_database', methods: ['POST'])]
    public function resetDatabase(Request $request, DatabaseResetter $databaseResetter): Response
    {
        if (!$this->isCsrfTokenValid('reset_database', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide, remise a zero annulee.');

            return $this->redirectToRoute('app_source_index', $request->query->all());
        }

        $selectedScopes = array_map('strval', $request->request->all('scopes'));
        $counts = $databaseResetter->reset($selectedScopes);
        $scopeLabel = $selectedScopes === [] ? 'tout' : implode(', ', $selectedScopes);

        $this->addFlash('success', sprintf(
            'RAZ terminee (%s) : %d source(s), %d entree(s), %d fiche(s), %d synthese(s), %d import(s) supprime(s).',
            $scopeLabel,
            $counts['sources'],
            $counts['entries'],
            $counts['reviews'],
            $counts['reports'],
            $counts['importRuns'],
        ));

        return $this->redirectToRoute('app_source_index', $request->query->all());
    }

    #[Route('/{id}', name: 'app_source_show', methods: ['GET'])]
    public function show(Source $source, ImportRunRepository $importRunRepository): Response
    {
        return $this->render('source/show.html.twig', [
            'source' => $source,
            'import_runs' => $importRunRepository->findLatestForSource($source),
            'rss_test' => null,
            'rss_preview' => null,
            'leslibraires_preview' => null,
        ]);
    }

    #[Route('/{id}/test-rss', name: 'app_source_test_rss', methods: ['POST'])]
    public function testRss(Request $request, Source $source, ImportRunRepository $importRunRepository, RssFeedInspector $rssFeedInspector): Response
    {
        if (!$this->isCsrfTokenValid('test_source_rss_'.$source->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide, test RSS annule.');

            return $this->redirectToRoute('app_source_show', ['id' => $source->getId()]);
        }

        $result = $rssFeedInspector->inspect($source, 5);
        $this->addFlash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Flux RSS valide.' : 'Flux RSS invalide.');

        return $this->render('source/show.html.twig', [
            'source' => $source,
            'import_runs' => $importRunRepository->findLatestForSource($source),
            'rss_test' => $result,
            'rss_preview' => null,
            'leslibraires_preview' => null,
        ]);
    }

    #[Route('/{id}/preview-rss', name: 'app_source_preview_rss', methods: ['POST'])]
    public function previewRss(Request $request, Source $source, ImportRunRepository $importRunRepository, RssFeedInspector $rssFeedInspector): Response
    {
        if (!$this->isCsrfTokenValid('preview_source_rss_'.$source->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide, previsualisation annulee.');

            return $this->redirectToRoute('app_source_show', ['id' => $source->getId()]);
        }

        $result = $rssFeedInspector->inspect($source, 10);

        return $this->render('source/show.html.twig', [
            'source' => $source,
            'import_runs' => $importRunRepository->findLatestForSource($source),
            'rss_test' => null,
            'rss_preview' => $result,
            'leslibraires_preview' => null,
        ]);
    }

    #[Route('/{id}/preview-leslibraires', name: 'app_source_preview_leslibraires', methods: ['POST'])]
    public function previewLesLibraires(Request $request, Source $source, ImportRunRepository $importRunRepository, LesLibrairesImporter $lesLibrairesImporter): Response
    {
        if (!$this->isCsrfTokenValid('preview_leslibraires_'.$source->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide, previsualisation annulee.');

            return $this->redirectToRoute('app_source_show', ['id' => $source->getId()]);
        }

        try {
            $window = trim((string) $request->request->get('window', '')) ?: null;
            $preview = $lesLibrairesImporter->preview($source, $window, 10);
        } catch (\Throwable $exception) {
            $preview = ['window' => null, 'pagesVisited' => 0, 'candidates' => [], 'books' => [], 'errors' => [$exception->getMessage()]];
            $this->addFlash('error', 'Previsualisation leslibraires.fr impossible.');
        }

        return $this->render('source/show.html.twig', [
            'source' => $source,
            'import_runs' => $importRunRepository->findLatestForSource($source),
            'rss_test' => null,
            'rss_preview' => null,
            'leslibraires_preview' => $preview,
        ]);
    }

    #[Route('/{id}/import', name: 'app_source_import', methods: ['POST'])]
    public function import(Request $request, Source $source, RssImporter $rssImporter): Response
    {
        if (!$this->isCsrfTokenValid('import_source_'.$source->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('app_source_show', ['id' => $source->getId()]);
        }

        if ($source->getFetchMode() !== FetchMode::Rss) {
            $this->addFlash('error', 'Cette source n’est pas configurée en import RSS.');

            return $this->redirectToRoute('app_source_show', ['id' => $source->getId()]);
        }

        $run = $rssImporter->import($source, $request->request->getBoolean('analyze', true));

        if ($run->getErrorMessage() !== null) {
            $this->addFlash('error', 'Import échoué : '.$run->getErrorMessage());
        } else {
            $this->addFlash('success', sprintf(
                'Import terminé : %d créée(s), %d ignorée(s).',
                $run->getCreatedCount(),
                $run->getSkippedCount(),
            ));
        }

        if ($run->getStatus()->value !== 'failed') {
            $summary = $this->formatAdmissionSummary($run->getDetails()['admission'] ?? null);
            if ($summary !== '') {
                $this->addFlash('info', 'Sas admission : '.$summary.'.');
            }
        }

        return $this->redirectToRoute('app_source_show', ['id' => $source->getId()]);
    }

    #[Route('/{id}/import-leslibraires', name: 'app_source_import_leslibraires', methods: ['POST'])]
    public function importLesLibraires(Request $request, Source $source, LesLibrairesImporter $lesLibrairesImporter): Response
    {
        if (!$this->isCsrfTokenValid('import_leslibraires_'.$source->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide, collecte annulee.');

            return $this->redirectToRoute('app_source_show', ['id' => $source->getId()]);
        }

        if ($source->getFetchMode() !== FetchMode::LesLibrairesCatalog) {
            $this->addFlash('error', 'Cette source n est pas configuree en connecteur leslibraires.fr.');

            return $this->redirectToRoute('app_source_show', ['id' => $source->getId()]);
        }

        $run = $lesLibrairesImporter->import(
            $source,
            trim((string) $request->request->get('window', '')) ?: null,
            $request->request->getBoolean('analyze', true),
        );

        if ($run->getStatus()->value === 'failed') {
            $this->addFlash('error', 'Collecte echouee : '.$run->getErrorMessage());
        } else {
            $summary = $lesLibrairesImporter->lastSummary();
            $this->addFlash('success', sprintf(
                'Collecte terminee : %d page(s), %d candidat(s), %d fiche(s) ouverte(s), %d creee(s), %d ignoree(s).',
                $summary['pagesVisited'],
                $summary['candidatesCount'],
                $summary['detailsOpened'],
                $run->getCreatedCount(),
                $run->getSkippedCount(),
            ));
        }

        if ($run->getStatus()->value !== 'failed') {
            $summary = $this->formatAdmissionSummary($run->getDetails()['admission'] ?? null);
            if ($summary !== '') {
                $this->addFlash('info', 'Sas admission : '.$summary.'.');
            }
        }

        return $this->redirectToRoute('app_source_show', ['id' => $source->getId()]);
    }

    #[Route('/{id}/edit', name: 'app_source_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Source $source, ReferenceFieldSynchronizer $referenceFieldSynchronizer, EntityManagerInterface $entityManager): Response
    {
        $referenceFieldSynchronizer->syncSourceToReferences($source);
        $form = $this->createForm(SourceType::class, $source);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $referenceFieldSynchronizer->syncSourceFromReferences($source);
            $referenceFieldSynchronizer->syncSourceToReferences($source);
            $entityManager->flush();

            $this->addFlash('success', 'Source mise à jour.');

            return $this->redirectToRoute('app_source_index');
        }

        return $this->render('source/edit.html.twig', [
            'source' => $source,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_source_delete', methods: ['POST'])]
    public function delete(Request $request, Source $source, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete_source_'.$source->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('app_source_index');
        }

        if ($source->getEntries()->count() > 0) {
            $this->addFlash('error', 'Impossible de supprimer une source qui contient des entrées.');

            return $this->redirectToRoute('app_source_show', ['id' => $source->getId()]);
        }

        $entityManager->remove($source);
        $entityManager->flush();
        $this->addFlash('success', 'Source supprimée.');

        return $this->redirectToRoute('app_source_index', $request->query->all());
    }

    private function formatAdmissionSummary(mixed $admission): string
    {
        if (!is_array($admission)) {
            return '';
        }

        return sprintf(
            '%d admis, %d en quarantaine, %d rejetes',
            (int) ($admission['admitted'] ?? 0),
            (int) ($admission['quarantined'] ?? 0),
            (int) ($admission['rejected'] ?? 0),
        );
    }
}
