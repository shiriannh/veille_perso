<?php

namespace App\Command;

use App\Repository\SourceRepository;
use App\Service\RssImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:import-sources', description: 'Importe toutes les sources RSS actives.')]
class ImportSourcesCommand extends Command
{
    public function __construct(
        private readonly SourceRepository $sourceRepository,
        private readonly RssImporter $rssImporter,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Nombre maximal de sources actives a importer.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $sources = $this->sourceRepository->findActiveRssSources();
        $limit = $input->getOption('limit') !== null ? max(1, (int) $input->getOption('limit')) : null;
        if ($limit !== null) {
            $sources = array_slice($sources, 0, $limit);
        }

        if ($sources === []) {
            $io->warning('Aucune source RSS active a importer.');

            return Command::SUCCESS;
        }

        $rows = [];
        $hasError = false;

        foreach ($sources as $source) {
            $run = $this->rssImporter->import($source);
            $hasError = $hasError || $run->getErrorMessage() !== null;

            $rows[] = [
                $source->getId(),
                $source->getName(),
                $run->getStatus()->label(),
                $run->getFetchedCount(),
                $run->getCreatedCount(),
                $run->getSkippedCount(),
                $run->getErrorMessage() ?? '',
            ];
        }

        $io->table(['ID', 'Source', 'Statut', 'Recuperes', 'Crees', 'Ignores', 'Erreur'], $rows);

        return $hasError ? Command::FAILURE : Command::SUCCESS;
    }
}
