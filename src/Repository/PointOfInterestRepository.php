<?php

namespace App\Repository;

use App\Entity\PointOfInterest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PointOfInterest>
 */
class PointOfInterestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly EntityManagerInterface $em)
    {
        parent::__construct($registry, PointOfInterest::class);
    }

    public function adminPointsOfInterest(int $page, string $sort, string $order, int $limit):array
    {
        $offset = ($page - 1) * $limit;
        $sql = "SELECT p.id, p.name, p.city, p.origin_country, p.created_at, p.updated_at, i.path as still_path
                FROM point_of_interest p
                    LEFT JOIN point_of_interest_image i ON i.id = p.still_id
                ORDER BY $sort $order LIMIT $limit OFFSET $offset";

        return $this->getAll($sql);
    }

    public function adminPointOfInterest(int $id): array|false
    {
        $sql = "SELECT p.id, p.name, p.city, p.origin_country, p.description, p.latitude, p.longitude, 
                       i.path as still_path, p.created_at, p.updated_at
                FROM point_of_interest p
                    LEFT JOIN point_of_interest_image i ON i.id = p.still_id
                WHERE p.id = $id";

        return $this->getOne($sql);
    }

    public function adminPointOfInterestImages(int $id): array
    {
        $sql = "SELECT i.id, i.path, i.created_at, i.updated_at
                FROM point_of_interest_image i
                WHERE i.point_of_interest_id = $id";

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

    public function getOne($sql): array|false
    {
        try {
            return $this->em->getConnection()->fetchAssociative($sql);
        } catch (Exception) {
            return [];
        }
    }
}
