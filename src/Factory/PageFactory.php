<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Page;
use App\Enum\PageStatus;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Page>
 */
final class PageFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Page::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'chapter' => ChapterFactory::new(),
            // Gapped like production data; unique per chapter.
            'position' => self::faker()->unique()->numberBetween(1, 99999) * 10,
            'originalKey' => 'originals/' . self::faker()->uuid() . '.jpg',
            'width' => 800,
            'height' => self::faker()->numberBetween(1200, 12000),
            'status' => PageStatus::Ready,
        ];
    }
}
