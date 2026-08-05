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

