<?php

namespace App\Command;

use App\Entity\ImportRun;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:prune-import-runs', description: 'Nettoie manuellement les anciens historiques d import.')]
class PruneImportRunsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly int $importRunRetentionDays,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', null, InputOption::VALUE_REQUIRED, 'Age minimal en jours.', $this->importRunRetentionDays)
            ->addOption('force', null, InputOption::VALUE_NONE, 'Supprime reellement. Sans cette option, la commande reste en dry-run.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = max(1, (int) $input->getOption('days'));
        $force = (bool) $input->getOption('force');
        $before = new \DateTimeImmutable(sprintf('-%d days', $days));

        $runs = $this->entityManager->getRepository(ImportRun::class)
            ->createQueryBuilder('run')
            ->leftJoin('run.source', 'source')
            ->addSelect('source')
            ->andWhere('run.startedAt < :before')
            ->setParameter('before', $before)
            ->orderBy('run.startedAt', 'ASC')
            ->getQuery()
            ->getResult();

        $io->title('Retention ImportRun');
        $io->writeln(sprintf('Politique: supprimer les imports plus vieux que %d jours.', $days));
        $io->writeln(sprintf('Candidats: %d', count($runs)));

        if (!$force) {
            $io->note('Dry-run: aucune suppression. Ajoute --force pour appliquer.');

            return Command::SUCCESS;
        }

        foreach ($runs as $run) {
            $this->entityManager->remove($run);
        }

        $this->entityManager->flush();
        $io->success(sprintf('%d ImportRun supprime(s).', count($runs)));

        return Command::SUCCESS;
    }
}
