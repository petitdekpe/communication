<?php

declare(strict_types=1);

namespace App\Retention;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Lance la purge à la demande (le Scheduler la lance chaque nuit). --dry-run compte sans rien supprimer.
 */
#[AsCommand(name: 'app:purge-personal-data', description: 'Supprime les données personnelles arrivées au terme de leur durée de conservation')]
final class PurgePersonalDataCommand extends Command
{
    public function __construct(private readonly PersonalDataPurger $purger)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Compte les lignes concernées sans les supprimer');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $report = $this->purger->purge($dryRun);

        $io->table(['Catégorie', $dryRun ? 'À purger' : 'Purgées'], [
            ['Demandes traitées', $report->requests],
            ['Contributions', $report->contributions],
            ['Inscrits à la lettre', $report->subscribers],
        ]);

        return Command::SUCCESS;
    }
}
