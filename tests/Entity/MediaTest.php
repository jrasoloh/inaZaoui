<?php

namespace App\Tests\Entity;

use App\Entity\Album;
use App\Entity\Media;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class MediaTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $user = new User();
        $album = new Album();
        $album->setName('Nature');

        $media = new Media();
        $media->setTitle('Sunset');
        $media->setPath('uploads/sunset.jpg');
        $media->setUser($user);
        $media->setAlbum($album);

        self::assertNull($media->getId());
        self::assertSame('Sunset', $media->getTitle());
        self::assertSame('uploads/sunset.jpg', $media->getPath());
        self::assertSame($user, $media->getUser());
        self::assertSame($album, $media->getAlbum());
        self::assertNull($media->getFile());
    }

    public function testOwnerlessMedia(): void
    {
        $media = new Media();
        $media->setUser(null);

        self::assertNull($media->getUser());
    }
}

