<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Chapter;
use App\Entity\Page;
use App\Factory\ChapterFactory;
use App\Factory\PageFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class ChapterPagesTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    public function testRemovingPageFromCollectionDeletesIt(): void
    {
        $chapter = ChapterFactory::createOne();
        PageFactory::createMany(3, ['chapter' => $chapter]);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        $chapter = $em->find(Chapter::class, $chapter->getId());
        self::assertNotNull($chapter);
        self::assertCount(3, $chapter->getPages());

        // orphanRemoval: dropping from the inverse collection DELETEs at flush.
        $chapter->getPages()->remove(0);
        $em->flush();

        self::assertSame(2, $em->getRepository(Page::class)->count([]));
    }
}
