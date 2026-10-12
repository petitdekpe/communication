<?php

declare(strict_types=1);

namespace App\Command;

use App\Cache\ContentCache;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:pages:cache-clear', description: 'Vide les caches de pages (HTML des pages publiées et blocs dynamiques). À lancer après un déploiement de gabarits.')]
final class ClearPageCacheCommand extends Command
{
    public function __construct(private readonly ContentCache $cache)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->cache->clear();
        $io->success('Caches de pages vidés.');

        return Command::SUCCESS;
    }
}
