<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Chapter;
use App\Entity\Work;
use PHPUnit\Framework\TestCase;

final class ChapterPublicationTest extends TestCase
{
    public function testFirstPublicationIsRecordedOnceAndKept(): void
    {
        self::assertNull((new Chapter(new Work('Saga', 'saga'), '1'))->getPublishedAt());

        $chapter = (new Chapter(new Work('Saga', 'saga'), '1'))->setPublished(true);
        $first = $chapter->getPublishedAt();
        self::assertNotNull($first);

        // Unpublish and republish: still the first date, so sitemaps and "new" do not jump.
        $chapter->setPublished(false)->setPublished(true);
        self::assertSame($first, $chapter->getPublishedAt());

        self::assertNotNull((new Chapter(new Work('Saga', 'saga'), '2', published: true))->getPublishedAt());
    }

    public function testNewForAWeekAfterFirstPublication(): void
    {
        $chapter = new Chapter(new Work('Saga', 'saga'), '1', published: true);
        self::assertTrue($chapter->isNew());

        self::publishedAgo($chapter, '8 days');
        self::assertFalse($chapter->isNew());

        $draft = new Chapter(new Work('Saga', 'saga'), '2');
        self::assertFalse($draft->isNew());
    }

    // No setter on purpose: the date is only ever set by the first publication.
    public static function publishedAgo(Chapter $chapter, string $interval): void
    {
        (new \ReflectionProperty(Chapter::class, 'publishedAt'))->setValue($chapter, new \DateTimeImmutable('-'.$interval));
    }
}
