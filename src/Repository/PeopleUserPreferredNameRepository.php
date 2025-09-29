<?php

namespace App\Repository;

use App\Entity\PeopleUserPreferredName;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PeopleUserPreferredName>
 */
class PeopleUserPreferredNameRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly EntityManagerInterface $em)
    {
        parent::__construct($registry, PeopleUserPreferredName::class);
    }

    public function save(PeopleUserPreferredName $entity, bool $flush = false): void
    {
        $this->em->persist($entity);

        if ($flush) {
            $this->em->flush();
        }
    }

    public function remove(PeopleUserPreferredName $entity, bool $flush = false): void
    {
        $this->em->remove($entity);

        if ($flush) {
            $this->em->flush();
        }
    }

    public function flush(): void
    {
        $this->em->flush();
    }

    public function getUserPreferredNames(int $userId): array
    {
        $sql = "SELECT * FROM people_user_preferred_name WHERE user_id=$userId";
        return $this->getAll($sql);
    }

    public function getPreferredNames(array $tmdbIds): array
    {
        $sql = "SELECT * FROM people_user_preferred_name WHERE tmdb_id IN (" . implode(',', $tmdbIds) . ")";
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
