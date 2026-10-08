<?php

declare(strict_types=1);

namespace App\Tests\Image;

use App\Image\VipsCliImageProcessor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

// Needs the vips CLI. Each tile of the source is one flat grey, so a misplaced tile shows as a wrong mean.
final class VipsCliImageProcessorTest extends TestCase
{
    private const TILE = 128;
    private const GUTTER = 8;
    private const CELL = self::TILE + 2 * self::GUTTER;
    private const COLUMNS = 5;
    private const ROWS = 4;
    // Partial edge tiles on the right and bottom: 600 = 4 * 128 + 88, 500 = 3 * 128 + 116.
    private const WIDTH = 600;
    private const HEIGHT = 500;

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/vips_test_'.bin2hex(random_bytes(6));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->dir);
    }

    public function testTilesLandInTheSlotsTheOrderNames(): void
    {
        $source = $this->tileSource();
        $packed = $this->dir.'/packed.webp';

        [$width, $height, $order] = (new VipsCliImageProcessor())->toScrambledWebp($source, $packed, 1200, self::TILE, self::GUTTER);

        self::assertSame([self::WIDTH, self::HEIGHT], [$width, $height]);
        $sorted = $order;
        sort($sorted);
        self::assertSame(range(0, self::COLUMNS * self::ROWS - 1), $sorted, 'Order is a permutation.');
        self::assertNotSame(range(0, self::COLUMNS * self::ROWS - 1), $order, 'Tiles are shuffled.');
        self::assertSame((string) (self::COLUMNS * self::CELL), $this->vips(['vipsheader', '-f', 'width', $packed]));
        self::assertSame((string) (self::ROWS * self::CELL), $this->vips(['vipsheader', '-f', 'height', $packed]));

        foreach ($order as $tile => $slot) {
            $tileWidth = min(self::TILE, self::WIDTH - ($tile % self::COLUMNS) * self::TILE);
            $tileHeight = min(self::TILE, self::HEIGHT - intdiv($tile, self::COLUMNS) * self::TILE);
            $crop = $this->dir."/slot$slot.v";
            $this->vips([
                'vips', 'extract_area', $packed, $crop,
                (string) (($slot % self::COLUMNS) * self::CELL + self::GUTTER), (string) (intdiv($slot, self::COLUMNS) * self::CELL + self::GUTTER),
                (string) $tileWidth, (string) $tileHeight,
            ]);
            self::assertEqualsWithDelta($this->grey($tile), (float) $this->vips(['vips', 'avg', $crop]), 2.0, "Tile $tile in slot $slot");
        }
    }

    public function testCoverIsCroppedToTheRequestedBoxInTheTargetFormat(): void
    {
        foreach (['cover.jpg' => 'jpegload', 'thumb.webp' => 'webpload'] as $name => $loader) {
            $cover = $this->dir.'/'.$name;
            (new VipsCliImageProcessor())->toCover($this->tileSource(), $cover, 300, 200);

            self::assertSame(['300', '200', $loader], [
                $this->vips(['vipsheader', '-f', 'width', $cover]),
                $this->vips(['vipsheader', '-f', 'height', $cover]),
                $this->vips(['vipsheader', '-f', 'vips-loader', $cover]),
            ]);
        }
    }

    // Distinct, well-separated greys, so lossy WebP cannot blur one tile's value into another's.
    private function grey(int $tile): float
    {
        return 10.0 + $tile * 12;
    }

    private function tileSource(): string
    {
        $values = '';
        for ($tile = 0; $tile < self::COLUMNS * self::ROWS; ++$tile) {
            $values .= $this->grey($tile).(0 === ($tile + 1) % self::COLUMNS ? "\n" : ' ');
        }
        file_put_contents($this->dir.'/tiles.mat', sprintf("%d %d\n%s", self::COLUMNS, self::ROWS, $values));

        $this->vips(['vips', 'cast', $this->dir.'/tiles.mat', $this->dir.'/tiles.v', 'uchar']);
        $this->vips(['vips', 'zoom', $this->dir.'/tiles.v', $this->dir.'/zoomed.v', (string) self::TILE, (string) self::TILE]);
        $this->vips(['vips', 'extract_area', $this->dir.'/zoomed.v', $this->dir.'/source.png', '0', '0', (string) self::WIDTH, (string) self::HEIGHT]);

        return $this->dir.'/source.png';
    }

    /**
     * @param list<string> $command
     */
    private function vips(array $command): string
    {
        $process = new Process($command);
        $process->mustRun();

        return trim($process->getOutput());
    }
}
