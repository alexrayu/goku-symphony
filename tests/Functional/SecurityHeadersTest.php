<?php

declare(strict_types=1);

namespace App\Tests\Functional;

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
        self::assertResponseHeaderSame('Content-Security-Policy', "frame-ancestors 'none'");
        self::assertResponseHeaderSame('X-Frame-Options', 'DENY');
        self::assertResponseNotHasHeader('Strict-Transport-Security');

        $client->request('GET', '/install', server: ['HTTPS' => 'on']);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Strict-Transport-Security', 'max-age=31536000');
    }
}
