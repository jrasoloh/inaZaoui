<?php

namespace App\DataFixtures;

use App\Entity\Album;
use App\Entity\Media;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Representative data set covering the main usage scenarios of the app:
 *  - the photographer (admin) who owns the public portfolio;
 *  - active guests whose photos are publicly visible;
 *  - a revoked (blocked) guest whose photos must stay hidden.
 *
 * These constants are reused verbatim by the test suite so the tests document
 * exactly which accounts and data they exercise.
 */
class AppFixtures extends Fixture
{
    public const ADMIN_EMAIL = 'admin@example.com';
    public const ADMIN_PASSWORD = 'adminpass';

    public const GUEST_ACTIVE_EMAIL = 'alice@example.com';
    public const GUEST_ACTIVE_NAME = 'Alice Active';
    public const GUEST_ACTIVE_PASSWORD = 'alicepass';

    public const GUEST_SECOND_EMAIL = 'carol@example.com';
    public const GUEST_SECOND_NAME = 'Carol Active';

    public const GUEST_BLOCKED_EMAIL = 'bob@example.com';
    public const GUEST_BLOCKED_NAME = 'Bob Blocked';
    public const GUEST_BLOCKED_PASSWORD = 'bobpass';

    public const ALBUM_NATURE = 'Nature';
    public const ALBUM_CITY = 'Ville';

    public function __construct(private readonly UserPasswordHasherInterface $hasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        // --- Photographer / admin -------------------------------------------
        $admin = new User();
        $admin->setName('Ina Zaoui');
        $admin->setEmail(self::ADMIN_EMAIL);
        $admin->setDescription('Photographe professionnelle.');
        $admin->setAdmin(true);
        $admin->setActive(true);
        $admin->setPassword($this->hasher->hashPassword($admin, self::ADMIN_PASSWORD));
        $manager->persist($admin);

        // --- Active guests ---------------------------------------------------
        $alice = new User();
        $alice->setName(self::GUEST_ACTIVE_NAME);
        $alice->setEmail(self::GUEST_ACTIVE_EMAIL);
        $alice->setDescription('Invitée active avec des photos publiques.');
        $alice->setAdmin(false);
        $alice->setActive(true);
        $alice->setPassword($this->hasher->hashPassword($alice, self::GUEST_ACTIVE_PASSWORD));
        $manager->persist($alice);

        $carol = new User();
        $carol->setName(self::GUEST_SECOND_NAME);
        $carol->setEmail(self::GUEST_SECOND_EMAIL);
        $carol->setDescription('Seconde invitée active.');
        $carol->setAdmin(false);
        $carol->setActive(true);
        $carol->setPassword($this->hasher->hashPassword($carol, 'carolpass'));
        $manager->persist($carol);

        // --- Blocked (revoked) guest ----------------------------------------
        $bob = new User();
        $bob->setName(self::GUEST_BLOCKED_NAME);
        $bob->setEmail(self::GUEST_BLOCKED_EMAIL);
        $bob->setDescription('Invité dont l’accès a été révoqué.');
        $bob->setAdmin(false);
        $bob->setActive(false);
        $bob->setPassword($this->hasher->hashPassword($bob, self::GUEST_BLOCKED_PASSWORD));
        $manager->persist($bob);

        // --- Albums ----------------------------------------------------------
        $nature = new Album();
        $nature->setName(self::ALBUM_NATURE);
        $manager->persist($nature);

        $city = new Album();
        $city->setName(self::ALBUM_CITY);
        $manager->persist($city);

        // --- Media -----------------------------------------------------------
        // Admin owns two photos in the Nature album (public portfolio).
        $this->createMedia($manager, 'Forêt brumeuse', 'uploads/0001.jpg', $admin, $nature);
        $this->createMedia($manager, 'Rivière calme', 'uploads/0002.jpg', $admin, $nature);

        // Active guest Alice: her photo IS publicly visible in Nature.
        $this->createMedia($manager, 'Photo d’Alice', 'uploads/0003.jpg', $alice, $nature);

        // Blocked guest Bob: his photo must stay hidden from the public portfolio.
        $this->createMedia($manager, 'Photo de Bob', 'uploads/0004.jpg', $bob, $nature);

        // Ownerless media: always visible.
        $this->createMedia($manager, 'Sans propriétaire', 'uploads/0005.jpg', null, $nature);

        $manager->flush();
    }

    private function createMedia(
        ObjectManager $manager,
        string $title,
        string $path,
        ?User $user,
        ?Album $album,
    ): Media {
        $media = new Media();
        $media->setTitle($title);
        $media->setPath($path);
        $media->setUser($user);
        $media->setAlbum($album);
        $manager->persist($media);

        return $media;
    }
}
