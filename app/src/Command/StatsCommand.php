<?php

namespace App\Command;

use App\Service\LocalStatusReporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:stats', description: 'Affiche un resume d exploitation locale.')]
class StatsCommand extends Command
{
    public function __construct(
        private readonly LocalStatusReporter $statusReporter,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $stats = $this->statusReporter->stats();

        $io->title('Stats locales');
        $io->table(['Indicateur', 'Valeur'], array_map(
            static fn (string $key, int $value): array => [$key, (string) $value],
            array_keys($stats),
            $stats,
        ));

        $io->section('Repartition par media final');
        $io->table(['Media', 'Entrees'], $this->statusReporter->mediaDistribution());

        $io->section('Repartition par decision');
        $io->table(['Decision', 'Entrees'], $this->statusReporter->decisionDistribution());

        return Command::SUCCESS;
    }
}
