<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Chapter;
use App\Entity\Work;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Chapter>
 */
class ChapterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Chapter::class);
    }

    /**
     * Published chapters of a work with pages hydrated in one query.
     *
     * @return list<Chapter>
     */
    public function findPublishedWithPages(Work $work): array
    {
        // Fetch join initializes Chapter::$pages, so no lazy load per chapter.
        // The mapping's OrderBy on pages is applied to the join by the ORM.
        return $this->createQueryBuilder('c')
            ->addSelect('p')
            ->leftJoin('c.pages', 'p')
            ->where('c.work = :work')
            ->andWhere('c.published = true')
            ->setParameter('work', $work)
            ->orderBy('c.number', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
