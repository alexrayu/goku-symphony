<?php

declare(strict_types=1);

namespace App\Image;

use Random\Randomizer;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

// libvips streams the image (shrink-on-load), so memory stays flat however tall the strip.
final class VipsCliImageProcessor implements ImageProcessor
{
    // WebP cannot exceed this on either side; very tall strips are scaled to fit.
    private const WEBP_MAX_SIDE = 16383;
    private const QUALITY = 80;

    public function __construct(private readonly Filesystem $filesystem = new Filesystem())
    {
    }

    public function toScrambledWebp(string $source, string $target, int $maxWidth, int $tileSize): array
    {
        // Random name, no spaces: vips takes the bandjoin inputs as one space-separated argument.
        $dir = sys_get_temp_dir().'/scramble_'.bin2hex(random_bytes(8));
        if (!mkdir($dir, 0o700)) {
            throw new \RuntimeException('Cannot create a temporary directory.');
        }

        try {
            // The packed image is padded to whole tiles, so the reading height is capped one tile row short.
            $reading = $dir.'/reading.v';
            $this->run([
                'vips', 'thumbnail', $source, $reading, (string) $maxWidth,
                '--height', (string) (intdiv(self::WEBP_MAX_SIDE, $tileSize) * $tileSize), '--size', 'down',
            ]);
            $width = (int) $this->run(['vipsheader', '-f', 'width', $reading]);
            $height = (int) $this->run(['vipsheader', '-f', 'height', $reading]);
            $columns = intdiv($width + $tileSize - 1, $tileSize);
            $rows = intdiv($height + $tileSize - 1, $tileSize);

            /** @var list<int> $order */
            $order = (new Randomizer())->shuffleArray(range(0, $columns * $rows - 1));

            // mapim index: every packed pixel holds the reading-copy coordinate it copies from.
            // Built at tile resolution as two matrices (x and y origin of the tile in each slot),
            // zoomed to full size, plus the pixel's offset inside its tile.
            $sourceOfSlot = array_flip($order);
            $origins = ['x' => '', 'y' => ''];
            for ($slot = 0; $slot < $columns * $rows; ++$slot) {
                $tile = $sourceOfSlot[$slot];
                $separator = 0 === ($slot + 1) % $columns ? "\n" : ' ';
                $origins['x'] .= ($tile % $columns) * $tileSize.$separator;
                $origins['y'] .= intdiv($tile, $columns) * $tileSize.$separator;
            }
            foreach ($origins as $axis => $values) {
                $this->filesystem->dumpFile("$dir/$axis.mat", "$columns $rows\n$values");
            }

            $packedWidth = (string) ($columns * $tileSize);
            $packedHeight = (string) ($rows * $tileSize);
            $size = (string) $tileSize;
            $this->run(['vips', 'bandjoin', "$dir/x.mat $dir/y.mat", "$dir/origin.v"]);
            $this->run(['vips', 'cast', "$dir/origin.v", "$dir/origin-int.v", 'ushort']);
            $this->run(['vips', 'zoom', "$dir/origin-int.v", "$dir/origin-full.v", $size, $size]);
            $this->run(['vips', 'xyz', "$dir/xy.v", $packedWidth, $packedHeight]);
            $this->run(['vips', 'remainder_const', "$dir/xy.v", "$dir/offset.v", $size]);
            $this->run(['vips', 'add', "$dir/origin-full.v", "$dir/offset.v", "$dir/index.v"]);
            $this->run(['vips', 'embed', $reading, "$dir/padded.v", '0', '0', $packedWidth, $packedHeight, '--extend', 'copy']);
            $this->run([
                'vips', 'mapim', "$dir/padded.v", sprintf('%s[Q=%d,keep=none]', $target, self::QUALITY), "$dir/index.v",
                '--interpolate', 'nearest',
            ]);

            return [$width, $height, $order];
        } finally {
            $this->filesystem->remove($dir);
        }
    }

    public function toCoverJpeg(string $source, string $target, int $width, int $height): void
    {
        $this->run([
            'vips', 'thumbnail', $source, sprintf('%s[Q=%d,keep=none]', $target, self::QUALITY), (string) $width,
            '--height', (string) $height, '--crop', 'attention', '--size', 'down',
        ]);
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
