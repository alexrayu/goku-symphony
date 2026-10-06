<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Chapter;
use App\Entity\Work;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Work>
 */
class WorkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Work::class);
    }

    /**
     * Works with at least one published chapter: what the public may see.
     *
     * @return list<Work>
     */
    public function findPublished(): array
    {
        return $this->createQueryBuilder('w')
            ->where(sprintf('EXISTS (SELECT 1 FROM %s c WHERE c.work = w AND c.published = true)', Chapter::class))
            ->orderBy('w.title', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
