<?php

namespace App\Command;

use App\Entity\Entry;
use App\Enum\MediaType;
use App\Repository\EntryRepository;
use App\Service\EntryAnalyzer;
use App\Service\MediaTypeResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:promote-media-types', description: 'Promeut les media_type other quand les signaux existants sont suffisants.')]
class PromoteMediaTypesCommand extends Command
{
    public function __construct(
        private readonly EntryRepository $entryRepository,
        private readonly MediaTypeResolver $mediaTypeResolver,
        private readonly EntryAnalyzer $entryAnalyzer,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simuler les promotions sans ecrire en base.')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Limiter le nombre d entries analysees.', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $limitOption = $input->getOption('limit');
        $limit = is_numeric($limitOption) ? max(1, (int) $limitOption) : null;
        $entries = $this->entryRepository->findStoredOtherMediaEntries($limit);
        $promotions = [];

        foreach ($entries as $entry) {
            if ($dryRun) {
                $promotion = $this->simulatePromotion($entry);
                if ($promotion !== null) {
                    $promotions[] = $promotion;
                }

                continue;
            }

            $before = $entry->getMediaType();
            $this->entryAnalyzer->analyze($entry);
            if ($before !== $entry->getMediaType() && $entry->getMediaType() !== MediaType::Other) {
                $promotions[] = [
                    $entry->getId(),
                    $entry->getTitle(),
                    $before->value,
                    $entry->getMediaType()->value,
                    $entry->getMediaTypeOrigin() ?: 'unknown',
                    $entry->getMediaDetectionConfidence() ?? 0,
                ];
            }
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        $io->title($dryRun ? 'Simulation promotion media' : 'Promotion media');
        $io->table(['ID', 'Titre', 'Avant', 'Apres', 'Origine', 'Confiance'], $promotions);
        $io->success(sprintf('%d promotion(s) %s.', count($promotions), $dryRun ? 'possibles' : 'appliquees'));

        return Command::SUCCESS;
    }

    /**
     * @return array<int, int|string>|null
     */
    private function simulatePromotion(Entry $entry): ?array
    {
        $resolution = $this->mediaTypeResolver->resolve($entry, $this->rssCategories($entry));
        $media = $resolution['media'];

        if (!$media instanceof MediaType || $media === MediaType::Other || $resolution['confidence'] < 35) {
            return null;
        }

        return [
            $entry->getId(),
            $entry->getTitle(),
            $entry->getMediaType()->value,
            $media->value,
            $resolution['origin'] ?? 'detected',
            $resolution['confidence'],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function rssCategories(Entry $entry): array
    {
        $rawPayload = $entry->getRawPayload();

        return is_array($rawPayload) && isset($rawPayload['categories']) && is_array($rawPayload['categories'])
            ? array_values(array_filter($rawPayload['categories'], 'is_string'))
            : [];
    }
}
