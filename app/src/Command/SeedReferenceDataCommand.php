<?php

namespace App\Command;

use App\Service\ReferenceDataSeeder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:seed-reference-data', description: 'Cree les valeurs de reference manquantes.')]
class SeedReferenceDataCommand extends Command
{
    public function __construct(
        private readonly ReferenceDataSeeder $referenceDataSeeder,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->referenceDataSeeder->seed();
        (new SymfonyStyle($input, $output))->success('Tables de reference synchronisees.');

        return Command::SUCCESS;
    }
}
