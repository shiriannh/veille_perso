<?php

namespace App\Command;

use App\Repository\SourceRepository;
use App\Service\RssFeedInspector;
use App\Service\RssImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:import-source', description: 'Importe manuellement une source RSS.')]
class ImportSourceCommand extends Command
{
    public function __construct(
        private readonly SourceRepository $sourceRepository,
        private readonly RssImporter $rssImporter,
        private readonly RssFeedInspector $rssFeedInspector,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('sourceId', InputArgument::REQUIRED, 'ID de la source a importer.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Lit le flux et affiche un apercu sans creer d Entry.')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Nombre maximal d items a importer ou previsualiser.')
            ->addOption('no-analysis', null, InputOption::VALUE_NONE, 'Importe sans lancer l analyse post-RSS.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $source = $this->sourceRepository->find((int) $input->getArgument('sourceId'));

        if ($source === null) {
            $io->error('Source introuvable.');

            return Command::FAILURE;
        }

        $limit = $input->getOption('limit') !== null ? max(1, (int) $input->getOption('limit')) : null;

        if ((bool) $input->getOption('dry-run')) {
            $preview = $this->rssFeedInspector->inspect($source, $limit ?? 10);
            if (!$preview['ok']) {
                $io->error($preview['errors']);

                return Command::FAILURE;
            }

            $io->table(['Titre du flux', 'Dernier item', 'Items lus'], [[
                $preview['title'] ?? '-',
                $preview['lastItemAt']?->format('Y-m-d H:i') ?? '-',
                $preview['itemCount'],
            ]]);
            $io->table(['Titre', 'Date', 'Categories'], array_map(static fn (array $item): array => [
                $item['title'],
                $item['publishedAt']?->format('Y-m-d H:i') ?? '-',
                implode(', ', $item['categories']),
            ], $preview['items']));

            return Command::SUCCESS;
        }

        $run = $this->rssImporter->import($source, !(bool) $input->getOption('no-analysis'), $limit);

        $io->table(['Statut', 'Recuperes', 'Crees', 'Ignores'], [[
            $run->getStatus()->label(),
            $run->getFetchedCount(),
            $run->getCreatedCount(),
            $run->getSkippedCount(),
        ]]);
        $this->renderAdmissionSummary($io, $run->getDetails()['admission'] ?? null);

        if ($run->getErrorMessage() !== null) {
            $io->error($run->getErrorMessage());

            return Command::FAILURE;
        }

        $io->success('Import termine.');

        return Command::SUCCESS;
    }

    private function renderAdmissionSummary(SymfonyStyle $io, mixed $admission): void
    {
        if (!is_array($admission)) {
            return;
        }

        $io->table(['Collectes', 'Admis', 'Quarantaine', 'Rejetes'], [[
            (int) ($admission['collected'] ?? 0),
            (int) ($admission['admitted'] ?? 0),
            (int) ($admission['quarantined'] ?? 0),
            (int) ($admission['rejected'] ?? 0),
        ]]);
    }
}
