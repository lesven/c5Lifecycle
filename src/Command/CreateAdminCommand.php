<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\Repository\UserRepositoryInterface;
use App\Infrastructure\Persistence\Entity\User;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Erstellt einen Admin-Benutzer',
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Admin-Benutzer anlegen');

        $email = $io->ask('E-Mail-Adresse', null, function (?string $value): string {
            if ($value === null || trim($value) === '') {
                throw new \RuntimeException('E-Mail-Adresse darf nicht leer sein.');
            }
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('Ungültige E-Mail-Adresse.');
            }

            return trim($value);
        });

        // Check if email already exists
        if ($this->userRepository->findByEmail($email) !== null) {
            $io->error(sprintf('Ein Benutzer mit der E-Mail „%s" existiert bereits.', $email));

            return Command::FAILURE;
        }

        $displayName = $io->ask('Anzeigename', null, function (?string $value): string {
            if ($value === null || trim($value) === '') {
                throw new \RuntimeException('Anzeigename darf nicht leer sein.');
            }

            return trim($value);
        });

        $plainPassword = $io->askHidden('Passwort (mind. 8 Zeichen)', function (?string $value): string {
            if ($value === null || $value === '') {
                throw new \RuntimeException('Passwort darf nicht leer sein.');
            }
            if (strlen($value) < 8) {
                throw new \RuntimeException('Passwort muss mindestens 8 Zeichen lang sein.');
            }

            return $value;
        });

        $user = new User($email, $displayName, '', ['ROLE_ADMIN']);
        $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->setPassword($hashedPassword);

        $this->userRepository->save($user);

        $io->success(sprintf('Admin-Benutzer „%s" (%s) wurde erfolgreich angelegt.', $displayName, $email));

        return Command::SUCCESS;
    }
}
