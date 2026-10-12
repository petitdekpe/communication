<?php

declare(strict_types=1);

namespace App\Newsletter;

use App\Data\KeyFigureCsvExporter;
use App\Repository\NewsletterSubscriberRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Export des abonnés confirmés (CSV UTF-8), pour l'administration, tant qu'aucune interface d'administration
 * ne le propose. Les désinscrits et les inscriptions non confirmées n'y figurent jamais.
 */
#[AsCommand(name: 'app:newsletter:export', description: 'Exporte les abonnés confirmés de la lettre d\'information au format CSV')]
final class ExportSubscribersCommand extends Command
{
    public function __construct(private readonly NewsletterSubscriberRepository $subscribers)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Fichier de sortie (par défaut : sortie standard)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = $input->getOption('output');
        $toFile = \is_string($file) && '' !== $file;
        // Sans fichier de sortie : CSV construit en mémoire puis écrit sur la sortie de la commande.
        $stream = $toFile ? fopen($file, 'w') : fopen('php://temp', 'w+');
        if (false === $stream) {
            (new SymfonyStyle($input, $output))->error('Impossible d\'écrire le fichier de sortie.');

            return Command::FAILURE;
        }

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, ['email', 'language', 'subscribed_at', 'confirmed_at'], ';', '"', '\\', "\r\n");
        $count = 0;
        foreach ($this->subscribers->findConfirmed() as $subscriber) {
            fputcsv($stream, [
                KeyFigureCsvExporter::safe($subscriber->getEmail()),
                $subscriber->getLocale(),
                $subscriber->getSubscribedAt()->format(\DATE_ATOM),
                $subscriber->getConfirmedAt()?->format(\DATE_ATOM) ?? '',
            ], ';', '"', '\\', "\r\n");
            ++$count;
        }
        if (!$toFile) {
            rewind($stream);
            $output->write((string) stream_get_contents($stream));
        }
        fclose($stream);

        if ($toFile) {
            (new SymfonyStyle($input, $output))->success(\sprintf('%d abonné(s) exporté(s) dans %s.', $count, $file));
        }

        return Command::SUCCESS;
    }
}
