<?php

namespace App\Entity;

use App\Repository\UserEpisodeNotificationRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserEpisodeNotificationRepository::class)]
class UserEpisodeNotification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'userEpisodeNotifications')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private EpisodeNotification $episodeNotification;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $validatedAt = null;

    public function __construct(User $user, EpisodeNotification $episodeNotification)
    {
        $this->user = $user;
        $this->episodeNotification = $episodeNotification;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getValidatedAt(): ?DateTimeImmutable
    {
        return $this->validatedAt;
    }

    public function setValidatedAt(?DateTimeImmutable $validatedAt): static
    {
        $this->validatedAt = $validatedAt;

        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(?User $user): void
    {
        $this->user = $user;
    }

    public function getEpisodeNotification(): EpisodeNotification
    {
        return $this->episodeNotification;
    }

    public function setEpisodeNotification(EpisodeNotification $episodeNotification): void
    {
        $this->episodeNotification = $episodeNotification;
    }
}
