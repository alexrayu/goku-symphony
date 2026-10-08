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
        foreach (['derivatives', 'covers', 'thumbs'] as $dir) {
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
        self::assertSame(['Visible'], $crawler->filter('.shelf h3')->each(static fn ($h) => $h->text()));
        self::assertStringStartsWith('/media/thumb/', (string) $crawler->filter('.shelf img')->attr('src'));
    }

    public function testSeriesListsPublishedChaptersAndHidesDrafts(): void
    {
        $work = WorkFactory::createOne(['slug' => 'saga', 'type' => WorkType::Series]);
        ChapterFactory::createOne(['work' => $work, 'number' => '2.0', 'title' => null, 'published' => true]);
        ChapterFactory::createOne(['work' => $work, 'number' => '1.5', 'title' => 'Extra', 'published' => true]);
        ChapterFactory::createOne(['work' => $work, 'number' => '3.0', 'published' => false]);

        $crawler = $this->client->request('GET', '/saga');

        self::assertResponseIsSuccessful();
        self::assertSame(['Chapter 1.5 Extra', 'Chapter 2'], $crawler->filter('ul.list a')->each(
            static fn ($a) => implode(' ', $a->filter('.chapter-number, .chapter-title')->each(static fn ($t) => $t->text())),
        ));
        // Freshly published: dated and marked new.
        self::assertCount(2, $crawler->filter('ul.list .badge.new'));
        self::assertCount(2, $crawler->filter('ul.list time[datetime]'));
        self::assertSame('/saga/chapter-1.5', $crawler->filter('ul.list a')->first()->attr('href'));
        $this->client->request('GET', '/saga/chapter-3');
        self::assertResponseStatusCodeSame(404);
    }

    public function testReaderShowsReadyPagesInOrderWithReservedSizeInFiveQueries(): void
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
        self::assertCount(1, $crawler->filter('.reader-progress'));

        // Installed check + site settings (both cached outside tests) + work by slug + chapter list
        // (previous/next) + chapter with pages (fetch join). An N+1 would grow with pages.
        $profile = $this->client->getProfile();
        self::assertInstanceOf(Profile::class, $profile);
        $db = $profile->getCollector('db');
        self::assertInstanceOf(DoctrineDataCollector::class, $db);
        self::assertSame(5, $db->getQueryCount());
    }

    public function testReaderLinksPreviousAndNextPublishedChapters(): void
    {
        $work = WorkFactory::createOne(['slug' => 'saga', 'type' => WorkType::Series]);
        foreach (['1.0' => true, '2.0' => true, '2.5' => false, '3.0' => true] as $number => $published) {
            ChapterFactory::createOne(['work' => $work, 'number' => (string) $number, 'published' => $published]);
        }

        $crawler = $this->client->request('GET', '/saga/chapter-2');
        self::assertSame(
            [['Previous', '/saga/chapter-1'], ['All chapters', '/saga'], ['Next', '/saga/chapter-3']],
            $crawler->filter('.reader-head .chapter-nav a')->each(static fn ($a) => [$a->text(), $a->attr('href')]),
        );

        $crawler = $this->client->request('GET', '/saga/chapter-1');
        self::assertSame(['All chapters', 'Next'], $crawler->filter('.reader-head .chapter-nav a')->each(static fn ($a) => $a->text()));
        // Arrow-key navigation gets the same targets; there is no previous chapter here.
        self::assertSame('/saga/chapter-2', $crawler->filter('.pages')->attr('data-reader-next-value'));
        self::assertNull($crawler->filter('.pages')->attr('data-reader-previous-value'));
    }

    public function testLoggedInUsersPreviewDraftsPrivately(): void
    {
        $work = WorkFactory::createOne(['slug' => 'saga', 'type' => WorkType::Series]);
        ChapterFactory::createOne(['work' => $work, 'number' => '1.0', 'published' => false]);

        $this->client->request('GET', '/saga/chapter-1');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/saga');
        self::assertResponseStatusCodeSame(404);

        $this->client->loginUser(UserFactory::createOne());
        $crawler = $this->client->request('GET', '/saga/chapter-1');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.reader-head .badge.draft', 'Draft preview');
        self::assertSame('noindex', $crawler->filter('meta[name=robots]')->attr('content'));
        // Never shared-cacheable: a draft must not reach Cloudflare.
        self::assertStringContainsString('private', (string) $this->client->getResponse()->headers->get('Cache-Control'));

        $this->client->request('GET', '/saga');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('ul.list .badge.draft', 'Draft');
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
        $cacheControl = (string) $this->client->getResponse()->headers->get('Cache-Control');
        self::assertStringContainsString('immutable', $cacheControl);
        // Edge copies expire within the hour, so unpublishing reaches Cloudflare without a purge.
        self::assertStringContainsString('s-maxage=3600', $cacheControl);
        $lastModified = (string) $this->client->getResponse()->headers->get('Last-Modified');

        $this->client->request('GET', sprintf('/media/page/%d.webp', $published->getId()), server: ['HTTP_IF_MODIFIED_SINCE' => $lastModified]);
        self::assertResponseStatusCodeSame(304);

        $this->client->request('GET', sprintf('/media/page/%d.webp', $draft->getId()));
        self::assertResponseStatusCodeSame(404);

        $this->client->loginUser(UserFactory::createOne());
        $this->client->request('GET', sprintf('/media/page/%d.webp', $draft->getId()));
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
    }

    public function testCoverAndThumbnailAreServedPerChapterAndHiddenForDrafts(): void
    {
        $published = ChapterFactory::createOne(['published' => true]);
        $draft = ChapterFactory::createOne(['published' => false]);
        $storage = static::getContainer()->get('default.storage');
        $media = [
            ['/media/cover/%d.jpg', StorageKeys::cover(...), 'image/jpeg'],
            ['/media/thumb/%d.webp', StorageKeys::thumbnail(...), 'image/webp'],
        ];
        foreach ($media as [$url, $key, $type]) {
            $storage->write($key($published), 'bytes');
            $storage->write($key($draft), 'bytes');

            $this->client->request('GET', sprintf($url, $published->getId()));
            self::assertResponseIsSuccessful();
            self::assertResponseHeaderSame('Content-Type', $type);
            self::assertStringNotContainsString('immutable', (string) $this->client->getResponse()->headers->get('Cache-Control'));
            self::assertStringContainsString('s-maxage=3600', (string) $this->client->getResponse()->headers->get('Cache-Control'));

            $this->client->request('GET', sprintf($url, $draft->getId()));
            self::assertResponseStatusCodeSame(404);
        }
    }
}
