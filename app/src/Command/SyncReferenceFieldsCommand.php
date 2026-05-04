<?php

namespace App\Command;

use App\Repository\EntryRepository;
use App\Repository\SourceRepository;
use App\Service\ReferenceDataSeeder;
use App\Service\ReferenceFieldSynchronizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:sync-reference-fields', description: 'Synchronise les colonnes enum historiques avec les tables de reference.')]
class SyncReferenceFieldsCommand extends Command
{
    public function __construct(
        private readonly EntryRepository $entryRepository,
        private readonly SourceRepository $sourceRepository,
        private readonly ReferenceDataSeeder $referenceDataSeeder,
        private readonly ReferenceFieldSynchronizer $referenceFieldSynchronizer,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simuler sans ecrire en base.')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Limiter le nombre d entries synchronisees.', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $limitOption = $input->getOption('limit');
        $limit = is_numeric($limitOption) ? max(1, (int) $limitOption) : null;
        $entries = $limit !== null ? array_slice($this->entryRepository->findAll(), 0, $limit) : $this->entryRepository->findAll();
        $sources = $this->sourceRepository->findAll();

        $this->referenceDataSeeder->seed(false);

        foreach ($entries as $entry) {
            $this->referenceFieldSynchronizer->syncEntryToReferences($entry);
        }

        foreach ($sources as $source) {
            $this->referenceFieldSynchronizer->syncSourceToReferences($source);
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        $io->success(sprintf('%d entry(s) et %d source(s) %s.', count($entries), count($sources), $dryRun ? 'verifiees' : 'synchronisees'));

        return Command::SUCCESS;
    }
}
