<?php

namespace App\Controller;

use App\Entity\AnalysisCorrection;
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
use App\Service\SourceQualityReporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/entries')]
class EntryController extends AbstractController
{
    #[Route('', name: 'app_entry_index', methods: ['GET'])]
    public function index(Request $request, EntryRepository $entryRepository, SourceRepository $sourceRepository, SourceQualityReporter $sourceQualityReporter): Response
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
            'source_quality' => $sourceQualityReporter->summarizeAll(),
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

    #[Route('/detect-tags-filtered', name: 'app_entry_detect_tags_filtered', methods: ['POST'])]
    public function detectTagsFiltered(
        Request $request,
        EntryRepository $entryRepository,
        SourceRepository $sourceRepository,
        EntryTagDetector $entryTagDetector,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$this->isCsrfTokenValid('detect_tags_filtered_entries', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide, detection non lancee.');

            return $this->redirectToRoute('app_entry_index', $request->query->all());
        }

        [$filters] = $this->filtersFromRequest($request, $sourceRepository);
        $entries = $entryRepository->findFiltered($filters);

        foreach ($entries as $entry) {
            $entryTagDetector->detect($entry);
        }

        $entityManager->flush();
        $this->addFlash('success', sprintf('Tags detectes recalcules pour %d entree(s) filtrees.', count($entries)));

        return $this->redirectToRoute('app_entry_index', $request->query->all());
    }

    #[Route('/{id}/mark-ignored', name: 'app_entry_mark_ignored', methods: ['POST'])]
    public function markIgnored(
        Request $request,
        Entry $entry,
        ReferenceFieldSynchronizer $referenceFieldSynchronizer,
        EntityManagerInterface $entityManager,
    ): Response {
        return $this->markDecision($request, $entry, AnalysisDecision::Ignored, 'ignoree', $referenceFieldSynchronizer, $entityManager);
    }

    #[Route('/{id}/mark-relevant', name: 'app_entry_mark_relevant', methods: ['POST'])]
    public function markRelevant(
        Request $request,
        Entry $entry,
        ReferenceFieldSynchronizer $referenceFieldSynchronizer,
        EntityManagerInterface $entityManager,
    ): Response {
        return $this->markDecision($request, $entry, AnalysisDecision::Relevant, 'pertinente', $referenceFieldSynchronizer, $entityManager);
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
            'media_types' => MediaType::cases(),
            'decisions' => AnalysisDecision::cases(),
        ]);
    }

    #[Route('/{id}/correct-media', name: 'app_entry_correct_media', methods: ['POST'])]
    public function correctMedia(
        Request $request,
        Entry $entry,
        ReferenceFieldSynchronizer $referenceFieldSynchronizer,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$this->isCsrfTokenValid('correct_entry_media_'.$entry->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide, correction non enregistree.');

            return $this->redirectToRoute('app_entry_show', ['id' => $entry->getId()]);
        }

        $mediaType = MediaType::tryFrom((string) $request->request->get('mediaType'));
        if (!$mediaType instanceof MediaType) {
            $this->addFlash('error', 'Media final invalide.');

            return $this->redirectToRoute('app_entry_show', ['id' => $entry->getId()]);
        }

        $oldValue = [
            'mediaType' => $entry->getMediaType()->value,
            'origin' => $entry->getMediaTypeOrigin(),
        ];
        $entry
            ->setMediaType($mediaType)
            ->setMediaTypeOrigin('manual')
            ->setMediaDetectionConfidence(100);
        $this->appendManualSignal($entry, sprintf('correction manuelle media: %s', $mediaType->value));
        $referenceFieldSynchronizer->syncEntryToReferences($entry);

        $this->recordCorrection($entry, 'media_type', $oldValue, [
            'mediaType' => $mediaType->value,
            'origin' => 'manual',
        ], $request, $entityManager);
        $entityManager->flush();

        $this->addFlash('success', 'Media final corrige. Les promotions automatiques ne l ecraseront plus.');

        return $this->redirectToRoute('app_entry_show', ['id' => $entry->getId()]);
    }

    #[Route('/{id}/correct-decision', name: 'app_entry_correct_decision', methods: ['POST'])]
    public function correctDecision(
        Request $request,
        Entry $entry,
        ReferenceFieldSynchronizer $referenceFieldSynchronizer,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$this->isCsrfTokenValid('correct_entry_decision_'.$entry->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide, correction non enregistree.');

            return $this->redirectToRoute('app_entry_show', ['id' => $entry->getId()]);
        }

        $decision = AnalysisDecision::tryFrom((string) $request->request->get('decision'));
        if (!$decision instanceof AnalysisDecision) {
            $this->addFlash('error', 'Decision invalide.');

            return $this->redirectToRoute('app_entry_show', ['id' => $entry->getId()]);
        }

        $oldValue = [
            'decision' => $entry->getDecision()?->value,
            'decisionReason' => $entry->getDecisionReason(),
        ];
        $reason = trim((string) $request->request->get('reason'));
        $entry->setDecision($decision);
        if ($reason !== '') {
            $entry->setDecisionReason(trim(($entry->getDecisionReason() ?? '')."\nCorrection manuelle: ".$reason));
        }
        $this->appendManualSignal($entry, sprintf('correction manuelle decision: %s', $decision->value));
        $referenceFieldSynchronizer->syncEntryToReferences($entry);

        $this->recordCorrection($entry, 'decision', $oldValue, [
            'decision' => $decision->value,
            'decisionReason' => $entry->getDecisionReason(),
        ], $request, $entityManager);
        $entityManager->flush();

        $this->addFlash('success', 'Decision corrigee et tracee dans les signaux.');

        return $this->redirectToRoute('app_entry_show', ['id' => $entry->getId()]);
    }

    #[Route('/{id}/correct-detected-tags', name: 'app_entry_correct_detected_tags', methods: ['POST'])]
    public function correctDetectedTags(Request $request, Entry $entry, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('correct_entry_detected_tags_'.$entry->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide, correction non enregistree.');

            return $this->redirectToRoute('app_entry_show', ['id' => $entry->getId()]);
        }

        $oldTags = $entry->getDetectedTags();
        $newTags = $this->normalizeTags((string) $request->request->get('detectedTags'));
        $entry->setDetectedTags($newTags);
        $this->appendManualSignal($entry, 'correction manuelle tags detectes');

        $this->recordCorrection($entry, 'detected_tags', [
            'detectedTags' => $oldTags,
        ], [
            'detectedTags' => $newTags,
        ], $request, $entityManager);
        $entityManager->flush();

        $this->addFlash('success', 'Tags detectes corriges.');

        return $this->redirectToRoute('app_entry_show', ['id' => $entry->getId()]);
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

                return $this->redirectToRoute('app_entry_index', $request->query->all());
            }

            $entityManager->remove($entry);
            $entityManager->flush();
            $this->addFlash('success', 'Entrée supprimée.');
        }

        return $this->redirectToRoute('app_entry_index', $request->query->all());
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
        $sort = in_array($request->query->get('sort'), ['published_desc', 'published_asc', 'imported_desc', 'imported_asc', 'relevance_desc', 'relevance_asc', 'clickbait_desc', 'clickbait_asc', 'interest_desc', 'interest_asc', 'analyzed_desc', 'analyzed_asc'], true)
            ? (string) $request->query->get('sort')
            : null;
        $synthesisState = in_array($request->query->get('synthesisState'), ['with', 'without'], true)
            ? (string) $request->query->get('synthesisState')
            : null;
        $viewMode = in_array($request->query->get('view'), ['default', 'compact'], true)
            ? (string) $request->query->get('view')
            : 'default';

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
                'rssCategory' => $request->query->get('rssCategory'),
                'synthesisState' => $synthesisState,
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
                'rssCategory' => (string) $request->query->get('rssCategory', ''),
                'synthesisState' => $synthesisState,
                'analysisLanguage' => $analysisLanguage ?? '',
                'sort' => $sort ?? '',
                'view' => $viewMode,
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

    /**
     * @param array<string, mixed>|null $oldValue
     * @param array<string, mixed>|null $newValue
     */
    private function recordCorrection(
        Entry $entry,
        string $fieldName,
        ?array $oldValue,
        ?array $newValue,
        Request $request,
        EntityManagerInterface $entityManager,
    ): void {
        $reason = trim((string) $request->request->get('reason'));
        $correction = (new AnalysisCorrection())
            ->setEntry($entry)
            ->setFieldName($fieldName)
            ->setOldValue($oldValue)
            ->setNewValue($newValue)
            ->setReason($reason !== '' ? $reason : null);

        $entityManager->persist($correction);
    }

    private function appendManualSignal(Entry $entry, string $signal): void
    {
        $signals = $entry->getAnalysisSignals();
        $signals[] = $signal;
        $entry->setAnalysisSignals($signals);
    }

    /**
     * @return array<int, string>
     */
    private function normalizeTags(string $tags): array
    {
        $normalized = [];

        foreach (explode(',', $tags) as $tag) {
            $tag = trim(mb_strtolower($tag));
            $tag = preg_replace('/\s+/', '-', $tag) ?? $tag;
            $tag = trim($tag, '-');
            if ($tag !== '') {
                $normalized[] = $tag;
            }
        }

        return array_values(array_unique($normalized));
    }

    private function markDecision(
        Request $request,
        Entry $entry,
        AnalysisDecision $decision,
        string $label,
        ReferenceFieldSynchronizer $referenceFieldSynchronizer,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$this->isCsrfTokenValid('mark_entry_'.$decision->value.'_'.$entry->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide, action annulee.');

            return $this->redirectToRoute('app_entry_index', $request->query->all());
        }

        $oldValue = ['decision' => $entry->getDecision()?->value];
        $entry->setDecision($decision);
        $entry->setDecisionReason(trim(($entry->getDecisionReason() ?? '')."\nAction manuelle liste: entree marquee ".$label.'.'));
        $this->appendManualSignal($entry, 'action manuelle liste: '.$decision->value);
        $referenceFieldSynchronizer->syncEntryToReferences($entry);
        $this->recordCorrection($entry, 'decision', $oldValue, ['decision' => $decision->value], $request, $entityManager);

        $entityManager->flush();
        $this->addFlash('success', sprintf('Entree marquee %s.', $label));

        return $this->redirectToRoute('app_entry_index', $request->query->all());
    }
}
