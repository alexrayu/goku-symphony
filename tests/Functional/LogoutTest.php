<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class LogoutTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testLogoutNeedsTheTokenFromTheGeneratedLink(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne());

        // A bare /logout, as a third-party page could trigger, leaves the session alone.
        $client->request('GET', '/logout');
        $client->request('GET', '/admin/work');
        self::assertResponseIsSuccessful();

        // EasyAdmin's user menu link carries the token.
        $link = $client->getCrawler()->filter('a[href*="/logout"]')->attr('href');
        self::assertStringContainsString('_csrf_token=', (string) $link);
        $client->request('GET', (string) $link);
        self::assertResponseRedirects();
        $client->request('GET', '/admin/work');
        self::assertResponseRedirects('/login');
    }
}
