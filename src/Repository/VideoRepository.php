<?php

namespace App\Repository;

use App\Entity\Video;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Video>
 */
class VideoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry,  private readonly EntityManagerInterface $em)
    {
        parent::__construct($registry, Video::class);
    }

    public function save(Video $video, bool $flush = false): void
    {
        $this->em->persist($video);

        if ($flush) {
            $this->em->flush();
        }
    }
}
