<?php

namespace App\Command;

use App\Repository\EntryRepository;
use App\Service\DraftReviewCreator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:generate-draft-reviews', description: 'Cree les fiches brouillon manquantes pour les entries a fort interet.')]
class GenerateDraftReviewsCommand extends Command
{
    public function __construct(
        private readonly EntryRepository $entryRepository,
        private readonly DraftReviewCreator $draftReviewCreator,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Nombre maximal d entries a traiter.', 200);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = max(1, (int) $input->getOption('limit'));
        $created = 0;

        foreach ($this->entryRepository->findBy([], ['updatedAt' => 'DESC'], $limit) as $entry) {
            if ($this->draftReviewCreator->createIfNeeded($entry) !== null) {
                ++$created;
            }
        }

        $this->entityManager->flush();
        $io->success(sprintf('%d fiches brouillon creees.', $created));

        return Command::SUCCESS;
    }
}
