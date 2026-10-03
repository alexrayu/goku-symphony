<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Page;
use App\Entity\Work;
use App\Factory\ChapterFactory;
use App\Factory\PageFactory;
use App\Factory\WorkFactory;
use App\Repository\ChapterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\PersistentCollection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class ChapterRepositoryTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    public function testFindPublishedWithPagesInitializesAndOrdersPages(): void
    {
        $work = WorkFactory::createOne();
        foreach (['2.0', '1.0'] as $number) {
            $chapter = ChapterFactory::createOne(['work' => $work, 'number' => $number, 'published' => true]);
            foreach ([30, 10, 20] as $position) {
                PageFactory::createOne(['chapter' => $chapter, 'position' => $position]);
            }
        }
        ChapterFactory::createOne(['work' => $work, 'number' => '3.0', 'published' => false]);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $repository = self::getContainer()->get(ChapterRepository::class);
        $work = $em->getReference(Work::class, $work->getId());
        self::assertNotNull($work);

        $chapters = $repository->findPublishedWithPages($work);

        self::assertSame(['1.0', '2.0'], array_map(static fn ($c) => $c->getNumber(), $chapters));
        foreach ($chapters as $chapter) {
            $pages = $chapter->getPages();
            self::assertInstanceOf(PersistentCollection::class, $pages);
            self::assertTrue($pages->isInitialized());
            self::assertSame([10, 20, 30], array_map(static fn (Page $p) => $p->getPosition(), $chapter->getPages()->toArray()));
        }
    }
}
