<?php

namespace App\Controller;

use App\Entity\Entry;
use App\Enum\AnalysisDecision;
use App\Enum\ClickbaitLevel;
use App\Enum\EntryStatus;
use App\Enum\MediaType;
use App\Form\EntryType;
use App\Repository\EntryRepository;
use App\Repository\SourceRepository;
use App\Service\EntryAnalyzer;
use App\Service\EntryTagDetector;
use App\Service\MediaTypeResolver;
use App\Service\ReferenceFieldSynchronizer;
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
        [$filters, $viewFilters] = $this->filtersFromRequest($request, $sourceRepository);

        $entries = $entryRepository->findFiltered($filters);
        [$paginatedEntries, $pagination] = $this->paginateEntries($request, $entries);
        $viewFilters['page'] = $pagination['page'];
        $viewFilters['perPage'] = $pagination['perPage'];

        return $this->render('entry/index.html.twig', [
            'entries' => $paginatedEntries,
            'pagination' => $pagination,
            'sources' => $sourceRepository->findAllOrdered(),
            'media_types' => MediaType::cases(),
            'statuses' => EntryStatus::cases(),
            'decisions' => AnalysisDecision::cases(),
            'clickbait_levels' => ClickbaitLevel::cases(),
            'media_type_origins' => MediaTypeResolver::origins(),
            'filters' => $viewFilters,
        ]);
    }

    #[Route('/reanalyze-filtered', name: 'app_entry_reanalyze_filtered', methods: ['POST'])]
    public function reanalyzeFiltered(
        Request $request,
        EntryRepository $entryRepository,
        SourceRepository $sourceRepository,
        EntryAnalyzer $entryAnalyzer,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$this->isCsrfTokenValid('reanalyze_filtered_entries', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide, reanalyse non lancee.');

            return $this->redirectToRoute('app_entry_index', $request->query->all());
        }

        [$filters] = $this->filtersFromRequest($request, $sourceRepository);
        $entries = $entryRepository->findFiltered($filters);

        foreach ($entries as $entry) {
            $entryAnalyzer->analyze($entry);
        }

        $entityManager->flush();
        $this->addFlash('success', sprintf('%d entree(s) filtrees reanalysees.', count($entries)));

        return $this->redirectToRoute('app_entry_index', $request->query->all());
    }

    #[Route('/{id}/analyze', name: 'app_entry_analyze', methods: ['POST'])]
    public function analyze(Request $request, Entry $entry, EntryAnalyzer $entryAnalyzer, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('analyze_entry_'.$entry->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide, analyse non lancee.');

            return $this->redirectToRoute('app_entry_show', ['id' => $entry->getId()]);
        }

        $entryAnalyzer->analyze($entry, $request->request->getBoolean('force_ai'));
        $entityManager->flush();

        $this->addFlash('success', 'Analyse relancee.');

        return $this->redirectToRoute('app_entry_show', ['id' => $entry->getId()]);
    }

    #[Route('/{id}/detect-tags', name: 'app_entry_detect_tags', methods: ['POST'])]
    public function detectTags(Request $request, Entry $entry, EntryTagDetector $entryTagDetector, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('detect_entry_tags_'.$entry->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide, detection non lancee.');

            return $this->redirectToRoute('app_entry_show', ['id' => $entry->getId()]);
        }

        $entryTagDetector->detect($entry);
        $entityManager->flush();

        $this->addFlash('success', 'Tags detectes recalcules.');

        return $this->redirectToRoute('app_entry_show', ['id' => $entry->getId()]);
    }

    #[Route('/new', name: 'app_entry_new', methods: ['GET', 'POST'])]
    public function new(Request $request, SourceRepository $sourceRepository, ReferenceFieldSynchronizer $referenceFieldSynchronizer, EntityManagerInterface $entityManager): Response
    {
        if ($sourceRepository->count([]) === 0) {
            $this->addFlash('error', 'Crée au moins une source avant de saisir une entrée.');

            return $this->redirectToRoute('app_source_new');
        }

        $entry = new Entry();
        $referenceFieldSynchronizer->syncEntryToReferences($entry);
        $form = $this->createForm(EntryType::class, $entry);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $referenceFieldSynchronizer->syncEntryFromReferences($entry);
            $referenceFieldSynchronizer->syncEntryToReferences($entry);
            $entry->setMediaTypeOrigin('manual');
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
    public function edit(Request $request, Entry $entry, ReferenceFieldSynchronizer $referenceFieldSynchronizer, EntityManagerInterface $entityManager): Response
    {
        $referenceFieldSynchronizer->syncEntryToReferences($entry);
        $form = $this->createForm(EntryType::class, $entry);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $referenceFieldSynchronizer->syncEntryFromReferences($entry);
            $referenceFieldSynchronizer->syncEntryToReferences($entry);
            $entry->setMediaTypeOrigin('manual');
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

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function filtersFromRequest(Request $request, SourceRepository $sourceRepository): array
    {
        $sourceId = (string) $request->query->get('source', '');
        $source = ctype_digit($sourceId) && (int) $sourceId > 0 ? $sourceRepository->find((int) $sourceId) : null;
        $mediaType = MediaType::tryFrom((string) $request->query->get('mediaType'));
        $detectedMediaType = MediaType::tryFrom((string) $request->query->get('detectedMediaType'));
        $mediaTypeOrigin = in_array($request->query->get('mediaTypeOrigin'), MediaTypeResolver::origins(), true)
            ? (string) $request->query->get('mediaTypeOrigin')
            : null;
        $status = EntryStatus::tryFrom((string) $request->query->get('status'));
        $interestLevelValue = (string) $request->query->get('interestLevel', '');
        $interestLevel = ctype_digit($interestLevelValue)
            ? max(0, min(5, (int) $interestLevelValue))
            : null;
        $reviewState = in_array($request->query->get('reviewState'), ['with', 'without'], true)
            ? (string) $request->query->get('reviewState')
            : null;
        $decision = AnalysisDecision::tryFrom((string) $request->query->get('decision'));
        $clickbaitLevel = ClickbaitLevel::tryFrom((string) $request->query->get('clickbaitLevel'));
        $analysisLanguage = in_array($request->query->get('analysisLanguage'), ['fr', 'en', 'mixed', 'unknown'], true)
            ? (string) $request->query->get('analysisLanguage')
            : null;
        $sort = in_array($request->query->get('sort'), ['published_desc', 'published_asc', 'imported_desc', 'imported_asc'], true)
            ? (string) $request->query->get('sort')
            : null;

        return [
            [
                'q' => $request->query->get('q'),
                'source' => $source,
                'mediaType' => $mediaType,
                'detectedMediaType' => $detectedMediaType,
                'mediaTypeOrigin' => $mediaTypeOrigin,
                'status' => $status,
                'interestLevel' => $interestLevel,
                'reviewState' => $reviewState,
                'decision' => $decision,
                'clickbaitLevel' => $clickbaitLevel,
                'keyword' => $request->query->get('keyword'),
                'detectedTag' => $request->query->get('detectedTag'),
                'analysisLanguage' => $analysisLanguage,
                'sort' => $sort,
            ],
            [
                'q' => (string) $request->query->get('q', ''),
                'source' => $source?->getId(),
                'mediaType' => $mediaType?->value,
                'detectedMediaType' => $detectedMediaType?->value,
                'mediaTypeOrigin' => $mediaTypeOrigin ?? '',
                'status' => $status?->value,
                'interestLevel' => $interestLevel,
                'reviewState' => $reviewState,
                'decision' => $decision?->value,
                'clickbaitLevel' => $clickbaitLevel?->value,
                'keyword' => (string) $request->query->get('keyword', ''),
                'detectedTag' => (string) $request->query->get('detectedTag', ''),
                'analysisLanguage' => $analysisLanguage ?? '',
                'sort' => $sort ?? '',
            ],
        ];
    }

    /**
     * @param array<int, Entry> $entries
     *
     * @return array{0: array<int, Entry>, 1: array<string, mixed>}
     */
    private function paginateEntries(Request $request, array $entries): array
    {
        $allowed = ['5', '10', '25', 'all'];
        $perPage = in_array($request->query->get('perPage'), $allowed, true) ? (string) $request->query->get('perPage') : '25';
        $total = count($entries);

        if ($perPage === 'all') {
            return [$entries, [
                'page' => 1,
                'perPage' => 'all',
                'total' => $total,
                'pages' => 1,
                'allowed' => $allowed,
            ]];
        }

        $limit = (int) $perPage;
        $pages = max(1, (int) ceil($total / $limit));
        $pageValue = (string) $request->query->get('page', '1');
        $page = ctype_digit($pageValue) ? max(1, min($pages, (int) $pageValue)) : 1;
        $offset = ($page - 1) * $limit;

        return [array_slice($entries, $offset, $limit), [
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'pages' => $pages,
            'allowed' => $allowed,
        ]];
    }
}
