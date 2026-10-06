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

    /**
     * Published chapters of a work for the chapter list; pages are not loaded.
     *
     * @return list<Chapter>
     */
    public function findPublishedByWork(Work $work): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.work = :work')
            ->andWhere('c.published = true')
            ->setParameter('work', $work)
            ->orderBy('c.number', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * One published chapter with its work and pages, in one query, for the reader.
     */
    public function findPublishedForReader(Work $work, string $number): ?Chapter
    {
        // Pages are fetch-joined unfiltered: a WHERE on p would leave a partial collection
        // marked as initialized. Readiness is filtered in PHP instead.
        return $this->createQueryBuilder('c')
            ->addSelect('p')
            ->leftJoin('c.pages', 'p')
            ->where('c.work = :work')
            ->andWhere('c.number = :number')
            ->andWhere('c.published = true')
            ->setParameter('work', $work)
            ->setParameter('number', $number)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Every published chapter with its work, in one query, for the sitemap.
     *
     * @return list<Chapter>
     */
    public function findAllPublishedWithWork(): array
    {
        return $this->createQueryBuilder('c')
            ->addSelect('w')
            ->join('c.work', 'w')
            ->where('c.published = true')
            ->orderBy('w.title', 'ASC')
            ->addOrderBy('w.id', 'ASC')
            ->addOrderBy('c.number', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
