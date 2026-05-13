<?php

namespace App\Command;

use App\Repository\SourceRepository;
use App\Service\LesLibrairesImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:import-leslibraires', description: 'Collecte les nouveautes leslibraires.fr pour une source dediee.')]
class ImportLesLibrairesCommand extends Command
{
    public function __construct(
        private readonly SourceRepository $sourceRepository,
        private readonly LesLibrairesImporter $importer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('sourceId', InputArgument::REQUIRED, 'ID de la source leslibraires.fr.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Previsualise sans creer d Entry.')
            ->addOption('window', null, InputOption::VALUE_REQUIRED, 'Fenetre: 7d, 1m ou 3m.')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Nombre maximal de fiches a ouvrir.')
            ->addOption('no-analysis', null, InputOption::VALUE_NONE, 'Cree les Entry sans analyse post-import.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $source = $this->sourceRepository->find((int) $input->getArgument('sourceId'));
        if ($source === null) {
            $io->error('Source introuvable.');

            return Command::FAILURE;
        }

        $window = is_string($input->getOption('window')) ? $input->getOption('window') : null;
        $limit = $input->getOption('limit') !== null ? max(1, (int) $input->getOption('limit')) : null;

        if ((bool) $input->getOption('dry-run')) {
            $preview = $this->importer->preview($source, $window, $limit ?? 10);
            $io->title('Previsualisation leslibraires.fr');
            $io->writeln('Fenetre: '.$preview['window']);
            $io->writeln(sprintf(
                'Pages parcourues: %d | Candidats: %d | Fiches ouvertes: %d',
                $preview['pagesVisited'],
                count($preview['candidates']),
                count($preview['books']),
            ));
            $io->table(['Titre', 'Auteur(s)', 'ISBN/EAN', 'Date', 'URL'], array_map(static fn ($book): array => [
                $book->title,
                implode(', ', $book->authors) ?: '-',
                $book->isbn ?? $book->ean13 ?? '-',
                $book->publishedAt?->format('Y-m-d') ?? '-',
                $book->url,
            ], $preview['books']));

            if ($preview['errors'] !== []) {
                $io->warning($preview['errors']);
            }

            return Command::SUCCESS;
        }

        $run = $this->importer->import($source, $window, !(bool) $input->getOption('no-analysis'), $limit);
        $summary = $this->importer->lastSummary();
        $io->table(['Statut', 'Recuperes', 'Crees', 'Ignores'], [[
            $run->getStatus()->label(),
            $run->getFetchedCount(),
            $run->getCreatedCount(),
            $run->getSkippedCount(),
        ]]);
        $this->renderAdmissionSummary($io, $run->getDetails()['admission'] ?? null);
        $io->writeln(sprintf(
            'Fenetre: %s | Pages parcourues: %d | Candidats: %d | Fiches ouvertes: %d',
            $summary['window'] ?? '-',
            $summary['pagesVisited'],
            $summary['candidatesCount'],
            $summary['detailsOpened'],
        ));

        if ($run->getStatus()->value === 'failed') {
            $io->error((string) $run->getErrorMessage());

            return Command::FAILURE;
        }

        if ($run->getErrorMessage() !== null) {
            $io->warning($run->getErrorMessage());
        }

        $io->success('Collecte leslibraires.fr terminee.');

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
