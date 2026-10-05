<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Validator\ValidatorInterface;

// Single place where users come into existence: console command and installer.
final class UserProvisioner
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * @throws \InvalidArgumentException with a message safe to show the operator
     */
    public function create(string $email, string $password): User
    {
        if ('' === $email || \count($this->validator->validate($email, new Email())) > 0) {
            throw new \InvalidArgumentException('Invalid email address.');
        }
        if ('' === $password) {
            throw new \InvalidArgumentException('Password must not be empty.');
        }
        if (null !== $this->em->getRepository(User::class)->findOneBy(['email' => $email])) {
            throw new \InvalidArgumentException(sprintf('User %s already exists.', $email));
        }

        $user = new User($email, '');
        $user->setPassword($this->hasher->hashPassword($user, $password));
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}
