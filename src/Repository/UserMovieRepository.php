<?php

namespace App\Repository;

use App\Entity\User;
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

    public function searchMoviesByTitle(User $getUser, string $query): array
    {
        $userId = $getUser->getId();
        $sql = "SELECT um.`id` as id,
                    m.`title` as title,
                    m.original_title as original_title,
                    mln.`name` as localized_name,
                    m.`poster_path` as poster_path,
                    m.`release_date` as release_date
                FROM `movie` m
                    INNER JOIN `user_movie` um ON um.`movie_id`=m.`id`
                    LEFT JOIN `movie_localized_name` mln on m.id = mln.movie_id
                WHERE um.`user_id`=$userId AND (m.`title` LIKE '%$query%' OR m.`original_title` LIKE '%$query%' OR mln.`name` LIKE '%$query%')
                ORDER BY m.`title`, m.original_title, mln.`name`
                LIMIT 100 OFFSET 0";

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
