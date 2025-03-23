<?php

namespace App\Repository;

use App\Entity\People;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
        $idList = implode(',', $ids);
        $sql = "SELECT p.*, pupn.name as preferred_name
                FROM people p
                    LEFT JOIN people_user_preferred_name pupn ON p.tmdb_id = pupn.tmdb_id
                WHERE p.tmdb_id IN ($idList)";

        return $this->getAll($sql);
    }

    public function getOnePeople(int $id): array
    {
        $sql = "SELECT * FROM people WHERE id = $id";

        return $this->getOne($sql);
    }
    public function getAll($sql): array
    {
        try {
            return $this->em->getConnection()->fetchAllAssociative($sql);
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
