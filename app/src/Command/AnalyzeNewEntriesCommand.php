<?php

namespace App\Command;

use App\Repository\EntryRepository;
use App\Service\EntryAnalyzer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:analyze-new-entries', description: 'Analyse les entries qui n ont pas encore de decision.')]
class AnalyzeNewEntriesCommand extends Command
{
    public function __construct(
        private readonly EntryRepository $entryRepository,
        private readonly EntryAnalyzer $entryAnalyzer,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Nombre maximal d entries a analyser.', 100);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = max(1, (int) $input->getOption('limit'));
        $entries = $this->entryRepository->findPendingAnalysis($limit);

        if ($entries === []) {
            $io->success('Aucune entry en attente d analyse.');

            return Command::SUCCESS;
        }

        foreach ($entries as $entry) {
            $this->entryAnalyzer->analyze($entry);
        }

        $this->entityManager->flush();
        $io->success(sprintf('%d entries analysees.', count($entries)));

        return Command::SUCCESS;
    }
}
