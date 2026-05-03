<?php

namespace App\Controller;

use App\Entity\AnalysisLanguageReference;
use App\Entity\ClickbaitLevelReference;
use App\Entity\DecisionTypeReference;
use App\Entity\FetchModeReference;
use App\Entity\MediaTypeReference;
use App\Entity\SourceTypeReference;
use App\Entity\Tag;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
class AdminController extends AbstractController
{
    #[Route('', name: 'app_admin_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/index.html.twig', [
            'sections' => [
                ['label' => 'Media types', 'route' => 'app_admin_reference_index', 'params' => ['type' => 'media-types'], 'class' => MediaTypeReference::class],
                ['label' => 'Tags', 'route' => 'app_admin_tag_index', 'params' => [], 'class' => Tag::class],
                ['label' => 'Source types', 'route' => 'app_admin_reference_index', 'params' => ['type' => 'source-types'], 'class' => SourceTypeReference::class],
                ['label' => 'Fetch modes', 'route' => 'app_admin_reference_index', 'params' => ['type' => 'fetch-modes'], 'class' => FetchModeReference::class],
                ['label' => 'Decision types', 'route' => 'app_admin_reference_index', 'params' => ['type' => 'decision-types'], 'class' => DecisionTypeReference::class],
                ['label' => 'Clickbait levels', 'route' => 'app_admin_reference_index', 'params' => ['type' => 'clickbait-levels'], 'class' => ClickbaitLevelReference::class],
                ['label' => 'Analysis languages', 'route' => 'app_admin_reference_index', 'params' => ['type' => 'analysis-languages'], 'class' => AnalysisLanguageReference::class],
            ],
        ]);
    }
}
