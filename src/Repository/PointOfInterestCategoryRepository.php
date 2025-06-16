<?php

namespace App\Repository;

use App\Entity\PointOfInterestCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PointOfInterestCategory>
 */
class PointOfInterestCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly EntityManagerInterface $em)
    {
        parent::__construct($registry, PointOfInterestCategory::class);
    }

    public function poiCategories(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        $ids = '(' . implode(',', $ids) . ')';
        $sql = "SELECT pc.point_of_interest_id as point_of_interest_id, poc.id AS category_id, poc.name as category_name, poc.icon as category_icon
                FROM point_of_interest_point_of_interest_category pc
                    LEFT JOIN point_of_interest_category poc ON pc.point_of_interest_category_id = poc.id
                WHERE pc.point_of_interest_id IN $ids";
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
