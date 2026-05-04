<?php

namespace App\Command;

use App\Entity\AnalysisCorrection;
use App\Repository\AnalysisCorrectionRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:export-analysis-corrections', description: 'Exporte les corrections manuelles d analyse en CSV.')]
class ExportAnalysisCorrectionsCommand extends Command
{
    public function __construct(
        private readonly AnalysisCorrectionRepository $analysisCorrectionRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('output', null, InputOption::VALUE_REQUIRED, 'Chemin du fichier CSV a generer.');
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Nombre maximal de corrections exportees.', 1000);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = max(1, (int) $input->getOption('limit'));
        $corrections = $this->analysisCorrectionRepository->findLatest($limit);
        $csv = $this->renderCsv($corrections);

        $outputPath = $input->getOption('output');
        if (is_string($outputPath) && trim($outputPath) !== '') {
            file_put_contents($outputPath, $csv);
            $io->success(sprintf('%d correction(s) exportee(s) dans %s.', count($corrections), $outputPath));

            return Command::SUCCESS;
        }

        $output->write($csv);

        return Command::SUCCESS;
    }

    /**
     * @param array<int, AnalysisCorrection> $corrections
     */
    private function renderCsv(array $corrections): string
    {
        $lines = [
            $this->csvRow(['created_at', 'entry_id', 'entry_title', 'field', 'old_value', 'new_value', 'reason']),
        ];

        foreach ($corrections as $correction) {
            $entry = $correction->getEntry();
            $lines[] = $this->csvRow([
                $correction->getCreatedAt()->format('Y-m-d H:i:s'),
                (string) ($entry?->getId() ?? ''),
                $entry?->getTitle() ?? '',
                $correction->getFieldName(),
                json_encode($correction->getOldValue(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '',
                json_encode($correction->getNewValue(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '',
                $correction->getReason() ?? '',
            ]);
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @param array<int, string> $values
     */
    private function csvRow(array $values): string
    {
        return implode(';', array_map(static function (string $value): string {
            $value = str_replace('"', '""', $value);

            return '"'.$value.'"';
        }, $values));
    }
}
