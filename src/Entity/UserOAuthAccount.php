<?php

namespace App\Entity;

use App\Repository\UserOAuthAccountRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserOAuthAccountRepository::class)]
#[ORM\Table(name: 'user_oauth_account')]
#[ORM\UniqueConstraint(name: 'uniq_provider_userid', columns: ['provider', 'provider_user_id'])]
#[ORM\UniqueConstraint(name: 'uniq_user_provider', columns: ['user_id', 'provider'])]
class UserOAuthAccount
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 20)]
    private string $provider;

    #[ORM\Column(length: 255)]
    private string $providerUserId;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): self
    {
        $this->provider = $provider;
        return $this;
    }

    public function getProviderUserId(): string
    {
        return $this->providerUserId;
    }

    public function setProviderUserId(string $providerUserId): self
    {
        $this->providerUserId = $providerUserId;
        return $this;
    }
}