<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\ChapterFactory;
use App\Factory\UserFactory;
use App\Factory\WorkFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class AdminListTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testChapterListShowsPublicNumbersAndBlankMissingTitles(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne());
        $work = WorkFactory::createOne();
        ChapterFactory::createOne(['work' => $work, 'number' => '1.0', 'title' => null]);
        ChapterFactory::createOne(['work' => $work, 'number' => '1.5', 'title' => '<b>Extra</b>']);

        $crawler = $client->request('GET', '/admin/chapter');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Chapters');
        $rows = $crawler->filter('tbody tr')->each(static fn ($row) => [
            trim($row->filter('td[data-column=number]')->text()),
            trim($row->filter('td[data-column=title]')->text()),
        ]);
        self::assertSame([['1', ''], ['1.5', '<b>Extra</b>']], $rows);
    }
}
