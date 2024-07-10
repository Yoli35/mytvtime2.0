<?php

namespace App\Entity;

use App\Repository\UserPinnedSeriesRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserPinnedSeriesRepository::class)]
class UserPinnedSeries
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'userPinnedSeries')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user;

    #[ORM\OneToOne(inversedBy: 'userPinnedSeries')]
    #[ORM\JoinColumn(nullable: false)]
    private ?UserSeries $userSeries;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt;

    public function __construct(User $user, UserSeries $userSeries)
    {
        $this->user = $user;
        $this->userSeries = $userSeries;
        $this->createdAt = new DateTimeImmutable();
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

    public function getUserSeries(): ?UserSeries
    {
        return $this->userSeries;
    }

    public function setUserSeries(UserSeries $userSeries): static
    {
        $this->userSeries = $userSeries;

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
