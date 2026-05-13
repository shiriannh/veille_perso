<?php

namespace App\Command;

use App\Entity\Entry;
use App\Enum\AnalysisDecision;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:prune-ignored-entries', description: 'Nettoie manuellement les Entry ignorees anciennes et sans liens metier.')]
class PruneIgnoredEntriesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly int $ignoredEntryRetentionDays,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', null, InputOption::VALUE_REQUIRED, 'Age minimal en jours.', $this->ignoredEntryRetentionDays)
            ->addOption('force', null, InputOption::VALUE_NONE, 'Supprime reellement. Sans cette option, la commande reste en dry-run.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = max(1, (int) $input->getOption('days'));
        $force = (bool) $input->getOption('force');
        $before = new \DateTimeImmutable(sprintf('-%d days', $days));

        $entries = $this->entityManager->getRepository(Entry::class)
            ->createQueryBuilder('entry')
            ->leftJoin('entry.review', 'review')
            ->leftJoin('entry.synthesisReports', 'report')
            ->andWhere('entry.decision = :ignored')
            ->andWhere('entry.importedAt IS NOT NULL')
            ->andWhere('entry.importedAt < :before')
            ->andWhere('review.id IS NULL')
            ->andWhere('report.id IS NULL')
            ->setParameter('ignored', AnalysisDecision::Ignored)
            ->setParameter('before', $before)
            ->orderBy('entry.importedAt', 'ASC')
            ->getQuery()
            ->getResult();

        $io->title('Retention Entry ignorees');
        $io->writeln(sprintf('Politique: supprimer uniquement les Entry ignorees importees depuis plus de %d jours, sans Review et sans synthese.', $days));
        $io->writeln(sprintf('Candidats: %d', count($entries)));

        if (!$force) {
            $io->note('Dry-run: aucune suppression. Ajoute --force pour appliquer.');

            return Command::SUCCESS;
        }

        foreach ($entries as $entry) {
            $this->entityManager->remove($entry);
        }

        $this->entityManager->flush();
        $io->success(sprintf('%d Entry supprimee(s).', count($entries)));

        return Command::SUCCESS;
    }
}
