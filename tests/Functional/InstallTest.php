<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class InstallTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testEmptySiteRedirectsEverythingToInstaller(): void
    {
        $client = static::createClient();

        foreach (['/', '/login', '/admin', '/no/such/page'] as $path) {
            $client->request('GET', $path);
            self::assertResponseRedirects('/install', null, $path);
        }
    }

    public function testMismatchedPasswordsCreateNoUser(): void
    {
        $client = static::createClient();
        $client->request('POST', '/install', [
            'email' => 'a@example.com', 'password' => 'x', 'password_confirm' => 'y',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[role=alert]', 'Passwords do not match');
        UserFactory::assert()->count(0);
    }

    public function testInstallCreatesFirstUserLogsInAndClosesInstaller(): void
    {
        $client = static::createClient();
        $client->request('POST', '/install', [
            'email' => 'artist@example.com', 'password' => 'secret', 'password_confirm' => 'secret',
        ]);

        self::assertResponseRedirects('/admin');
        UserFactory::assert()->count(1);
        UserFactory::assert()->exists(['email' => 'artist@example.com']);

        // Logged in: the dashboard forwards to the Work list instead of the login form.
        $client->request('GET', '/admin');
        self::assertResponseRedirects('/admin/work');

        $client->request('GET', '/install');
        self::assertResponseRedirects('/login');
    }

    public function testExistingUserMeansNoRedirect(): void
    {
        $client = static::createClient();
        UserFactory::createOne();

        $client->request('GET', '/login');
        self::assertResponseIsSuccessful();
    }
}
