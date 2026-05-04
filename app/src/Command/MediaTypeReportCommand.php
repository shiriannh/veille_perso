<?php

namespace App\Command;

use App\Enum\MediaType;
use App\Repository\EntryRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:media-type-report', description: 'Affiche la repartition des medias finaux et de leurs origines.')]
class MediaTypeReportCommand extends Command
{
    public function __construct(
        private readonly EntryRepository $entryRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('output', null, InputOption::VALUE_REQUIRED, 'Chemin d export texte du rapport.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $summary = $this->entryRepository->summarizeMediaTypes();
        $rows = [];
        $total = 0;

        foreach ($summary as $media => $origins) {
            foreach ($origins as $origin => $count) {
                $rows[] = [$media, $origin, $count];
                $total += $count;
            }
        }

        $otherCount = array_sum($summary[MediaType::Other->value] ?? []);

        $io->title('Rapport media final');
        $io->table(['Media final', 'Origine', 'Entries'], $rows);
        $io->writeln(sprintf('Total entries: %d', $total));
        $io->writeln(sprintf('Entries encore stockees en other: %d', $otherCount));

        $outputPath = $input->getOption('output');
        if (is_string($outputPath) && trim($outputPath) !== '') {
            file_put_contents($outputPath, $this->renderTextReport($rows, $total, $otherCount));
            $io->success(sprintf('Rapport exporte dans %s.', $outputPath));
        }

        return Command::SUCCESS;
    }

    /**
     * @param array<int, array{0: string, 1: string, 2: int}> $rows
     */
    private function renderTextReport(array $rows, int $total, int $otherCount): string
    {
        $lines = [
            'Rapport media final',
            '===================',
            '',
            'media_final;origine;entries',
        ];

        foreach ($rows as $row) {
            $lines[] = implode(';', $row);
        }

        $lines[] = '';
        $lines[] = sprintf('total_entries;%d', $total);
        $lines[] = sprintf('entries_other;%d', $otherCount);

        return implode("\n", $lines)."\n";
    }
}
