<?php

namespace App\Tests\Controller\Admin;

use App\DataFixtures\AppFixtures;
use App\Entity\Media;
use App\Tests\AbstractWebTestCase;

class MediaControllerTest extends AbstractWebTestCase
{
    public function testIndexForGuestShowsOnlyOwnMedia(): void
    {
        $alice = $this->findUserByEmail(AppFixtures::GUEST_ACTIVE_EMAIL);
        $this->client->loginUser($alice);

        $this->client->request('GET', '/admin/media');

        self::assertResponseIsSuccessful();
        // Alice sees her own media...
        self::assertSelectorTextContains('body', 'Photo d’Alice');
        // ...but not the admin's photos.
        self::assertSelectorTextNotContains('body', 'Forêt brumeuse');
    }

    public function testAddPageRendersForAdmin(): void
    {
        $admin = $this->findUserByEmail(AppFixtures::ADMIN_EMAIL);
        $this->client->loginUser($admin);

        $this->client->request('GET', '/admin/media/add');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }

    public function testAddPageRendersForGuest(): void
    {
        $alice = $this->findUserByEmail(AppFixtures::GUEST_ACTIVE_EMAIL);
        $this->client->loginUser($alice);

        $this->client->request('GET', '/admin/media/add');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
        // A guest can set a title and a file...
        self::assertSelectorExists('#media_title');
        self::assertSelectorExists('#media_file');
        // ...but never chooses the owner or the album (admin-only fields).
        self::assertSelectorNotExists('#media_user');
        self::assertSelectorNotExists('#media_album');
    }

    public function testGuestCanAddOwnMedia(): void
    {
        $alice = $this->findUserByEmail(AppFixtures::GUEST_ACTIVE_EMAIL);
        $this->client->loginUser($alice);

        $crawler = $this->client->request('GET', '/admin/media/add');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Ajouter')->form();
        $form['media[title]'] = 'Nouvelle photo de test';
        $form['media[file]']->upload($this->createTemporaryImage());

        $this->client->submit($form);

        self::assertResponseRedirects('/admin/media');

        $this->entityManager->clear();
        $media = $this->entityManager->getRepository(Media::class)
            ->findOneBy(['title' => 'Nouvelle photo de test']);
        self::assertNotNull($media);
        // The media is automatically owned by the guest who uploaded it.
        self::assertSame($alice->getId(), $media->getUser()?->getId());
    }

    /**
     * Writes a tiny but valid JPEG to a temp file and returns its path, so the
     * upload test stays self-contained (no dependency on public/uploads).
     */
    private function createTemporaryImage(): string
    {
        // 1x1 pixel JPEG.
        $jpeg = base64_decode(
            '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRof'
            .'Hh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/wAALCAABAAEBAREA/8QAFAAB'
            .'AAAAAAAAAAAAAAAAAAAAAv/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AfwD/2Q==',
            true
        );
        self::assertNotFalse($jpeg);

        $path = tempnam(sys_get_temp_dir(), 'media_upload_').'.jpg';
        file_put_contents($path, $jpeg);

        return $path;
    }

    public function testDeleteMediaAsAdmin(): void
    {
        $admin = $this->findUserByEmail(AppFixtures::ADMIN_EMAIL);
        $this->client->loginUser($admin);

        $media = $this->entityManager->getRepository(Media::class)
            ->findOneBy(['title' => 'Photo d’Alice']);
        $id = $media->getId();

        $this->client->request('GET', '/admin/media/delete/'.$id);

        self::assertResponseRedirects('/admin/media');
        $this->entityManager->clear();
        self::assertNull($this->entityManager->getRepository(Media::class)->find($id));
    }
}

