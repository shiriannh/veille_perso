<?php

namespace App\Controller;

use App\Entity\AnalysisLanguageReference;
use App\Entity\ClickbaitLevelReference;
use App\Entity\DecisionTypeReference;
use App\Entity\Entry;
use App\Entity\FetchModeReference;
use App\Entity\InterestProfileRule;
use App\Entity\MediaTypeReference;
use App\Entity\ReferenceEntityInterface;
use App\Entity\SourceTypeReference;
use App\Entity\Tag;
use Doctrine\ORM\EntityManagerInterface;
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
                ['label' => 'Profil d interet', 'route' => 'app_admin_interest_profile_index', 'params' => [], 'class' => InterestProfileRule::class],
                ['label' => 'Tags', 'route' => 'app_admin_tag_index', 'params' => [], 'class' => Tag::class],
                ['label' => 'Source types', 'route' => 'app_admin_reference_index', 'params' => ['type' => 'source-types'], 'class' => SourceTypeReference::class],
                ['label' => 'Fetch modes', 'route' => 'app_admin_reference_index', 'params' => ['type' => 'fetch-modes'], 'class' => FetchModeReference::class],
                ['label' => 'Decision types', 'route' => 'app_admin_reference_index', 'params' => ['type' => 'decision-types'], 'class' => DecisionTypeReference::class],
                ['label' => 'Clickbait levels', 'route' => 'app_admin_reference_index', 'params' => ['type' => 'clickbait-levels'], 'class' => ClickbaitLevelReference::class],
                ['label' => 'Analysis languages', 'route' => 'app_admin_reference_index', 'params' => ['type' => 'analysis-languages'], 'class' => AnalysisLanguageReference::class],
                ['label' => 'References inactives', 'route' => 'app_admin_inactive_references', 'params' => [], 'class' => ReferenceEntityInterface::class],
                ['label' => 'Diagnostic media final', 'route' => 'app_admin_media_diagnostic', 'params' => [], 'class' => Entry::class],
                ['label' => 'Mappings media actifs', 'route' => 'app_admin_media_mappings', 'params' => [], 'class' => MediaTypeReference::class],
            ],
        ]);
    }

    #[Route('/references-inactive', name: 'app_admin_inactive_references', methods: ['GET'])]
    public function inactiveReferences(EntityManagerInterface $entityManager): Response
    {
        $types = [
            'media-types' => ['class' => MediaTypeReference::class, 'label' => 'Media types'],
            'source-types' => ['class' => SourceTypeReference::class, 'label' => 'Source types'],
            'fetch-modes' => ['class' => FetchModeReference::class, 'label' => 'Fetch modes'],
            'decision-types' => ['class' => DecisionTypeReference::class, 'label' => 'Decision types'],
            'clickbait-levels' => ['class' => ClickbaitLevelReference::class, 'label' => 'Clickbait levels'],
            'analysis-languages' => ['class' => AnalysisLanguageReference::class, 'label' => 'Analysis languages'],
        ];
        $items = [];

        foreach ($types as $type => $config) {
            foreach ($entityManager->getRepository($config['class'])->findBy(['isActive' => false], ['name' => 'ASC']) as $reference) {
                if ($reference instanceof ReferenceEntityInterface) {
                    $items[] = [
                        'type' => $type,
                        'label' => $config['label'],
                        'reference' => $reference,
                    ];
                }
            }
        }

        return $this->render('admin/inactive_references.html.twig', [
            'items' => $items,
        ]);
    }
}
