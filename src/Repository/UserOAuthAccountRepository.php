<?php

namespace App\Repository;

use App\Entity\UserOAuthAccount;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class UserOAuthAccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserOAuthAccount::class);
    }

    public function findOneByProviderAndProviderUserId(string $provider, string $providerUserId): ?UserOAuthAccount
    {
        return $this->findOneBy([
            'provider' => $provider,
            'providerUserId' => $providerUserId,
        ]);
    }

    public function findOneByUserAndProvider(int $userId, string $provider): ?UserOAuthAccount
    {
        return $this->findOneBy([
            'user' => $userId,
            'provider' => $provider,
        ]);
    }
}