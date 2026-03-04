<?php

namespace App\Repository;

use App\Entity\Movie;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
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
        $params = [
            'userId' => $user->getId(),
        ];
        $types = [
            'userId' => ParameterType::INTEGER,
        ];
        $sql = <<<SQL
            SELECT
                m.tmdb_id         AS tmdbId,
                um.favorite       AS favorite,
                um.last_viewed_at AS lastViewedAt,
                um.rating         AS rating
            FROM movie m
                     INNER JOIN user_movie um ON m.id = um.movie_id
            WHERE um.user_id = :userId
        SQL;

        return $this->getAll($sql, $params, $types);
    }

    public function getMovieCards(User $user, array $filters): array
    {
        $userId = $user->getId();
        $sort = $filters['sort'];
        $order = $filters['order'];
        $page = $filters['page'];
        $limit = 1 * $filters['limit'];
        $title = $filters['title'];

        $offset = ($page - 1) * $limit;
        // Sort: name, release date
        $sort = match ($sort) {
            'name' => 'm.title',
            'addedAt' => 'um.created_at',
            default => 'm.release_date'
        };
        $params = [
            'userId' => $userId,
            'offset' => $offset,
            'limit' => $limit,
        ];
        $types = [
            'userId' => ParameterType::INTEGER,
            'offset' => ParameterType::INTEGER,
            'limit' => ParameterType::INTEGER,
        ];

        if (strlen($title)) {
            $params['title'] = '%' . $title . '%';
            $types['title'] = ParameterType::STRING;
            $sql = <<<SQL
                SELECT um.id             as userMovieId,
                       m.title           as title,
                       m.poster_path     as posterPath,
                       m.release_date    as releaseDate,
                       m.runtime         as runtime,
                       um.favorite       as favorite,
                       um.rating         as rating,
                       um.last_viewed_at as lastViewedAt
                FROM movie m
                INNER JOIN user_movie um ON m.id = um.movie_id
                WHERE um.user_id = :userId AND (m.title LIKE :title OR m.original_title LIKE :title)
                ORDER BY $sort $order
                LIMIT :limit OFFSET :offset
            SQL;

        } else {
            $sql = <<<SQL
                SELECT um.id             as userMovieId,
                       m.title           as title,
                       m.poster_path     as posterPath,
                       m.release_date    as releaseDate,
                       m.runtime         as runtime,
                       um.favorite       as favorite,
                       um.rating         as rating,
                       um.last_viewed_at as lastViewedAt
                FROM movie m
                         INNER JOIN user_movie um ON m.id = um.movie_id
                WHERE um.user_id = :userId
                ORDER BY $sort $order
                LIMIT :limit OFFSET :offset
            SQL;
        }

        return $this->getAll($sql, $params, $types);
    }

    public function countMovieCards(User $user, array $filters): int
    {
        $title = $filters['title'];
        $params = [
            'userId' => $user->getId(),
        ];
        $types = [
            'userId' => ParameterType::INTEGER,
        ];
        if (strlen($title)) {
            $params['title'] = '%' . $title . '%';
            $types['title'] = ParameterType::STRING;
            $sql = <<<SQL
                SELECT COUNT(*) 
                FROM movie m
                    INNER JOIN user_movie um ON m.id = um.movie_id
                WHERE um.user_id = :userId AND (m.title LIKE :title OR m.original_title LIKE :title)
            SQL;
        } else {
            $sql = <<<SQL
                SELECT COUNT(*) 
                FROM movie m
                    INNER JOIN user_movie um ON m.id = um.movie_id
                WHERE um.user_id = :userId
            SQL;
        }

        return $this->getOneAsInt($sql, $params, $types);
    }

    public function moviesOfTheIntervalForTwig(User $user, string $start, string $end, string $locale = 'fr'): array
    {
        $userId = $user->getId();
        $params = [
            'userId' => $userId,
            'start' => $start,
            'end' => $end,
            'locale' => $locale,
        ];
        $types = [
            'userId' => ParameterType::INTEGER,
            'start' => ParameterType::STRING,
            'end' => ParameterType::STRING,
            'locale' => ParameterType::STRING,
        ];
        $sql = <<<SQL
            SELECT
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
                LEFT JOIN `movie_localized_name` mln ON mln.`movie_id`=m.`id` AND mln.`locale`=:locale
                LEFT JOIN `movie_direct_link` mdl ON mdl.`movie_id`=m.`id`
                LEFT JOIN `watch_provider` wp ON wp.`provider_id`=mdl.`provider_id`
            WHERE um.user_id = :userId
                AND DATE(m.release_date) >= :start
                AND DATE(m.release_date) <= :end
        SQL;

        return $this->getAll($sql, $params, $types);
    }

    public function adminMovies(string $locale, int $page, string $sort, string $order, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;
        $params = [
            'locale' => $locale,
            'offset' => $offset,
            'limit' => $limit,
        ];
        $types = [
            'locale' => ParameterType::STRING,
            'offset' => ParameterType::INTEGER,
            'limit' => ParameterType::INTEGER,
        ];
        $sql = <<<SQL
            SELECT
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
                LEFT JOIN movie_localized_name mln ON m.id = mln.movie_id AND mln.locale = :locale
                ORDER BY $sort $order
                LIMIT :limit OFFSET :offset
        SQL;

        return $this->getAll($sql, $params, $types);
    }

    public function adminMovieById(int $id): array
    {
        $params['id'] = $id;
        $types['id'] = ParameterType::INTEGER;
        $sql = <<<SQL
            SELECT *
            FROM movie m
            WHERE m.id=:id
        SQL;

        return $this->getOne($sql, $params, $types);
    }

    public function movieAdditionalOverviews(int $id): array
    {
        $params['id'] = $id;
        $types['id'] = ParameterType::INTEGER;
        $sql = <<<SQL
            SELECT *
                FROM movie_additional_overview mao
                WHERE mao.movie_id=:id
        SQL;

        return $this->getAll($sql, $params, $types);
    }

    public function movieImagesById(int $id): array
    {
        $params['id'] = $id;
        $types['id'] = ParameterType::INTEGER;
        $sql = <<<SQL
            SELECT *
                FROM movie_image mi
                WHERE mi.movie_id=:id
        SQL;

        return $this->getAll($sql, $params, $types);
    }

    public function movieLocalizedNames(int $id): array
    {
        $params['id'] = $id;
        $types['id'] = ParameterType::INTEGER;
        $sql = <<<SQL
            SELECT *
                FROM movie_localized_name mln
                WHERE mln.movie_id=:id
        SQL;

        return $this->getAll($sql, $params, $types);
    }

    // 'series_localized_overview'
    public function movieLocalizedOverviews(int $id): array
    {
        $params['id'] = $id;
        $types['id'] = ParameterType::INTEGER;
        $sql = <<<SQL
            SELECT *
                FROM movie_localized_overview mlo
                WHERE mlo.movie_id=:id
        SQL;

        return $this->getAll($sql, $params, $types);
    }

    public function movieDirectLinks(int $id): array
    {
        $params['id'] = $id;
        $types['id'] = ParameterType::INTEGER;
        $sql = <<<SQL
            SELECT mdl.*,
                       wp.provider_name as provider_name,
                       wp.logo_path as provider_logo
                FROM movie_direct_link mdl
                LEFT JOIN watch_provider wp ON mdl.provider_id = wp.provider_id
                WHERE mdl.movie_id=:id
        SQL;

        return $this->getAll($sql, $params, $types);
    }

    public function getFavoriteMovieCards(User $user): array
    {
        $userId = $user->getId();
        $params['userId'] = $userId;
        $types['id'] = ParameterType::INTEGER;
        $sql = <<<SQL
            SELECT um.id             as userMovieId,
                       m.title           as title,
                       m.poster_path     as posterPath,
                       m.release_date    as releaseDate,
                       m.runtime         as runtime,
                       um.favorite       as favorite,
                       um.rating         as rating,
                       um.last_viewed_at as lastViewedAt
                FROM movie m
                         INNER JOIN user_movie um ON m.id = um.movie_id
                WHERE um.user_id = :userId and um.favorite = 1
                ORDER BY um.last_viewed_at DESC
        SQL;

        return $this->getAll($sql, $params, $types);
    }

    public function getAll($sql, array $params = [], array $types = []): array
    {
        try {
            return $this->em->getConnection()->fetchAllAssociative($sql, $params, $types);
        } catch (Exception) {
            return [];
        }
    }

    public function getOneAsInt($sql, array $params = [], array $types = []): mixed
    {
        try {
            return $this->em->getConnection()->fetchOne($sql, $params, $types);
        } catch (Exception) {
            return [];
        }
    }

    public function getOne($sql, array $params = [], array $types = []): array|false
    {
        try {
            return $this->em->getConnection()->fetchAssociative($sql, $params, $types);
        } catch (Exception) {
            return [];
        }
    }
}
