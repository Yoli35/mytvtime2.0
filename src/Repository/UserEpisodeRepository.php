<?php /** @noinspection PhpMultipleClassDeclarationsInspection */

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserEpisode;
use App\Entity\UserSeries;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface as MonologLogger;

/**
 * @extends ServiceEntityRepository<UserEpisode>
 *
 * @method UserEpisode|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserEpisode|null findOneBy(array $criteria, array $orderBy = null)
 * @method UserEpisode[]    findAll()
 * @method UserEpisode[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserEpisodeRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry                         $registry,
        private readonly EntityManagerInterface $em,
        private readonly MonologLogger          $logger,
    )
    {
        parent::__construct($registry, UserEpisode::class);
    }

    public function save(UserEpisode $userEpisode, bool $flush = false): void
    {
        $this->em->persist($userEpisode);

        if ($flush) {
            $this->em->flush();
        }
    }

    public function flush(): void
    {
        $this->em->flush();
    }

    public function remove(UserEpisode $userEpisode): void
    {
        $this->em->remove($userEpisode);
        $this->em->flush();
    }

    public function getUserEpisodeViews(int $userId, int $episodeId): array
    {
        $params = [
            'userId' => $userId,
            'episodeId' => $episodeId,
        ];
        $types = [
            'userId' => ParameterType::INTEGER,
            'episodeId' => ParameterType::INTEGER,
        ];
        $sql = <<<SQL
            SELECT ue.id AS id,
                   ue.watch_at AS watch_at
            FROM user_episode ue 
            WHERE ue.user_id = $userId 
              AND ue.episode_id = $episodeId
            ORDER BY ue.id
        SQL;

        return $this->getAll($sql, $params, $types);
    }

    public function lastViewedEpisodeId(User $user): int
    {
        $params = [
            'userId' => $user->getId(),
        ];
        $types = [
            'userId' => ParameterType::INTEGER,
        ];
        $sql = <<<SQL
            SELECT ue.`episode_id`
            FROM `user_episode` ue
            WHERE ue.`user_id` = :userId AND ue.`watch_at` IS NOT NULL
            ORDER BY ue.`watch_at` DESC
            LIMIT 1
        SQL;
        return $this->getOne($sql, $params, $types);
    }

    /*public function isFullyReleased(UserSeries $userSeries): int
    {
        $params = [
            'userId' => $userSeries->getUser()->getId(),
            'userSeriesId' => $userSeries->getId(),
        ];
        $types = [
            'userId' => ParameterType::INTEGER,
            'userSeriesId' => ParameterType::INTEGER,
        ];
        $sql = <<<SQL
            SELECT ue.`air_date` <= NOW()
            FROM `user_episode` ue
            WHERE ue.`user_id` = :userId AND ue.`user_series_id` = :userSeriesId
            ORDER BY ue.`air_date` DESC LIMIT 1
        SQL;

        return $this->getOne($sql, $params, $types) ?? 0;
    }*/

    public function lastAddedSeries(User $user, string $locale, int $page, int $limit): array
    {
        $params = [
            'userId' => $user->getId(),
            'locale' => $locale,
            'limit' => $limit,
            'offset' => $page * $limit - $limit,
        ];
        $types = [
            'userId' => ParameterType::INTEGER,
            'locale' => ParameterType::STRING,
            'limit' => ParameterType::INTEGER,
            'offset' => ParameterType::INTEGER,
        ];
        $sql = <<<SQL
            SELECT DISTINCT 
                s.`id`                               AS id,
                s.`name`                             AS name,
                s.`poster_path`                      AS posterPath, 
                s.`slug`                             AS slug,
                s.`status`                           AS status,
                (s.first_air_date <= NOW())          AS released,
                s.`tmdb_id`                          AS tmdbId,
                sln.`name`                           AS localizedName, 
                sln.`slug`                           AS localizedSlug, 
                us.`favorite`                        AS favorite,
                us.`last_episode`                    AS episodeNumber,
                us.`last_season`                     AS seasonNumber, 
                us.`progress`                        AS progress,
                us.`added_at`                        AS addedAt,
                (SELECT COUNT(*)
                       FROM `user_list_series` uls
                           INNER JOIN `user_list` ul ON ul.`user_id` = :userId AND uls.`user_list_id`=ul.`id`
                       WHERE uls.`series_id`=s.`id`) AS is_series_in_list
            FROM `user_series` us 
            INNER JOIN `series` s ON s.`id` = us.`series_id` 
            LEFT JOIN `series_localized_name` sln ON sln.`series_id`=s.`id` AND sln.`locale` = :locale 
            WHERE us.`user_id` = :userId 
            ORDER BY us.`added_at` DESC 
            LIMIT :limit OFFSET :offset
        SQL;

        return $this->getAll($sql, $params, $types);
    }

    public function historySeries(User $user, string $locale, int $page, int $limit): array
    {
        $params = [
            'userId' => $user->getId(),
            'locale' => $locale,
            'limit' => $limit,
            'offset' => $page * $limit - $limit,
        ];
        $types = [
            'userId' => ParameterType::INTEGER,
            'locale' => ParameterType::STRING,
            'limit' => ParameterType::INTEGER,
            'offset' => ParameterType::INTEGER,
        ];
        $sql = <<<SQL
            SELECT DISTINCT 
                   s.id                            AS id,
                   s.tmdb_id                       AS tmdbId,
                   s.`name`                        AS name,
                   s.`slug`                        AS slug,
                   s.status                        AS status,
                   sln.`name`                      AS localizedName,
                   sln.`slug`                      AS localizedSlug,
                   s.`poster_path`                 AS posterPath,
                   us.`favorite`                   AS favorite,
                   us.`progress`                   AS progress,
                   us.`last_episode`               AS episodeNumber,
                   us.`last_season`                AS seasonNumber,
                   us.`last_watch_at`              AS lastWatchAt,
                   (SELECT count(*)
                    FROM user_episode ue
                        LEFT JOIN series_broadcast_schedule sbs ON s.id = sbs.series_id AND IF(sbs.multi_part, ue.episode_number BETWEEN sbs.season_part_first_episode AND (sbs.season_part_first_episode + sbs.season_part_episode_count - 1), 1)
                        LEFT JOIN series_broadcast_date sbd ON sbd.series_broadcast_schedule_id = sbs.id AND sbd.episode_id = ue.episode_id
                    WHERE ue.user_series_id = us.id
                      AND ue.season_number > 0
                      AND IF(sbd.id, DATE(sbd.date) <= CURDATE(), ue.air_date <= CURDATE())
                      AND ue.watch_at IS NOT NULL
                   )                               AS watched_aired_episode_count,
                   (SELECT count(*)
                    FROM user_episode ue
                        LEFT JOIN series_broadcast_schedule sbs ON s.id = sbs.series_id AND IF(sbs.multi_part, ue.episode_number BETWEEN sbs.season_part_first_episode AND (sbs.season_part_first_episode + sbs.season_part_episode_count - 1), 1)
                        LEFT JOIN series_broadcast_date sbd ON sbd.series_broadcast_schedule_id = sbs.id AND sbd.episode_id = ue.episode_id
                    WHERE ue.user_series_id = us.id
                      AND ue.season_number > 0
                      AND IF(sbd.id, DATE(sbd.date) <= CURDATE(), ue.air_date <= CURDATE())
                   )                               AS aired_episode_count,
                   (SELECT COUNT(*)
                          FROM `user_list_series` uls
                              INNER JOIN `user_list` ul ON ul.`user_id` = :userId AND uls.`user_list_id`=ul.`id`
                          WHERE uls.`series_id`=s.`id`) AS is_series_in_list
            FROM `user_series` us
                     INNER JOIN `series` s ON s.`id` = us.`series_id`
                     LEFT JOIN `series_localized_name` sln ON sln.`series_id` = s.`id` AND sln.`locale` = :locale
            WHERE us.`user_id` = :userId
              AND us.`last_watch_at` IS NOT NULL
            ORDER BY us.`last_watch_at` DESC
            LIMIT :limit OFFSET :offset
        SQL;

        return $this->getAll($sql, $params, $types);
    }

    public function seriesHistoryForTwig(User $user, string $locale, string $list, int $page, int $limit): array
    {
        $params = [
            'userId' => $user->getId(),
            'locale' => $locale,
            'offset' => $page * $limit - $limit,
            'limit' => $limit,
        ];
        $types = [
            'userId' => ParameterType::INTEGER,
            'locale' => ParameterType::STRING,
            'limit' => ParameterType::INTEGER,
            'offset' => ParameterType::INTEGER,
        ];
        $sql = null;
        if ($list == 'series') {
            $sql = <<<SQL
                SELECT s.id                            AS id,
                       ue.episode_id                   AS episodeId,
                       s.`poster_path`                 AS posterPath,
                       us.`last_episode`               AS episodeNumber,
                       us.`last_season`                AS seasonNumber,
                       us.last_watch_at                AS lastWatchAt,
                       us.progress                     AS progress,
                       wp.logo_path                    AS providerLogoPath,
                       wp.provider_name                AS providerName,
                       d.svg                           AS deviceSvg,
                       ue.vote                         AS vote,
                       IF(sln.name IS NULL, s.name, sln.name) AS name,
                       IF(sln.slug IS NULL, s.slug, sln.slug) AS slug
                FROM `user_series` us
                         INNER JOIN `series` s ON s.`id` = us.`series_id`
                         INNER JOIN `user_episode` ue ON us.`id` = ue.`user_series_id` AND ue.`season_number` = us.`last_season` AND ue.`episode_number` = us.`last_episode`
                         LEFT JOIN `series_localized_name` sln ON sln.`series_id` = s.`id` AND sln.`locale` = :locale
                         LEFT JOIN watch_provider wp ON wp.provider_id = ue.provider_id
                         LEFT JOIN device d ON ue.device_id = d.id
                WHERE us.`user_id`=:userId
                  AND us.`last_watch_at` IS NOT NULL
                ORDER BY us.`last_watch_at` DESC
                LIMIT :limit OFFSET :offset
            SQL;
        }
        if ($list == 'episode') {
            $sql = <<<SQL
                SELECT s.id                                   AS id,
                       s.`poster_path`                        AS posterPath,
                       ue.episode_id                          AS episodeId,
                       ue.episode_number                      AS episodeNumber,
                       ue.season_number                       AS seasonNumber,
                       ue.watch_at                            AS lastWatchAt,
                       us.progress                            AS progress,
                       wp.logo_path                           AS providerLogoPath,
                       wp.provider_name                       AS providerName,
                       d.svg                                  AS deviceSvg,
                       ue.vote                                AS vote,
                       IF(sln.name IS NULL, s.name, sln.name) AS name,
                       IF(sln.slug IS NULL, s.slug, sln.slug) AS slug
                FROM `user_episode` ue
                         INNER JOIN `user_series` us ON us.`id` = ue.`user_series_id`
                         INNER JOIN `series` s ON s.`id` = us.`series_id`
                         LEFT JOIN `series_localized_name` sln ON sln.`series_id` = s.`id` AND sln.`locale` = :locale
                         LEFT JOIN watch_provider wp ON wp.provider_id = ue.provider_id
                         LEFT JOIN device d ON ue.device_id = d.id
                WHERE us.`user_id` = :userId
                  AND ue.watch_at IS NOT NULL
                ORDER BY ue.watch_at DESC
                LIMIT :limit OFFSET :offset
            SQL;
        }
        return $sql ? $this->getAll($sql, $params, $types) : [];
    }

    public function getLastWatchedEpisode(User $user): int
    {
        $params = [
            'userId' => $user->getId(),
        ];
        $types = [
            'userId' => ParameterType::INTEGER,
        ];
        $sql = <<<SQL
        SELECT ue.episode_id
            FROM user_episode ue
            WHERE ue.user_id = :userId
            ORDER BY ue.watch_at DESC
            LIMIT 1
        SQL;

        return $this->getOne($sql, $params, $types);
    }

    public function getScheduleNextEpisode(int $id, int $userSeriesId): array
    {
        $params = [
            'id' => $id,
            'userSeriesId' => $userSeriesId,
        ];
        $types = [
            'id' => ParameterType::INTEGER,
            'userSeriesId' => ParameterType::INTEGER,
        ];
        $sql = <<<SQL
            SELECT ue.`season_number`,
                   ue.`episode_number`,
                   IF(sbd.id, DATE(sbd.date), ue.`air_date`) AS air_date
            FROM user_episode ue
                INNER JOIN user_series us ON us.id = :userSeriesId AND ue.`user_series_id` = us.`id`
                LEFT JOIN series_broadcast_schedule sbs ON sbs.id = :id AND IF(sbs.multi_part, ue.episode_number BETWEEN sbs.season_part_first_episode AND (sbs.season_part_first_episode + sbs.season_part_episode_count - 1), 1)
                LEFT JOIN series_broadcast_date sbd ON sbd.series_broadcast_schedule_id = sbs.id AND sbd.episode_id = ue.episode_id
            WHERE sbs.season_number = ue.season_number
                AND ue.`watch_at` IS NULL AND ue.previous_occurrence_id IS NULL
            ORDER BY  ue.`season_number`, ue.`episode_number`
            LIMIT 1
        SQL;

        return $this->getAll($sql, $params, $types);
    }

    public function getScheduleNextEpisodes(int $id, int $usId, string $airDate): array
    {
        $params = [
            'id' => $id,
            'usId' => $usId,
            'airDate' => $airDate,
        ];
        $types = [
            'id' => ParameterType::INTEGER,
            'usId' => ParameterType::INTEGER,
            'airDate' => ParameterType::STRING,
        ];
        $sql = <<<SQL
            SELECT ue.`season_number`,
                   ue.`episode_number`,
                   IF(sbs.override, DATE(sbd.date), ue.`air_date`) AS air_date
            FROM user_episode ue
                INNER JOIN user_series us ON us.id = :usId AND ue.`user_series_id` = us.`id`
                LEFT JOIN series_broadcast_schedule sbs ON sbs.id = :id AND IF(sbs.multi_part, ue.episode_number BETWEEN sbs.season_part_first_episode AND (sbs.season_part_first_episode + sbs.season_part_episode_count - 1), 1)
                LEFT JOIN series_broadcast_date sbd ON sbd.series_broadcast_schedule_id = sbs.id AND sbd.episode_id = ue.episode_id
            WHERE sbs.season_number = ue.season_number
                AND IF(sbs.override, DATE(sbd.date), ue.`air_date`) = DATE(:airDate)
                AND ue.previous_occurrence_id IS NULL AND ue.previous_occurrence_id IS NULL
            ORDER BY  ue.`season_number`, ue.`episode_number`
        SQL;

        return $this->getAll($sql, $params, $types);
    }

    public function getScheduleLastEpisode(int $id, int $userSeriesId): array
    {
        $params = [
            'id' => $id,
            'userSeriesId' => $userSeriesId,
        ];
        $types = [
            'id' => ParameterType::INTEGER,
            'userSeriesId' => ParameterType::INTEGER,
        ];
        $sql = <<<SQL
            SELECT ue.`season_number`,
                   ue.`episode_number`,
                   IF(sbs.override, DATE(sbd.date), ue.`air_date`) AS air_date,
                   ue.`watch_at`
            FROM user_episode ue
                INNER JOIN user_series us ON us.`id`=$userSeriesId AND ue.user_series_id=us.id
                LEFT JOIN series_broadcast_schedule sbs ON sbs.id=$id AND IF(sbs.multi_part, ue.episode_number BETWEEN sbs.season_part_first_episode AND (sbs.season_part_first_episode + sbs.season_part_episode_count - 1), 1)
                LEFT JOIN series_broadcast_date sbd ON sbd.series_broadcast_schedule_id = sbs.id AND sbd.episode_id = ue.episode_id
            WHERE sbs.season_number = ue.season_number
                AND ue.`watch_at` IS NOT NULL AND ue.previous_occurrence_id IS NULL
            ORDER BY  ue.`season_number` DESC, ue.`episode_number` DESC
            LIMIT 1
        SQL;

        return $this->getAll($sql, $params, $types);
    }

    public function episodesOfTheDay(User $user, string $locale = 'fr', bool $next7Days = true): array
    {
        $params = [
            'userId' => $user->getId(),
            'locale' => $locale,
        ];
        $types = [
            'userId' => ParameterType::INTEGER,
            'locale' => ParameterType::STRING,
        ];

        if ($next7Days) {
            $dayCondition = "IF(sbd.id IS NULL, ue.air_date >= CURDATE() AND ue.air_date <= ADDDATE(CURDATE(), INTERVAL 7 DAY), DATE(sbd.date) >= CURDATE() AND DATE(sbd.date) <= ADDDATE(CURDATE(), INTERVAL 7 DAY))";
        } else {
            $dayCondition = "IF(sbd.id IS NULL, ue.air_date = CURDATE(), DATE(sbd.date) = CURDATE())";
        }

        $sql = <<<SQL
            SELECT
                ue.id                                               AS episode_id,
                s.id                                                AS id,
                s.tmdb_id                                           AS tmdb_id,
                IF(sbd.id IS NULL, ue.air_date, DATE(sbd.date))     AS date,
                s.name                                              AS name,
                s.slug                                              AS slug,
                sln.name                                            AS sln_name,
                sln.slug                                            AS sln_slug,
                s.poster_path                                       AS poster_path,
                s.status                                            AS status,
                (s.first_air_date <= NOW())                         AS released,
                us.favorite                                         AS favorite,
                us.progress                                         AS progress,
                ue.episode_number                                   AS episode_number,
                ue.season_number                                    AS season_number,
                ue.watch_at                                         AS watch_at,
                sbs.air_at                                          AS air_at,
                IF(sbs.id, sbs.provider_id, swl.provider_id)        AS provider_id,
                wp.provider_name                                    AS provider_name,
                wp.logo_path                                        AS provider_logo_path,
                counts.watched_aired_episode_count                  AS watched_aired_episode_count,
                counts.aired_episode_count                          AS aired_episode_count,
                us.last_watch_at                                    AS series_last_watch_at,
                (SELECT COUNT(*)
                 FROM user_list_series uls
                     INNER JOIN user_list ul ON ul.user_id = :userId AND uls.user_list_id = ul.id
                 WHERE uls.series_id = s.id
                )                                                   AS is_series_in_list
            FROM series s
                INNER JOIN user_series us ON s.id = us.series_id
                INNER JOIN user_episode ue ON us.id = ue.user_series_id
                LEFT JOIN series_broadcast_schedule sbs ON s.id = sbs.series_id AND sbs.season_number = ue.season_number AND IF(sbs.multi_part, ue.episode_number BETWEEN sbs.season_part_first_episode AND (sbs.season_part_first_episode + sbs.season_part_episode_count - 1), 1)
                LEFT JOIN series_broadcast_date sbd ON sbd.series_broadcast_schedule_id = sbs.id AND sbd.episode_id = ue.episode_id
                LEFT JOIN series_watch_link swl ON s.id = swl.series_id
                LEFT JOIN watch_provider wp ON wp.provider_id = IF(sbs.id, sbs.provider_id, swl.provider_id)
                LEFT JOIN series_localized_name sln ON s.id = sln.series_id AND sln.locale = :locale
                LEFT JOIN LATERAL (
                    -- Calcul fusionné des counts, corrélé avec us.id (via corrélation latérale)
                    SELECT 
                        COUNT(*) AS aired_episode_count,
                        SUM(IF(cue.watch_at IS NOT NULL, 1, 0)) AS watched_aired_episode_count
                    FROM user_episode cue
                        LEFT JOIN series_broadcast_schedule csbs ON s.id = csbs.series_id AND csbs.season_number = cue.season_number AND IF(csbs.multi_part, cue.episode_number BETWEEN csbs.season_part_first_episode AND (csbs.season_part_first_episode + csbs.season_part_episode_count - 1), 1)
                        LEFT JOIN series_broadcast_date csbd ON csbd.series_broadcast_schedule_id = csbs.id AND csbd.episode_id = cue.episode_id
                    WHERE cue.user_series_id = us.id  -- Corrélation avec la requête externe
                      AND cue.season_number > 0
                      AND IF(csbd.id IS NULL, cue.air_date <= CURDATE(), DATE(csbd.date) <= CURDATE())
                      AND cue.previous_occurrence_id IS NULL
                ) AS counts ON TRUE  -- 'ON TRUE' pour JOIN latéral sans condition supplémentaire
            WHERE us.user_id = :userId
              AND $dayCondition
            ORDER BY date, sbs.air_at, ue.season_number, ue.episode_number
        SQL;

        try {
            return $this->em->getConnection()->executeQuery($sql, $params, $types)->fetchAllAssociative();
        } catch (\Exception $e) {
            $this->logger->error('Erreur episodesOfTheDay: ' . $e->getMessage());
            return [];
        }
    }

    public function episodesOneOfTheDay(User $user, bool $next7Days = true, ?string $startDate = null, ?string $endDate = null): array
    {
        $params = ['userId' => $user->getId()];
        $types = ['userId' => ParameterType::INTEGER];

        // Dates fixes si fournies, sinon CURDATE()/ADDDATE()
        if ($startDate && $endDate) {
            $dayCondition = "IF(sbd.id IS NULL, ue.air_date >= :startDate AND ue.air_date <= :endDate, DATE(sbd.date) >= :startDate AND DATE(sbd.date) <= :endDate)";
            $params['startDate'] = $startDate;
            $params['endDate'] = $endDate;
            $types['startDate'] = ParameterType::STRING;
            $types['endDate'] = ParameterType::STRING;
        } elseif ($next7Days) {
            $dayCondition = "IF(sbd.id IS NULL, ue.air_date >= CURDATE() AND ue.air_date <= ADDDATE(CURDATE(), INTERVAL 7 DAY), DATE(sbd.date) >= CURDATE() AND DATE(sbd.date) <= ADDDATE(CURDATE(), INTERVAL 7 DAY))";
        } else {
            $dayCondition = "IF(sbd.id IS NULL, ue.air_date = CURDATE(), DATE(sbd.date) = CURDATE())";
        }

        $sql = <<<SQL
            SELECT DISTINCT
                ue.id                                               AS episode_id,
                s.id                                                AS series_id,
                s.tmdb_id                                           AS tmdb_id,
                IF(sbd.id IS NULL, ue.air_date, DATE(sbd.date))     AS date,  -- Date effective pour comparaison TMDB
                ue.season_number                                    AS season_number,
                ue.episode_number                                   AS episode_number,  -- Toujours 1 ici
                sbd.id                                              AS is_custom_date,  -- NULL si pas custom
                (SELECT COUNT(*) FROM user_episode cue WHERE cue.user_series_id = us.id AND cue.season_number = ue.season_number) AS db_episode_count  -- Compte épisodes DB pour sync
            FROM series s
                INNER JOIN user_series us ON s.id = us.series_id
                INNER JOIN user_episode ue ON us.id = ue.user_series_id
                LEFT JOIN series_broadcast_schedule sbs ON s.id = sbs.series_id AND sbs.season_number = ue.season_number AND IF(sbs.multi_part, ue.episode_number BETWEEN sbs.season_part_first_episode AND (sbs.season_part_first_episode + sbs.season_part_episode_count - 1), 1)
                LEFT JOIN series_broadcast_date sbd ON sbd.series_broadcast_schedule_id = sbs.id AND sbd.episode_id = ue.episode_id
            WHERE us.user_id = :userId
              AND ue.episode_number = 1  -- seulement premiers épisodes (inclut spéciaux).
              AND $dayCondition
            ORDER BY date, ue.season_number
        SQL;

        try {
            return $this->em->getConnection()->executeQuery($sql, $params, $types)->fetchAllAssociative();
        } catch (\Exception $e) {
            $this->logger->error('Erreur episodesOneOfTheDay: ' . $e->getMessage());
            return [];
        }
    }

    public function episodesToWatch(User $user, string $locale = 'fr', int $page = 1, int $limit = 20): array
    {
        $params = [
            'userId' => $user->getId(),
            'locale' => $locale,
            'offset' => ($page - 1) * $limit,
            'limit' => $limit,
        ];
        $types = [
            'userId' => ParameterType::INTEGER,
            'locale' => ParameterType::STRING,
            'offset' => ParameterType::INTEGER,
            'limit' => ParameterType::INTEGER,
        ];
        $sql = <<<SQL
            SELECT s.id                                 AS id,
                   s.tmdb_id                            AS tmdbId,
                   s.`name`                             AS name,
                   s.`slug`                             AS slug,
                   sln.`name`                           AS localizedName,
                   sln.`slug`                           AS localizedSlug,
                   s.`poster_path`                      AS posterPath,
                   us.`favorite`                        AS favorite,
                   us.`progress`                        AS progress,
                   ue.season_number                     AS seasonNumber,
                   ue.episode_number                    AS episodeNumber,
                   (SELECT COUNT(*)
                          FROM `user_list_series` uls
                              INNER JOIN `user_list` ul ON ul.`user_id`=:userId AND uls.`user_list_id`=ul.`id`
                          WHERE uls.`series_id`=s.`id`) AS is_series_in_list 
            FROM `user_series` us
                     INNER JOIN user_episode ue ON ue.`user_series_id` = us.`id`
                     LEFT JOIN `series` s ON s.`id` = us.`series_id`
                     LEFT JOIN `series_localized_name` sln ON sln.`series_id` = s.`id` AND sln.`locale` = :locale
                     LEFT JOIN series_broadcast_schedule sbs ON s.id = sbs.series_id AND sbs.season_number = ue.season_number AND IF(sbs.multi_part, ue.episode_number BETWEEN sbs.season_part_first_episode AND (sbs.season_part_first_episode + sbs.season_part_episode_count - 1), 1)
                     LEFT JOIN series_broadcast_date sbd ON sbd.series_broadcast_schedule_id = sbs.id AND sbd.episode_id = ue.episode_id
            WHERE us.`user_id` = :userId
              AND us.progress < 100
              AND ue.id=(SELECT ue2.id
                         FROM user_episode ue2
                         WHERE ue2.user_series_id = us.id
                           AND ue2.`watch_at` IS NULL
                           AND ue2.season_number > 0
                           AND IF(sbd.id IS NULL, ue2.`air_date` <= NOW(), DATE(sbd.date) <= NOW())
                         ORDER BY ue2.season_number, ue2.episode_number
                         LIMIT 1)
              AND us.progress > 0
            ORDER BY us.`last_watch_at` DESC
            LIMIT :limit OFFSET :offset
        SQL;

        return $this->getAll($sql, $params, $types);
    }

    public function episodesOfTheIntervalForTwig(User $user, string $startDate, string $endDate, string $locale = 'fr'): array
    {
        $params = [
            'userId' => $user->getId(),
            'startDate' => $startDate,
            'endDate' => $endDate,
            'locale' => $locale,
        ];
        $types = [
            'userId' => ParameterType::INTEGER,
            'startDate' => ParameterType::STRING,
            'endDate' => ParameterType::STRING,
            'locale' => ParameterType::STRING,
        ];
        $sql = <<<SQL
            SELECT
                IF(sbd.id, DATE(sbd.date), ue.air_date) AS airDate,
                sbs.`override`                          AS override,
                'series'                                AS type,
                DATEDIFF(IF(sbd.id, DATE(sbd.date), ue.air_date), DATE(NOW())) AS days,
                s.id                                    AS id, 
                IF(sln.name IS NULL, s.name, sln.name)  AS name,
                s.poster_path                           AS posterPath,
                ue.episode_id                           AS episodeId,
                ue.`episode_number`                     AS episodeNumber, 
                ue.`season_number`                      AS seasonNumber,
                ue.`watch_at`                           AS watchAt,
                sbs.air_at                              AS airAt,
                sbd.date                                AS customDate,
                wp.provider_name                        AS providerName,
                wp.logo_path                            AS providerLogoPath,
                ((SELECT COUNT(*)
                FROM `user_episode` ue1
                WHERE ue1.`user_series_id`=ue.`user_series_id` AND ue1.`season_number`=ue.`season_number`) = ue.`episode_number`)
                                                        AS last_episode  
            FROM series s 
                 INNER JOIN user_series us ON s.id = us.series_id 
                 INNER JOIN user_episode ue ON us.id = ue.user_series_id 
                 LEFT JOIN series_localized_name sln ON s.id = sln.series_id AND sln.locale = :locale
                 LEFT JOIN series_broadcast_schedule sbs ON s.id = sbs.series_id AND sbs.season_number = ue.season_number AND IF(sbs.multi_part, ue.episode_number BETWEEN sbs.season_part_first_episode AND (sbs.season_part_first_episode + sbs.season_part_episode_count - 1), 1)
                 LEFT JOIN series_broadcast_date sbd ON sbd.series_broadcast_schedule_id = sbs.id AND sbd.episode_id = ue.episode_id
                 LEFT JOIN series_watch_link swl ON s.id = swl.`series_id`
                 LEFT JOIN watch_provider wp ON wp.provider_id = IF(sbs.`id`, sbs.provider_id, swl.`provider_id`)
            WHERE us.user_id = :userId
                 AND IF(sbd.id, DATE(sbd.date) >= :startDate, ue.air_date >= :startDate)
                 AND IF(sbd.id, DATE(sbd.date) <= :endDate,   ue.air_date <= :endDate)
            ORDER BY name, seasonNumber, episodeNumber
        SQL;

        return $this->getAll($sql, $params, $types);
    }

    public function historyEpisode(User $user, int $dayCount, string $locale): array
    {
        $params = [
            'userId' => $user->getId(),
            'dayCount' => $dayCount,
            'locale' => $locale,
        ];
        $types = [
            'userId' => ParameterType::INTEGER,
            'dayCount' => ParameterType::INTEGER,
            'locale' => ParameterType::STRING,
        ];
        $sql = <<<SQL
            SELECT DISTINCT
                   s.id                            AS id,
                   s.tmdb_id                       AS tmdbId,
                   IF(sln.`id`, CONCAT(sln.`name`, ' - ', s.`name`), s.`name`) AS name,
                   IF(sln.`id`, sln.`slug`, s.`slug`)                          AS slug,
                   sln.`name`                      AS localizedName,
                   sln.`slug`                      AS localizedSlug,
                   s.`poster_path`                 AS posterPath,
                   ue.`watch_at`                   AS watchAt,
                   ue.`quick_watch_day`            AS qDay,
                   ue.`quick_watch_week`           AS qWeek,
                   us.`favorite`                   AS favorite,
                   us.`progress`                   AS progress,
                   ue.`episode_number`             AS episodeNumber,
                   ue.`season_number`              AS seasonNumber,
                   wp.`provider_name`              AS providerName,
                   wp.`logo_path`                  AS providerLogoPath,
                   wp.provider_id                  AS providerId,
                   (SELECT count(*)
                    FROM user_episode ue
                    WHERE ue.user_series_id = us.id
                      AND ue.season_number > 0
                      AND IF(sbs.override, DATE(sbd.date) <= CURDATE(), ue.air_date <= CURDATE())
                      AND ue.watch_at IS NOT NULL) AS watched_aired_episode_count,
                   (SELECT count(*)
                    FROM user_episode ue
                    WHERE ue.user_series_id = us.id
                      AND ue.season_number > 0
                      AND IF(sbs.override, DATE(sbd.date) <= CURDATE(), ue.air_date <= CURDATE())
                   )                               AS aired_episode_count,
                   (SELECT COUNT(*)
                          FROM `user_list_series` uls
                              INNER JOIN `user_list` ul ON ul.`user_id`=:userId AND uls.`user_list_id`=ul.`id`
                          WHERE uls.`series_id`=s.`id`) AS is_series_in_list 
            FROM `user_episode` ue
                     INNER JOIN `user_series` us ON us.`id` = ue.`user_series_id`
                     INNER JOIN `series` s ON s.`id` = us.`series_id`
                     LEFT JOIN series_broadcast_schedule sbs ON s.id = sbs.series_id AND sbs.season_number = ue.season_number AND IF(sbs.multi_part, ue.episode_number BETWEEN sbs.season_part_first_episode AND (sbs.season_part_first_episode + sbs.season_part_episode_count - 1), 1)
                     LEFT JOIN series_broadcast_date sbd ON sbd.series_broadcast_schedule_id = sbs.id AND sbd.episode_id = ue.episode_id
                     LEFT JOIN `watch_provider` wp ON wp.`provider_id` = ue.`provider_id`
                     LEFT JOIN `series_localized_name` sln ON sln.`series_id` = s.`id` AND sln.`locale` = :locale
            WHERE ue.`user_id` = :userId
              AND ue.`watch_at` IS NOT NULL
              AND ue.`watch_at` >= DATE_SUB(NOW(), INTERVAL :dayCount DAY)
            ORDER BY ue.`watch_at` DESC
        SQL;

        return $this->getAll($sql, $params, $types);
    }

    public function getEpisodeListBetweenDates(int $userId, string $startDate, string $endDate): array
    {
        $params = [
            'userId' => $userId,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ];
        $types = [
            'userId' => ParameterType::INTEGER,
            'startDate' => ParameterType::STRING,
            'endDate' => ParameterType::STRING,
        ];
        $sql = <<<SQL
            SELECT ue.`episode_number`, ue.`season_number`, ue.`user_series_id`, ue.`watch_at`
            FROM `user_episode` ue
            WHERE ue.`user_id` = :userId AND ue.`watch_at` BETWEEN :startDate AND :endDate
            ORDER BY ue.`watch_at` DESC
        SQL;

        return $this->getAll($sql, $params, $types);
    }

    public function getUserEpisodesDB(int $userSeriesId, int $seasonNumber, string $locale, bool $all = false): array
    {
        $params = [
            'userSeriesId' => $userSeriesId,
            'seasonNumber' => $seasonNumber,
            'locale' => $locale,
        ];
        $types = [
            'userSeriesId' => ParameterType::INTEGER,
            'seasonNumber' => ParameterType::INTEGER,
            'locale' => ParameterType::STRING,
        ];
        $andWhere = $all ? '' : 'AND ue.previous_occurrence_id IS NULL';
        $sql = <<<SQL
            SELECT ue.id                     AS id,
                   ue.episode_id             AS episode_id,
                   esn.name                  AS substitute_name,
                   elo.overview              AS localized_overview,
                   ue.episode_number         AS episode_number,
                   ue.watch_at               AS watch_at,
                   ue.air_date               AS air_date,
                   sbd.date                  AS custom_date,
                   sbs.air_at                AS air_at,
                   -- IF(sbs.air_at, STR_TO_DATE(CONCAT(ue.`air_date`, ' ', sbs.air_at), '%Y-%m-%d %H:%i:%s'), NULL) AS date_string,
                   ue.provider_id            AS provider_id,
                   wp.provider_name          AS provider_name,
                   wp.logo_path              AS provider_logo_path,
                   ue.device_id              AS device_id,
                   d.name                    AS device_name,
                   d.logo_path               AS device_logo_path,
                   d.svg                     AS device_svg,
                   ue.vote                   AS vote,
                   ue.previous_occurrence_id AS previous_occurrence_id
            FROM user_episode ue
                     LEFT JOIN user_series us ON ue.user_series_id = us.id
                     LEFT JOIN series s ON us.series_id = s.id
                     LEFT JOIN series_broadcast_schedule sbs ON sbs.series_id = s.id AND sbs.season_number = ue.season_number AND IF(sbs.multi_part, ue.episode_number BETWEEN sbs.season_part_first_episode AND (sbs.season_part_first_episode + sbs.season_part_episode_count -1), 1)
                     LEFT JOIN series_broadcast_date sbd ON ue.episode_id = sbd.episode_id
                     LEFT JOIN episode_substitute_name esn ON ue.episode_id = esn.episode_id
                     LEFT JOIN episode_localized_overview elo ON ue.episode_id = elo.episode_id AND elo.locale = :locale
                     LEFT JOIN watch_provider wp ON ue.provider_id = wp.provider_id
                     LEFT JOIN device d ON ue.device_id = d.id
            WHERE ue.user_series_id = :userSeriesId
              AND ue.season_number = :seasonNumber $andWhere
        SQL;

        return $this->getAll($sql, $params, $types);
    }

    public function getUserEpisodeDB(int $userEpisodeId, string $locale): array
    {
        $params = [
            'userEpisodeId' => $userEpisodeId,
            'locale' => $locale,
        ];
        $types = [
            'userEpisodeId' => ParameterType::INTEGER,
            'locale' => ParameterType::STRING,
        ];
        $sql = <<<SQL
            SELECT ue.id                     AS id,
                   ue.episode_id             AS episode_id,
                   esn.name                  AS substitute_name,
                   elo.overview              AS localized_overview,
                   ue.episode_number         AS episode_number,
                   ue.watch_at               AS watch_at,
                   ue.air_date               AS air_date,
                   sbd.date                  AS custom_date,
                   sbs.air_at                AS air_at,
                   -- IF(sbs.air_at, STR_TO_DATE(CONCAT(ue.`air_date`, ' ', sbs.air_at), '%Y-%m-%d %H:%i:%s'), NULL) AS date_string,
                   ue.provider_id            AS provider_id,
                   wp.provider_name          AS provider_name,
                   wp.logo_path              AS provider_logo_path,
                   ue.device_id              AS device_id,
                   d.name                    AS device_name,
                   d.logo_path               AS device_logo_path,
                   d.svg                     AS device_svg,
                   ue.vote                   AS vote,
                   ue.previous_occurrence_id AS previous_occurrence_id
            FROM user_episode ue
                     LEFT JOIN user_series us ON ue.user_series_id = us.id
                     LEFT JOIN series s ON us.series_id = s.id
                     LEFT JOIN series_broadcast_schedule sbs ON sbs.series_id = s.id
                     LEFT JOIN series_broadcast_date sbd ON ue.episode_id = sbd.episode_id
                     LEFT JOIN episode_substitute_name esn ON ue.episode_id = esn.episode_id
                     LEFT JOIN episode_localized_overview elo ON ue.episode_id = elo.episode_id AND elo.locale = :locale
                     LEFT JOIN watch_provider wp ON ue.provider_id = wp.provider_id
                     LEFT JOIN device d ON ue.device_id = d.id
            WHERE ue.id = :userEpisodeId
        SQL;

        return array_first($this->getAll($sql, $params, $types));
    }

    public function inProgressSeriesForTwig(User $user, string $locale): array
    {
        $params = [
            'userId' => $user->getId(),
            'locale' => $locale,
        ];
        $types = [
            'userId' => ParameterType::INTEGER,
            'locale' => ParameterType::STRING,
        ];
        $sql = <<<SQL
            SELECT s.`id`                                           AS id,
                   s.poster_path                                    AS posterPath, 
                   IF(sln.`name` IS NOT NULL, sln.`name`, s.`name`) AS name,
                   IF(sln.`name` IS NOT NULL, sln.`slug`, s.`slug`) AS slug,
                   ue.episode_id                                    AS episodeId,
                   ue.`season_number`                               AS nextEpisodeSeason,
                   (SELECT ue1.`episode_number`
                    FROM `user_episode` ue1
                    WHERE ue1.`user_series_id`=us.`id` AND ue1.`season_number`=ue.`season_number` AND ue1.`watch_at` IS NULL
                    ORDER BY ue1.`episode_id` LIMIT 1)              AS nextEpisodeNumber,
                   (SELECT COUNT(*)
                    FROM `user_episode` ue2
                    WHERE ue2.`user_series_id`=us.`id`
                      AND ue2.`season_number`=ue.`season_number`)   AS seasonEpisodeCount,
                   (SELECT COUNT(*)
                    FROM `user_episode` ue3
                    WHERE ue3.`user_series_id`=us.`id` 
                      AND ue3.`season_number`=ue.`season_number` 
                      AND ue3.`watch_at` IS NOT NULL)               AS seasonViewedEpisodeCount
            FROM `user_episode` ue
                LEFT JOIN `user_series` us ON us.`id`=ue.`user_series_id`
                LEFT JOIN `series` s ON s.`id`=us.`series_id`
                LEFT JOIN `series_localized_name` sln ON sln.`series_id`=s.`id` AND sln.`locale`=:locale
            WHERE ue.`user_id`=:userId AND ue.`watch_at` IS NOT NULL
            ORDER BY ue.`watch_at` DESC
            LIMIT 1
        SQL;

        return $this->getAll($sql, $params, $types);
    }

    public function seasonProgress(UserSeries $userSeries, int $seasonNumber): array
    {
        $params = [
            'userSeriesId' => $userSeries->getId(),
            'seasonNumber' => $seasonNumber,
        ];
        $types = [
            'userSeriesId' => ParameterType::INTEGER,
            'seasonNumber' => ParameterType::INTEGER,
        ];
        $sql = <<<SQL
            SELECT COUNT(*) AS episodeCount, SUM(IF(ue.`watch_at` IS NOT NULL, 1, 0)) episodeWatchedCount
            FROM `user_episode` ue
            WHERE ue.`user_series_id` = :userSeriesId
              AND ue.`season_number` = :seasonNumber 
              AND ue.previous_occurrence_id IS NULL
        SQL;

        $result = $this->getAssociative($sql, $params, $types);

        if ($result) {
            return [
                'value' => round($result['episodeWatchedCount'] / $result['episodeCount'] * 100, 2),
                'episodeCount' => $result['episodeCount'],
                'episodeWatchedCount' => intval($result['episodeWatchedCount']),
            ];
        }

        return ['value' => 0, 'episodeCount' => 0, 'episodeWatchedCount' => 0];
    }

    public function getSeasonEpisodeIds(int $userSeriesId, int $seasonNumber): array
    {
        $params = [
            'userSeriesId' => $userSeriesId,
            'seasonNumber' => $seasonNumber,
        ];
        $types = [
            'userSeriesId' => ParameterType::INTEGER,
            'seasonNumber' => ParameterType::INTEGER,
        ];
        $sql = <<<SQL
            SELECT ue.episode_id
            FROM user_episode ue
            WHERE ue.user_series_id = :userSeriesId
              AND ue.season_number = :seasonNumber
              AND ue.previous_occurrence_id IS NULL
            ORDER BY ue.episode_number
        SQL;

        return $this->getAll($sql, $params, $types);
    }

    public function getAll($sql, array $params = [], array $types = []): array
    {
        try {
            return $this->em->getConnection()->fetchAllAssociative($sql, $params, $types);
        } catch (\Exception $e) {
            $this->logger->error('Error: ' . $e->getMessage());
            return [];
        }
    }

    public function getAssociative($sql, array $params = [], array $types = []): array
    {
        try {
            return $this->em->getConnection()->fetchAssociative($sql, $params, $types);
        } catch (\Exception $e) {
            $this->logger->error('Error: ' . $e->getMessage());
            return [];
        }
    }

    public function getOne($sql, array $params = [], array $types = []): mixed
    {
        try {
            return $this->em->getConnection()->fetchOne($sql, $params, $types);
        } catch (\Exception $e) {
            $this->logger->error('Error: ' . $e->getMessage());
            return [];
        }
    }

    public function removeByEpisodeIds(UserSeries $userSeries, array $removedEpisodeIds): bool
    {
        if (count($removedEpisodeIds) === 0) {
            return true;
        }
        $userSeriesId = $userSeries->getId();
        $ids = implode(',', $removedEpisodeIds);
        $sql = "DELETE FROM user_episode WHERE user_series_id=$userSeriesId AND episode_id IN ($ids)";
        try {
            $this->em->getConnection()->executeStatement($sql);
        } catch (Exception) {
            // Nothing
            return false;
        }
        return true;
    }
}
