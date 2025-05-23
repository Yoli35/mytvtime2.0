<?php

namespace App\Repository;

use App\Entity\UserVideo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserVideo>
 */
class UserVideoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly EntityManagerInterface $em)
    {
        parent::__construct($registry, UserVideo::class);
    }
    public function save(UserVideo $userVideo, bool $flush = false): void
    {
        $this->em->persist($userVideo);

        if ($flush) {
            $this->em->flush();
        }
    }
}
