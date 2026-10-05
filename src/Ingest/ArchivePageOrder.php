<?php

declare(strict_types=1);

namespace App\Ingest;

// Yours to write (phase 4), with tests/Ingest/ArchivePageOrderTest.php.
final class ArchivePageOrder
{
    /**
     * Picks the page images out of a ZIP listing and returns them in reading order.
     *
     * Keep: entries ending in .jpg, .jpeg, .png, .webp or .gif, case-insensitive.
     * Drop: directories (trailing "/"), anything under "__MACOSX/", any entry with a path
     * segment starting with "." (".DS_Store", ".hidden/page.png").
     * Order: natural, case-insensitive, on the full path, so "page_2" precedes "page_10"
     * and "ch1/..." precedes "ch2/...".
     *
     * @param list<string> $entryNames as returned by ZipArchive::getNameIndex()
     *
     * @return list<string> the kept names, unchanged, in reading order
     */
    public function sort(array $entryNames): array
    {
        throw new \LogicException('ArchivePageOrder::sort() is yours to write; see the docblock.');
    }
}
