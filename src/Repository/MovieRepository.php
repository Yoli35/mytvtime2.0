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

    public function getMovieCards(User $user, int $page = 1, int $perPage = 20): array
    {
        $userId = $user->getId();
        $offset = ($page - 1) * $perPage;

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
                ORDER BY m.release_date DESC
                LIMIT $offset, $perPage";

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

    public function getOne($sql): mixed
    {
        try {
            return $this->em->getConnection()->fetchOne($sql);
        } catch (Exception) {
            return [];
        }
    }
}
