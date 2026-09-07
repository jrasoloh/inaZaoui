<?php

namespace App\DataFixtures;

use App\Factory\AlbumFactory;
use App\Factory\MediaFactory;
use App\Factory\UserFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Representative data set covering the main usage scenarios of the app:
 *  - the photographer (admin) who owns the public portfolio;
 *  - active guests whose photos are publicly visible;
 *  - a revoked (blocked) guest whose photos must stay hidden.
 *
 * These constants are reused verbatim by the test suite so the tests document
 * exactly which accounts and data they exercise. The data is built through
 * Foundry factories (see {@see UserFactory}, {@see AlbumFactory},
 * {@see MediaFactory}) which persist and flush each object as it is created.
 */
class AppFixtures extends Fixture
{
    public const ADMIN_EMAIL = 'ina@zaoui.com';
    public const ADMIN_PASSWORD = 'password';

    public const GUEST_ACTIVE_EMAIL = 'alice@example.com';
    public const GUEST_ACTIVE_NAME = 'Alice Active';
    public const GUEST_ACTIVE_PASSWORD = 'test';

    public const GUEST_SECOND_EMAIL = 'carol@example.com';
    public const GUEST_SECOND_NAME = 'Carol Active';
    public const GUEST_SECOND_PASSWORD = 'test';

    public const GUEST_BLOCKED_EMAIL = 'bob@example.com';
    public const GUEST_BLOCKED_NAME = 'Bob Blocked';
    public const GUEST_BLOCKED_PASSWORD = 'test';

    public const ALBUM_NATURE = 'Nature';
    public const ALBUM_CITY = 'Ville';

    public function load(ObjectManager $manager): void
    {
        // --- Photographer / admin -------------------------------------------
        $admin = UserFactory::new()->admin()->create([
            'name' => 'Ina Zaoui',
            'email' => self::ADMIN_EMAIL,
            'description' => 'Photographe professionnelle.',
            'password' => self::ADMIN_PASSWORD,
        ]);

        // --- Active guests ---------------------------------------------------
        $alice = UserFactory::new()->activeGuest()->create([
            'name' => self::GUEST_ACTIVE_NAME,
            'email' => self::GUEST_ACTIVE_EMAIL,
            'description' => 'Invitée active avec des photos publiques.',
            'password' => self::GUEST_ACTIVE_PASSWORD,
        ]);

        UserFactory::new()->activeGuest()->create([
            'name' => self::GUEST_SECOND_NAME,
            'email' => self::GUEST_SECOND_EMAIL,
            'description' => 'Seconde invitée active.',
            'password' => self::GUEST_SECOND_PASSWORD,
        ]);

        // --- Blocked (revoked) guest ----------------------------------------
        $bob = UserFactory::new()->blockedGuest()->create([
            'name' => self::GUEST_BLOCKED_NAME,
            'email' => self::GUEST_BLOCKED_EMAIL,
            'description' => 'Invité dont l’accès a été révoqué.',
            'password' => self::GUEST_BLOCKED_PASSWORD,
        ]);

        // --- Albums ----------------------------------------------------------
        $nature = AlbumFactory::createOne(['name' => self::ALBUM_NATURE]);
        AlbumFactory::createOne(['name' => self::ALBUM_CITY]);

        // --- Media -----------------------------------------------------------
        // Admin owns two photos in the Nature album (public portfolio).
        MediaFactory::createOne(['title' => 'Forêt brumeuse', 'path' => 'uploads/0001.jpg', 'user' => $admin, 'album' => $nature]);
        MediaFactory::createOne(['title' => 'Rivière calme', 'path' => 'uploads/0002.jpg', 'user' => $admin, 'album' => $nature]);

        // Active guest Alice: her photo IS publicly visible in Nature.
        MediaFactory::createOne(['title' => 'Photo d’Alice', 'path' => 'uploads/0003.jpg', 'user' => $alice, 'album' => $nature]);

        // Blocked guest Bob: his photo must stay hidden from the public portfolio.
        MediaFactory::createOne(['title' => 'Photo de Bob', 'path' => 'uploads/0004.jpg', 'user' => $bob, 'album' => $nature]);

        // Ownerless media: always visible.
        MediaFactory::createOne(['title' => 'Sans propriétaire', 'path' => 'uploads/0005.jpg', 'user' => null, 'album' => $nature]);
    }
}
