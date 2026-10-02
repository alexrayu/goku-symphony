<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Work;
use App\Enum\WorkType;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Work>
 */
final class WorkFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Work::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'title' => self::faker()->unique()->words(3, true),
            'slug' => self::faker()->unique()->slug(3),
            'type' => self::faker()->randomElement(WorkType::cases()),
            'description' => self::faker()->optional()->paragraph(),
        ];
    }
}
