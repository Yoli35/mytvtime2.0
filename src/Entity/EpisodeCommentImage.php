<?php

namespace App\Entity;

use App\Repository\EpisodeCommentImageRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EpisodeCommentImageRepository::class)]
class EpisodeCommentImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'episodeCommentImages')]
    #[ORM\JoinColumn(nullable: false)]
    private ?EpisodeComment $episodeComment = null;

    #[ORM\Column(length: 255)]
    private ?string $path = null;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    public function __construct(EpisodeComment $episodeComment, string $path, DateTimeImmutable $createdAt)
    {
        $this->episodeComment = $episodeComment;
        $this->path = $path;
        $this->createdAt = $createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEpisodeComment(): ?EpisodeComment
    {
        return $this->episodeComment;
    }

    public function setEpisodeComment(?EpisodeComment $episodeComment): static
    {
        $this->episodeComment = $episodeComment;

        return $this;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
