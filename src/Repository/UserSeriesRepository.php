<?php /** @noinspection PhpMultipleClassDeclarationsInspection */

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserSeries;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserSeries>
 *
 * @method UserSeries|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserSeries|null findOneBy(array $criteria, array $orderBy = null)
 * @method UserSeries[]    findAll()
 * @method UserSeries[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserSeriesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly EntityManagerInterface $em)
    {
        parent::__construct($registry, UserSeries::class);
    }

    public function save(UserSeries $userSeries, bool $flush = false): void
    {
        $this->em->persist($userSeries);

        if ($flush) {
            $this->em->flush();
        }
    }

    public function flush(): void
    {
        $this->em->flush();
    }

    public function userSeriesTMDBIds(User $user): array
    {
        $userId = $user->getId();
        $sql = <<<SQL
                SELECT s.tmdb_id AS id
                FROM series s
                         INNER JOIN user_series us ON s.id = us.series_id
                WHERE us.user_id=:userId
            SQL;

        return $this->getAll($sql, ["userId" => $userId], ['userId' => ParameterType::INTEGER]);
    }

    public function getUserSeries(User $user, string $locale, int $page = 1, int $limit = 20): array
    {
        $params = [
            "userId" => $user->getId(),
            "locale" => $locale,
            "offset" => (($page - 1) * $limit),
            "limit" => $limit,
        ];
        $types = [
            "userId" => ParameterType::INTEGER,
            "locale" => ParameterType::STRING,
            "offset" => ParameterType::INTEGER,
            "limit" => ParameterType::INTEGER,
        ];
        $sql = <<<SQL
                SELECT 
                    s.`id`                              AS id,
                    s.`name`                            AS name,
                    s.`poster_path`                     AS poster_path, 
                    s.`tmdb_id`                         AS tmdbId,
                    s.`slug`                            AS slug,
                    s.status                            AS status,
                    (s.first_air_date <= NOW())         AS released,
                    us.`user_id`                        AS user_id, 
                    us.`progress`                       AS progress,
                    us.`favorite`                       AS favorite, 
                    sln.`name`                          AS localized_name,
                    sln.`slug`                          AS localized_slug,
                   (SELECT COUNT(*)
                          FROM `user_list_series` uls
                              INNER JOIN `user_list` ul ON ul.`user_id`=:userId AND uls.`user_list_id`=ul.`id`
                          WHERE uls.`series_id`=s.`id`) AS is_series_in_list 
                FROM `user_series` us 
                    INNER JOIN `series` s ON s.`id` = us.`series_id` 
                    LEFT JOIN `series_localized_name` sln ON s.`id` = sln.`series_id` AND sln.locale = :locale 
                WHERE us.user_id = :userId 
                ORDER BY s.`first_air_date` DESC 
                LIMIT :limit OFFSET :offset
            SQL;

        return $this->getAll($sql, $params, $types);
    }

    public function getUserSeriesStatus(User $user): array
    {
        $sql = <<<SQL
                SELECT s.`status`, COUNT(s.`status`) AS count
                FROM `series` s
                    INNER JOIN `user_series` us ON s.id = us.series_id
                WHERE us.user_id = :userId
                GROUP BY s.`status`
            SQL;

        return $this->getAll($sql, ["userId" => $user->getId()], ['status' => ParameterType::INTEGER]);
    }

    public function seriesToStart(User $user, string $locale, string $sort, string $order): array
    {
        $userId = $user->getId();
        match ($sort) {
            'addedAt' => $sort = 'us.`added_at`',
            default => $sort = 's.`first_air_date`'
        };
        $params = ['userId' => $userId, 'locale' => $locale, 'sort' => $sort];
        $types = [
            'userId' => ParameterType::INTEGER,
            'locale' => ParameterType::STRING,
            'sort' => ParameterType::STRING,
        ];
        $sql = <<<SQL
                SELECT s.id                                              AS id,
                       s.tmdb_id                                         AS tmdb_id,
                       s.`name`                                          AS name,
                       sln.`name`	                                     AS sln_name,
                       IF(sln.`slug` IS NOT NULL, sln.`slug`, s.`slug`)  AS slug,
                       s.`poster_path`                                   AS poster_path,
                       s.`first_air_date`                                AS final_air_date,
                       us.`added_at`                                     AS added_at,
                       swl.`name`	                                     AS link_name,
                       wp.`logo_path`                                    AS provider_logo_path,
                       wp.`provider_name`                                AS provider_name,
                       (SELECT COUNT(*)
                            FROM `user_episode` ue
                            WHERE ue.`user_series_id`=us.id)             AS number_of_episode,
                       (SELECT COUNT(*)
                       		  FROM `user_list_series` uls
                       		      INNER JOIN `user_list` ul ON ul.`user_id` = :userId AND uls.`user_list_id`=ul.`id`
                       		  WHERE uls.`series_id`=s.`id`)              AS is_series_in_list
                FROM `series` s
                INNER JOIN `user_series` us ON us.series_id=s.id AND us.`progress`=0
                LEFT JOIN `series_localized_name` sln ON sln.`series_id`=s.id AND sln.`locale` = :locale
                LEFT JOIN `series_watch_link` swl ON swl.`series_id`=s.id
                LEFT JOIN `watch_provider` wp ON wp.`provider_id`=swl.`provider_id`
                WHERE s.`first_air_date` <= NOW() AND us.user_id = :userId
                ORDER BY :sort $order
            SQL;

        return $this->getAll($sql, $params, $types);
    }

    public function upComingSeries(User $user, string $locale, string $sort): array
    {
        $userId = $user->getId();
        match ($sort) {
            'addedAt' => $sort = 'us.`added_at`',
            default => $sort = 's.`first_air_date`'
        };
        $params = ['userId' => $userId, 'locale' => $locale, 'sort' => $sort];
        $types = [
            'userId' => ParameterType::INTEGER,
            'locale' => ParameterType::STRING,
            'sort' => ParameterType::STRING,
        ];

        $sql = <<<SQL
                SELECT s.id                                              AS id,
                       s.tmdb_id                                         AS tmdb_id,
                       s.`name`                                          AS name,
                       sln.`name`	                                     AS sln_name,
                       IF(sln.`slug` IS NOT NULL, sln.`slug`, s.`slug`)  AS slug,
                       s.`poster_path`                                   AS poster_path,
                       s.`first_air_date`                                AS final_air_date
                FROM `series` s
                INNER JOIN `user_series` us ON us.series_id=s.id
                LEFT JOIN `series_localized_name` sln ON sln.`series_id`=s.id AND sln.`locale` = :locale
                WHERE (s.`first_air_date` > NOW() OR s.first_air_date IS NULL) AND us.user_id = :userId
                ORDER BY :sort DESC
            SQL;

        return $this->getAll($sql, $params, $types);
    }

    public function rankingByVote(User $user, string $locale): array
    {
        $params = ['userId' => $user->getId(), 'locale' => $locale];
        $types = [
            'userId' => ParameterType::INTEGER,
            'locale' => ParameterType::STRING,
        ];

        $sql = <<<SQL
                SELECT 
                       s.id                                                 AS id,
                       s.tmdb_id                                            AS tmdb_id,
                       s.`name`                                             AS name,
                       sln.`name`	                                        AS sln_name,
                       IF(sln.`slug` IS NOT NULL, sln.`slug`, s.`slug`)     AS slug,
                       s.`poster_path`                                      AS poster_path,
                       s.`first_air_date`                                   AS final_air_date,
                       (SELECT AVG(IF(ue.`vote`, ue.`vote`, 0))
                                FROM `user_episode` ue
                                WHERE ue.`user_series_id`=us.`id`
                                  AND ue.`vote` > 0
                                  AND ue.`previous_occurrence_id` IS NULL)  AS average_vote,
                       (SELECT COUNT(*)
                               FROM `user_episode` ue
                           WHERE ue.`user_series_id`=us.`id`
                             AND ue.`vote`>0
                             AND ue.`previous_occurrence_id` IS NULL)       AS episode_count
                FROM `user_series` us
                    INNER JOIN `series` s ON us.`series_id`=s.`id`
                    LEFT JOIN `series_localized_name` sln ON sln.`series_id`=s.`id` AND sln.`locale` = :locale
                WHERE us.`user_id` = :userId
                ORDER BY average_vote DESC
            SQL;

        return $this->getAll($sql, $params, $types);
    }

    public function favoriteSeries(User $user, string $locale): array
    {
        $params = ['userId' => $user->getId(), 'locale' => $locale];
        $types = [
            'userId' => ParameterType::INTEGER,
            'locale' => ParameterType::STRING,
        ];

        $sql = <<<SQL
                SELECT 
                       s.id                                             AS id,
                       s.tmdb_id                                        AS tmdb_id,
                       s.`name`                                         AS name,
                       sln.`name`	                                    AS sln_name,
                       IF(sln.`slug` IS NOT NULL, sln.`slug`, s.`slug`) AS slug,
                       IF(sln.`slug` IS NOT NULL, sln.`slug`, s.`slug`) AS slug,
                       s.`poster_path`                                  AS poster_path,
                       s.`backdrop_path`                                AS backdrop_path,
                       s.overview                                       AS overview,
                       s.`first_air_date`                               AS final_air_date
                FROM `user_series` us
                    INNER JOIN `series` s ON us.`series_id`=s.`id`
                    LEFT JOIN `series_localized_name` sln ON sln.`series_id`=s.`id` AND sln.`locale` = :locale
                WHERE us.`user_id`=:userId AND us.`favorite`=1
                ORDER BY final_air_date DESC
            SQL;

        return $this->getAll($sql, $params, $types);
    }

    public function seriesNotSeenInAWhile(User $user, string $locale, string $date): array
    {
        $params = ['userId' => $user->getId(), 'locale' => $locale, 'date' => $date];
        $types = [
            'userId' => ParameterType::INTEGER,
            'locale' => ParameterType::STRING,
            'date' => ParameterType::STRING,
        ];

        $sql = <<<SQL
                SELECT
                    s.id                                                                      AS `id`,
                    s.tmdb_id                                                                 AS `tmdb_id`,
                    IF(sln.`name` IS NOT NULL, CONCAT(sln.`name`, ' - ', s.`name`), s.`name`) AS `name`,
                    IF(sln.`slug` IS NOT NULL, sln.`slug`, s.`slug`)                          AS `slug`,
                    s.`poster_path`                                                           AS `poster_path`,
                    us.`last_season`                                                          AS `last_season`,
                    us.`last_episode`                                                         AS `last_episode`,
                    us.`last_watch_at`                                                        AS `last_viewed_at`
                FROM `user_series` us
                LEFT JOIN `series` s ON us.`series_id`=s.`id`
                LEFT JOIN `series_localized_name` sln ON sln.`series_id`=s.`id` AND sln.locale = :locale
                WHERE us.`user_id` = :userId
                    AND us.`progress` > 0
                    AND us.`progress` < 100
                    AND us.`last_watch_at` <= :date
                ORDER BY us.`last_watch_at` DESC
            SQL;

        return $this->getAll($sql, $params, $types);
    }

    public function getAllSeries(
        User  $user,
        array $localisation,
        array $filters,
        bool  $includeFirstEpisode = false
    ): array
    {
        $page = intval($filters['page'] ?? 1);
        $limit = intval($filters['limit'] ?? 20);
        $sort = $filters['sort'] ?? 'firstAirDate';
        $order = $filters['order'] ?? 'ASC';
        $network = $filters['network'];

        $sort = match ($sort) {
            'lastWatched' => 'us.`last_watch_at`',
            'episodeAirDate' => 'lue.`air_date`',
            'name' => 's.`name`',
            'addedAt' => 'us.`added_at`',
            'finalAirDate' => 'IF(sbd.id IS NULL, nue.`air_date`, sbd.`date`)',
            default => 's.`first_air_date`',
        };
        if ($network !== 'all') {
//            $params['network'] = intval($network);
//            $types['network'] = ParameterType::INTEGER;
            $innerJoin = " INNER JOIN series_network sn ON sn.`network_id` = $network AND sn.`series_id` = us.`series_id` ";
        } else {
            $innerJoin = '';
        }

        $params = [
            'userId' => $user->getId(),
            'locale' => $localisation['locale'],
            'offset' => ($page - 1) * $limit,
            'limit' => $limit,
        ];
        $types = [
            'userId' => ParameterType::INTEGER,
            'locale' => ParameterType::STRING,
            'offset' => ParameterType::INTEGER,
            'limit' => ParameterType::INTEGER,
        ];

        // Si on inclut le premier épisode, on fait un LEFT JOIN pour récupérer les séries à commencer
        // Sinon, on fait un INNER JOIN pour ne récupérer que les séries commencées
        // Cela permet de filtrer les séries en fonction de la progression de l'utilisateur
        // et d'éviter d'afficher des séries que l'utilisateur n'a pas encore commencé à regarder.
        // Menu Séries en cours ($includeFirstEpisode = false) : afficher les séries commencées
        // Bouton "Que regarder ensuite" ($includeFirstEpisode = true) : afficher toutes les séries.

        $lastEpisodeInnerJoin = !$includeFirstEpisode ? " INNER JOIN `user_episode` lue ON lue.`id`=us.`last_user_episode_id` " : " LEFT JOIN `user_episode` lue ON lue.`id`=us.`last_user_episode_id` ";

        $sql = <<<SQL
                SELECT
                    s.`id`                                         AS id,
                    s.`name`                                       AS name,
                    s.`poster_path`                                AS poster_path, 
                    s.`tmdb_id`                                    AS tmdb_id,
                    s.`slug`                                       AS slug,
                    s.`status`                                     AS status,
                    (s.first_air_date <= NOW())                    AS released,
                    us.`user_id`                                   AS user_id, 
                    us.`added_at`                                  AS added_at,
                    us.`progress`                                  AS progress,
                    us.`favorite`                                  AS favorite, 
                    sln.`name`                                     AS sln_name,
                    sln.`slug`                                     AS sln_slug,
                    nue.air_date                                   AS next_episode_air_date,
                    nue.season_number                              AS next_episode_season_number,
                    nue.episode_number                             AS next_episode_episode_number,
                    IF(sbd.id IS NULL, nue.`air_date`, sbd.`date`) AS final_air_date,
                    (SELECT COUNT(*)
                        FROM user_episode ue2
                        WHERE ue2.user_series_id = us.id
                          AND ue2.season_number > 0
                          AND ue2.`watch_at` IS NULL)               AS remainingEpisodes,
                    (SELECT COUNT(*)
                        FROM `user_list_series` uls
                            INNER JOIN `user_list` ul ON ul.`user_id` = :userId AND uls.`user_list_id`=ul.`id`
                        WHERE uls.`series_id`=s.`id`)               AS is_series_in_list
                FROM `user_series` us
                    $lastEpisodeInnerJoin
                    INNER JOIN `user_episode` nue ON nue.`id`=us.`next_user_episode_id` AND nue.`air_date` IS NOT NULL
                    $innerJoin
                    LEFT JOIN `series` s ON s.`id`=us.`series_id`
                    LEFT JOIN `series_localized_name` sln ON sln.`series_id`=s.`id` AND sln.`locale` = :locale 
                    LEFT JOIN series_broadcast_schedule sbs ON s.id = sbs.series_id AND sbs.season_number = nue.season_number AND IF(sbs.multi_part, nue.episode_number BETWEEN sbs.season_part_first_episode AND (sbs.season_part_first_episode + sbs.season_part_episode_count - 1), 1)
                    LEFT JOIN `series_broadcast_date` sbd ON sbd.series_broadcast_schedule_id = sbs.id AND sbd.`episode_id`=nue.`episode_id`
                WHERE us.`user_id` = :userId
                    AND IF(sbd.`date`, DATE(sbd.`date`)<=NOW(), nue.`air_date`<=NOW())
                    AND nue.`season_number`>0
                ORDER BY $sort $order
                LIMIT :limit OFFSET :offset
            SQL;

        return $this->getAll($sql, $params, $types);
    }


    public function countAllSeries(
        User  $user,
        array $filters,
        bool  $includeFirstEpisode = false): int
    {
        $network = $filters['network'];
        if ($network !== 'all') {
            $params['network'] = $network;
            $types['network'] = ParameterType::STRING;
            $innerJoin = " INNER JOIN series_network sn ON sn.`network_id` = $network AND sn.`series_id` = us.`series_id` ";
        } else {
            $innerJoin = '';
        }
        $params['userId'] = $user->getId();
        $types['userId'] = ParameterType::STRING;

        $lastEpisodeInnerJoin = !$includeFirstEpisode ? " INNER JOIN `user_episode` lue ON lue.`id`=us.`last_user_episode_id` " : " LEFT JOIN `user_episode` lue ON lue.`id`=us.`last_user_episode_id` ";

        $sql = <<<SQL
                SELECT COUNT(*)
                FROM `user_series` us
                    $lastEpisodeInnerJoin
                    INNER JOIN `user_episode` nue ON nue.`id`=us.`next_user_episode_id` AND nue.`air_date` IS NOT NULL
                    $innerJoin
                    LEFT JOIN `series` s ON s.`id`=us.`series_id`
                    LEFT JOIN series_broadcast_schedule sbs ON s.id = sbs.series_id AND sbs.season_number = nue.season_number AND IF(sbs.multi_part, nue.episode_number BETWEEN sbs.season_part_first_episode AND (sbs.season_part_first_episode + sbs.season_part_episode_count - 1), 1)
                    LEFT JOIN `series_broadcast_date` sbd ON sbd.series_broadcast_schedule_id = sbs.id AND sbd.`episode_id`=nue.`episode_id`
                WHERE us.`user_id` = :userId
                    AND IF(sbd.`date`, DATE(sbd.`date`)<=NOW(), nue.`air_date`<=NOW())
                    AND nue.`season_number`>0
            SQL;
        $count = $this->getOne($sql, $params, $types);

        return $count ?: 0;
    }

    public function searchSeries(User $user, mixed $query, string $locale): array
    {
        $params = [
            'userId' => $user->getId(),
            'query' => "%$query%",
            'locale' => $locale,
        ];
        $types = [
            'userId' => ParameterType::INTEGER,
            'query' => ParameterType::STRING,
            'locale' => ParameterType::STRING,
        ];
        $sql = <<<SQL
                SELECT s.`id`                                     AS id,
                       s.`tmdb_id`                                AS tmdb_id,
                       s.`poster_path`                            AS poster_path,
                       IF(sln.name IS NOT NULL, sln.name, s.name) AS display_name,
                       IF(sln.name IS NOT NULL, sln.slug, s.slug) AS display_slug
                FROM `user_series` us
                         INNER JOIN `series` s ON s.`id` = us.`series_id`
                         LEFT JOIN `series_localized_name` sln ON s.`id` = sln.`series_id` AND sln.locale = :locale
                WHERE us.user_id = :userId
                  AND (s.name LIKE :query OR s.original_name LIKE :query OR sln.name LIKE :query)
                ORDER BY s.`first_air_date` DESC
                LIMIT 20
            SQL;

        return $this->getAll($sql, $params, $types);
    }

    public function seriesByCountry(User $user, string $country, string $locale): array
    {
        $userId = $user->getId();
        $params = [
            'userId' => $userId,
            'country' => "%$country%",
            'locale' => $locale,
        ];
        $types = [
            'userId' => ParameterType::INTEGER,
            'country' => ParameterType::STRING,
            'locale' => ParameterType::STRING,
        ];

        $sql = <<<SQL
                SELECT s.id                                                                      AS id,
                   s.tmdb_id                                                                 AS tmdb_id,
                   s.`name`                                                                  AS name,
                   sln.`name`                                                                AS sln_name,
                   IF(sln.`slug` IS NOT NULL, sln.`slug`, s.`slug`)                          AS slug,
                   s.`poster_path`                                                           AS poster_path,
                   s.`first_air_date`                                                        AS final_air_date,
                   us.`progress`                                                             AS progress,
                   s.first_air_date <= NOW()                                                 AS released,
                   s.status                                                                  AS status,
                   (SELECT count(*)
                    FROM user_episode cue
                        LEFT JOIN series_broadcast_schedule sbs ON s.id = sbs.series_id AND sbs.season_number = cue.season_number AND IF(sbs.multi_part, cue.episode_number BETWEEN sbs.season_part_first_episode AND (sbs.season_part_first_episode + sbs.season_part_episode_count - 1), 1)
                        LEFT JOIN series_broadcast_date sbd ON sbd.series_broadcast_schedule_id = sbs.id AND sbd.episode_id = cue.episode_id
                    WHERE cue.user_series_id = us.id
                      AND cue.season_number > 0
                      AND IF(sbs.`override`, DATE(sbd.date) <= CURDATE(), cue.air_date <= CURDATE())
                      AND cue.watch_at IS NOT NULL)                                          AS watched_aired_episode_count,
                   (SELECT count(*)
                    FROM user_episode cue
                        LEFT JOIN series_broadcast_schedule sbs ON s.id = sbs.series_id AND sbs.season_number = cue.season_number AND IF(sbs.multi_part, cue.episode_number BETWEEN sbs.season_part_first_episode AND (sbs.season_part_first_episode + sbs.season_part_episode_count - 1), 1)
                        LEFT JOIN series_broadcast_date sbd ON sbd.series_broadcast_schedule_id = sbs.id AND sbd.episode_id = cue.episode_id
                    WHERE cue.user_series_id = us.id
                      AND cue.season_number > 0
                      AND IF(sbs.`override`, DATE(sbd.date) <= CURDATE(), cue.air_date <= CURDATE())
                    )                                                                        AS aired_episode_count,
                   (SELECT COUNT(*)
                        FROM `user_episode` ue
                        WHERE ue.`user_series_id`=us.id)                                     AS number_of_episode,
                   (SELECT CONCAT(ue.`season_number`, '/',ue.`episode_number`)
                        FROM `user_episode` ue
                        WHERE ue.`user_series_id`=us.id AND ue.`season_number`>0 AND ue.`watch_at` IS NULL
                        ORDER BY ue.`episode_number` LIMIT 1)                                AS episode,
                   (SELECT COUNT(*)
                          FROM `user_list_series` uls
                              INNER JOIN `user_list` ul ON ul.`user_id` = :userId AND uls.`user_list_id`=ul.`id`
                          WHERE uls.`series_id`=s.`id`)                                      AS is_series_in_list
                FROM `series` s
                    INNER JOIN `user_series` us ON us.user_id = :userId AND us.series_id=s.id
                    LEFT JOIN `series_localized_name` sln ON sln.`series_id`=s.id AND sln.`locale` = :locale
                WHERE s.origin_country LIKE :country
                ORDER BY s.`first_air_date` DESC
            SQL;

        return $this->getAll($sql, $params, $types);
    }

    public function getUserSeriesCountries(User $user): array
    {
        $userId = $user->getId();

        $sql = <<<SQL
                SELECT s.`origin_country`
                FROM user_series us
                         INNER JOIN `series` s ON s.`id` = us.`series_id`
                WHERE us.`user_id`=:userId
                GROUP BY s.`origin_country`
                ORDER BY s.`origin_country`
            SQL;

        return $this->getAll($sql, ['userId' => $userId], ['userId' => ParameterType::INTEGER]);
    }

    public function getSeriesAround(int $userId, int $userSeriesId, string $locale): array
    {
        $params = [
            'userId' => $userId,
            'userSeriesId' => $userSeriesId,
            'locale' => $locale,
        ];
        $types = [
            'userId' => ParameterType::INTEGER,
            'userSeriesId' => ParameterType::INTEGER,
            'locale' => ParameterType::STRING,
        ];

        $sql = <<<SQL
                SELECT
                    s.`id` AS id,
                    IF(sln.`id`, sln.`name`, s.`name`) AS name,
                    IF(sln.`id`, sln.`slug`, s.`slug`) AS slug,
                    s.`poster_path` AS poster_path,
                    us.progress AS progress
                FROM `user_series` us
                    LEFT JOIN `series` s ON s.`id`=us.`series_id`
                    LEFT JOIN `series_localized_name` sln ON sln.`series_id`=s.`id` AND sln.`locale`=:locale
                WHERE us.`user_id`=:userId
                  AND (us.`id`=(
                                SELECT us1.`id` AS previoud_id
                                FROM `user_series` us1
                                WHERE us1.`id`<$userSeriesId AND us1.`user_id` = :userId
                                ORDER BY id DESC
                                LIMIT 1
                            )
                  OR us.`id`=(
                                SELECT us2.`id` AS previoud_id
                                FROM `user_series` us2
                                WHERE us2.`id`>$userSeriesId AND us2.`user_id` = :userId
                                ORDER BY id
                                LIMIT 1
                            ))
            SQL;

        return $this->getAll($sql, $params, $types);
    }

    public function getFirstSeries(int $userId, string $locale): array
    {
        $params = [
            'userId' => $userId,
            'locale' => $locale,
        ];
        $types = [
            'userId' => ParameterType::INTEGER,
            'locale' => ParameterType::STRING,
        ];

        $sql = <<<SQL
                SELECT
                    s.`id` AS id,
                    IF(sln.`id`, sln.`name`, s.`name`) AS name,
                    IF(sln.`id`, sln.`slug`, s.`slug`) AS slug,
                    s.`poster_path` AS poster_path,
                    us.progress AS progress
                FROM `user_series` us
                    LEFT JOIN `series` s ON s.`id`=us.`series_id`
                    LEFT JOIN `series_localized_name` sln ON sln.`series_id`=s.`id` AND sln.`locale` = :locale
                WHERE us.`user_id` = :userId
                ORDER BY us.`id` LIMIT 1
            SQL;

        return $this->getAll($sql, $params, $types);
    }

    public function getLastSeries(int $userId, string $locale): array
    {
        $params = [
            'userId' => $userId,
            'locale' => $locale,
        ];
        $types = [
            'userId' => ParameterType::INTEGER,
            'locale' => ParameterType::STRING,
        ];

        $sql = <<<SQL
                SELECT
                    s.`id` AS id,
                    IF(sln.`id`, sln.`name`, s.`name`) AS name,
                    IF(sln.`id`, sln.`slug`, s.`slug`) AS slug,
                    s.`poster_path` AS poster_path,
                    us.progress AS progress
                FROM `user_series` us
                    LEFT JOIN `series` s ON s.`id`=us.`series_id`
                    LEFT JOIN `series_localized_name` sln ON sln.`series_id`=s.`id` AND sln.`locale` = :locale
                WHERE us.`user_id` = :userId
                ORDER BY us.`id` DESC LIMIT 1
            SQL;

        return $this->getAll($sql, $params, $types);
    }

    public function getAll(string $sql, array $params = [], array $types = []): array
    {
        try {
            return $this->em->getConnection()->fetchAllAssociative($sql, $params, $types);
        } catch (Exception) {
            return [];
        }
    }

    public function getOne($sql, array $params = [], array $types = []): mixed
    {
        try {
            return $this->em->getConnection()->fetchOne($sql, $params, $types);
        } catch (Exception) {
            return [];
        }
    }

    public function remove(?UserSeries $userSeries): void
    {
        if ($userSeries) {
            $this->em->remove($userSeries);
            $this->em->flush();
        }
    }
}
