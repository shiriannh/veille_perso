<?php

namespace App\Command;

use App\Repository\SourceRepository;
use App\Service\RssImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:import-source', description: 'Importe manuellement une source RSS.')]
class ImportSourceCommand extends Command
{
    public function __construct(
        private readonly SourceRepository $sourceRepository,
        private readonly RssImporter $rssImporter,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('sourceId', InputArgument::REQUIRED, 'ID de la source à importer.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $source = $this->sourceRepository->find((int) $input->getArgument('sourceId'));

        if ($source === null) {
            $io->error('Source introuvable.');

            return Command::FAILURE;
        }

        $run = $this->rssImporter->import($source);

        $io->table(['Statut', 'Récupérés', 'Créés', 'Ignorés'], [[
            $run->getStatus()->label(),
            $run->getFetchedCount(),
            $run->getCreatedCount(),
            $run->getSkippedCount(),
        ]]);

        if ($run->getErrorMessage() !== null) {
            $io->error($run->getErrorMessage());

            return Command::FAILURE;
        }

        $io->success('Import terminé.');

        return Command::SUCCESS;
    }
}
