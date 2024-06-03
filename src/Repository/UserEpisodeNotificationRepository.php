<?php

namespace App\Repository;

use App\Entity\UserEpisodeNotification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserEpisodeNotification>
 */
class UserEpisodeNotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly EntityManagerInterface $em)
    {
        parent::__construct($registry, UserEpisodeNotification::class);
    }

    public function save(UserEpisodeNotification $userEpisodeNotification, bool $flush = false): void
    {
        $this->em->persist($userEpisodeNotification);
        if ($flush) {
            $this->em->flush();
        }
    }

    public function flush(): void
    {
        $this->em->flush();
    }
}
