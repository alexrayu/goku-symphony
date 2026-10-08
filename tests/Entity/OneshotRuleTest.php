<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Chapter;
use App\Entity\Work;
use App\Enum\WorkType;
use App\Factory\ChapterFactory;
use App\Factory\WorkFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class OneshotRuleTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    public function testOneshotTakesASingleChapter(): void
    {
        $work = $this->reload(WorkFactory::createOne(['type' => WorkType::Oneshot]), static function (Work $work): void {
            ChapterFactory::createOne(['work' => $work, 'number' => '1.0']);
        });

        // Editing the existing chapter is fine; adding a second one is not.
        self::assertCount(0, $this->validator()->validate($work->getChapters()->first()));
        $violations = $this->validator()->validate(new Chapter($work, '2'));
        self::assertCount(1, $violations);
        self::assertSame('work', $violations->get(0)->getPropertyPath());

        // A series takes any number.
        $work->setType(WorkType::Series);
        self::assertCount(0, $this->validator()->validate(new Chapter($work, '2')));
    }

    public function testSeriesWithSeveralChaptersCannotBecomeAOneshot(): void
    {
        $work = $this->reload(WorkFactory::createOne(['type' => WorkType::Series]), static function (Work $work): void {
            ChapterFactory::createMany(2, static fn (int $i): array => ['work' => $work, 'number' => $i.'.0']);
        });

        $work->setType(WorkType::Oneshot);
        $violations = $this->validator()->validate($work);

        self::assertCount(1, $violations);
        self::assertSame('type', $violations->get(0)->getPropertyPath());
    }

    // Factories do not fill the inverse side: reload so Work::$chapters holds what the database holds.
    private function reload(Work $work, callable $addChapters): Work
    {
        $addChapters($work);
        $em = static::getContainer()->get('doctrine')->getManager();
        $em->clear();

        return $em->find(Work::class, $work->getId()) ?? self::fail('Work vanished.');
    }

    private function validator(): ValidatorInterface
    {
        return static::getContainer()->get(ValidatorInterface::class);
    }
}
