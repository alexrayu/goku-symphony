<?php

declare(strict_types=1);

namespace App\Image;

// Seam for the shared-hosting port (Imagick, GD): swap the implementation via a DI alias.
interface ImageProcessor
{
    /**
     * Shrinks $source to at most $maxWidth wide (never upscaled), cuts it into $tileSize squares
     * and writes them to $target as one WebP with the tiles in shuffled slots. Edge tiles are
     * padded to full size. Same format as goku-static: order[sourceTile] = slot, row-major.
     *
     * @return array{int, int, list<int>} reading width, reading height, tile order
     */
    public function toScrambledWebp(string $source, string $target, int $maxWidth, int $tileSize): array;

    /**
     * Writes an unscrambled JPEG of $source cropped to $width x $height (never upscaled), for link previews.
     */
    public function toCoverJpeg(string $source, string $target, int $width, int $height): void;
}
