<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Enum\AdminRole;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Crée un compte d'administration (premier super administrateur, notamment). Le mot de passe est demandé sans écho ;
 * l'application de double authentification sera enrôlée par l'utilisateur à sa première connexion.
 */
#[AsCommand(name: 'app:user:create', description: "Crée un utilisateur de l'administration")]
final class CreateUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Adresse électronique (identifiant de connexion)')
            ->addArgument('name', InputArgument::REQUIRED, 'Nom complet')
            ->addOption('role', 'r', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Rôle (répétable) : '.implode(', ', array_column(AdminRole::cases(), 'value')), [AdminRole::SuperAdmin->value]);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = mb_strtolower(trim((string) $input->getArgument('email')));
        $roles = array_map('strval', (array) $input->getOption('role'));

        $invalid = array_diff($roles, array_column(AdminRole::cases(), 'value'));
        if ([] !== $invalid) {
            $io->error('Rôle inconnu : '.implode(', ', $invalid));

            return Command::INVALID;
        }
        if (!filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            $io->error('Adresse électronique invalide.');

            return Command::INVALID;
        }
        if (null !== $this->users->findByEmail($email)) {
            $io->error('Un compte existe déjà avec cette adresse.');

            return Command::FAILURE;
        }

        $password = (string) $io->askHidden('Mot de passe (12 caractères au minimum)');
        if (mb_strlen($password) < 12) {
            $io->error('Le mot de passe doit contenir au moins 12 caractères.');

            return Command::INVALID;
        }

        $user = (new User())->setEmail($email)->setName((string) $input->getArgument('name'))->setRoles($roles);
        $user->setPassword($this->hasher->hashPassword($user, $password));
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(\sprintf('Compte %s créé (%s). La double authentification sera enrôlée à la première connexion.', $email, implode(', ', $user->getRoles())));

        return Command::SUCCESS;
    }
}
