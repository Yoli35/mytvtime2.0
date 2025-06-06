<?php

namespace App\Entity;

use App\Repository\WatchProviderRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WatchProviderRepository::class)]
class WatchProvider
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private array $displayPriorities;

    #[ORM\Column]
    private ?int $displayPriority;

    #[ORM\Column(length: 255)]
    private ?string $logoPath;

    #[ORM\Column(length: 255)]
    private ?string $providerName;

    #[ORM\Column]
    private ?int $providerId;

    #[ORM\Column(nullable: true)]
    private ?bool $removed;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $updatedAt = null;

    public function __construct(int $providerId, string $providerName, ?string $logoPath, int $displayPriority, array $displayPriorities, DateTimeImmutable $updatedAt, bool $removed)
    {
        $this->providerId = $providerId;
        $this->providerName = $providerName;
        $this->logoPath = $logoPath;
        $this->displayPriority = $displayPriority;
        $this->displayPriorities = $displayPriorities;
        $this->updatedAt = $updatedAt;
        $this->removed = $removed;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDisplayPriorities(): array
    {
        return $this->displayPriorities;
    }

    public function setDisplayPriorities(array $displayPriorities): static
    {
        $this->displayPriorities = $displayPriorities;

        return $this;
    }

    public function getDisplayPriority(): ?int
    {
        return $this->displayPriority;
    }

    public function setDisplayPriority(int $displayPriority): static
    {
        $this->displayPriority = $displayPriority;

        return $this;
    }

    public function getLogoPath(): ?string
    {
        return $this->logoPath;
    }

    public function setLogoPath(string $logoPath): static
    {
        $this->logoPath = $logoPath;

        return $this;
    }

    public function getProviderName(): ?string
    {
        return $this->providerName;
    }

    public function setProviderName(string $providerName): static
    {
        $this->providerName = $providerName;

        return $this;
    }

    public function getProviderId(): ?int
    {
        return $this->providerId;
    }

    public function setProviderId(int $providerId): static
    {
        $this->providerId = $providerId;

        return $this;
    }

    public function isRemoved(): ?bool
    {
        return $this->removed;
    }

    public function setRemoved(?bool $removed): static
    {
        $this->removed = $removed;

        return $this;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
