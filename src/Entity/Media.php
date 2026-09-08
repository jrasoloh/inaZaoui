<?php

namespace App\Entity;

use App\Repository\MediaRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MediaRepository::class)]
class Media
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: "medias", fetch: "EAGER")]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Album::class, fetch: "EAGER")]
    private ?Album $album = null;

    #[ORM\Column]
    private string $path;

    #[ORM\Column]
    private string $title;

    #[Assert\NotNull(message: 'Veuillez sélectionner une image à uploader.')]
    #[Assert\Image(
        maxSize: '8M',
        mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
        mimeTypesMessage: 'Format invalide : merci d’uploader une image JPEG, PNG, WEBP ou GIF.',
        maxSizeMessage: 'L’image est trop lourde ({{ size }} {{ suffix }}). Taille maximale autorisée : {{ limit }} {{ suffix }}.',
        uploadIniSizeErrorMessage: 'L’image dépasse la taille maximale autorisée (8 Mo).',
        uploadErrorMessage: 'L’upload de l’image a échoué. Merci de réessayer.',
    )]
    private ?UploadedFile $file = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): void
    {
        $this->user = $user;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getFile(): ?UploadedFile
    {
        return $this->file;
    }

    public function setFile(?UploadedFile $file): void
    {
        $this->file = $file;
    }

    public function getAlbum(): ?Album
    {
        return $this->album;
    }

    public function setAlbum(?Album $album): void
    {
        $this->album = $album;
    }
}
