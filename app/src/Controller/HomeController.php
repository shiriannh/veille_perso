<?php

namespace App\Controller;

use App\Repository\EntryRepository;
use App\Repository\ReviewRepository;
use App\Repository\SourceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(
        SourceRepository $sourceRepository,
        EntryRepository $entryRepository,
        ReviewRepository $reviewRepository,
    ): Response {
        return $this->render('home/index.html.twig', [
            'source_count' => $sourceRepository->count([]),
            'entry_count' => $entryRepository->count([]),
            'unreviewed_entry_count' => $entryRepository->countWithoutReview(),
            'review_count' => $reviewRepository->count([]),
            'latest_entries' => $entryRepository->findLatest(5),
            'latest_reviews' => $reviewRepository->findLatestUpdated(5),
        ]);
    }
}
