<?php

namespace App\Repository;

use App\Entity\PeopleUserRating;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
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
        $p = [
            'userId' => $userId,
            'id' => $id
        ];
        $t = [
            'userId' => ParameterType::INTEGER,
            'id' => ParameterType::INTEGER,
        ];
        $sql = <<<SQL
            SELECT AVG(pur.rating)                         as avg_rating,
            (SELECT rating 
                FROM people_user_rating 
                WHERE user_id = :userId AND tmdb_id = :id) as rating
            FROM people_user_rating  pur
            WHERE tmdb_id = $id
        SQL;

        return $this->getOne($sql, $p, $t);
    }

    public function getAll(string $sql, array $p = [], array $t = []): array
    {
        try {
            return $this->em->getConnection()->fetchAllAssociative($sql, $p, $t);
        } catch (Exception) {
            return [];
        }
    }

    public function getOne(string $sql, array $p = [], array $t = []): array
    {
        try {
            return $this->em->getConnection()->fetchAssociative($sql, $p, $t);
        } catch (Exception) {
            return [];
        }
    }
}
