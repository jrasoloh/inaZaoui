<?php

namespace App\Tests\Entity;

use App\Entity\Album;
use PHPUnit\Framework\TestCase;

class AlbumTest extends TestCase
{
    public function testNameGetterAndSetter(): void
    {
        $album = new Album();
        $album->setName('Ville');

        self::assertSame('Ville', $album->getName());
        self::assertNull($album->getId());
    }
}

