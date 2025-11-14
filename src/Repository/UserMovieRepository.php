<?php

namespace App\Repository;

use App\Entity\UserMovie;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserMovie>
 */
class UserMovieRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly EntityManagerInterface $em)
    {
        parent::__construct($registry, UserMovie::class);
    }

    public function save(UserMovie $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(UserMovie $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function lastViewedMovies(int $userId, int $limit = 20): array
    {
        $sql = "SELECT um.`id` as userMovieId,
                    m.`title` as title,
                    m.`poster_path` as posterPath,
                    m.`release_date` as releaseDate,
                    um.`last_viewed_at` as lastViewedAt,
                    um.`favorite` as favorite,
                    um.`rating`,
                    um.`viewed`
                FROM `movie` m
                    INNER JOIN `user_movie` um ON um.`movie_id`=m.`id`
                WHERE um.`user_id`=$userId AND um.`last_viewed_at` IS NOT NULL
                ORDER BY um.`last_viewed_at` DESC
                LIMIT $limit OFFSET 0";

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
