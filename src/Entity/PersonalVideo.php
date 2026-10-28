<?php

namespace App\Entity;

use App\Repository\PersonalVideoRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Vidéo personnelle importée depuis un fichier (mov, mp4, etc.) puis convertie en MP4 (H.264 / AAC).
 * Fichiers : /public/videos/personal/{userId}/{filename} et /public/videos/personal/{userId}/thumbnails/{thumbnail}
 */
#[ORM\Entity(repositoryClass: PersonalVideoRepository::class)]
#[ORM\Index(name: 'personal_video_status_idx', columns: ['status'])]
class PersonalVideo
{
    public const string STATUS_PENDING = 'pending';       // en attente de réencodage (commande app:videos:process)
    public const string STATUS_PROCESSING = 'processing'; // réencodage en cours
    public const string STATUS_READY = 'ready';
    public const string STATUS_FAILED = 'failed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user;

    #[ORM\Column(length: 255)]
    private ?string $title;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    // Fichier d'origine, en attente de conversion, dans /var/videos/temp/ — supprimé après conversion
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sourcePath = null;

    // Fichier MP4 converti
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $filename = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $thumbnail = null;

    // Durée en secondes
    #[ORM\Column(nullable: true)]
    private ?int $duration = null;

    #[ORM\Column(nullable: true)]
    private ?int $width = null;

    #[ORM\Column(nullable: true)]
    private ?int $height = null;

    // Taille du fichier MP4 en octets
    #[ORM\Column(type: Types::BIGINT, nullable: true)]
    private ?string $size = null;

    #[ORM\Column(length: 16)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $error = null;

    // Date de tournage (métadonnées du fichier)
    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $recordedAt = null;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt;

    #[ORM\Column]
    private ?DateTimeImmutable $updatedAt;

    public function __construct(User $user, string $title, DateTimeImmutable $createdAt)
    {
        $this->user = $user;
        $this->title = $title;
        $this->createdAt = $createdAt;
        $this->updatedAt = $createdAt;
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY;
    }

    public function isPortrait(): bool
    {
        return $this->width && $this->height && $this->height > $this->width;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getSourcePath(): ?string
    {
        return $this->sourcePath;
    }

    public function setSourcePath(?string $sourcePath): static
    {
        $this->sourcePath = $sourcePath;

        return $this;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(?string $filename): static
    {
        $this->filename = $filename;

        return $this;
    }

    public function getThumbnail(): ?string
    {
        return $this->thumbnail;
    }

    public function setThumbnail(?string $thumbnail): static
    {
        $this->thumbnail = $thumbnail;

        return $this;
    }

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(?int $duration): static
    {
        $this->duration = $duration;

        return $this;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function setWidth(?int $width): static
    {
        $this->width = $width;

        return $this;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function setHeight(?int $height): static
    {
        $this->height = $height;

        return $this;
    }

    public function getSize(): ?int
    {
        return $this->size === null ? null : (int)$this->size;
    }

    public function setSize(?int $size): static
    {
        $this->size = $size === null ? null : (string)$size;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function setError(?string $error): static
    {
        $this->error = $error;

        return $this;
    }

    public function getRecordedAt(): ?DateTimeImmutable
    {
        return $this->recordedAt;
    }

    public function setRecordedAt(?DateTimeImmutable $recordedAt): static
    {
        $this->recordedAt = $recordedAt;

        return $this;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
