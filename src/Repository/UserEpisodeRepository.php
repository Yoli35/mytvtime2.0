<?php /** @noinspection PhpMultipleClassDeclarationsInspection */

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserEpisode;
use App\Entity\UserSeries;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

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
    public function __construct(ManagerRegistry $registry, private readonly EntityManagerInterface $em)
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

    public function removeWithoutFlush(UserEpisode $userEpisode): void
    {
        $this->em->remove($userEpisode);
    }

    public function getUserEpisodeViews(int $userId, int $episodeId): array
    {
        $sql = "SELECT ue.id as id,
                       ue.watch_at as watch_at
                FROM user_episode ue 
                WHERE ue.user_id = $userId 
                  AND ue.episode_id = $episodeId
                ORDER BY ue.id";

        return $this->getAll($sql);
    }

    public function isFullyReleased(UserSeries $userSeries): int
    {
        $userId = $userSeries->getUser()->getId();
        $userSeriesId = $userSeries->getId();

        $sql = "SELECT ue.`air_date` <= NOW()
                FROM `user_episode` ue
                WHERE ue.`user_id`=$userId AND ue.`user_series_id`=$userSeriesId
                ORDER BY ue.`air_date` DESC LIMIT 1";

        return $this->getOne($sql) ?? 0;
    }

    public function lastAddedSeries(User $user, $locale, $page, $perPage): array
    {
        $userId = $user->getId();
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT 
              s.`id`                      as id,
              s.`name`                    as name,
              s.`poster_path`             as posterPath, 
              s.`slug`                    as slug,
              s.`status`                  as status,
              (s.first_air_date <= NOW()) as released,
              s.`tmdb_id`                 as tmdbId,
              sln.`name`                  as localizedName, 
              sln.`slug`                  as localizedSlug, 
              us.`favorite`               as favorite,
              us.`last_episode`           as episodeNumber,
              us.`last_season`            as seasonNumber, 
              us.`progress`               as progress
            FROM `user_series` us 
            INNER JOIN `series` s ON s.`id` = us.`series_id` 
            LEFT JOIN `series_localized_name` sln ON sln.`series_id`=s.`id` AND sln.`locale`='$locale' 
            WHERE us.`user_id`=$userId 
            ORDER BY us.`added_at` DESC 
            LIMIT $perPage OFFSET $offset";

        return $this->getAll($sql);
    }

    public function historySeries(User $user, string $locale, int $page, int $perPage): array
    {
        $userId = $user->getId();
        $sql = "SELECT s.id                            as id,
                   s.tmdb_id                       as tmdbId,
                   s.`name`                        as name,
                   s.`slug`                        as slug,
                   s.status                        as status,
                   sln.`name`                      as localizedName,
                   sln.`slug`                      as localizedSlug,
                   s.`poster_path`                 as posterPath,
                   us.`favorite`                   as favorite,
                   us.`progress`                   as progress,
                   us.`last_episode`               as episodeNumber,
                   us.`last_season`                as seasonNumber,
                   (SELECT count(*)
                    FROM user_episode ue
                        LEFT JOIN series_broadcast_schedule sbs ON s.id = sbs.series_id AND IF(sbs.multi_part, ue.episode_number BETWEEN sbs.season_part_first_episode AND (sbs.season_part_first_episode + sbs.season_part_episode_count - 1), 1)
                        LEFT JOIN series_broadcast_date sbd ON sbd.series_broadcast_schedule_id = sbs.id AND sbd.episode_id = ue.episode_id
                    WHERE ue.user_series_id = us.id
                      AND ue.season_number > 0
                      AND IF(sbd.id, DATE(sbd.date) <= CURDATE(), ue.air_date <= CURDATE())
                      AND ue.watch_at IS NOT NULL
                   )                               as watched_aired_episode_count,
                   (SELECT count(*)
                    FROM user_episode ue
                        LEFT JOIN series_broadcast_schedule sbs ON s.id = sbs.series_id AND IF(sbs.multi_part, ue.episode_number BETWEEN sbs.season_part_first_episode AND (sbs.season_part_first_episode + sbs.season_part_episode_count - 1), 1)
                        LEFT JOIN series_broadcast_date sbd ON sbd.series_broadcast_schedule_id = sbs.id AND sbd.episode_id = ue.episode_id
                    WHERE ue.user_series_id = us.id
                      AND ue.season_number > 0
                      AND IF(sbd.id, DATE(sbd.date) <= CURDATE(), ue.air_date <= CURDATE())
                   )                               as aired_episode_count
            FROM `user_series` us
                     INNER JOIN `series` s ON s.`id` = us.`series_id`
                     LEFT JOIN `series_localized_name` sln ON sln.`series_id` = s.`id` AND sln.`locale` = '$locale'
            WHERE us.`user_id`=$userId
              AND us.`last_watch_at` IS NOT NULL
            ORDER BY us.`last_watch_at` DESC
            LIMIT $perPage OFFSET " . ($page - 1) * $perPage;

        return $this->getAll($sql);
    }

    public function seriesHistoryForTwig(User $user, string $locale, string $list, int $page, int $count): array
    {
        $userId = $user->getId();
        $offset = ($page - 1) * $count;
        $sql = null;
        if ($list == 'series') {
            $sql = "SELECT s.id                            as id,
                           ue.episode_id                   as episodeId,
                           s.`poster_path`                 as posterPath,
                           us.`last_episode`               as episodeNumber,
                           us.`last_season`                as seasonNumber,
                           us.last_watch_at                as lastWatchAt,
                           us.progress                     as progress,
                           wp.logo_path                    as providerLogoPath,
                           wp.provider_name                as providerName,
                           d.svg                           as deviceSvg,
                           ue.vote                         as vote,
                           IF(sln.name IS NULL, s.name, sln.name) as name,
                           IF(sln.slug IS NULL, s.slug, sln.slug) as slug
                FROM `user_series` us
                         INNER JOIN `series` s ON s.`id` = us.`series_id`
                         INNER JOIN `user_episode` ue ON us.`id` = ue.`user_series_id` AND ue.`season_number` = us.`last_season` AND ue.`episode_number` = us.`last_episode`
                         LEFT JOIN `series_localized_name` sln ON sln.`series_id` = s.`id` AND sln.`locale` = '$locale'
                         LEFT JOIN watch_provider wp ON wp.provider_id = ue.provider_id
                         LEFT JOIN device d ON ue.device_id = d.id
                WHERE us.`user_id`=$userId
                  AND us.`last_watch_at` IS NOT NULL
                ORDER BY us.`last_watch_at` DESC
                LIMIT $count OFFSET $offset";
        }
        if ($list == 'episode') {
            $sql = "SELECT s.id                                   as id,
                           s.`poster_path`                        as posterPath,
                           ue.episode_id                          as episodeId,
                           ue.episode_number                      as episodeNumber,
                           ue.season_number                       as seasonNumber,
                           ue.watch_at                            as lastWatchAt,
                           us.progress                            as progress,
                           wp.logo_path                           as providerLogoPath,
                           wp.provider_name                       as providerName,
                           d.svg                                  as deviceSvg,
                           ue.vote                                as vote,
                           IF(sln.name IS NULL, s.name, CONCAT(sln.name,' - ',s.name)) as name,
                           IF(sln.slug IS NULL, s.slug, sln.slug) as slug
                    FROM `user_episode` ue
                             INNER JOIN `user_series` us ON us.`id` = ue.`user_series_id`
                             INNER JOIN `series` s ON s.`id` = us.`series_id`
                             LEFT JOIN `series_localized_name` sln ON sln.`series_id` = s.`id` AND sln.`locale` = '$locale'
                             LEFT JOIN watch_provider wp ON wp.provider_id = ue.provider_id
                             LEFT JOIN device d ON ue.device_id = d.id
                    WHERE us.`user_id` = $userId
                      AND ue.watch_at IS NOT NULL
                    ORDER BY ue.watch_at DESC
                    LIMIT $count OFFSET $offset";
        }

        return $sql ? $this->getAll($sql) : [];
    }

    public function getLastWatchedEpisode(User $user): int
    {
        $userId = $user->getId();
        $sql = "SELECT ue.episode_id
                FROM user_episode ue
                WHERE ue.user_id = $userId
                ORDER BY ue.watch_at DESC
                LIMIT 1";

        return $this->getOne($sql);
    }

    public function getScheduleNextEpisode(int $id, int $userSeriesIde): array
    {
        $sql = "SELECT ue.`season_number`,
                       ue.`episode_number`,
                       IF(sbd.id, DATE(sbd.date), ue.`air_date`) as air_date
                FROM user_episode ue
                    INNER JOIN user_series us ON us.id=$userSeriesIde AND ue.`user_series_id` = us.`id`
                    LEFT JOIN series_broadcast_schedule sbs ON sbs.id=$id AND IF(sbs.multi_part, ue.episode_number BETWEEN sbs.season_part_first_episode AND (sbs.season_part_first_episode + sbs.season_part_episode_count - 1), 1)
                    LEFT JOIN series_broadcast_date sbd ON sbd.series_broadcast_schedule_id = sbs.id AND sbd.episode_id = ue.episode_id
                WHERE sbs.season_number = ue.season_number
                    AND ue.`watch_at` IS NULL AND ue.previous_occurrence_id IS NULL
                ORDER BY  ue.`season_number`, ue.`episode_number`
                LIMIT 1";

        return $this->getAll($sql);
    }

    public function getScheduleNextEpisodes(int $id, int $usId, string $airDate): array
    {
        $sql = "SELECT ue.`season_number`,
                       ue.`episode_number`,
                       IF(sbs.override, DATE(sbd.date), ue.`air_date`) as air_date
                FROM user_episode ue
                    INNER JOIN user_series us ON us.id = $usId AND ue.`user_series_id` = us.`id`
                    LEFT JOIN series_broadcast_schedule sbs ON sbs.id = $id AND IF(sbs.multi_part, ue.episode_number BETWEEN sbs.season_part_first_episode AND (sbs.season_part_first_episode + sbs.season_part_episode_count - 1), 1)
                    LEFT JOIN series_broadcast_date sbd ON sbd.series_broadcast_schedule_id = sbs.id AND sbd.episode_id = ue.episode_id
                WHERE sbs.season_number = ue.season_number
                    AND IF(sbs.override, DATE(sbd.date), ue.`air_date`) = DATE('$airDate')
                    AND ue.previous_occurrence_id IS NULL AND ue.previous_occurrence_id IS NULL
                ORDER BY  ue.`season_number`, ue.`episode_number`";

        return $this->getAll($sql);
    }

    public function getScheduleLastEpisode(int $id, int $userSeriesId): array
    {
        $sql = "SELECT ue.`season_number`,
                       ue.`episode_number`,
                       IF(sbs.override, DATE(sbd.date), ue.`air_date`) as air_date,
                       ue.`watch_at`
                FROM user_episode ue
                    INNER JOIN user_series us ON us.`id`=$userSeriesId AND ue.user_series_id=us.id
                    LEFT JOIN series_broadcast_schedule sbs ON sbs.id=$id AND IF(sbs.multi_part, ue.episode_number BETWEEN sbs.season_part_first_episode AND (sbs.season_part_first_episode + sbs.season_part_episode_count - 1), 1)
                    LEFT JOIN series_broadcast_date sbd ON sbd.series_broadcast_schedule_id = sbs.id AND sbd.episode_id = ue.episode_id
                WHERE sbs.season_number = ue.season_number
                  AND ue.`watch_at` IS NOT NULL AND ue.previous_occurrence_id IS NULL
                ORDER BY  ue.`season_number` DESC, ue.`episode_number` DESC
                LIMIT 1";

        return $this->getAll($sql);
    }

    public function episodesOfTheDay(User $user, string $locale = 'fr', bool $next7Days = true): array
    {
        $userId = $user->getId();
        if ($next7Days) {
            $dayCondition = "IF(sbd.id IS NULL, ue.air_date >= CURDATE() AND ue.`air_date` <= ADDDATE(CURDATE(), INTERVAL 7 DAY), DATE(sbd.date) >= CURDATE() AND DATE(sbd.date) <= ADDDATE(CURDATE(), INTERVAL 7 DAY))";
        } else {
            $dayCondition = "IF(sbd.id IS NULL, ue.air_date = CURDATE(), DATE(sbd.date) = CURDATE())";
        }
        $sql = "SELECT
                       ue.id                                               as episode_id,
                       s.id                                                as id,
                       s.tmdb_id                                           as tmdb_id,
                       IF(sbd.id IS NULL, ue.`air_date`, DATE(sbd.`date`)) as date,
                       s.name                                              as name,
                       s.slug                                              as slug,
                       sln.name                                            as localized_name,
                       sln.slug                                            as localized_slug,
                       s.poster_path                                       as poster_path,
                       s.status                                            as status,
                       (s.first_air_date <= NOW())                         as released,
                       us.favorite                                         as favorite,
                       us.progress                                         as progress,
                       ue.`episode_number`                                 as episode_number,
                       ue.`season_number`                                  as season_number,
                       ue.`watch_at`                                       as watch_at,
                       sbs.`air_at`                                        as air_at,
                       IF(sbs.`id`, sbs.provider_id, swl.`provider_id`)    as provider_id,
                       wp.`provider_name`                                  as provider_name,
                       wp.`logo_path`                                      as provider_logo_path,
                       (SELECT count(*)
                        FROM user_episode cue
                            LEFT JOIN series_broadcast_schedule csbs ON s.id = csbs.series_id AND csbs.`season_number`=cue.`season_number` AND IF(csbs.multi_part, cue.episode_number BETWEEN csbs.season_part_first_episode AND (csbs.season_part_first_episode + csbs.season_part_episode_count - 1), 1)
                            LEFT JOIN series_broadcast_date csbd ON csbd.series_broadcast_schedule_id = csbs.id AND csbd.episode_id = cue.episode_id
                        WHERE cue.user_series_id = us.id
                          AND cue.season_number > 0
                          AND IF(csbd.id IS NULL, cue.air_date <= CURDATE(), DATE(csbd.date) <= CURDATE())
                          AND cue.watch_at IS NOT NULL
                          AND cue.previous_occurrence_id IS NULL
                       )                                                as watched_aired_episode_count,
                       (SELECT count(*)
                        FROM user_episode cue
                            LEFT JOIN series_broadcast_schedule csbs ON s.id = csbs.series_id AND csbs.`season_number`=cue.`season_number` AND IF(csbs.multi_part, cue.episode_number BETWEEN csbs.season_part_first_episode AND (csbs.season_part_first_episode + csbs.season_part_episode_count - 1), 1)
                            LEFT JOIN series_broadcast_date csbd ON csbd.series_broadcast_schedule_id = csbs.id AND csbd.episode_id = cue.episode_id
                        WHERE cue.user_series_id = us.id
                          AND cue.season_number > 0
                          AND IF(csbd.id IS NULL, cue.air_date <= CURDATE(), DATE(csbd.date) <= CURDATE())
                          AND cue.previous_occurrence_id IS NULL
                        )                                               as aired_episode_count,
                       us.last_watch_at                                 as series_last_watch_at,
                       ue.episode_number                                as episode_number,
                       ue.season_number                                 as season_number
                FROM series s
                         INNER JOIN user_series us ON s.id = us.series_id
                         INNER JOIN user_episode ue ON us.id = ue.user_series_id
                         LEFT JOIN series_broadcast_schedule sbs ON s.id = sbs.series_id AND sbs.`season_number`=ue.`season_number` AND IF(sbs.multi_part, ue.episode_number BETWEEN sbs.season_part_first_episode AND (sbs.season_part_first_episode + sbs.season_part_episode_count - 1), 1)
                         LEFT JOIN series_broadcast_date sbd ON sbd.series_broadcast_schedule_id = sbs.id AND sbd.episode_id = ue.episode_id
                         LEFT JOIN `series_watch_link` swl ON s.id = swl.`series_id`
                         LEFT JOIN watch_provider wp ON wp.provider_id = IF(sbs.`id`, sbs.provider_id, swl.`provider_id`)
                         LEFT JOIN series_localized_name sln ON s.id = sln.series_id AND sln.locale = '$locale'
                WHERE us.user_id = $userId
                  AND $dayCondition
                ORDER BY date, sbs.air_at, ue.season_number , ue.episode_number";
//        AND ue.season_number > 0

        return $this->getAll($sql);
    }

    public function episodesToWatch(User $user, string $locale = 'fr', int $page = 1, int $limit = 20): array
    {
        $userId = $user->getId();
        $offset = ($page - 1) * $limit;
        $sql = "SELECT s.id              as id,
                       s.tmdb_id         as tmdbId,
                       s.`name`          as name,
                       s.`slug`          as slug,
                       sln.`name`        as localizedName,
                       sln.`slug`        as localizedSlug,
                       s.`poster_path`   as posterPath,
                       us.`favorite`     as favorite,
                       us.`progress`     as progress,
                       ue.season_number  as seasonNumber,
                       ue.episode_number as episodeNumber
                FROM `user_series` us
                         INNER JOIN user_episode ue ON ue.`user_series_id` = us.`id`
                         LEFT JOIN `series` s ON s.`id` = us.`series_id`
                         LEFT JOIN `series_localized_name` sln ON sln.`series_id` = s.`id` AND sln.`locale` = '$locale'
                         LEFT JOIN series_broadcast_schedule sbs ON s.id = sbs.series_id AND sbs.season_number = ue.season_number AND IF(sbs.multi_part, ue.episode_number BETWEEN sbs.season_part_first_episode AND (sbs.season_part_first_episode + sbs.season_part_episode_count - 1), 1)
                         LEFT JOIN series_broadcast_date sbd ON sbd.series_broadcast_schedule_id = sbs.id AND sbd.episode_id = ue.episode_id
                WHERE us.`user_id` = $userId
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
                LIMIT $limit OFFSET $offset";

        return $this->getAll($sql);
    }

    public function episodesOfTheIntervalForTwig(User $user, string $start, string $end, string $locale = 'fr'): array
    {
        $userId = $user->getId();
        $sql = "SELECT
                     IF(sbd.id, DATE(sbd.date), ue.air_date) as airDate,
                     sbs.`override`                          as override,
                     'series'                                as type,
                     DATEDIFF(IF(sbd.id, DATE(sbd.date), ue.air_date), DATE(NOW())) as days,
                     s.id                                    as id, 
                     s.name                                  as name, 
                     s.poster_path                           as posterPath,
                     s.slug                                  as slug, 
                     sln.name                                as localizedName, 
                     sln.slug                                as localizedSlug, 
                     ue.`episode_number`                     as episodeNumber, 
                     ue.`season_number`                      as seasonNumber,
                     ue.`watch_at`                           as watchAt,
                     sbs.air_at                              as airAt,
                     sbd.date                                as customDate,
                     wp.provider_name                        as providerName,
                     wp.logo_path                            as providerLogoPath,
                     IF(ue.vote IS NULL, 0, ue.vote)         as vote,
                     IF(sln.name IS NULL, s.name, sln.name)  as displayName,
                     ((SELECT COUNT(*)
                      FROM `user_episode` ue1
                      WHERE ue1.`user_series_id`=ue.`user_series_id` AND ue1.`season_number`=ue.`season_number`) = ue.`episode_number`)
                      					                     as last_episode  
              FROM series s 
                     INNER JOIN user_series us ON s.id = us.series_id 
                     INNER JOIN user_episode ue ON us.id = ue.user_series_id 
                     LEFT JOIN series_localized_name sln ON s.id = sln.series_id AND sln.locale = '$locale'
                     LEFT JOIN series_broadcast_schedule sbs ON s.id = sbs.series_id AND sbs.season_number = ue.season_number AND IF(sbs.multi_part, ue.episode_number BETWEEN sbs.season_part_first_episode AND (sbs.season_part_first_episode + sbs.season_part_episode_count - 1), 1)
                     LEFT JOIN series_broadcast_date sbd ON sbd.series_broadcast_schedule_id = sbs.id AND sbd.episode_id = ue.episode_id
                     LEFT JOIN series_watch_link swl ON s.id = swl.`series_id`
                     LEFT JOIN watch_provider wp ON wp.provider_id = IF(sbs.`id`, sbs.provider_id, swl.`provider_id`)
              WHERE us.user_id = $userId
                     AND IF(sbd.id, DATE(sbd.date) >= '$start', ue.air_date >= '$start')
                     AND IF(sbd.id, DATE(sbd.date) <= '$end',   ue.air_date <= '$end')
              ORDER BY displayName ";
        //       (WHERE ...) AND ue.season_number > 0

        return $this->getAll($sql);
    }

    public function historyEpisode(User $user, int $dayCount, string $locale): array
    {
        $userId = $user->getId();
        $sql = "SELECT s.id                            as id,
                       s.tmdb_id                       as tmdbId,
                       IF(sln.`id`, CONCAT(sln.`name`, ' - ', s.`name`), s.`name`) as name,
                       IF(sln.`id`, sln.`slug`, s.`slug`)                          as slug,
                       sln.`name`                      as localizedName,
                       sln.`slug`                      as localizedSlug,
                       s.`poster_path`                 as posterPath,
                       ue.`watch_at`                   as watchAt,
                       ue.`quick_watch_day`            as qDay,
                       ue.`quick_watch_week`           as qWeek,
                       us.`favorite`                   as favorite,
                       us.`progress`                   as progress,
                       ue.`episode_number`             as episodeNumber,
                       ue.`season_number`              as seasonNumber,
                       wp.`provider_name`              as providerName,
                       wp.`logo_path`                  as providerLogoPath,
                       wp.provider_id                  as providerId,
                       (SELECT count(*)
                        FROM user_episode ue
                        WHERE ue.user_series_id = us.id
                          AND ue.season_number > 0
                          AND IF(sbs.override, DATE(sbd.date) <= CURDATE(), ue.air_date <= CURDATE())
                          AND ue.watch_at IS NOT NULL) as watched_aired_episode_count,
                       (SELECT count(*)
                        FROM user_episode ue
                        WHERE ue.user_series_id = us.id
                          AND ue.season_number > 0
                          AND IF(sbs.override, DATE(sbd.date) <= CURDATE(), ue.air_date <= CURDATE())
                       )                               as aired_episode_count
                FROM `user_episode` ue
                         INNER JOIN `user_series` us ON us.`id` = ue.`user_series_id`
                         INNER JOIN `series` s ON s.`id` = us.`series_id`
                         LEFT JOIN series_broadcast_schedule sbs ON s.id = sbs.series_id AND sbs.season_number = ue.season_number AND IF(sbs.multi_part, ue.episode_number BETWEEN sbs.season_part_first_episode AND (sbs.season_part_first_episode + sbs.season_part_episode_count - 1), 1)
                         LEFT JOIN series_broadcast_date sbd ON sbd.series_broadcast_schedule_id = sbs.id AND sbd.episode_id = ue.episode_id
                         LEFT JOIN `watch_provider` wp ON wp.`provider_id` = ue.`provider_id`
                         LEFT JOIN `series_localized_name` sln ON sln.`series_id` = s.`id` AND sln.`locale` = '$locale'
                WHERE ue.`user_id` = $userId
                  AND ue.`watch_at` IS NOT NULL
                  AND ue.`watch_at` >= DATE_SUB(NOW(), INTERVAL $dayCount DAY)
                ORDER BY ue.`watch_at` DESC";

        return $this->getAll($sql);
    }

    public function getEpisodeListBetweenDates($userId, $startDate, $endDate): array
    {
        $sql = "SELECT ue.`episode_number`, ue.`season_number`, ue.`user_series_id`, ue.`watch_at`
                FROM `user_episode` ue
                WHERE ue.`user_id`=$userId AND ue.`watch_at` BETWEEN '$startDate' AND '$endDate'
                ORDER BY ue.`watch_at` DESC";
        return $this->getAll($sql);
    }

    public function getUserEpisodesDB(int $userSeriesId, int $seasonNumber, string $locale, bool $all = false): array
    {
        $sql = "SELECT ue.id                     as id,
                       ue.episode_id             as episode_id,
                       esn.name                  as substitute_name,
                       elo.overview              as localized_overview,
                       ue.episode_number         as episode_number,
                       ue.watch_at               as watch_at,
                       ue.air_date               as air_date,
                       sbd.date                  as custom_date,
                       sbs.air_at                as air_at,
                       /*IF(sbs.air_at, STR_TO_DATE(CONCAT(ue.`air_date`, ' ', sbs.air_at), '%Y-%m-%d %H:%i:%s'), NULL) as date_string,*/
                       ue.provider_id            as provider_id,
                       wp.provider_name          as provider_name,
                       wp.logo_path              as provider_logo_path,
                       ue.device_id              as device_id,
                       d.name                    as device_name,
                       d.logo_path               as device_logo_path,
                       d.svg                     as device_svg,
                       ue.vote                   as vote,
                       ue.previous_occurrence_id as previous_occurrence_id
                FROM user_episode ue
                         LEFT JOIN user_series us ON ue.user_series_id = us.id
                         LEFT JOIN series s ON us.series_id = s.id
                         LEFT JOIN series_broadcast_schedule sbs ON sbs.series_id = s.id AND sbs.season_number = ue.season_number AND IF(sbs.multi_part, ue.episode_number BETWEEN sbs.season_part_first_episode AND (sbs.season_part_first_episode + sbs.season_part_episode_count -1), 1)
                         LEFT JOIN series_broadcast_date sbd ON ue.episode_id = sbd.episode_id
                         LEFT JOIN episode_substitute_name esn ON ue.episode_id = esn.episode_id
                         LEFT JOIN episode_localized_overview elo ON ue.episode_id = elo.episode_id AND elo.locale = '$locale'
                         LEFT JOIN watch_provider wp ON ue.provider_id = wp.provider_id
                         LEFT JOIN device d ON ue.device_id = d.id
                WHERE ue.user_series_id = $userSeriesId
                  AND ue.season_number = $seasonNumber";
        if (!$all) $sql .= "                  AND ue.previous_occurrence_id IS NULL";

        return $this->getAll($sql);
    }

    public function getUserEpisodeDB(int $userEpisodeId, string $locale): array
    {
        $sql = "SELECT ue.id                     as id,
                       ue.episode_id             as episode_id,
                       esn.name                  as substitute_name,
                       elo.overview              as localized_overview,
                       ue.episode_number         as episode_number,
                       ue.watch_at               as watch_at,
                       ue.air_date               as air_date,
                       sbd.date                  as custom_date,
                       sbs.air_at                as air_at,
                       /*IF(sbs.air_at, STR_TO_DATE(CONCAT(ue.`air_date`, ' ', sbs.air_at), '%Y-%m-%d %H:%i:%s'), NULL) as date_string,*/
                       ue.provider_id            as provider_id,
                       wp.provider_name          as provider_name,
                       wp.logo_path              as provider_logo_path,
                       ue.device_id              as device_id,
                       d.name                    as device_name,
                       d.logo_path               as device_logo_path,
                       d.svg                     as device_svg,
                       ue.vote                   as vote,
                       ue.previous_occurrence_id as previous_occurrence_id
                FROM user_episode ue
                         LEFT JOIN user_series us ON ue.user_series_id = us.id
                         LEFT JOIN series s ON us.series_id = s.id
                         LEFT JOIN series_broadcast_schedule sbs ON sbs.series_id = s.id
                         LEFT JOIN series_broadcast_date sbd ON ue.episode_id = sbd.episode_id
                         LEFT JOIN episode_substitute_name esn ON ue.episode_id = esn.episode_id
                         LEFT JOIN episode_localized_overview elo ON ue.episode_id = elo.episode_id AND elo.locale = '$locale'
                         LEFT JOIN watch_provider wp ON ue.provider_id = wp.provider_id
                         LEFT JOIN device d ON ue.device_id = d.id
                WHERE ue.id = $userEpisodeId";

        $result = $this->getAll($sql);
        return $result[0] ?? [];
    }

    public function inProgressSeriesForTwig(User $user, string $locale): array
    {
        $userId = $user->getId();
        $sql = "SELECT s.`id` as id,
                       s.poster_path as posterPath, 
                       IF(sln.`name` IS NOT NULL, sln.`name`, s.`name`) as name,
                       /*IF(sln.`name` IS NOT NULL, CONCAT(sln.`name`, ' - ', s.`name`), s.`name`) as name,*/
                       IF(sln.`name` IS NOT NULL, sln.`slug`, s.`slug`) as slug,
                       ue.episode_id as episodeId,
                       ue.`season_number` as nextEpisodeSeason,
                       (SELECT ue1.`episode_number`
                        FROM `user_episode` ue1
                        WHERE ue1.`user_series_id`=us.`id` AND ue1.`season_number`=ue.`season_number` AND ue1.`watch_at` IS NULL
                        ORDER BY ue1.`episode_id` LIMIT 1) as nextEpisodeNumber,
                       (SELECT COUNT(*)
                        FROM `user_episode` ue2
                        WHERE ue2.`user_series_id`=us.`id` AND ue2.`season_number`=ue.`season_number`) as seasonEpisodeCount,
                       (SELECT COUNT(*)
                        FROM `user_episode` ue3
                        WHERE ue3.`user_series_id`=us.`id` AND ue3.`season_number`=ue.`season_number` AND ue3.`watch_at` IS NOT NULL) as seasonViewedEpisodeCount
                FROM `user_episode` ue
                LEFT JOIN `user_series` us ON us.`id`=ue.`user_series_id`
                LEFT JOIN `series` s ON s.`id`=us.`series_id`
                LEFT JOIN `series_localized_name` sln ON sln.`series_id`=s.`id` AND sln.`locale`='$locale'
                WHERE ue.`user_id`=$userId AND ue.`watch_at` IS NOT NULL
                ORDER BY ue.`watch_at` DESC
                LIMIT 1";

        return $this->getAll($sql);
    }

    public function seasonProgress(UserSeries $userSeries, int $seasonNumber): ?float
    {
        $userSeriesId = $userSeries->getId();
        $sql = "SELECT
                    (
                     SELECT COUNT(*)
                        FROM `user_episode` ue
                        WHERE ue.`user_series_id`=$userSeriesId AND ue.`season_number`=$seasonNumber AND ue.previous_occurrence_id IS NULL
                     ) as episodeCount,
                    (
                     SELECT COUNT(*)
                        FROM `user_episode` ue
                        WHERE ue.`user_series_id`=$userSeriesId AND ue.`season_number`=$seasonNumber AND ue.`watch_at` IS NOT NULL AND ue.previous_occurrence_id IS NULL
                     ) as episodeWatchedCount";

        $result = $this->getAll($sql);
        $result = $result[0] ?? null;

        return $result ? $result['episodeWatchedCount'] / $result['episodeCount'] * 100 : null;
    }

    public function getViewedEpisodes(UserSeries $userSeries, int $seasonNumber): int
    {
        $userSeriesId = $userSeries->getId();
        $sql = "
                SELECT COUNT(*)
                FROM `user_episode` ue
                WHERE ue.`user_series_id`=$userSeriesId
                  AND ue.`season_number`=$seasonNumber
                  AND ue.watch_at IS NOT NULL
                  AND ue.previous_occurrence_id IS NULL";

        return $this->getOne($sql);
    }

    public function getSeasonEpisodeIds(int $userSeriesId, int $seasonNumber): array
    {
        $sql = "SELECT ue.episode_id
                FROM user_episode ue
                WHERE ue.user_series_id = $userSeriesId
                  AND ue.season_number = $seasonNumber
                  AND ue.previous_occurrence_id IS NULL
                ORDER BY ue.episode_number";

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
