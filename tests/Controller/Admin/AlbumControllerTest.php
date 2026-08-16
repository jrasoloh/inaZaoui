<?php

namespace App\Tests\Controller\Admin;

use App\DataFixtures\AppFixtures;
use App\Entity\Album;
use App\Tests\AbstractWebTestCase;

class AlbumControllerTest extends AbstractWebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $admin = $this->findUserByEmail(AppFixtures::ADMIN_EMAIL);
        $this->client->loginUser($admin);
    }

    public function testIndexListsAlbums(): void
    {
        $this->client->request('GET', '/admin/album');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', AppFixtures::ALBUM_NATURE);
        self::assertSelectorTextContains('body', AppFixtures::ALBUM_CITY);
    }

    public function testAddAlbum(): void
    {
        $crawler = $this->client->request('GET', '/admin/album/add');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Ajouter')->form([
            'album[name]' => 'Montagne',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/admin/album');

        $album = $this->entityManager->getRepository(Album::class)->findOneBy(['name' => 'Montagne']);
        self::assertNotNull($album);
    }

    public function testUpdateAlbum(): void
    {
        $album = $this->entityManager->getRepository(Album::class)
            ->findOneBy(['name' => AppFixtures::ALBUM_CITY]);
        $id = $album->getId();

        $crawler = $this->client->request('GET', '/admin/album/update/'.$id);
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Modifier')->form([
            'album[name]' => 'Ville renommée',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/admin/album');

        $this->entityManager->clear();
        $updated = $this->entityManager->getRepository(Album::class)->find($id);
        self::assertSame('Ville renommée', $updated->getName());
    }

    public function testDeleteAlbum(): void
    {
        $album = $this->entityManager->getRepository(Album::class)
            ->findOneBy(['name' => AppFixtures::ALBUM_CITY]);
        $id = $album->getId();

        $this->client->request('GET', '/admin/album/delete/'.$id);

        self::assertResponseRedirects('/admin/album');
        $this->entityManager->clear();
        self::assertNull($this->entityManager->getRepository(Album::class)->find($id));
    }
}

