<?php

namespace App\Controller;

use App\Entity\Source;
use App\Enum\FetchMode;
use App\Form\SourceCsvImportType;
use App\Form\SourceType;
use App\Repository\ImportRunRepository;
use App\Repository\SourceRepository;
use App\Service\DatabaseResetter;
use App\Service\RssImporter;
use App\Service\SourceCsvImporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/sources')]
class SourceController extends AbstractController
{
    #[Route('', name: 'app_source_index', methods: ['GET'])]
    public function index(SourceRepository $sourceRepository): Response
    {
        return $this->render('source/index.html.twig', [
            'sources' => $sourceRepository->findAllOrdered(),
        ]);
    }

    #[Route('/new', name: 'app_source_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $source = new Source();
        $form = $this->createForm(SourceType::class, $source);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($source);
            $entityManager->flush();

            $this->addFlash('success', 'Source créée.');

            return $this->redirectToRoute('app_source_index');
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
            $result = $sourceCsvImporter->import($form->get('file')->getData());

            if (!$result->hasErrors()) {
                $this->addFlash('success', sprintf('%d sources importees avec succes.', $result->importedCount()));

                return $this->redirectToRoute('app_source_index');
            }

            $errors = $result->errors();
            $this->addFlash('error', 'Import annule : le CSV contient des erreurs.');
        }

        return $this->render('source/import_csv.html.twig', [
            'form' => $form,
            'errors' => $errors,
        ]);
    }

    #[Route('/reset-database', name: 'app_source_reset_database', methods: ['POST'])]
    public function resetDatabase(Request $request, DatabaseResetter $databaseResetter): Response
    {
        if (!$this->isCsrfTokenValid('reset_database', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide, remise a zero annulee.');

            return $this->redirectToRoute('app_source_index');
        }

        $counts = $databaseResetter->reset();

        $this->addFlash('success', sprintf(
            'Base videe : %d source(s), %d entree(s), %d fiche(s), %d synthese(s), %d import(s) supprime(s).',
            $counts['sources'],
            $counts['entries'],
            $counts['reviews'],
            $counts['reports'],
            $counts['importRuns'],
        ));

        return $this->redirectToRoute('app_source_index');
    }

    #[Route('/{id}', name: 'app_source_show', methods: ['GET'])]
    public function show(Source $source, ImportRunRepository $importRunRepository): Response
    {
        return $this->render('source/show.html.twig', [
            'source' => $source,
            'import_runs' => $importRunRepository->findLatestForSource($source),
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

        $run = $rssImporter->import($source);

        if ($run->getErrorMessage() !== null) {
            $this->addFlash('error', 'Import échoué : '.$run->getErrorMessage());
        } else {
            $this->addFlash('success', sprintf(
                'Import terminé : %d créée(s), %d ignorée(s).',
                $run->getCreatedCount(),
                $run->getSkippedCount(),
            ));
        }

        return $this->redirectToRoute('app_source_show', ['id' => $source->getId()]);
    }

    #[Route('/{id}/edit', name: 'app_source_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Source $source, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(SourceType::class, $source);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
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

        return $this->redirectToRoute('app_source_index');
    }
}
