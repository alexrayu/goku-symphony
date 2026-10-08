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
     * Readable chapters of a work for the chapter list; pages are not loaded.
     * Drafts are included for logged-in users previewing before release.
     *
     * @return list<Chapter>
     */
    public function findReadableByWork(Work $work, bool $includeDrafts = false): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.work = :work')
            ->setParameter('work', $work)
            ->orderBy('c.number', 'ASC');
        if (!$includeDrafts) {
            $qb->andWhere('c.published = true');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * One readable chapter with its pages, in one query, for the reader. Drafts as in findReadableByWork().
     */
    public function findForReader(Work $work, string $number, bool $includeDrafts = false): ?Chapter
    {
        // Pages are fetch-joined unfiltered: a WHERE on p would leave a partial collection
        // marked as initialized. Readiness is filtered in PHP instead.
        $qb = $this->createQueryBuilder('c')
            ->addSelect('p')
            ->leftJoin('c.pages', 'p')
            ->where('c.work = :work')
            ->andWhere('c.number = :number')
            ->setParameter('work', $work)
            ->setParameter('number', $number);
        if (!$includeDrafts) {
            $qb->andWhere('c.published = true');
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Every published chapter, grouped per work and most recently updated work first, in one query:
     * the home shelf and the sitemap. updatedAt is the work's latest first publication.
     *
     * @return list<array{work: Work, chapters: non-empty-list<Chapter>, updatedAt: ?\DateTimeImmutable}>
     */
    public function findPublishedGroupedByWork(): array
    {
        /** @var list<Chapter> $chapters */
        $chapters = $this->createQueryBuilder('c')
            ->addSelect('w')
            ->join('c.work', 'w')
            ->where('c.published = true')
            ->orderBy('w.title', 'ASC')
            ->addOrderBy('w.id', 'ASC')
            ->addOrderBy('c.number', 'ASC')
            ->getQuery()
            ->getResult();

        $groups = [];
        foreach ($chapters as $chapter) {
            $id = (int) $chapter->getWork()->getId();
            $groups[$id] ??= ['work' => $chapter->getWork(), 'chapters' => [], 'updatedAt' => null];
            $groups[$id]['chapters'][] = $chapter;
            if ($chapter->getPublishedAt() > $groups[$id]['updatedAt']) {
                $groups[$id]['updatedAt'] = $chapter->getPublishedAt();
            }
        }
        // Stable sort: equal dates keep the title order.
        usort($groups, static fn (array $a, array $b): int => $b['updatedAt'] <=> $a['updatedAt']);

        return $groups;
    }
}
