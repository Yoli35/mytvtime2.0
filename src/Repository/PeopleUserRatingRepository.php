<?php

namespace App\Repository;

use App\Entity\PeopleUserRating;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PeopleUserRating>
 */
class PeopleUserRatingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly EntityManagerInterface $em)
    {
        parent::__construct($registry, PeopleUserRating::class);
    }

    public function save(PeopleUserRating $peopleUserRating, bool $flush = false): void
    {
        $this->em->persist($peopleUserRating);
        if ($flush) $this->em->flush();
    }

    public function flush(): void
    {
        $this->em->flush();
    }

    public function getPeopleUserRating(int $userId, int $id): array
    {
        $sql = "SELECT AVG(pur.rating)                         as avg_rating,
                (SELECT rating 
                    FROM people_user_rating 
                    WHERE user_id = $userId AND tmdb_id = $id) as rating
                FROM people_user_rating  pur
                WHERE tmdb_id = $id";

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

    public function getOne($sql): array
    {
        try {
            return $this->em->getConnection()->fetchAssociative($sql);
        } catch (Exception) {
            return [];
        }
    }
}
