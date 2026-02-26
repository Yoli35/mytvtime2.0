<?php

namespace App\Repository;

use App\Entity\People;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<People>
 */
class PeopleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly EntityManagerInterface $em)
    {
        parent::__construct($registry, People::class);
    }
    public function save(People $dbPeople, bool $flush = false): void
    {
        $this->em->persist($dbPeople);

        if ($flush) {
            $this->em->flush();
        }
    }

    public function remove(People $dbPeople, bool $flush = false): void
    {
        $this->em->remove($dbPeople);

        if ($flush) {
            $this->em->flush();
        }
    }

    public function flush(): void
    {
        $this->em->flush();
    }

    public function getPeopleByTMDBId(array $ids): array
    {
        $params = ['ids' => $ids];
        $types = ['ids' => ArrayParameterType::INTEGER];
        $sql = <<<SQL
            SELECT p.*, pupn.name as preferred_name
            FROM people p
                LEFT JOIN people_user_preferred_name pupn ON p.tmdb_id = pupn.tmdb_id
            WHERE p.tmdb_id IN (:ids)
        SQL;

        return $this->getAll($sql, $params, $types);
    }

    public function getAll(string $sql, array $params, array $types): array
    {
        try {
            return $this->em->getConnection()->fetchAllAssociative($sql, $params, $types);
        } catch (Exception) {
            return [];
        }
    }

    public function getOne($sql): mixed
    {
        try {
            return $this->em->getConnection()->fetchOne($sql);
        } catch (Exception) {
            return [];
        }
    }
}
