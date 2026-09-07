<?php

namespace App\Factory;

use App\Entity\Album;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Album>
 */
final class AlbumFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Album::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'name' => self::faker()->unique()->word(),
        ];
    }
}


