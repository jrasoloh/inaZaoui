<?php

namespace App\Tests\Repository;

use App\DataFixtures\AppFixtures;
use App\Entity\Album;
use App\Entity\Media;
use App\Tests\FixturesTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class MediaRepositoryTest extends KernelTestCase
{
    use FixturesTrait;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->resetDatabase(static::getContainer());
    }

    public function testFindVisibleByAlbumHidesBlockedGuestMedia(): void
    {
        $album = $this->entityManager
            ->getRepository(Album::class)
            ->findOneBy(['name' => AppFixtures::ALBUM_NATURE]);

        self::assertNotNull($album);

        $medias = $this->entityManager
            ->getRepository(Media::class)
            ->findVisibleByAlbum($album);

        $titles = array_map(static fn (Media $m) => $m->getTitle(), $medias);

        // Admin, active guest and ownerless media are visible.
        self::assertContains('Forêt brumeuse', $titles);
        self::assertContains('Photo d’Alice', $titles);
        self::assertContains('Sans propriétaire', $titles);

        // Blocked guest (Bob) media must be excluded.
        self::assertNotContains('Photo de Bob', $titles);
    }

    public function testBlockingAGuestRemovesTheirMediaFromVisibleSet(): void
    {
        $mediaRepository = $this->entityManager->getRepository(Media::class);
        $album = $this->entityManager
            ->getRepository(Album::class)
            ->findOneBy(['name' => AppFixtures::ALBUM_NATURE]);

        // Initially Alice (active) is visible.
        $titlesBefore = array_map(
            static fn (Media $m) => $m->getTitle(),
            $mediaRepository->findVisibleByAlbum($album)
        );
        self::assertContains('Photo d’Alice', $titlesBefore);

        // Block Alice.
        $alice = $this->findUserByEmail(AppFixtures::GUEST_ACTIVE_EMAIL);
        $alice->setActive(false);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $titlesAfter = array_map(
            static fn (Media $m) => $m->getTitle(),
            $mediaRepository->findVisibleByAlbum($album)
        );
        self::assertNotContains('Photo d’Alice', $titlesAfter);
    }
}

