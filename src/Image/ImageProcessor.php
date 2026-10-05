<?php

declare(strict_types=1);

namespace App\Image;

// Seam for the shared-hosting port (Imagick, GD): swap the implementation via a DI alias.
interface ImageProcessor
{
    /**
     * Writes a WebP of $source to $target, shrunk to at most $maxWidth wide, never upscaled.
     *
     * @return array{int, int} width and height of the written file
     */
    public function toWebp(string $source, string $target, int $maxWidth): array;
}
