<?php

declare(strict_types=1);

namespace App\Tests\Ingest;

use App\Entity\Chapter;
use App\Entity\Page;
use App\Enum\PageStatus;
use App\Factory\ChapterFactory;
use App\Factory\PageFactory;
use App\Ingest\ArchiveIngestor;
use App\Ingest\DerivativeGenerator;
use App\Ingest\Message\GenerateDerivative;
use App\Ingest\Message\IngestArchive;
use App\Ingest\StorageKeys;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Process\Process;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

// Runs both handlers synchronously on a generated archive; needs the vips CLI.
final class IngestPipelineTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    private string $workDir;

    protected function setUp(): void
    {
        $this->workDir = sys_get_temp_dir().'/ingest-test-'.bin2hex(random_bytes(4));
        mkdir($this->workDir);
    }

    protected function tearDown(): void
    {
        $storage = static::getContainer()->get('default.storage');
        foreach (['incoming', 'originals', 'derivatives'] as $dir) {
            $storage->deleteDirectory($dir);
        }
        (new Filesystem())->remove($this->workDir);
        parent::tearDown();
    }

    public function testArchiveBecomesNaturallyOrderedReadyPages(): void
    {
        $chapter = ChapterFactory::createOne();
        // Width encodes the source name, so the stored order is checkable after resizing.
        $key = $this->storeArchive($chapter, [
            'page_10.png' => [2400, 100],
            'page_2.png' => [50, 60],
            'page_1.jpg' => [40, 60],
            'page_11_strip.png' => [100, 20000],
            '__MACOSX/._page_1.jpg' => null,
            '.DS_Store' => null,
            'credits.txt' => null,
        ]);

        static::getContainer()->get(ArchiveIngestor::class)->ingest(new IngestArchive((int) $chapter->getId(), $key));

        $generator = static::getContainer()->get(DerivativeGenerator::class);
        $sent = $this->asyncTransport()->getSent();
        self::assertCount(4, $sent);
        foreach ($sent as $envelope) {
            $message = $envelope->getMessage();
            self::assertInstanceOf(GenerateDerivative::class, $message);
            $generator->generate($message);
        }

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $pages = $em->getRepository(Page::class)->findBy(['chapter' => $chapter->getId()], ['position' => 'ASC']);

        self::assertSame([10, 20, 30, 40], array_map(static fn (Page $p) => $p->getPosition(), $pages));
        // page_1 and page_2 kept as is, page_10 shrunk to 1200 wide, the strip capped at WebP's 16383 px height.
        self::assertSame([[40, 60], [50, 60], [1200, 50]], array_map(
            static fn (Page $p) => [$p->getWidth(), $p->getHeight()],
            \array_slice($pages, 0, 3),
        ));
        self::assertSame(16383, $pages[3]->getHeight());
        self::assertLessThan(100, (int) $pages[3]->getWidth());

        $storage = static::getContainer()->get('default.storage');
        foreach ($pages as $page) {
            self::assertSame(PageStatus::Ready, $page->getStatus());
            self::assertTrue($storage->fileExists($page->getOriginalKey()));
            self::assertTrue($storage->fileExists(StorageKeys::derivative($page)));
        }
        self::assertFalse($storage->fileExists($key), 'Incoming archive is deleted after ingest.');
    }

    public function testChapterWithPagesRejectsArchiveWithoutRetry(): void
    {
        $chapter = ChapterFactory::createOne();
        PageFactory::createOne(['chapter' => $chapter]);
        $key = $this->storeArchive($chapter, ['page_1.png' => [10, 10]]);

        try {
            static::getContainer()->get(ArchiveIngestor::class)->ingest(new IngestArchive((int) $chapter->getId(), $key));
            self::fail('Expected an unrecoverable failure.');
        } catch (UnrecoverableMessageHandlingException) {
        }

        self::assertFalse(static::getContainer()->get('default.storage')->fileExists($key));
        self::assertCount(0, $this->asyncTransport()->getSent());
    }

    /**
     * @param array<string, array{int, int}|null> $entries name => image size, or null for a junk text entry
     */
    private function storeArchive(Chapter $chapter, array $entries): string
    {
        $zipPath = $this->workDir.'/chapter.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        foreach ($entries as $name => $size) {
            if (null === $size) {
                $zip->addFromString($name, 'junk');
                continue;
            }
            $file = $this->workDir.'/'.basename($name);
            (new Process(['vips', 'black', $file, (string) $size[0], (string) $size[1]]))->mustRun();
            $zip->addFile($file, $name);
        }
        $zip->close();

        $key = StorageKeys::incoming($chapter);
        $stream = fopen($zipPath, 'rb');
        self::assertIsResource($stream);
        static::getContainer()->get('default.storage')->writeStream($key, $stream);
        fclose($stream);

        return $key;
    }

    private function asyncTransport(): InMemoryTransport
    {
        $transport = static::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);

        return $transport;
    }
}
