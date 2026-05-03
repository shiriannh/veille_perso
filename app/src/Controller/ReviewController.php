<?php

namespace App\Controller;

use App\Entity\Review;
use App\Enum\MediaType;
use App\Enum\ReviewVerdict;
use App\Form\ReviewType;
use App\Repository\EntryRepository;
use App\Repository\ReviewRepository;
use App\Repository\SourceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/reviews')]
class ReviewController extends AbstractController
{
    #[Route('', name: 'app_review_index', methods: ['GET'])]
    public function index(Request $request, ReviewRepository $reviewRepository, SourceRepository $sourceRepository): Response
    {
        $verdict = ReviewVerdict::tryFrom((string) $request->query->get('verdict'));
        $mediaType = MediaType::tryFrom((string) $request->query->get('mediaType'));
        $source = $request->query->getInt('source') > 0 ? $sourceRepository->find($request->query->getInt('source')) : null;
        $minScore = $request->query->has('minScore') && $request->query->get('minScore') !== ''
            ? max(0, min(100, $request->query->getInt('minScore')))
            : null;
        $sort = in_array($request->query->get('sort'), ['updated_desc', 'updated_asc', 'score_desc', 'score_asc'], true)
            ? (string) $request->query->get('sort')
            : null;

        $filters = [
            'verdict' => $verdict,
            'mediaType' => $mediaType,
            'source' => $source,
            'minScore' => $minScore,
            'sort' => $sort,
        ];

        return $this->render('review/index.html.twig', [
            'reviews' => $reviewRepository->findFiltered($filters),
            'sources' => $sourceRepository->findAllOrdered(),
            'media_types' => MediaType::cases(),
            'verdicts' => ReviewVerdict::cases(),
            'filters' => [
                'verdict' => $verdict?->value,
                'mediaType' => $mediaType?->value,
                'source' => $source?->getId(),
                'minScore' => $minScore,
                'sort' => $sort ?? '',
            ],
        ]);
    }

    #[Route('/new', name: 'app_review_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntryRepository $entryRepository, EntityManagerInterface $entityManager): Response
    {
        if ($entryRepository->count([]) === 0) {
            $this->addFlash('error', 'Crée au moins une entrée avant de créer une fiche.');

            return $this->redirectToRoute('app_entry_new');
        }

        if ($entryRepository->countWithoutReview() === 0 && $request->query->getInt('entry') === 0) {
            $this->addFlash('error', 'Toutes les entrées ont déjà une fiche.');

            return $this->redirectToRoute('app_review_index');
        }

        $review = new Review();
        $entryId = $request->query->getInt('entry');

        if ($entryId > 0) {
            $entry = $entryRepository->find($entryId);

            if ($entry === null) {
                $this->addFlash('error', 'Entrée introuvable.');

                return $this->redirectToRoute('app_entry_index');
            }

            if ($entry->getReview() !== null) {
                return $this->redirectToRoute('app_review_edit', ['id' => $entry->getReview()->getId()]);
            }

            $review->setEntry($entry);
        }

        $form = $this->createForm(ReviewType::class, $review, [
            'current_entry' => $review->getEntry(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($review);
            $entityManager->flush();

            $this->addFlash('success', 'Fiche créée.');

            return $this->redirectToRoute('app_review_index');
        }

        return $this->render('review/new.html.twig', [
            'review' => $review,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_review_show', methods: ['GET'])]
    public function show(Review $review): Response
    {
        return $this->render('review/show.html.twig', [
            'review' => $review,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_review_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Review $review, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ReviewType::class, $review, [
            'current_entry' => $review->getEntry(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Fiche mise à jour.');

            return $this->redirectToRoute('app_review_index');
        }

        return $this->render('review/edit.html.twig', [
            'review' => $review,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_review_delete', methods: ['POST'])]
    public function delete(Request $request, Review $review, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete_review_'.$review->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($review);
            $entityManager->flush();
            $this->addFlash('success', 'Fiche supprimée.');
        }

        return $this->redirectToRoute('app_review_index');
    }
}
