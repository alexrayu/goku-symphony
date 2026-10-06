<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Work;
use App\Enum\WorkType;
use App\Factory\ChapterFactory;
use App\Factory\UserFactory;
use App\Factory\WorkFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class SeoTest extends WebTestCase
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

    public function testChapterPageCarriesMetadataAndStructuredData(): void
    {
        $work = WorkFactory::createOne(['title' => 'Saga', 'slug' => 'saga', 'type' => WorkType::Series]);
        $chapter = ChapterFactory::createOne(['work' => $work, 'number' => '2.0', 'title' => 'Storm', 'published' => true]);

        $crawler = $this->client->request('GET', '/saga/chapter-2');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('title', 'Saga Chapter 2: Storm | Goku');
        self::assertSame('http://localhost/saga/chapter-2', $crawler->filter('link[rel=canonical]')->attr('href'));
        self::assertSame(
            sprintf('http://localhost/media/cover/%d.jpg', $chapter->getId()),
            $crawler->filter('meta[property="og:image"]')->attr('content'),
        );
        self::assertStringContainsString('Saga Chapter 2', (string) $crawler->filter('meta[name=description]')->attr('content'));
        self::assertSame('en', $crawler->filter('html')->attr('lang'));

        $graph = json_decode($crawler->filter('script[type="application/ld+json"]')->text(), true, flags: \JSON_THROW_ON_ERROR)['@graph'];
        self::assertSame(['ComicIssue', 'BreadcrumbList'], array_column($graph, '@type'));
        self::assertSame('Saga', $graph[0]['isPartOf']['name']);
    }

    public function testRobotsAndSitemapListOnlyPublicUrls(): void
    {
        $series = WorkFactory::createOne(['slug' => 'saga', 'type' => WorkType::Series]);
        ChapterFactory::createOne(['work' => $series, 'number' => '1.0', 'published' => true]);
        ChapterFactory::createOne(['work' => $series, 'number' => '2.0', 'published' => false]);
        ChapterFactory::createOne(['work' => WorkFactory::createOne(['slug' => 'single', 'type' => WorkType::Oneshot]), 'published' => true]);

        $this->client->request('GET', '/robots.txt');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Disallow: /media/page/', (string) $this->client->getResponse()->getContent());
        self::assertStringContainsString('Sitemap: http://localhost/sitemap.xml', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/sitemap.xml');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('xml', (string) $this->client->getResponse()->headers->get('Content-Type'));
        $sitemap = simplexml_load_string((string) $this->client->getResponse()->getContent());
        self::assertNotFalse($sitemap);
        $urls = array_map('strval', iterator_to_array($sitemap->xpath('//*[local-name()="loc"]') ?: [], false));
        sort($urls);
        self::assertSame(['http://localhost/', 'http://localhost/saga', 'http://localhost/saga/chapter-1', 'http://localhost/single'], $urls);
    }

    public function testAnonymousPagesAreSharedCacheableAndRevalidate(): void
    {
        ChapterFactory::createOne(['work' => WorkFactory::createOne(['slug' => 'saga']), 'published' => true]);

        $this->client->request('GET', '/saga');
        $response = $this->client->getResponse();
        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertSame('300', $response->headers->getCacheControlDirective('s-maxage'));
        $etag = (string) $response->getEtag();

        $this->client->request('GET', '/saga', server: ['HTTP_IF_NONE_MATCH' => $etag]);
        self::assertResponseStatusCodeSame(304);

        // A logged-in visitor's pages must never land in a shared cache.
        $this->client->loginUser(UserFactory::createOne());
        $this->client->request('GET', '/saga');
        self::assertTrue($this->client->getResponse()->headers->hasCacheControlDirective('private'));
    }

    public function testSlugsThatShadowSitePathsAreRejected(): void
    {
        $validator = static::getContainer()->get(ValidatorInterface::class);

        self::assertCount(1, $validator->validate(new Work('Admin', 'admin')));
        self::assertCount(1, $validator->validate(new Work('Bad', 'Bad Slug')));
        self::assertCount(0, $validator->validate(new Work('Fine', 'admin-diaries')));
    }
}
