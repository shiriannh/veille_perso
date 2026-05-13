<?php

namespace App\Command;

use App\Service\LocalStatusReporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:healthcheck', description: 'Verifie rapidement la sante locale de l application.')]
class HealthcheckCommand extends Command
{
    public function __construct(
        private readonly LocalStatusReporter $statusReporter,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $status = $this->statusReporter->status();

        $io->title('Healthcheck local');
        $io->table(['Controle', 'Etat'], [
            ['Base de donnees', $status['databaseOk'] ? 'OK' : 'Erreur'],
            ['Derniere migration', $status['latestMigration'] ?? 'Non disponible'],
            ['References critiques', $status['missingReferences'] === [] ? 'OK' : implode(', ', $status['missingReferences'])],
            ['Sources actives', (string) $status['activeSourceCount']],
            ['Dernier import', $this->formatImport($status['lastImportRun'] ?? null)],
            ['Entrees non analysees', (string) $status['pendingAnalysisCount']],
            ['Entrees media other', (string) $status['otherMediaCount']],
        ]);

        if (!$status['databaseOk'] || $status['missingReferences'] !== []) {
            $io->warning('Healthcheck termine avec des points a corriger.');

            return Command::FAILURE;
        }

        $io->success('Healthcheck OK.');

        return Command::SUCCESS;
    }

    private function formatImport(mixed $run): string
    {
        if (!is_object($run) || !method_exists($run, 'getStartedAt')) {
            return 'Aucun';
        }

        return sprintf('%s / %s', $run->getStartedAt()->format('Y-m-d H:i'), $run->getStatus()->value);
    }
}
