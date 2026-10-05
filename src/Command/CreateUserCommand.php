<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Validator\ValidatorInterface;

// No public registration: users are created here only.
#[AsCommand(name: 'app:user:create', description: 'Create a user who can log in and manage works')]
final class CreateUserCommand
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Login email')] string $email,
        #[Argument('Plain password (prompted if omitted)')] ?string $password = null,
    ): int {
        if (\count($this->validator->validate($email, new Email())) > 0 || '' === $email) {
            $io->error('Invalid email address.');

            return Command::INVALID;
        }

        if ($this->em->getRepository(User::class)->findOneBy(['email' => $email])) {
            $io->error(sprintf('User %s already exists.', $email));

            return Command::FAILURE;
        }

        $password ??= $io->askHidden('Password');
        if (null === $password || \strlen($password) < 12) {
            $io->error('Password must be at least 12 characters.');

            return Command::INVALID;
        }

        $user = new User($email, '');
        $user->setPassword($this->hasher->hashPassword($user, $password));
        $this->em->persist($user);
        $this->em->flush();

        $io->success(sprintf('Created %s.', $email));

        return Command::SUCCESS;
    }
}
