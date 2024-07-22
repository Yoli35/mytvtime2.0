<?php

namespace App\Repository;

use App\Entity\Movie;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Movie>
 */
class MovieRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly EntityManagerInterface $em)
    {
        parent::__construct($registry, Movie::class);
    }

    public function save(Movie $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function getMovieCards(User $user, array $filters): array
    {
        $userId = $user->getId();
        $sort = $filters['sort'];
        $order = $filters['order'];
        $page = $filters['page'];
        $perPage = $filters['perPage'];
        $title = $filters['title'];

        $offset = ($page - 1) * $perPage;
        // Sort: name, release date
        $sort = match ($sort) {
            'name' => 'm.title',
            'addedAt' => 'um.created_at',
            default => 'm.release_date'
        };

        if ($title) {
            $sql = "SELECT um.id             as userMovieId,
                           m.title           as title,
                           m.poster_path     as posterPath,
                           m.release_date    as releaseDate,
                           m.runtime         as runtime,
                           um.favorite       as favorite,
                           um.rating         as rating,
                           um.last_viewed_at as lastViewedAt
                    FROM movie m
                             INNER JOIN user_movie um ON m.id = um.movie_id
                    WHERE um.user_id = $userId AND (m.title LIKE '%$title%' OR m.original_title LIKE '%$title%')
                    ORDER BY $sort $order
                    LIMIT $offset, $perPage";
        } else {
            $sql = "SELECT um.id             as userMovieId,
                       m.title           as title,
                       m.poster_path     as posterPath,
                       m.release_date    as releaseDate,
                       m.runtime         as runtime,
                       um.favorite       as favorite,
                       um.rating         as rating,
                       um.last_viewed_at as lastViewedAt
                FROM movie m
                         INNER JOIN user_movie um ON m.id = um.movie_id
                WHERE um.user_id = $userId
                ORDER BY $sort $order
                LIMIT $offset, $perPage";
        }

        return $this->getAll($sql);
    }

    public function countMovieCards(User $user, array $filters): int
    {
        $userId = $user->getId();
        $sort = $filters['sort'];
        $order = $filters['order'];
        $page = $filters['page'];
        $perPage = $filters['perPage'];
        $title = $filters['title'];

        $offset = ($page - 1) * $perPage;
        // Sort: name, release date
        $sort = match ($sort) {
            'name' => 'm.title',
            default => 'm.release_date'
        };

        if ($title) {
            $sql = "SELECT COUNT(*) 
                    FROM movie m
                             INNER JOIN user_movie um ON m.id = um.movie_id
                    WHERE um.user_id = $userId AND m.title LIKE '%$title%'
                    ORDER BY $sort $order
                    LIMIT $offset, $perPage";
        } else {
            $sql = "SELECT COUNT(*) 
                    FROM movie m
                             INNER JOIN user_movie um ON m.id = um.movie_id
                    WHERE um.user_id = $userId
                    ORDER BY $sort $order
                    LIMIT $offset, $perPage";
        }

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
