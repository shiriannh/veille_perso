<?php

namespace App\Controller;

use App\Entity\Entry;
use App\Enum\EntryStatus;
use App\Enum\MediaType;
use App\Form\EntryType;
use App\Repository\EntryRepository;
use App\Repository\SourceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/entries')]
class EntryController extends AbstractController
{
    #[Route('', name: 'app_entry_index', methods: ['GET'])]
    public function index(Request $request, EntryRepository $entryRepository, SourceRepository $sourceRepository): Response
    {
        $source = $request->query->getInt('source') > 0 ? $sourceRepository->find($request->query->getInt('source')) : null;
        $mediaType = MediaType::tryFrom((string) $request->query->get('mediaType'));
        $status = EntryStatus::tryFrom((string) $request->query->get('status'));
        $interestLevel = $request->query->has('interestLevel') && $request->query->get('interestLevel') !== ''
            ? max(0, min(5, $request->query->getInt('interestLevel')))
            : null;
        $reviewState = in_array($request->query->get('reviewState'), ['with', 'without'], true)
            ? (string) $request->query->get('reviewState')
            : null;
        $sort = in_array($request->query->get('sort'), ['published_desc', 'published_asc', 'imported_desc', 'imported_asc'], true)
            ? (string) $request->query->get('sort')
            : null;

        $filters = [
            'q' => $request->query->get('q'),
            'source' => $source,
            'mediaType' => $mediaType,
            'status' => $status,
            'interestLevel' => $interestLevel,
            'reviewState' => $reviewState,
            'sort' => $sort,
        ];

        return $this->render('entry/index.html.twig', [
            'entries' => $entryRepository->findFiltered($filters),
            'sources' => $sourceRepository->findAllOrdered(),
            'media_types' => MediaType::cases(),
            'statuses' => EntryStatus::cases(),
            'filters' => [
                'q' => (string) $request->query->get('q', ''),
                'source' => $source?->getId(),
                'mediaType' => $mediaType?->value,
                'status' => $status?->value,
                'interestLevel' => $interestLevel,
                'reviewState' => $reviewState,
                'sort' => $sort ?? '',
            ],
        ]);
    }

    #[Route('/new', name: 'app_entry_new', methods: ['GET', 'POST'])]
    public function new(Request $request, SourceRepository $sourceRepository, EntityManagerInterface $entityManager): Response
    {
        if ($sourceRepository->count([]) === 0) {
            $this->addFlash('error', 'Crée au moins une source avant de saisir une entrée.');

            return $this->redirectToRoute('app_source_new');
        }

        $entry = new Entry();
        $form = $this->createForm(EntryType::class, $entry);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($entry);
            $entityManager->flush();

            $this->addFlash('success', 'Entrée créée.');

            return $this->redirectToRoute('app_entry_index');
        }

        return $this->render('entry/new.html.twig', [
            'entry' => $entry,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_entry_show', methods: ['GET'])]
    public function show(Entry $entry): Response
    {
        return $this->render('entry/show.html.twig', [
            'entry' => $entry,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_entry_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Entry $entry, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(EntryType::class, $entry);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Entrée mise à jour.');

            return $this->redirectToRoute('app_entry_index');
        }

        return $this->render('entry/edit.html.twig', [
            'entry' => $entry,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_entry_delete', methods: ['POST'])]
    public function delete(Request $request, Entry $entry, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete_entry_'.$entry->getId(), (string) $request->request->get('_token'))) {
            if ($entry->getReview() !== null) {
                $this->addFlash('error', 'Impossible de supprimer une entrée qui possède une fiche. Supprime d’abord la fiche associée.');

                return $this->redirectToRoute('app_entry_index');
            }

            $entityManager->remove($entry);
            $entityManager->flush();
            $this->addFlash('success', 'Entrée supprimée.');
        }

        return $this->redirectToRoute('app_entry_index');
    }
}
