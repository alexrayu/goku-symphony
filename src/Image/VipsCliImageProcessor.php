<?php

declare(strict_types=1);

namespace App\Image;

use Symfony\Component\Process\Process;

// libvips streams the image (shrink-on-load), so memory stays flat however tall the strip.
final class VipsCliImageProcessor implements ImageProcessor
{
    // WebP cannot exceed this on either side; very tall strips are scaled to fit.
    private const WEBP_MAX_SIDE = 16383;
    private const QUALITY = 80;

    public function toWebp(string $source, string $target, int $maxWidth): array
    {
        $this->run([
            'vips', 'thumbnail', $source, sprintf('%s[Q=%d,keep=none]', $target, self::QUALITY), (string) $maxWidth,
            '--height', (string) self::WEBP_MAX_SIDE, '--size', 'down',
        ]);

        return [
            (int) $this->run(['vipsheader', '-f', 'width', $target]),
            (int) $this->run(['vipsheader', '-f', 'height', $target]),
        ];
    }

    /**
     * @param list<string> $command
     */
    private function run(array $command): string
    {
        $process = new Process($command, timeout: 120);
        $process->mustRun();

        return trim($process->getOutput());
    }
}
