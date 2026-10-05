<?php

declare(strict_types=1);

namespace App\Ingest;

final class ArchivePageOrder
{
    private const IMAGE = '/\.(jpe?g|png|webp|gif)$/i';
    // Directory entries, macOS resource forks, and any dot-file or dot-directory segment.
    private const JUNK = '#/$|^__MACOSX/|(^|/)\.#';

    /**
     * Picks the page images out of a ZIP listing and returns them in reading order:
     * natural, case-insensitive, on the full path ("page_2" before "page_10", "ch1/" before "ch2/").
     *
     * @param list<string> $entryNames as returned by ZipArchive::getNameIndex()
     *
     * @return list<string> the kept names, unchanged, in reading order
     */
    public function sort(array $entryNames): array
    {
        $pages = array_values(array_filter(
            $entryNames,
            static fn (string $name): bool => 1 === preg_match(self::IMAGE, $name) && 0 === preg_match(self::JUNK, $name),
        ));
        usort($pages, strnatcasecmp(...));

        return $pages;
    }
}
