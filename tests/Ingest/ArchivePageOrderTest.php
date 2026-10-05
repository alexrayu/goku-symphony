<?php

declare(strict_types=1);

namespace App\Tests\Ingest;

use App\Ingest\ArchivePageOrder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ArchivePageOrderTest extends TestCase
{
    /**
     * @param list<string> $entries
     * @param list<string> $expected
     */
    #[DataProvider('listings')]
    public function testSort(array $entries, array $expected): void
    {
        self::assertSame($expected, (new ArchivePageOrder())->sort($entries));
    }

    /**
     * @return iterable<string, array{list<string>, list<string>}>
     */
    public static function listings(): iterable
    {
        yield 'natural order, not lexical' => [
            ['page_10.png', 'page_2.png', 'page_1.png'],
            ['page_1.png', 'page_2.png', 'page_10.png'],
        ];
        yield 'case-insensitive names and extensions' => [
            ['B.JPG', 'a.Png', 'c.jpeg'],
            ['a.Png', 'B.JPG', 'c.jpeg'],
        ];
        yield 'folders order before their files' => [
            ['ch2/001.webp', 'ch10/001.webp', 'ch1/002.gif', 'ch1/001.gif'],
            ['ch1/001.gif', 'ch1/002.gif', 'ch2/001.webp', 'ch10/001.webp'],
        ];
        yield 'junk dropped' => [
            ['scans/', '__MACOSX/._01.png', '.DS_Store', '.hidden/01.png', 'scans/.thumb.png', 'notes.txt', 'cover.bmp', 'scans/01.png'],
            ['scans/01.png'],
        ];
        yield 'no images' => [['readme.txt', 'scans/'], []];
    }
}
