<?php

declare(strict_types=1);

namespace App\Install;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;

// The site counts as installed once any user exists. Only the positive answer is cached.
final class InstallState
{
    private const KEY = 'app.installed';

    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function isInstalled(): bool
    {
        $item = $this->cache->getItem(self::KEY);
        if ($item->isHit()) {
            return true;
        }

        $exists = null !== $this->em->createQuery('SELECT u.id FROM '.User::class.' u')
            ->setMaxResults(1)
            ->getOneOrNullResult();
        if ($exists) {
            $this->cache->save($item->set(true));
        }

        return $exists;
    }
}
