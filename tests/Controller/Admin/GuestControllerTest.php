<?php

namespace App\Tests\Controller\Admin;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use App\Tests\AbstractWebTestCase;

class GuestControllerTest extends AbstractWebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $admin = $this->findUserByEmail(AppFixtures::ADMIN_EMAIL);
        $this->client->loginUser($admin);
    }

    public function testIndexListsGuests(): void
    {
        $this->client->request('GET', '/admin/guest');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', AppFixtures::GUEST_ACTIVE_NAME);
        self::assertSelectorTextContains('body', AppFixtures::GUEST_BLOCKED_NAME);
    }

    public function testAddGuest(): void
    {
        $crawler = $this->client->request('GET', '/admin/guest/add');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Ajouter')->form([
            'guest[name]' => 'David New',
            'guest[email]' => 'david.new@example.com',
            'guest[description]' => 'Freshly invited',
            'guest[plainPassword]' => 'strongpass',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/admin/guest');

        $created = $this->findUserByEmail('david.new@example.com');
        self::assertNotNull($created);
        self::assertFalse($created->isAdmin());
        self::assertTrue($created->isActive());
        // Password must be hashed, not stored in clear text.
        self::assertNotSame('strongpass', $created->getPassword());
    }

    public function testToggleBlocksAndUnblocksGuest(): void
    {
        $alice = $this->findUserByEmail(AppFixtures::GUEST_ACTIVE_EMAIL);
        self::assertTrue($alice->isActive());
        $id = $alice->getId();

        // First toggle -> blocked.
        $this->client->request('GET', '/admin/guest/toggle/'.$id);
        self::assertResponseRedirects('/admin/guest');
        $this->reloadEntityManager();
        self::assertFalse($this->findUserByEmail(AppFixtures::GUEST_ACTIVE_EMAIL)->isActive());

        // Second toggle -> active again.
        $this->client->request('GET', '/admin/guest/toggle/'.$id);
        $this->reloadEntityManager();
        self::assertTrue($this->findUserByEmail(AppFixtures::GUEST_ACTIVE_EMAIL)->isActive());
    }

    public function testRevokeSelectedGuests(): void
    {
        $alice = $this->findUserByEmail(AppFixtures::GUEST_ACTIVE_EMAIL);
        $carol = $this->findUserByEmail(AppFixtures::GUEST_SECOND_EMAIL);

        // Grab a valid CSRF token from the index page.
        $crawler = $this->client->request('GET', '/admin/guest');
        $token = $crawler->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', '/admin/guest/revoke', [
            '_token' => $token,
            'guests' => [$alice->getId(), $carol->getId()],
        ]);

        self::assertResponseRedirects('/admin/guest');
        $this->reloadEntityManager();
        self::assertFalse($this->findUserByEmail(AppFixtures::GUEST_ACTIVE_EMAIL)->isActive());
        self::assertFalse($this->findUserByEmail(AppFixtures::GUEST_SECOND_EMAIL)->isActive());
    }

    public function testRevokeWithInvalidCsrfTokenIsDenied(): void
    {
        $alice = $this->findUserByEmail(AppFixtures::GUEST_ACTIVE_EMAIL);

        $this->client->request('POST', '/admin/guest/revoke', [
            '_token' => 'invalid-token',
            'guests' => [$alice->getId()],
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testDeleteGuest(): void
    {
        $bob = $this->findUserByEmail(AppFixtures::GUEST_BLOCKED_EMAIL);
        $id = $bob->getId();

        $this->client->request('GET', '/admin/guest/delete/'.$id);

        self::assertResponseRedirects('/admin/guest');
        $this->reloadEntityManager();
        self::assertNull($this->entityManager->getRepository(User::class)->find($id));
    }

    private function reloadEntityManager(): void
    {
        $this->entityManager->clear();
    }
}

