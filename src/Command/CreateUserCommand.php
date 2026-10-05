<?php

declare(strict_types=1);

namespace App\Command;

use App\Security\UserProvisioner;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

// No public registration: users are created here or by the first-run installer only.
#[AsCommand(name: 'app:user:create', description: 'Create a user who can log in and manage works')]
final class CreateUserCommand
{
    public function __construct(private readonly UserProvisioner $provisioner)
    {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Login email')] string $email,
        #[Argument('Plain password (prompted if omitted)')] ?string $password = null,
    ): int {
        $password ??= (string) $io->askHidden('Password');

        try {
            $this->provisioner->create($email, $password);
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        }

        $io->success(sprintf('Created %s.', $email));

        return Command::SUCCESS;
    }
}
