<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Chapter;
use App\Enum\PageStatus;
use App\Factory\ChapterFactory;
use App\Factory\PageFactory;
use App\Factory\UserFactory;
use App\Entity\Page;
use App\Ingest\ChapterPages;
use App\Ingest\Message\GenerateChapterCovers;
use App\Ingest\StorageKeys;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
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

    public function testMoveTakesTheGapBetweenItsNewNeighbours(): void
    {
        static::bootKernel();
        $chapter = ChapterFactory::createOne();
        [$first, $second, $third] = $this->pagesAt($chapter, [10, 20, 30]);

        static::getContainer()->get(ChapterPages::class)->move($chapter, (int) $third->getId(), $first->getId());

        self::assertSame([[$first->getId(), 10], [$third->getId(), 15], [$second->getId(), 20]], $this->order($chapter));
        self::assertCount(0, $this->asyncTransport()->getSent(), 'The first page is unchanged.');
    }

    public function testMoveRespacesTheChapterWhenNoPositionIsLeft(): void
    {
        static::bootKernel();
        $chapter = ChapterFactory::createOne();
        [$first, $second, $third] = $this->pagesAt($chapter, [10, 11, 12]);

        static::getContainer()->get(ChapterPages::class)->move($chapter, (int) $third->getId(), $first->getId());

        self::assertSame([[$first->getId(), 10], [$third->getId(), 20], [$second->getId(), 30]], $this->order($chapter));
    }

    public function testANewFirstPageRebuildsTheCovers(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne());
        $chapter = ChapterFactory::createOne();
        [, $second] = $this->pagesAt($chapter, [10, 20]);

        $crawler = $client->request('GET', sprintf('/admin/chapter/%d', $chapter->getId()));
        $token = (string) $crawler->filter('[data-chapter-pages-token-value]')->attr('data-chapter-pages-token-value');
        $client->request('POST', sprintf('/admin/chapter/%d/move-page', $chapter->getId()), ['_token' => $token, 'page' => $second->getId()]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame($second->getId(), $this->order($chapter)[0][0]);
        $sent = $this->asyncTransport()->getSent();
        self::assertCount(1, $sent);
        self::assertEquals(new GenerateChapterCovers((int) $chapter->getId()), $sent[0]->getMessage());
    }

    public function testMoveNeedsAValidTokenAndAPageOfTheChapter(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne());
        $chapter = ChapterFactory::createOne();
        [$first, $second] = $this->pagesAt($chapter, [10, 20]);
        $foreign = PageFactory::createOne();
        $url = sprintf('/admin/chapter/%d/move-page', $chapter->getId());

        $client->request('POST', $url, ['_token' => 'forged', 'page' => $second->getId()]);
        self::assertResponseStatusCodeSame(409);

        $crawler = $client->request('GET', sprintf('/admin/chapter/%d', $chapter->getId()));
        $token = (string) $crawler->filter('[data-chapter-pages-token-value]')->attr('data-chapter-pages-token-value');
        $client->request('POST', $url, ['_token' => $token, 'page' => $foreign->getId()]);
        self::assertResponseStatusCodeSame(409);

        self::assertSame([[$first->getId(), 10], [$second->getId(), 20]], $this->order($chapter));
    }

    /**
     * @param list<int> $positions
     *
     * @return list<Page>
     */
    private function pagesAt(Chapter $chapter, array $positions): array
    {
        return array_map(static fn (int $position): Page => PageFactory::createOne(['chapter' => $chapter, 'position' => $position]), $positions);
    }

    /**
     * @return list<array{int|null, int}> page id and position, in reading order, as stored
     */
    private function order(Chapter $chapter): array
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $pages = $em->getRepository(Page::class)->findBy(['chapter' => $chapter->getId()], ['position' => 'ASC']);

        return array_map(static fn (Page $page): array => [$page->getId(), $page->getPosition()], $pages);
    }

    private function asyncTransport(): InMemoryTransport
    {
        $transport = static::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);

        return $transport;
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
