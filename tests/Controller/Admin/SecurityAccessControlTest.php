<?php

namespace App\Tests\Controller\Admin;

use App\DataFixtures\AppFixtures;
use App\Tests\AbstractWebTestCase;

class SecurityAccessControlTest extends AbstractWebTestCase
{
    public function testAnonymousIsRedirectedToLogin(): void
    {
        $this->client->request('GET', '/admin/media');

        self::assertResponseRedirects('/login');
    }

    public function testGuestCannotAccessGuestManagement(): void
    {
        // A regular guest has ROLE_USER but not ROLE_ADMIN.
        $guest = $this->findUserByEmail(AppFixtures::GUEST_ACTIVE_EMAIL);
        $this->client->loginUser($guest);

        $this->client->request('GET', '/admin/guest');

        self::assertResponseStatusCodeSame(403);
    }

    public function testGuestCanAccessMediaSection(): void
    {
        $guest = $this->findUserByEmail(AppFixtures::GUEST_ACTIVE_EMAIL);
        $this->client->loginUser($guest);

        $this->client->request('GET', '/admin/media');

        self::assertResponseIsSuccessful();
    }

    public function testAdminCanAccessGuestManagement(): void
    {
        $admin = $this->findUserByEmail(AppFixtures::ADMIN_EMAIL);
        $this->client->loginUser($admin);

        $this->client->request('GET', '/admin/guest');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main h1', 'Invités');
    }
}


