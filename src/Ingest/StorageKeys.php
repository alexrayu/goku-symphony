<?php

declare(strict_types=1);

namespace App\Ingest;

use App\Entity\Chapter;
use App\Entity\Page;
use App\Entity\Work;

// Storage layout in one place. Keys, never URLs: URLs come from the URL generator (phase 5).
final class StorageKeys
{
    public static function incoming(Chapter $chapter): string
    {
        return sprintf('%s/%s.zip', self::incomingDirectory($chapter), bin2hex(random_bytes(8)));
    }

    public static function incomingDirectory(Chapter $chapter): string
    {
        return sprintf('incoming/%d', $chapter->getId());
    }

    public static function original(Chapter $chapter, string $extension): string
    {
        return sprintf('%s/%s.%s', self::originalsDirectory($chapter), bin2hex(random_bytes(8)), strtolower($extension));
    }

    public static function derivative(Page $page): string
    {
        return sprintf('%s/%d.webp', self::derivativesDirectory($page->getChapter()), $page->getId());
    }

    // Unscrambled link-preview image, made from the chapter's first page.
    public static function cover(Chapter $chapter): string
    {
        return sprintf('covers/%d.jpg', $chapter->getId());
    }

    // Unscrambled portrait thumbnail for listings, from the same page as the cover.
    public static function thumbnail(Chapter $chapter): string
    {
        return sprintf('thumbs/%d.webp', $chapter->getId());
    }

    /**
     * Everything stored for a chapter's pages: originals, reading copies, cover and thumbnail.
     *
     * @return array{directories: list<string>, files: list<string>}
     */
    public static function pageFiles(Chapter $chapter): array
    {
        return [
            'directories' => [self::originalsDirectory($chapter), self::derivativesDirectory($chapter)],
            'files' => [self::cover($chapter), self::thumbnail($chapter)],
        ];
    }

    // Chosen work cover, replacing the chapter-derived pair. Overwritten in place; URLs carry the version.
    public static function workCover(Work $work): string
    {
        return sprintf('covers/works/%d.jpg', $work->getId());
    }

    public static function workThumbnail(Work $work): string
    {
        return sprintf('thumbs/works/%d.webp', $work->getId());
    }

    public static function logo(): string
    {
        return 'site/logo.webp';
    }

    private static function originalsDirectory(Chapter $chapter): string
    {
        return sprintf('originals/%d', $chapter->getId());
    }

    private static function derivativesDirectory(Chapter $chapter): string
    {
        return sprintf('derivatives/%d', $chapter->getId());
    }
}
