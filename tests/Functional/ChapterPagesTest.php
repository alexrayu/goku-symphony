<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Chapter;
use App\Enum\PageStatus;
use App\Factory\ChapterFactory;
use App\Factory\PageFactory;
use App\Factory\UserFactory;
use App\Ingest\ChapterPages;
use App\Ingest\StorageKeys;
use League\Flysystem\FilesystemOperator;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class ChapterPagesTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    protected function tearDown(): void
    {
        foreach (['incoming', 'originals', 'derivatives', 'covers', 'thumbs'] as $dir) {
            static::getContainer()->get('default.storage')->deleteDirectory($dir);
        }
        parent::tearDown();
    }

    public function testDeletePagesRemovesRowsAndFilesAndAllowsANewUpload(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne());
        $chapter = ChapterFactory::createOne();
        $files = $this->storeChapter($chapter);

        $crawler = $client->request('GET', sprintf('/admin/chapter/%d/delete-pages', $chapter->getId()));
        self::assertResponseIsSuccessful();
        $client->submit($crawler->selectButton('Delete 2 pages')->form());

        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Pages deleted');
        self::assertSame(0, PageFactory::count(['chapter' => $chapter]));
        $this->assertNoneExist($files);

        // The upload form no longer refuses the chapter.
        $client->request('GET', sprintf('/admin/chapter/%d/upload', $chapter->getId()));
        self::assertResponseIsSuccessful();
    }

    public function testDeletePagesNeedsAValidToken(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne());
        $chapter = ChapterFactory::createOne();
        PageFactory::createOne(['chapter' => $chapter]);

        $client->request('POST', sprintf('/admin/chapter/%d/delete-pages', $chapter->getId()), ['_token' => 'forged']);

        self::assertSelectorTextContains('.alert-danger', 'Invalid CSRF token');
        self::assertSame(1, PageFactory::count(['chapter' => $chapter]));
    }

    public function testDeletingAChapterRemovesItsFiles(): void
    {
        static::bootKernel();
        $chapter = ChapterFactory::createOne();
        $files = $this->storeChapter($chapter);
        $incoming = StorageKeys::incoming($chapter);
        $this->storage()->write($incoming, 'zip');

        static::getContainer()->get(ChapterPages::class)->deleteChapter($chapter);

        self::assertSame(0, ChapterFactory::count());
        $this->assertNoneExist([...$files, $incoming]);
    }

    public function testChapterStillProcessingIsKept(): void
    {
        static::bootKernel();
        $chapter = ChapterFactory::createOne();
        PageFactory::createOne(['chapter' => $chapter, 'status' => PageStatus::Processing]);

        try {
            static::getContainer()->get(ChapterPages::class)->clear($chapter);
            self::fail('Expected the processing chapter to be refused.');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('still being processed', $e->getMessage());
        }
        self::assertSame(1, PageFactory::count(['chapter' => $chapter]));
    }

    /**
     * Two ready pages with originals and reading copies, plus cover and thumbnail.
     *
     * @return list<string> the stored keys
     */
    private function storeChapter(Chapter $chapter): array
    {
        $keys = [StorageKeys::cover($chapter), StorageKeys::thumbnail($chapter)];
        foreach ([10, 20] as $position) {
            $original = StorageKeys::original($chapter, 'png');
            $page = PageFactory::createOne(['chapter' => $chapter, 'position' => $position, 'originalKey' => $original]);
            $keys[] = $original;
            $keys[] = StorageKeys::derivative($page);
        }
        foreach ($keys as $key) {
            $this->storage()->write($key, 'bytes');
        }

        return $keys;
    }

    /**
     * @param list<string> $keys
     */
    private function assertNoneExist(array $keys): void
    {
        foreach ($keys as $key) {
            self::assertFalse($this->storage()->fileExists($key), $key);
        }
    }

    private function storage(): FilesystemOperator
    {
        return static::getContainer()->get('default.storage');
    }
}
