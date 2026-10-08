<?php

declare(strict_types=1);

namespace App\Ingest;

use App\Entity\Chapter;
use App\Entity\Page;

// Storage layout in one place. Keys, never URLs: URLs come from the URL generator (phase 5).
final class StorageKeys
{
    public static function incoming(Chapter $chapter): string
    {
        return sprintf('incoming/%d/%s.zip', $chapter->getId(), bin2hex(random_bytes(8)));
    }

    public static function original(Chapter $chapter, string $extension): string
    {
        return sprintf('originals/%d/%s.%s', $chapter->getId(), bin2hex(random_bytes(8)), strtolower($extension));
    }

    public static function derivative(Page $page): string
    {
        return sprintf('derivatives/%d/%d.webp', $page->getChapter()->getId(), $page->getId());
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
}
