<?php /** @noinspection PhpMultipleClassDeclarationsInspection */

namespace App\Repository;

use App\Entity\Series;
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

    public function historySeries(User $user, string $country, string $locale, int $page, int $perPage): array
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
                    WHERE ue.user_series_id = us.id
                      AND ue.season_number > 0
                      AND (
                        ((sdo.offset IS NULL OR sdo.offset = 0) AND ue.`air_date` <= CURDATE())
                            OR ((sdo.offset > 0) AND ue.`air_date` <= DATE_SUB(CURDATE(), INTERVAL sdo.offset DAY))
                            OR ((sdo.offset < 0) AND ue.`air_date` <= DATE_ADD(CURDATE(), INTERVAL ABS(sdo.offset) DAY))
                        )
                      AND ue.watch_at IS NOT NULL) as watched_aired_episode_count,
                   (SELECT count(*)
                    FROM user_episode ue
                    WHERE ue.user_series_id = us.id
                      AND ue.season_number > 0
                      AND (
                        ((sdo.offset IS NULL OR sdo.offset = 0) AND ue.`air_date` <= CURDATE())
                            OR ((sdo.offset > 0) AND ue.`air_date` <= DATE_SUB(CURDATE(), INTERVAL sdo.offset DAY))
                            OR ((sdo.offset < 0) AND ue.`air_date` <= DATE_ADD(CURDATE(), INTERVAL ABS(sdo.offset) DAY))
                        ))                         as aired_episode_count
            FROM `user_series` us
                     INNER JOIN `series` s ON s.`id` = us.`series_id`
                     LEFT JOIN `series_day_offset` sdo ON s.id = sdo.series_id AND sdo.country = '$country'
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

    public function getScheduleNextEpisode(User $user, Series $series, int $seasonNumber, int $firstEpisodeNumber, int $lastEpisodeNumber): array
    {
        $userId = $user->getId();
        $seriesId = $series->getId();
        $country = $user->getCountry() ?? 'FR';

        $sql = "SELECT ue.`season_number`,
                       ue.`episode_number`,
                       ue.`air_date`,
                       IF(sdo.offset IS NULL, ue.`air_date`, DATE_ADD(ue.`air_date`, INTERVAL sdo.offset DAY)) as air_date_offset
                FROM user_episode ue
                INNER JOIN user_series us ON ue.`user_series_id` = us.`id`
                INNER JOIN series s ON us.`series_id` = s.`id`
                LEFT JOIN series_day_offset sdo ON s.id = sdo.series_id AND sdo.country = '$country'
                WHERE us.`user_id` = $userId
                  AND s.`id` = $seriesId
                  AND ue.`season_number` = $seasonNumber
                  AND ue.episode_number >= $firstEpisodeNumber AND ue.episode_number <= $lastEpisodeNumber
                  AND ue.`watch_at` IS NULL
                ORDER BY  ue.`season_number`, ue.`episode_number`
                LIMIT 1";

        return $this->getAll($sql);
    }

    public function getScheduleNextEpisodes(User $user, Series $series, $airDate, int $seasonNumber, int $firstEpisodeNumber, int $lastEpisodeNumber): array
    {
        $userId = $user->getId();
        $seriesId = $series->getId();
        $country = $user->getCountry() ?? 'FR';

        $sql = "SELECT ue.`season_number`,
                       ue.`episode_number`,
                       ue.`air_date`,
                       IF(sdo.offset IS NULL, ue.`air_date`, DATE_ADD(ue.`air_date`, INTERVAL sdo.offset DAY)) as air_date_offset
                FROM user_episode ue
                INNER JOIN user_series us ON ue.`user_series_id` = us.`id`
                INNER JOIN series s ON us.`series_id` = s.`id`
                LEFT JOIN series_day_offset sdo ON s.id = sdo.series_id AND sdo.country = '$country'
                WHERE us.`user_id` = $userId
                  AND s.`id` = $seriesId
                  AND ue.`season_number` = $seasonNumber
                  AND ue.episode_number >= $firstEpisodeNumber AND ue.episode_number <= $lastEpisodeNumber
                  AND ue.`air_date` = '$airDate'
                ORDER BY  ue.`season_number`, ue.`episode_number`";

        return $this->getAll($sql);
    }

    public function getScheduleLastEpisode(User $user, Series $series, int $seasonNumber, int $firstEpisodeNumber, int $lastEpisodeNumber): array
    {
        $userId = $user->getId();
        $seriesId = $series->getId();
        $country = $user->getCountry() ?? 'FR';

        $sql = "SELECT ue.`season_number`,
                       ue.`episode_number`,
                       ue.`air_date`,
                       IF(sdo.offset IS NULL, ue.`air_date`, DATE_ADD(ue.`air_date`, INTERVAL sdo.offset DAY)) as air_date_offset,
                       ue.`watch_at`
                FROM user_episode ue
                INNER JOIN user_series us ON ue.`user_series_id` = us.`id`
                INNER JOIN series s ON us.`series_id` = s.`id`
                LEFT JOIN series_day_offset sdo ON s.id = sdo.series_id AND sdo.country = '$country'
                WHERE us.`user_id` = $userId
                  AND s.`id` = $seriesId
                  AND ue.`season_number` = $seasonNumber
                  AND ue.episode_number >= $firstEpisodeNumber AND ue.episode_number <= $lastEpisodeNumber
                  AND ue.`watch_at` IS NOT NULL
                ORDER BY  ue.`season_number` DESC, ue.`episode_number` DESC
                LIMIT 1";

        return $this->getAll($sql);
    }

    public function episodesOfTheDay(User $user, string $country = 'FR', string $locale = 'fr'): array
    {
        $userId = $user->getId();
        $sql = "SELECT s.id                            as id,
                       s.tmdb_id                       as tmdbId,
                       CURDATE()                       as date,
                       s.name                          as name,
                       s.slug                          as slug,
                       sln.name                        as localizedName,
                       sln.slug                        as localizedSlug,
                       s.poster_path                   as posterPath,
                       s.status                        as status,
                       (s.first_air_date <= NOW())     as released,
                       us.favorite                     as favorite,
                       us.progress                     as progress,
                       ue.`episode_number`             as episodeNumber,
                       ue.`season_number`              as seasonNumber,
                       ue.`watch_at`                   as watchAt,
                       sbs.`air_at`                    as airAt,
                       sbs.`provider_id`               as providerId,
                       wp.`provider_name`              as providerName,
                       wp.`logo_path`                  as providerLogoPath,
                       (SELECT count(*)
                        FROM user_episode cue
                        WHERE cue.user_series_id = us.id
                          AND cue.season_number > 0
                          AND (
                            ((sdo.offset IS NULL OR sdo.offset = 0) AND cue.air_date <= CURDATE())
                                OR ((sdo.offset > 0) AND cue.air_date <= DATE_SUB(CURDATE(), INTERVAL sdo.offset DAY))
                                OR ((sdo.offset < 0) AND cue.air_date <= DATE_ADD(CURDATE(), INTERVAL ABS(sdo.offset) DAY))
                            )
                          AND cue.watch_at IS NOT NULL) as watched_aired_episode_count,
                       (SELECT count(*)
                        FROM user_episode cue
                        WHERE cue.user_series_id = us.id
                          AND cue.season_number > 0
                          AND (
                            ((sdo.offset IS NULL OR sdo.offset = 0) AND cue.air_date <= CURDATE())
                                OR ((sdo.offset > 0) AND cue.air_date <= DATE_SUB(CURDATE(), INTERVAL sdo.offset DAY))
                                OR ((sdo.offset < 0) AND cue.air_date <= DATE_ADD(CURDATE(), INTERVAL ABS(sdo.offset) DAY))
                            ))                         as aired_episode_count,
                       (SELECT count(*)
                        FROM user_episode cue
                        WHERE cue.user_series_id = us.id
                          AND cue.season_number = ue.season_number
                          AND cue.air_date = ue.air_date
                            )                          as released_episode_count,
                       us.last_watch_at                as last_watch_at,
                       ue.episode_number               as episodeNumber,
                       ue.season_number                as seasonNumber
                FROM series s
                         INNER JOIN user_series us ON s.id = us.series_id
                         INNER JOIN user_episode ue ON us.id = ue.user_series_id
                         LEFT JOIN series_day_offset sdo ON s.id = sdo.series_id AND sdo.country = '$country'
                         LEFT JOIN series_broadcast_schedule sbs ON s.id = sbs.series_id
                         LEFT JOIN series_broadcast_date sbd ON sbd.series_broadcast_schedule_id = sbs.id AND sbd.episode_id = ue.episode_id
                         LEFT JOIN watch_provider wp ON sbs.provider_id = wp.provider_id
                         LEFT JOIN series_localized_name sln ON s.id = sln.series_id AND sln.locale = '$locale'
                WHERE us.user_id = $userId
                  AND 
                    (
                        (
                        ((sdo.offset IS NULL OR sdo.offset = 0) AND ue.air_date = CURDATE())
                            OR ((sdo.offset > 0) AND ue.air_date = DATE_SUB(CURDATE(), INTERVAL sdo.offset DAY))
                            OR ((sdo.offset < 0) AND ue.air_date = DATE_ADD(CURDATE(), INTERVAL ABS(sdo.offset) DAY))
                        )
                     OR (
                         sbd.id IS NOT NULL
                         AND DATE(sbd.date) = CURDATE()
                        )
                    )
                ORDER BY sbs.air_at, ue.season_number , ue.episode_number";
//        AND ue.season_number > 0

        return $this->getAll($sql);
    }

    public function episodesToWatch(User $user, string $country = 'FR', string $locale = 'fr'): array
    {
        $userId = $user->getId();
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
                         LEFT JOIN `series_day_offset` sdo ON s.id = sdo.series_id AND sdo.country = '$country'
                         LEFT JOIN `series_localized_name` sln ON sln.`series_id` = s.`id` AND sln.`locale` = '$locale'
                WHERE us.`user_id` = $userId
                  AND us.progress < 100
                  AND ue.id=(SELECT ue2.id
                             FROM user_episode ue2
                             WHERE ue2.user_series_id = us.id
                               AND ue2.`watch_at` IS NULL
                               AND ue2.season_number > 0
                               AND (
                                 ((sdo.offset IS NULL OR sdo.offset = 0) AND ue2.`air_date` <= CURDATE())
                                     OR ((sdo.offset > 0) AND ue2.`air_date` <= DATE_SUB(CURDATE(), INTERVAL sdo.offset DAY))
                                     OR ((sdo.offset < 0) AND ue2.`air_date` <= DATE_ADD(CURDATE(), INTERVAL ABS(sdo.offset) DAY))
                                 )
                             ORDER BY ue2.air_date
                             LIMIT 1)
                  AND us.progress > 0
                ORDER BY us.`last_watch_at` DESC
                LIMIT 20 OFFSET 0";

        return $this->getAll($sql);
    }

    public function episodesOfTheDayForTwig(User $user, string $day, string $country = 'FR', string $locale = 'fr'): array
    {
        $userId = $user->getId();
        $sql = "SELECT 
                     s.id                                   as id, 
                     s.name                                 as name, 
                     s.poster_path                          as posterPath,
                     s.slug                                 as slug, 
                     sln.name                               as localizedName, 
                     sln.slug                               as localizedSlug, 
                     ue.`episode_number`                    as episodeNumber, 
                     ue.`season_number`                     as seasonNumber,
                     ue.`watch_at`                          as watchAt,
                     sbs.air_at                             as airAt,
                     sbd.date                               as customDate,
                     p.name                                 as providerName,
                     p.logo_path                            as providerLogoPath,
                     IF(ue.vote IS NULL, 0, ue.vote)        as vote,
                     IF(sln.name IS NULL, s.name, sln.name) as displayName 
              FROM series s 
                     INNER JOIN user_series us ON s.id = us.series_id 
                     INNER JOIN user_episode ue ON us.id = ue.user_series_id 
                     LEFT JOIN series_day_offset sdo ON s.id = sdo.series_id AND sdo.country = '$country'
                     LEFT JOIN series_localized_name sln ON s.id = sln.series_id AND sln.locale = '$locale'
                     LEFT JOIN series_broadcast_schedule sbs ON s.id = sbs.series_id AND sbs.season_number = ue.season_number
                     LEFT JOIN series_broadcast_date sbd ON sbd.series_broadcast_schedule_id = sbs.id AND sbd.episode_id = ue.episode_id
                     LEFT JOIN provider p ON sbs.provider_id = p.provider_id
              WHERE us.user_id = $userId
                     AND (
                            (
                             sbd.id IS NULL
                             AND ( 
                                 ((sdo.offset IS NULL OR sdo.offset = 0) AND ue.air_date = '$day') 
                              OR ((sdo.offset > 0) AND ue.air_date = DATE_SUB('$day', INTERVAL sdo.offset DAY)) 
                              OR ((sdo.offset < 0) AND ue.air_date = DATE_ADD('$day', INTERVAL ABS(sdo.offset) DAY)) 
                                 ) 
                             )
                         OR (
                             sbd.id IS NOT NULL
                             AND DATE(sbd.date) = '$day'
                            )
                         )
              ORDER BY displayName ";
        //       (WHERE ...) AND ue.season_number > 0

        return $this->getAll($sql);
    }

    public function historyEpisode(User $user, int $dayCount, string $country, string $locale): array
    {
        $userId = $user->getId();
        $sql = "SELECT s.id                            as id,
                       s.tmdb_id                       as tmdbId,
                       IF(sln.`name` IS NOT NULL, CONCAT(sln.`name`, ' - ', s.`name`), s.`name`) as name,
                       IF(sln.`slug` IS NOT NULL, sln.`slug`, s.`slug`)                          as slug,
                       /*s.`name`                        as name,*/
                       /*s.`slug`                        as slug,*/
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
                       p.`name`                        as providerName,
                       p.`logo_path`                   as providerLogoPath,
                       p.provider_id                   as providerId,
                       sdo.offset                      as dayOffset,
                       (SELECT count(*)
                        FROM user_episode ue
                        WHERE ue.user_series_id = us.id
                          AND ue.season_number > 0
                          AND (
                            ((sdo.offset IS NULL OR sdo.offset = 0) AND ue.`air_date` <= CURDATE())
                                OR ((sdo.offset > 0) AND ue.`air_date` <= DATE_SUB(CURDATE(), INTERVAL sdo.offset DAY))
                                OR ((sdo.offset < 0) AND ue.`air_date` <= DATE_ADD(CURDATE(), INTERVAL ABS(sdo.offset) DAY))
                            )
                          AND ue.watch_at IS NOT NULL) as watched_aired_episode_count,
                       (SELECT count(*)
                        FROM user_episode ue
                        WHERE ue.user_series_id = us.id
                          AND ue.season_number > 0
                          AND (
                            ((sdo.offset IS NULL OR sdo.offset = 0) AND ue.`air_date` <= CURDATE())
                                OR ((sdo.offset > 0) AND ue.`air_date` <= DATE_SUB(CURDATE(), INTERVAL sdo.offset DAY))
                                OR ((sdo.offset < 0) AND ue.`air_date` <= DATE_ADD(CURDATE(), INTERVAL ABS(sdo.offset) DAY))
                            ))                         as aired_episode_count
                FROM `user_episode` ue
                         INNER JOIN `user_series` us ON us.`id` = ue.`user_series_id`
                         INNER JOIN `series` s ON s.`id` = us.`series_id`
                         LEFT JOIN `series_day_offset` sdo ON s.id = sdo.series_id AND sdo.country = '$country'
                         LEFT JOIN `provider` p ON p.`provider_id` = ue.`provider_id`
                         LEFT JOIN `series_localized_name` sln ON sln.`series_id` = s.`id` AND sln.`locale` = '$locale'
                WHERE ue.`user_id` = $userId
                  AND ue.`watch_at` IS NOT NULL
                  AND ue.`watch_at` >= DATE_SUB(NOW(), INTERVAL $dayCount DAY)
                ORDER BY ue.`watch_at` DESC";

        return $this->getAll($sql);
    }

    public function getEpisodeListBetweenIds($userId, $startId, $endId): array
    {
        $sql = "SELECT ue.`episode_number`, ue.`season_number`, ue.`user_series_id`, ue.`watch_at` "
            . "FROM `user_episode` ue "
            . "WHERE ue.`user_id`=$userId AND ue.`id` BETWEEN $startId AND $endId "
            . "ORDER BY ue.`watch_at` DESC";
        return $this->getAll($sql);
    }

    public function getUserEpisodesDB(int $userSeriesId, int $seasonNumber, string $locale): array
    {
        $sql = "SELECT ue.id             as id,
                       ue.episode_id     as episode_id,
                       esn.name          as substitute_name,
                       elo.overview      as localized_overview,
                       ue.episode_number as episode_number,
                       ue.watch_at       as watch_at,
                       ue.air_date       as air_date,
                       sbd.date          as custom_date,
                       ue.provider_id    as provider_id,
                       p.name            as provider_name,
                       p.logo_path       as provider_logo_path,
                       ue.device_id      as device_id,
                       d.name            as device_name,
                       d.logo_path       as device_logo_path,
                       d.svg             as device_svg,
                       ue.vote           as vote,
                       ue.number_of_view as number_of_view,
                       ue.still          as still
                FROM user_episode ue
                         LEFT JOIN series_broadcast_date sbd ON ue.episode_id = sbd.episode_id
                         LEFT JOIN episode_substitute_name esn ON ue.episode_id = esn.episode_id
                         LEFT JOIN episode_localized_overview elo ON ue.episode_id = elo.episode_id AND elo.locale = '$locale'
                         LEFT JOIN provider p ON ue.provider_id = p.provider_id
                         LEFT JOIN device d ON ue.device_id = d.id
                WHERE ue.user_series_id = $userSeriesId
                  AND ue.season_number = $seasonNumber";

        return $this->getAll($sql);
    }

    public function inProgressSeriesForTwig(User $user, string $locale): array
    {
        $userId = $user->getId();
        $sql = "SELECT s.`id` as id,
                       s.poster_path as posterPath, 
                       IF(sln.`name` IS NOT NULL, CONCAT(sln.`name`, ' - ', s.`name`), s.`name`) as name,
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
                        WHERE ue.`user_series_id`=$userSeriesId AND ue.`season_number`=$seasonNumber
                     ) as episodeCount,
                    (
                     SELECT COUNT(*)
                        FROM `user_episode` ue
                        WHERE ue.`user_series_id`=$userSeriesId AND ue.`season_number`=$seasonNumber AND ue.`watch_at` IS NOT NULL
                     ) as episodeWatchedCount";

        $result = $this->getAll($sql);
        $result = $result[0] ?? null;

        return $result ? $result['episodeWatchedCount'] / $result['episodeCount'] * 100 : null;
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
