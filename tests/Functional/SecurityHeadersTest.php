<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\ChapterFactory;
use App\Factory\UserFactory;
use App\Factory\WorkFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class SecurityHeadersTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testEveryResponseIsHardenedAndHstsNeedsHttps(): void
    {
        $client = static::createClient();

        // No user yet: a redirect to the installer, which must carry the headers too.
        $client->request('GET', '/admin');
        self::assertResponseRedirects('/install');
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertResponseHeaderSame('Referrer-Policy', 'strict-origin-when-cross-origin');
        self::assertResponseHeaderSame('X-Frame-Options', 'DENY');
        self::assertStringContainsString("frame-ancestors 'none'", (string) $client->getResponse()->headers->get('Content-Security-Policy'));
        self::assertResponseNotHasHeader('Strict-Transport-Security');

        $client->request('GET', '/install', server: ['HTTPS' => 'on']);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Strict-Transport-Security', 'max-age=31536000');
    }

    public function testInlineBlocksAreAllowedByHashOnly(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/install');

        $policy = (string) $client->getResponse()->headers->get('Content-Security-Policy');
        $style = $crawler->filter('style')->text(normalizeWhitespace: false);
        self::assertStringContainsString(sprintf("style-src 'self' 'sha256-%s'", base64_encode(hash('sha256', $style, true))), $policy);
        self::assertDoesNotMatchRegularExpression("/(script|style)-src [^;]*'unsafe-inline'/", $policy);
        self::assertStringContainsString("script-src 'self';", $policy, 'No inline script on this page.');
    }

    public function testRevalidationKeepsThePolicyOfTheFullPage(): void
    {
        $client = static::createClient();
        UserFactory::createOne();
        ChapterFactory::createOne(['work' => WorkFactory::createOne(['slug' => 'saga']), 'published' => true]);

        $client->request('GET', '/saga');
        $policy = $client->getResponse()->headers->get('Content-Security-Policy');
        self::assertStringContainsString("style-src 'self' 'sha256-", (string) $policy);

        // Browsers merge a 304's headers into the cached page: a hash-less policy would break it.
        $client->request('GET', '/saga', server: ['HTTP_IF_NONE_MATCH' => (string) $client->getResponse()->getEtag()]);
        self::assertResponseStatusCodeSame(304);
        self::assertResponseHeaderSame('Content-Security-Policy', (string) $policy);
    }
}
