<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Chapter;
use App\Enum\ReadingDirection;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Chapter>
 */
final class ChapterFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Chapter::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'work' => WorkFactory::new(),
            // Unique per work; decimal(6,1) as string.
            'number' => self::faker()->unique()->numberBetween(1, 9999) . '.0',
            'direction' => self::faker()->randomElement(ReadingDirection::cases()),
            'title' => self::faker()->optional()->sentence(3),
            'published' => self::faker()->boolean(),
        ];
    }
}
