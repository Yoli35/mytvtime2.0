<?php

namespace App\Repository;

use App\Entity\SeriesVideo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SeriesVideo>
 */
class SeriesVideoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly EntityManagerInterface $em)
    {
        parent::__construct($registry, SeriesVideo::class);
    }

    public function save(SeriesVideo $video, bool $flush = false): void
    {
        $this->em->persist($video);

        if ($flush) {
            $this->em->flush();
        }
    }
}
