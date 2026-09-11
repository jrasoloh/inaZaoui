<?php

namespace App\Tests\Controller;

use App\DataFixtures\AppFixtures;
use App\Entity\Album;
use App\Tests\AbstractWebTestCase;

class HomeControllerTest extends AbstractWebTestCase
{
    public function testHomePageIsSuccessful(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h2', 'Photographe');
    }

    public function testAboutPageIsSuccessful(): void
    {
        $this->client->request('GET', '/about');

        self::assertResponseIsSuccessful();
    }

    public function testGuestsPageListsActiveGuestsOnly(): void
    {
        $crawler = $this->client->request('GET', '/guests');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h3', 'Invités');

        $text = $crawler->filter('.guests')->text();

        // Active guests are listed.
        self::assertStringContainsString(AppFixtures::GUEST_ACTIVE_NAME, $text);
        self::assertStringContainsString(AppFixtures::GUEST_SECOND_NAME, $text);

        // Blocked guest and the admin/photographer are NOT listed.
        self::assertStringNotContainsString(AppFixtures::GUEST_BLOCKED_NAME, $text);
        self::assertStringNotContainsString('Ina Zaoui', $text);
    }

    public function testGuestDetailPageForActiveGuest(): void
    {
        $alice = $this->findUserByEmail(AppFixtures::GUEST_ACTIVE_EMAIL);

        $this->client->request('GET', '/guest/'.$alice->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h3', AppFixtures::GUEST_ACTIVE_NAME);
    }

    public function testGuestDetailPageForBlockedGuestReturns404(): void
    {
        $bob = $this->findUserByEmail(AppFixtures::GUEST_BLOCKED_EMAIL);

        $this->client->request('GET', '/guest/'.$bob->getId());

        self::assertResponseStatusCodeSame(404);
    }

    public function testGuestDetailPageForUnknownIdReturns404(): void
    {
        $this->client->request('GET', '/guest/999999');

        self::assertResponseStatusCodeSame(404);
    }

    public function testGuestDetailPageForAdminReturns404(): void
    {
        // The admin is not a "guest" and must not be reachable via /guest/{id}.
        $admin = $this->findUserByEmail(AppFixtures::ADMIN_EMAIL);

        $this->client->request('GET', '/guest/'.$admin->getId());

        self::assertResponseStatusCodeSame(404);
    }

    public function testPortfolioWithoutAlbumShowsAdminMedia(): void
    {
        $crawler = $this->client->request('GET', '/portfolio');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h3', 'Portfolio');
        self::assertStringContainsString('Forêt brumeuse', $crawler->filter('body')->text());
    }

    public function testPortfolioByAlbumHidesBlockedGuestMedia(): void
    {
        $album = static::getContainer()->get('doctrine')->getManager()
            ->getRepository(Album::class)
            ->findOneBy(['name' => AppFixtures::ALBUM_NATURE]);

        $crawler = $this->client->request('GET', '/portfolio/'.$album->getId());

        self::assertResponseIsSuccessful();

        $text = $crawler->filter('body')->text();
        self::assertStringContainsString('Photo d’Alice', $text);
        self::assertStringContainsString('Sans propriétaire', $text);
        self::assertStringNotContainsString('Photo de Bob', $text);
    }

    public function testNavShowsLoginLinkForAnonymous(): void
    {
        $crawler = $this->client->request('GET', '/');

        $nav = $crawler->filter('header nav')->text();
        self::assertStringContainsString('Connexion', $nav);
        self::assertStringNotContainsString('Déconnexion', $nav);
    }

    public function testNavShowsMediaSpaceLinkForGuest(): void
    {
        $alice = $this->findUserByEmail(AppFixtures::GUEST_ACTIVE_EMAIL);
        $this->client->loginUser($alice);

        $crawler = $this->client->request('GET', '/');

        // A logged-in guest gets a link to their own media space...
        $link = $crawler->selectLink('Mes médias')->link();
        self::assertStringContainsString('/admin/media', $link->getUri());

        // ...but not the admin-only label.
        $nav = $crawler->filter('header nav')->text();
        self::assertStringContainsString('Déconnexion', $nav);
        self::assertStringNotContainsString('Admin', $nav);
    }

    public function testNavShowsAdminLinkForAdmin(): void
    {
        $admin = $this->findUserByEmail(AppFixtures::ADMIN_EMAIL);
        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/');

        $link = $crawler->selectLink('Admin')->link();
        self::assertStringContainsString('/admin/media', $link->getUri());
    }
}

