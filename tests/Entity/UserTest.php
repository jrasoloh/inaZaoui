<?php

namespace App\Tests\Entity;

use App\Entity\Media;
use App\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testGuestHasOnlyUserRole(): void
    {
        $user = new User();
        $user->setAdmin(false);

        self::assertSame(['ROLE_USER'], $user->getRoles());
    }

    public function testAdminHasAdminAndUserRoles(): void
    {
        $user = new User();
        $user->setAdmin(true);

        self::assertContains('ROLE_ADMIN', $user->getRoles());
        self::assertContains('ROLE_USER', $user->getRoles());
    }

    public function testUserIdentifierIsEmail(): void
    {
        $user = new User();
        $user->setEmail('someone@example.com');

        self::assertSame('someone@example.com', $user->getUserIdentifier());
    }

    public function testDefaultsAreActiveNonAdmin(): void
    {
        $user = new User();

        self::assertTrue($user->isActive());
        self::assertFalse($user->isAdmin());
    }

    public function testGettersAndSetters(): void
    {
        $user = new User();
        $user->setName('Jane');
        $user->setEmail('jane@example.com');
        $user->setDescription('A guest');
        $user->setPassword('hashed');
        $user->setActive(false);

        self::assertSame('Jane', $user->getName());
        self::assertSame('jane@example.com', $user->getEmail());
        self::assertSame('A guest', $user->getDescription());
        self::assertSame('hashed', $user->getPassword());
        self::assertFalse($user->isActive());
    }

    public function testMediasCollectionIsInitialized(): void
    {
        $user = new User();

        self::assertCount(0, $user->getMedias());

        $collection = new ArrayCollection([new Media()]);
        $user->setMedias($collection);

        self::assertCount(1, $user->getMedias());
    }
}

