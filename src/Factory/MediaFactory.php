<?php

namespace App\Factory;

use App\Entity\Media;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Media>
 */
final class MediaFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Media::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'title' => self::faker()->sentence(3),
            'path' => 'uploads/'.self::faker()->numberBetween(1, 40).'.jpg',
            'user' => null,
            'album' => null,
        ];
    }
}


