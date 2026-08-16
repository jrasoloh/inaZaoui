<?php

namespace App\Tests\Controller;

use App\DataFixtures\AppFixtures;
use App\Tests\AbstractWebTestCase;

class SecurityControllerTest extends AbstractWebTestCase
{
    public function testLoginPageIsSuccessful(): void
    {
        $this->client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Connexion');
    }

    public function testAdminCanLogIn(): void
    {
        $this->submitLogin(AppFixtures::ADMIN_EMAIL, AppFixtures::ADMIN_PASSWORD);

        // Successful login redirects away from the login page.
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
    }

    public function testActiveGuestCanLogIn(): void
    {
        $this->submitLogin(AppFixtures::GUEST_ACTIVE_EMAIL, AppFixtures::GUEST_ACTIVE_PASSWORD);

        self::assertResponseRedirects();
    }

    public function testLoginWithWrongPasswordShowsError(): void
    {
        $this->submitLogin(AppFixtures::ADMIN_EMAIL, 'wrong-password');

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');
    }

    public function testBlockedGuestCannotLogIn(): void
    {
        $this->submitLogin(AppFixtures::GUEST_BLOCKED_EMAIL, AppFixtures::GUEST_BLOCKED_PASSWORD);

        self::assertResponseRedirects('/login');
        $crawler = $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');
        self::assertStringContainsString('révoqué', $crawler->filter('.alert-danger')->text());
    }

    public function testLogoutRedirectsHome(): void
    {
        $admin = $this->findUserByEmail(AppFixtures::ADMIN_EMAIL);
        $this->client->loginUser($admin);

        $this->client->request('GET', '/logout');

        self::assertResponseRedirects();
    }

    private function submitLogin(string $email, string $password): void
    {
        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->selectButton('Connexion')->form([
            '_username' => $email,
            '_password' => $password,
        ]);
        $this->client->submit($form);
    }
}

