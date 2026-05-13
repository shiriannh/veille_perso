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
use App\Repository\AnalysisCorrectionRepository;
use App\Service\LocalStatusReporter;
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
            'groups' => [
                'Configuration' => [
                    ['label' => 'Tags', 'route' => 'app_admin_tag_index', 'params' => [], 'description' => "Gerer les tags utiles a l'analyse, leur role et leur validation.", 'featured' => true],
                    ['label' => 'Profil d interet', 'route' => 'app_admin_interest_profile_index', 'params' => [], 'description' => 'Definir ce qui augmente ou reduit la pertinence.', 'featured' => true],
                    ['label' => 'Types de media', 'route' => 'app_admin_reference_index', 'params' => ['type' => 'media-types'], 'description' => 'Maintenir les medias utilises par les filtres et syntheses.'],
                    ['label' => 'Types de source', 'route' => 'app_admin_reference_index', 'params' => ['type' => 'source-types'], 'description' => 'Classer les sources selon leur nature editoriale.'],
                    ['label' => 'Modes d import', 'route' => 'app_admin_reference_index', 'params' => ['type' => 'fetch-modes'], 'description' => 'Piloter les modes RSS, manuel et connecteurs cibles.'],
                    ['label' => 'Types de decision', 'route' => 'app_admin_reference_index', 'params' => ['type' => 'decision-types'], 'description' => 'Administrer les decisions finales de l analyse.'],
                    ['label' => 'Niveaux clickbait', 'route' => 'app_admin_reference_index', 'params' => ['type' => 'clickbait-levels'], 'description' => 'Nommer les niveaux de bruit editorial.'],
                    ['label' => 'Langues d analyse', 'route' => 'app_admin_reference_index', 'params' => ['type' => 'analysis-languages'], 'description' => 'Suivre les langues reconnues par les regles.'],
                ],
                'Surveillance' => [
                    ['label' => 'Statut local', 'route' => 'app_admin_status', 'params' => [], 'description' => "Voir rapidement l'etat de la base locale."],
                    ['label' => 'Diagnostic media final', 'route' => 'app_admin_media_diagnostic', 'params' => [], 'description' => 'Reperer les entrees encore mal classees.', 'featured' => true],
                    ['label' => 'References inactives', 'route' => 'app_admin_inactive_references', 'params' => [], 'description' => 'Controler les valeurs desactivees mais conservees.'],
                ],
                'Corrections' => [
                    ['label' => 'Corrections analyse', 'route' => 'app_admin_analysis_corrections', 'params' => [], 'description' => 'Suivre les corrections manuelles appliquees aux Entry.'],
                ],
                'References avancees' => [
                    ['label' => 'Mappings media actifs', 'route' => 'app_admin_media_mappings', 'params' => [], 'description' => 'Comprendre comment les tags et categories promeuvent un media.'],
                ],
            ],
        ]);
    }

    #[Route('/status', name: 'app_admin_status', methods: ['GET'])]
    public function status(LocalStatusReporter $statusReporter): Response
    {
        return $this->render('admin/status.html.twig', [
            'status' => $statusReporter->status(),
            'stats' => $statusReporter->stats(),
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

    #[Route('/analysis-corrections', name: 'app_admin_analysis_corrections', methods: ['GET'])]
    public function analysisCorrections(AnalysisCorrectionRepository $analysisCorrectionRepository): Response
    {
        return $this->render('admin/analysis_correction/index.html.twig', [
            'corrections' => $analysisCorrectionRepository->findLatest(200),
        ]);
    }
}
