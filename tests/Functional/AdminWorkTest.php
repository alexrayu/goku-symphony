<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Enum\WorkType;
use App\Factory\ChapterFactory;
use App\Factory\PageFactory;
use App\Factory\UserFactory;
use App\Factory\WorkFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class AdminWorkTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testWorkPageListsOnlyItsChaptersWithPageCounts(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne());
        $work = WorkFactory::createOne(['title' => 'Neon Tide', 'type' => WorkType::Series]);
        $first = ChapterFactory::createOne(['work' => $work, 'number' => '1.0', 'title' => 'Low Water']);
        PageFactory::createMany(2, ['chapter' => $first]);
        ChapterFactory::createOne(['work' => $work, 'number' => '2.5', 'title' => 'Extra']);
        ChapterFactory::createOne(['title' => 'Elsewhere']);

        $crawler = $client->request('GET', sprintf('/admin/work/%d', $work->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Neon Tide');
        $rows = $crawler->filter('.table-responsive tbody tr')->each(static fn ($row) => [
            trim($row->filter('td')->eq(0)->text()),
            trim($row->filter('td')->eq(3)->text()),
        ]);
        self::assertSame([['1 · Low Water', '2'], ['2.5 · Extra', '0']], $rows);
    }

    public function testChapterAddedFromWorkPageIsNumberedNextAndReturnsToTheWork(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne());
        $work = WorkFactory::createOne(['type' => WorkType::Series]);
        ChapterFactory::createOne(['work' => $work, 'number' => '2.5']);
        $workUrl = sprintf('/admin/work/%d', $work->getId());

        $crawler = $client->request('GET', $workUrl);
        $crawler = $client->click($crawler->selectLink('Add chapter')->link());
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[name="Chapter[work]"]');

        $form = $crawler->filter('button[value=saveAndReturn]')->form();
        self::assertSame('3.0', $crawler->filter('[name="Chapter[number]"]')->attr('value'));
        $client->submit($form, ['Chapter[title]' => 'Next']);

        self::assertResponseRedirects($workUrl);
        self::assertSame(1, ChapterFactory::count(['work' => $work, 'title' => 'Next']));
    }

    public function testDuplicateChapterNumberIsAFormErrorNotACrash(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne());
        $work = WorkFactory::createOne(['type' => WorkType::Series]);
        ChapterFactory::createOne(['work' => $work, 'number' => '4.0']);

        $crawler = $client->request('GET', sprintf('/admin/chapter/new?work=%d', $work->getId()));
        $client->submit($crawler->filter('button[value=saveAndReturn]')->form(), ['Chapter[number]' => '4']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.invalid-feedback', 'already has a chapter with this number');
        self::assertSame(1, ChapterFactory::count(['work' => $work]));
    }

    public function testOneshotWithItsChapterOffersNoSecondOne(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne());
        $chapter = ChapterFactory::createOne(['work' => WorkFactory::new(['type' => WorkType::Oneshot])]);

        $client->request('GET', sprintf('/admin/work/%d', $chapter->getWork()->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('.page-actions', 'Add chapter');
    }
}
