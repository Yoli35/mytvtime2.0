<?php

namespace App\Repository;

use App\Entity\UserSeason;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserSeason>
 */
class UserSeasonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserSeason::class);
    }

    public function save(UserSeason $userSeason, bool $flush = false): void
    {
        $this->getEntityManager()->persist($userSeason);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(UserSeason $userSeason, bool $flush = false): void
    {
        $this->getEntityManager()->remove($userSeason);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function flush(): void
    {
        $this->getEntityManager()->flush();
    }
}
