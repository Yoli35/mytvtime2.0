<?php

namespace App\Repository;

use App\Entity\PointOfInterest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
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

    public function save(PointOfInterest $poi, bool $flush = false): void
    {
        $this->em->persist($poi);
        if ($flush) {
            $this->em->flush();
        }
    }

    public function allPointsOfInterest(): array
    {
        $sql = <<<SQL
            SELECT p.*, i.path as still_path
            FROM point_of_interest p
                LEFT JOIN point_of_interest_image i ON i.id = p.still_id
        SQL;

        return $this->getAll($sql);
    }

    public function adminPointsOfInterest(int $page, string $sort, string $order, int $limit):array
    {
        $p = ['offset' => ($page - 1) * $limit, 'sort' => $sort, 'limit' => $limit];
        $t = ['offset'=> ParameterType::INTEGER, 'sort'=> ParameterType::STRING, 'limit'=> ParameterType::INTEGER];
        $sql = <<<SQL
            SELECT p.id, p.name, p.address, p.city, p.origin_country, p.latitude, p.longitude, p.created_at, p.updated_at, i.path AS still_path
            FROM point_of_interest p
                LEFT JOIN point_of_interest_image i ON i.id = p.still_id
            ORDER BY :sort $order LIMIT :limit OFFSET :offset
        SQL;

        return $this->getAll($sql, $p, $t);
    }

    public function adminPointOfInterest(int $id): array|false
    {
        $p = ['id' => $id];
        $t = ['id'=> ParameterType::INTEGER];
        $sql = <<<SQL
            SELECT p.id, p.name, p.city, p.origin_country, p.description, p.latitude, p.longitude, 
                   i.path AS still_path, p.created_at, p.updated_at
            FROM point_of_interest p
                LEFT JOIN point_of_interest_image i ON i.id = p.still_id
            WHERE p.id = $id
        SQL;

        return $this->getOne($sql, $p, $t);
    }

    public function adminPointOfInterestImages(int $id): array
    {
        $p = ['id' => $id];
        $t = ['id'=> ParameterType::INTEGER];
        $sql = <<<SQL
            SELECT i.id, i.path, i.created_at, i.caption
            FROM point_of_interest_image i
            WHERE i.point_of_interest_id = :id
        SQL;

        return $this->getAll($sql, $p, $t);
    }

    public function getAll(string $sql, array $params = [], array $types = []): array
    {
        try {
            return $this->em->getConnection()->fetchAllAssociative($sql, $params, $types);
        } catch (Exception) {
            return [];
        }
    }

    public function getOne(string $sql, array $params = [], array $types = []): array|false
    {
        try {
            return $this->em->getConnection()->fetchAssociative($sql, $params, $types);
        } catch (Exception) {
            return [];
        }
    }
}
