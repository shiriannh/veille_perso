<?php

namespace App\Command;

use App\Repository\EntryRepository;
use App\Service\EntryTagDetector;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:detect-entry-tags',
    description: 'Detecte les tags automatiques des entries.',
    aliases: ['app:rebuild-entry-tags'],
)]
class DetectEntryTagsCommand extends Command
{
    public function __construct(
        private readonly EntryRepository $entryRepository,
        private readonly EntryTagDetector $entryTagDetector,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('entryId', InputArgument::OPTIONAL, 'ID de l entry a recalculer.')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Recalculer toutes les entries.')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Nombre maximal d entries sans tags a traiter.', 100);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $entryId = $input->getArgument('entryId');

        if ($entryId !== null) {
            $entry = $this->entryRepository->find((int) $entryId);
            if ($entry === null) {
                $io->error(sprintf('Entry %s introuvable.', $entryId));

                return Command::FAILURE;
            }

            $this->entryTagDetector->detect($entry);
            $this->entityManager->flush();
            $io->success(sprintf('Tags recalcules pour l entry %d.', $entry->getId()));

            return Command::SUCCESS;
        }

        $entries = $input->getOption('all')
            ? $this->entryRepository->findAll()
            : $this->entryRepository->findWithoutDetectedTags(max(1, (int) $input->getOption('limit')));

        if ($entries === []) {
            $io->success('Aucune entry a traiter.');

            return Command::SUCCESS;
        }

        foreach ($entries as $entry) {
            $this->entryTagDetector->detect($entry);
        }

        $this->entityManager->flush();
        $io->success(sprintf('%d entries traitees.', count($entries)));

        return Command::SUCCESS;
    }
}
