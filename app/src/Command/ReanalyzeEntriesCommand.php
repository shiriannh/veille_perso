<?php

namespace App\Command;

use App\Repository\EntryRepository;
use App\Service\EntryAnalyzer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:reanalyze-entries', description: 'Reanalyse une entry precise ou toutes les entries.')]
class ReanalyzeEntriesCommand extends Command
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
        $this
            ->addArgument('entryId', InputArgument::OPTIONAL, 'ID de l entry a reanalyser.')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Reanalyser toutes les entries.')
            ->addOption('force-ai', null, InputOption::VALUE_NONE, 'Autoriser un appel IA meme hors zone ambigue.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $entryId = $input->getArgument('entryId');
        $forceAi = (bool) $input->getOption('force-ai');

        if ($entryId !== null) {
            $entry = $this->entryRepository->find((int) $entryId);
            if ($entry === null) {
                $io->error(sprintf('Entry %s introuvable.', $entryId));

                return Command::FAILURE;
            }

            $this->entryAnalyzer->analyze($entry, $forceAi);
            $this->entityManager->flush();
            $io->success(sprintf('Entry %d reanalysee.', $entry->getId()));

            return Command::SUCCESS;
        }

        if (!$input->getOption('all')) {
            $io->warning('Indique un ID ou utilise --all.');

            return Command::FAILURE;
        }

        $entries = $this->entryRepository->findAll();
        foreach ($entries as $entry) {
            $this->entryAnalyzer->analyze($entry, $forceAi);
        }

        $this->entityManager->flush();
        $io->success(sprintf('%d entries reanalysees.', count($entries)));

        return Command::SUCCESS;
    }
}
