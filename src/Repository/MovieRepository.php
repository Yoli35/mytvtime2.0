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

    public function movieInfos(User $user): array
    {
        $userId = $user->getId();
        $sql = "SELECT
                    m.tmdb_id as tmdbId,
                    um.favorite as favorite,
                    um.last_viewed_at as lastViewedAt,
                    um.rating as rating
                FROM movie m
                         INNER JOIN user_movie um ON m.id = um.movie_id
                WHERE um.user_id=$userId";

        return $this->getAll($sql);
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

        if (strlen($title)) {
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
        $title = $filters['title'];

        if (strlen($title)) {
            $sql = "SELECT COUNT(*) 
                    FROM movie m
                             INNER JOIN user_movie um ON m.id = um.movie_id
                    WHERE um.user_id = $userId AND m.title LIKE '%$title%'";
        } else {
            $sql = "SELECT COUNT(*) 
                    FROM movie m
                             INNER JOIN user_movie um ON m.id = um.movie_id
                    WHERE um.user_id = $userId";
        }

        return $this->getOneAsInt($sql);
    }

    public function moviesOfTheDayForTwig(User $user, string $day, string $locale = 'fr'): array
    {
        $userId = $user->getId();
        $sql = "SELECT m.`id` as id,
                    m.`title` as name,
                    m.`poster_path` as posterPath,
                    mln.`name` as localizedName,
                    wp.`provider_name` as providerName,
                    wp.`logo_path` as providerLogoPath,
                    um.last_viewed_at as watchAt,
                    IF(mln.name IS NULL, m.title, mln.name) as displayName 
                FROM movie m
                    INNER JOIN `user_movie` um ON um.`movie_id`=m.`id`
                    LEFT JOIN `movie_localized_name` mln ON mln.`movie_id`=m.`id` AND mln.`locale`='$locale'
                    LEFT JOIN `movie_direct_link` mdl ON mdl.`movie_id`=m.`id`
                    LEFT JOIN `watch_provider` wp ON wp.`provider_id`=mdl.`provider_id`
                WHERE um.user_id = $userId
                    AND DATE(m.release_date) = '$day'";

        return $this->getAll($sql);
    }

    public function moviesOfTheIntervalForTwig(User $user, string $start, string $end, string $locale = 'fr'): array
    {
        $userId = $user->getId();
        $sql = "SELECT
                    m.`release_date`                        as airDate,
                    'movie'                                 as type,
                    m.`id`                                  as id,
                    m.`title`                               as name,
                    m.`poster_path`                         as posterPath,
                    mln.`name`                              as localizedName,
                    wp.`provider_name`                                as providerName,
                    wp.`logo_path`                           as providerLogoPath,
                    um.last_viewed_at                       as watchAt,
                    IF(mln.name IS NULL, m.title, mln.name) as displayName 
                FROM movie m
                    INNER JOIN `user_movie` um ON um.`movie_id`=m.`id`
                    LEFT JOIN `movie_localized_name` mln ON mln.`movie_id`=m.`id` AND mln.`locale`='$locale'
                    LEFT JOIN `movie_direct_link` mdl ON mdl.`movie_id`=m.`id`
                    LEFT JOIN `watch_provider` wp ON wp.`provider_id`=mdl.`provider_id`
                WHERE um.user_id = $userId
                    AND DATE(m.release_date) >= '$start'
                    AND DATE(m.release_date) <= '$end'";

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

    public function getOneAsInt($sql): mixed
    {
        try {
            return $this->em->getConnection()->fetchOne($sql);
        } catch (Exception) {
            return [];
        }
    }

    public function getOne($sql): mixed
    {
        try {
            return $this->em->getConnection()->fetchAssociative($sql);
        } catch (Exception) {
            return [];
        }
    }

    public function adminMovies(string $locale, int $page, string $sort, string $order, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT
                    m.id,
                    m.origin_country,
                    m.poster_path,
                    m.release_date,
                    m.runtime,
                    m.status,
                    m.title,
                    m.tmdb_id,
                    mln.name as localized_name,
                    (SELECT CONCAT(wp.`provider_name`, '|', wp.`logo_path`)
                     FROM movie_direct_link mdl
                              LEFT JOIN watch_provider wp ON mdl.provider_id = wp.provider_id
                     WHERE mdl.movie_id = m.id
                     LIMIT 1) as provider
        FROM movie m
                LEFT JOIN movie_localized_name mln ON m.id = mln.movie_id AND mln.locale = '$locale'
                ORDER BY $sort $order
                LIMIT $perPage OFFSET $offset";

        return $this->getAll($sql);
    }

    public function adminMovieById(int $id): array
    {
        $sql = "SELECT *
                FROM movie m
                WHERE m.id=$id";

        return $this->getOne($sql);
    }

    public function movieAdditionalOverviews(int $id): array
    {
        $sql = "SELECT *
                FROM movie_additional_overview mao
                WHERE mao.movie_id=$id";

        return $this->getAll($sql);
    }

    public function movieImagesById(int $id): array
    {
        $sql = "SELECT *
                FROM movie_image mi
                WHERE mi.movie_id=$id";

        return $this->getAll($sql);
    }

    public function movieLocalizedNames(int $id): array
    {
        $sql = "SELECT *
                FROM movie_localized_name mln
                WHERE mln.movie_id=$id";

        return $this->getAll($sql);
    }

    // 'series_localized_overview'
    public function movieLocalizedOverviews(int $id): array
    {
        $sql = "SELECT *
                FROM movie_localized_overview mlo
                WHERE mlo.movie_id=$id";

        return $this->getAll($sql);
    }

    public function movieDirectLinks(int $id): array
    {
        $sql = "SELECT mdl.*,
                       wp.provider_name as provider_name,
                       wp.logo_path as provider_logo
                FROM movie_direct_link mdl
                LEFT JOIN watch_provider wp ON mdl.provider_id = wp.provider_id
                WHERE mdl.movie_id=$id";

        return $this->getAll($sql);
    }
}
