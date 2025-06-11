<?php

namespace App\Repository;

use App\Entity\PointOfInterestImage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PointOfInterestImage>
 */
class PointOfInterestImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly EntityManagerInterface $em)
    {
        parent::__construct($registry, PointOfInterestImage::class);
    }

    public function save(PointOfInterestImage $poiImage, bool $flush = false): void
    {
        $this->em->persist($poiImage);
        if ($flush) {
            $this->em->flush();
        }
    }

    public function poiImages(array $pointOfInterestIds): array
    {
        $ids = implode(',', $pointOfInterestIds);
        $sql = "SELECT i.id, i.path, i.caption, i.created_at, i.point_of_interest_id
                FROM point_of_interest_image i
                WHERE i.point_of_interest_id IN ($ids)";
        return $this->getAll($sql);
    }

    public function getAll($sql): array
    {
        try {
            return $this->em->getConnection()->fetchAllAssociative($sql);
        } catch (Exception) {
            return [];
        }
    }
}
