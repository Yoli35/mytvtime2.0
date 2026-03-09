<?php

namespace App\Repository;

use App\Entity\VideoCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VideoCategory>
 */
class VideoCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct($registry, VideoCategory::class);
    }

    public function save(VideoCategory $videoCategory, bool $flush = false): void
    {
        $this->entityManager->persist($videoCategory);
        if ($flush) {
            $this->entityManager->flush();
        }
    }
}
