<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Enum\PageStatus;
use App\Enum\WorkType;
use App\Factory\ChapterFactory;
use App\Factory\PageFactory;
use App\Factory\UserFactory;
use App\Factory\WorkFactory;
use App\Ingest\StorageKeys;
use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class ReaderTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // A user must exist, or every page redirects to the installer.
        UserFactory::createOne();
    }

    protected function tearDown(): void
    {
        foreach (['derivatives', 'covers'] as $dir) {
            static::getContainer()->get('default.storage')->deleteDirectory($dir);
        }
        parent::tearDown();
    }

    public function testHomeListsOnlyWorksWithPublishedChapters(): void
    {
        ChapterFactory::createOne(['work' => WorkFactory::createOne(['title' => 'Visible']), 'published' => true]);
        ChapterFactory::createOne(['work' => WorkFactory::createOne(['title' => 'Draft']), 'published' => false]);

        $crawler = $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSame(['Visible'], $crawler->filter('ul.list a')->each(static fn ($a) => $a->text()));
    }

    public function testSeriesListsPublishedChaptersAndHidesDrafts(): void
    {
        $work = WorkFactory::createOne(['slug' => 'saga', 'type' => WorkType::Series]);
        ChapterFactory::createOne(['work' => $work, 'number' => '2.0', 'title' => null, 'published' => true]);
        ChapterFactory::createOne(['work' => $work, 'number' => '1.5', 'title' => 'Extra', 'published' => true]);
        ChapterFactory::createOne(['work' => $work, 'number' => '3.0', 'published' => false]);

        $crawler = $this->client->request('GET', '/saga');

        self::assertResponseIsSuccessful();
        self::assertSame(['Chapter 1.5: Extra', 'Chapter 2'], $crawler->filter('ul.list a')->each(static fn ($a) => trim($a->text())));
        self::assertSame('/saga/chapter-1.5', $crawler->filter('ul.list a')->first()->attr('href'));
        $this->client->request('GET', '/saga/chapter-3');
        self::assertResponseStatusCodeSame(404);
    }

    public function testReaderShowsReadyPagesInOrderWithReservedSizeInThreeQueries(): void
    {
        $work = WorkFactory::createOne(['slug' => 'saga', 'type' => WorkType::Series]);
        $chapter = ChapterFactory::createOne(['work' => $work, 'number' => '1.0', 'published' => true]);
        PageFactory::createOne(['chapter' => $chapter, 'position' => 20, 'width' => 800, 'height' => 1200, 'tileOrder' => '1,0']);
        PageFactory::createOne(['chapter' => $chapter, 'position' => 10, 'width' => 800, 'height' => 3000, 'tileOrder' => '0,1']);
        PageFactory::createOne(['chapter' => $chapter, 'position' => 30, 'status' => PageStatus::Processing]);

        // Start the request with an empty identity map, as in production; otherwise the
        // chapter's pages collection is already initialized in insertion order.
        static::getContainer()->get('doctrine')->getManager()->clear();
        // The first request shares the kernel with the factories: drop their INSERTs from the count.
        static::getContainer()->get('doctrine.debug_data_holder')->reset();
        $this->client->enableProfiler();
        $crawler = $this->client->request('GET', '/saga/chapter-1');

        self::assertResponseIsSuccessful();
        // No <img>: scrambled pages are drawn on canvas; the box reserves the reading size.
        self::assertCount(0, $crawler->filter('.pages img'));
        $pages = $crawler->filter('.pages [data-reader-target=page]');
        self::assertSame([['800', '3000', '0,1'], ['800', '1200', '1,0']], $pages->each(
            static fn ($p) => [$p->attr('data-width'), $p->attr('data-height'), $p->attr('data-order')],
        ));
        self::assertStringContainsString('aspect-ratio: 800 / 3000', (string) $pages->eq(0)->attr('style'));
        self::assertStringStartsWith('/media/page/', (string) $pages->eq(0)->attr('data-src'));

        // Installed check + work by slug + chapter with pages (fetch join). An N+1 would grow with pages.
        $profile = $this->client->getProfile();
        self::assertInstanceOf(Profile::class, $profile);
        $db = $profile->getCollector('db');
        self::assertInstanceOf(DoctrineDataCollector::class, $db);
        self::assertSame(3, $db->getQueryCount());
    }

    public function testOneshotIsReadOnTheWorkPage(): void
    {
        $work = WorkFactory::createOne(['slug' => 'single', 'type' => WorkType::Oneshot]);
        PageFactory::createOne(['chapter' => ChapterFactory::createOne(['work' => $work, 'published' => true])]);

        $crawler = $this->client->request('GET', '/single');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('.pages [data-reader-target=page]'));
        self::assertCount(0, $crawler->filter('ul.list'));

        // One URL per oneshot: the chapter URL would be duplicate content.
        $this->client->request('GET', '/single/chapter-1');
        self::assertResponseRedirects('/single', 301);
    }

    public function testMediaServesPublishedToEveryoneAndDraftsOnlyToUsers(): void
    {
        $published = PageFactory::createOne(['chapter' => ChapterFactory::createOne(['published' => true])]);
        $draft = PageFactory::createOne(['chapter' => ChapterFactory::createOne(['published' => false])]);
        $storage = static::getContainer()->get('default.storage');
        $storage->write(StorageKeys::derivative($published), 'webp-bytes');
        $storage->write(StorageKeys::derivative($draft), 'webp-bytes');

        $this->client->request('GET', sprintf('/media/page/%d.webp', $published->getId()));
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/webp');
        self::assertStringContainsString('immutable', (string) $this->client->getResponse()->headers->get('Cache-Control'));

        $this->client->request('GET', sprintf('/media/page/%d.webp', $draft->getId()));
        self::assertResponseStatusCodeSame(404);

        $this->client->loginUser(UserFactory::createOne());
        $this->client->request('GET', sprintf('/media/page/%d.webp', $draft->getId()));
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
    }

    public function testCoverIsServedPerChapterAndHiddenForDrafts(): void
    {
        $published = ChapterFactory::createOne(['published' => true]);
        $draft = ChapterFactory::createOne(['published' => false]);
        $storage = static::getContainer()->get('default.storage');
        $storage->write(StorageKeys::cover($published), 'jpeg-bytes');
        $storage->write(StorageKeys::cover($draft), 'jpeg-bytes');

        $this->client->request('GET', sprintf('/media/cover/%d.jpg', $published->getId()));
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/jpeg');
        self::assertStringNotContainsString('immutable', (string) $this->client->getResponse()->headers->get('Cache-Control'));

        $this->client->request('GET', sprintf('/media/cover/%d.jpg', $draft->getId()));
        self::assertResponseStatusCodeSame(404);
    }
}
