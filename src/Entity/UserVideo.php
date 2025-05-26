<?php

namespace App\Entity;

use App\Repository\UserVideoRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserVideoRepository::class)]
class UserVideo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'videos')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user;

    #[ORM\ManyToOne]
    private ?Video $video;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt;

    #[ORM\Column]
    private ?DateTimeImmutable $UpdatedAt;

    private ?string $publishedAtString = null;

    private ?string $addedAtString = null;

    public function __construct(User $user, Video $video, DateTimeImmutable $now)
    {
        $this->user = $user;
        $this->video = $video;
        $this->createdAt = $now;
        $this->UpdatedAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getVideo(): ?Video
    {
        return $this->video;
    }

    public function setVideo(?Video $video): static
    {
        $this->video = $video;

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

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->UpdatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $UpdatedAt): static
    {
        $this->UpdatedAt = $UpdatedAt;

        return $this;
    }

    public function getPublishedAtString(): ?string
    {
        return $this->publishedAtString;
    }

    public function setPublishedAtString(?string $publishedAtString): void
    {
        $this->publishedAtString = $publishedAtString;
    }

    public function getAddedAtString(): ?string
    {
        return $this->addedAtString;
    }

    public function setAddedAtString(?string $addedAtString): void
    {
        $this->addedAtString = $addedAtString;
    }
}
