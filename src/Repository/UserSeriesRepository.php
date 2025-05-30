<?php /** @noinspection PhpMultipleClassDeclarationsInspection */

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserSeries;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
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

//    public function getLastWatchedUserSeries(User $user, $locale, int $page = 1, int $perPage = 20): array
//    {
//        $userId = $user->getId();
//        $sql = "SELECT s.`id` as id, s.`name` as name, sln.`name` as localized_name, us.`progress` as progress, "
//            . "	    ue.`episode_number` as last_episode, ue.`season_number` as last_season, ue.`watch_at` as last_watch_at, "
//            . "     s.`slug` as slug, sln.`slug` as localized_slug, "
//            . "     s.`poster_path` as poster_path "
//            . "FROM `user_series` us "
//            . "INNER JOIN `series` s ON s.`id`=us.`series_id` "
//            . "INNER JOIN `user_episode` ue ON ue.`user_series_id`=us.`id` "
//            . "LEFT JOIN `series_localized_name` sln ON sln.`series_id`=s.`id` AND sln.`locale`='$locale' "
//            . "WHERE us.`user_id`=$userId "
//            . "     AND ue.`watch_at` IS NOT NULL "
//            . "ORDER BY ue.`watch_at` DESC "
//            . "LIMIT " . $perPage . " OFFSET " . ($page - 1) * $perPage;
//
//        return $this->getAll($sql);
//    }

    public function userSeriesTMDBIds(User $user): array
    {
        $userId = $user->getId();
        $sql = "SELECT s.tmdb_id as id
                FROM series s
                         INNER JOIN user_series us ON s.id = us.series_id
                WHERE us.user_id=$userId";

        return $this->getAll($sql);
    }

    public function getUserSeriesOfTheNext7Days(User $user, string $locale): array
    {
        $userId = $user->getId();
        $sql = "SELECT
                    s.`id`                                              as id,
                    s.`tmdb_id`                                         as tmdb_id,
                    s.`name`                                            as name,
                    sln.`name`                                          as localized_name,
                    us.`progress`                                       as progress,
                    IF(sbd.id IS NULL, ue.`air_date`, DATE(sbd.`date`)) as air_date,
                    ue.`air_date`                                       as original_air_date,
                    ue.`season_number`                                  as season_number,
                    ue.`episode_number`                                 as episode_number,
                    ue.watch_at                                         as watch_at,
                    us.`last_episode`                                   as last_episode,
                    us.`last_season`                                    as last_season,
                    s.`slug` as slug, sln.`slug`                        as localized_slug,
                    s.`poster_path`                                     as poster_path,
                    (s.first_air_date <= NOW())                         as released,
                    sbs.`air_at`                                        as air_at,
                    IF(sbs.`id`, sbs.provider_id, swl.`provider_id`)    as provider_id,
                    wp.`provider_name`                                  as provider_name,
                    wp.`logo_path`                                      as provider_logo_path,
                    s.`status`                                          as status,
                    (SELECT count(*)
                        FROM user_episode cue
                        WHERE cue.user_series_id = us.id
                          AND cue.season_number = ue.season_number
                          AND cue.air_date = ue.air_date
                            )                                           as released_episode_count
                FROM `series` s
                    INNER JOIN `user_series` us ON s.`id`=us.`series_id`
                    INNER JOIN `user_episode` ue on us.`id` = ue.`user_series_id`
                    LEFT JOIN series_broadcast_schedule sbs ON s.id = sbs.series_id
                    LEFT JOIN series_broadcast_date sbd ON sbd.series_broadcast_schedule_id = sbs.id AND sbd.episode_id = ue.episode_id
                    LEFT JOIN `series_watch_link` swl ON s.id = swl.`series_id`
                    LEFT JOIN watch_provider wp ON wp.provider_id = IF(sbs.`id`, sbs.provider_id, swl.`provider_id`)
                    LEFT JOIN `series_localized_name` sln ON sln.`series_id`=s.`id` AND sln.`locale`='$locale'
                WHERE us.`user_id`=$userId
                    AND IF(sbd.id IS NULL, ue.`air_date` > CURDATE() AND ue.`air_date` <= ADDDATE(CURDATE(), INTERVAL 7 DAY), DATE(sbd.date) > CURDATE() AND DATE(sbd.date) <= ADDDATE(CURDATE(), INTERVAL 7 DAY))
                ORDER BY air_date, air_at, ue.season_number, ue.episode_number";

        return $this->getAll($sql);
    }

    public function getUserSeries(User $user, string $locale, int $page = 1, int $perPage = 20): array
    {
        $userId = $user->getId();
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT 
                    s.`id`                      as id,
                    s.`name`                    as name,
                    s.`poster_path`             as poster_path, 
                    s.`tmdb_id`                 as tmdbId,
                    s.`slug`                    as slug,
                    s.status                    as status,
                    (s.first_air_date <= NOW()) as released,
                    us.`user_id`                as user_id, 
                    us.`progress`               as progress,
                    us.`favorite`               as favorite, 
                    sln.`name`                  as localized_name,
                    sln.`slug`                  as localized_slug 
                FROM `user_series` us 
                    INNER JOIN `series` s ON s.`id` = us.`series_id` 
                    LEFT JOIN `series_localized_name` sln ON s.`id` = sln.`series_id` AND sln.locale='$locale' 
            WHERE us.user_id=$userId 
            ORDER BY s.`first_air_date` DESC 
            LIMIT $perPage OFFSET $offset";

        return $this->getAll($sql);
    }

    public function seriesToStart(User $user, string $locale, string $order,  int $page, int $perPage): array
    {
        $userId = $user->getId();
        $offset = ($page - 1) * $perPage;
        match ($order) {
            'addedAt' => $order = 'us.`added_at`',
            default => $order = 's.`first_air_date`'
        };
        $sql = "SELECT s.id                                                                      as id,
                       s.tmdb_id                                                                 as tmdb_id,
                       IF(sln.`name` IS NOT NULL, CONCAT(sln.`name`, ' - ', s.`name`), s.`name`) as name,
                       IF(sln.`slug` IS NOT NULL, sln.`slug`, s.`slug`)                          as slug,
                       s.`poster_path`                                                           as poster_path,
                       s.`first_air_date`                                                        as final_air_date,
                       swl.`name`	                                                             as link_name,
                       wp.`logo_path`                                                            as provider_logo_path,
                       wp.`provider_name`                                                        as provider_name,
                       (SELECT COUNT(*)
                            FROM `user_episode` ue
                            WHERE ue.`user_series_id`=us.id)                                     as number_of_episode
                FROM `series` s
                INNER JOIN `user_series` us ON us.series_id=s.id AND us.`progress`=0
                LEFT JOIN `series_localized_name` sln ON sln.`series_id`=s.id AND sln.`locale`='$locale'
                LEFT JOIN `series_watch_link` swl ON swl.`series_id`=s.id
                LEFT JOIN `watch_provider` wp ON wp.`provider_id`=swl.`provider_id`
                WHERE s.`first_air_date` <= NOW() AND us.user_id=$userId
                ORDER BY $order DESC ";
        if ($perPage > 0) $sql .= "LIMIT $perPage OFFSET $offset";

        return $this->getAll($sql);
    }

    public function upComingSeries(User $user, string $locale, string $order, int $page, int $perPage): array
    {
        $userId = $user->getId();
        $offset = ($page - 1) * $perPage;
        match ($order) {
            'addedAt' => $order = 'us.`added_at`',
            default => $order = 's.`first_air_date`'
        };
        $sql = "SELECT s.id                                                                      as id,
                       s.tmdb_id                                                                 as tmdb_id,
                       IF(sln.`name` IS NOT NULL, CONCAT(sln.`name`, ' - ', s.`name`), s.`name`) as name,
                       IF(sln.`slug` IS NOT NULL, sln.`slug`, s.`slug`)                          as slug,
                       s.`poster_path`                                                           as poster_path,
                       s.`first_air_date`                                                        as final_air_date
                FROM `series` s
                INNER JOIN `user_series` us ON us.series_id=s.id
                LEFT JOIN `series_localized_name` sln ON sln.`series_id`=s.id AND sln.`locale`='$locale'
                WHERE (s.`first_air_date` > NOW() OR s.first_air_date IS NULL) AND us.user_id=$userId
                ORDER BY $order DESC ";
        if ($perPage > 0) $sql .= "LIMIT $perPage OFFSET $offset";

        return $this->getAll($sql);
    }

    public function seriesToStartCount(User $user, string $locale): int
    {
        $userId = $user->getId();
        $sql = "SELECT COUNT(*) as count
                FROM `series` s
                INNER JOIN `user_series` us ON us.series_id=s.id
                LEFT JOIN `series_localized_name` sln ON sln.`series_id`=s.id AND sln.`locale`='$locale'
                WHERE s.`first_air_date` <= NOW() AND us.user_id=$userId AND us.`progress`=0";

        return $this->getOne($sql);
    }

    public function seriesNotSeenInAWhile(User $user, string $locale, string $date, int $page, int $perPage): array
    {
        $userId = $user->getId();
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT
                    s.id                                                                      as `id`,
                    s.tmdb_id                                                                 as `tmdb_id`,
                    IF(sln.`name` IS NOT NULL, CONCAT(sln.`name`, ' - ', s.`name`), s.`name`) as `name`,
                    IF(sln.`slug` IS NOT NULL, sln.`slug`, s.`slug`)                          as `slug`,
                    s.`poster_path`                                                           as `poster_path`,
                    us.`last_season`                                                          as `last_season`,
                    us.`last_episode`                                                         as `last_episode`,
                    us.`last_watch_at`                                                        as `last_viewed_at`
                FROM `user_series` us
                LEFT JOIN `series` s ON us.`series_id`=s.`id`
                LEFT JOIN `series_localized_name` sln ON sln.`series_id`=s.`id` AND sln.locale='$locale'
                WHERE us.`user_id`=$userId
                    AND us.`progress` > 0
                    AND us.`progress` < 100
                    AND us.`last_watch_at` <= '$date'
                ORDER BY us.`last_watch_at` DESC";
        if ($perPage > 0) $sql .= "LIMIT $perPage OFFSET $offset";

        return $this->getAll($sql);
    }

    public function getAllSeries(
        User $user,
        array $localisation,
        array $filters): array
    {
        $page = intval($filters['page'] ?? 1);
        $perPage = intval($filters['perPage'] ?? 20);
        $sort = $filters['sort'] ?? 'firstAirDate';
        $order = $filters['order'] ?? 'ASC';
        $network = $filters['network'];

        $sort = match ($sort) {
            'lastWatched' => 'us.`last_watch_at`',
            'episodeAirDate' => 'lue.`air_date`',
            'name' => 's.`name`',
            'addedAt' => 'us.`added_at`',
            default => 's.`first_air_date`',
        };
        if ($network !== 'all') {
            $innerJoin = " INNER JOIN series_network sn ON sn.`network_id` = $network AND sn.`series_id` = s.`id` ";
        } else {
            $innerJoin = '';
        }
        $userId = $user->getId();
        $locale = $localisation['locale'];
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT s.`id`                                         as id,
                    s.`name`                                       as name,
                    s.`poster_path`                                as poster_path, 
                    s.`tmdb_id`                                    as tmdbId,
                    s.`slug`                                       as slug,
                    s.`status`                                     as status,
                    (s.first_air_date <= NOW())                    as released,
                    us.`user_id`                                   as user_id, 
                    us.`added_at`                                  as added_at,
                    us.`progress`                                  as progress,
                    us.`favorite`                                  as favorite, 
                    sln.`name`                                     as localized_name,
                    sln.`slug`                                     as localized_slug,
                    nue.air_date                                   as next_episode_air_date,
                    nue.season_number                              as next_episode_season_number,
                    nue.episode_number                             as next_episode_episode_number,
                    IF(sbd.id IS NULL, nue.`air_date`, sbd.`date`) as final_air_date,
                    (SELECT COUNT(*)
                    FROM user_episode ue2
                    WHERE ue2.user_series_id = us.id
                      AND ue2.season_number > 0
                      AND ue2.`watch_at` IS NULL)                  as remainingEpisodes
                FROM `user_series` us
                    INNER JOIN `user_episode` lue ON lue.`id`=us.`last_user_episode_id`
                    INNER JOIN `user_episode` nue ON nue.`id`=us.`next_user_episode_id` AND nue.`air_date` IS NOT NULL
                    $innerJoin
                    LEFT JOIN `series` s ON s.`id`=us.`series_id`
                    LEFT JOIN `series_localized_name` sln ON sln.`series_id`=s.`id` AND sln.`locale`='$locale' 
                    LEFT JOIN series_broadcast_schedule sbs ON s.id = sbs.series_id AND sbs.season_number = nue.season_number AND IF(sbs.multi_part, nue.episode_number BETWEEN sbs.season_part_first_episode AND (sbs.season_part_first_episode + sbs.season_part_episode_count - 1), 1)
                    LEFT JOIN `series_broadcast_date` sbd ON sbd.series_broadcast_schedule_id = sbs.id AND sbd.`episode_id`=nue.`episode_id`
                WHERE us.`user_id`=$userId
                    AND IF(sbd.`date`, DATE(sbd.`date`)<=NOW(), nue.`air_date`<=NOW())
                    AND nue.`season_number`>0
                ORDER BY $sort $order
                LIMIT $perPage OFFSET $offset";
//        dump($sql);
        return $this->getAll($sql);
    }


    public function countAllSeries(
        User $user,
        array $localisation,
        array $filters): int
    {
        $network = $filters['network'];
        if ($network !== 'all') {
            $innerJoin = " INNER JOIN series_network sn ON sn.`network_id` = $network AND sn.`series_id` = s.`id` ";
        } else {
            $innerJoin = '';
        }
        $userId = $user->getId();
        $country = $localisation['country'];

        $sql = "SELECT COUNT(*)
                FROM `user_series` us
                    INNER JOIN `user_episode` lue ON lue.`id`=us.`last_user_episode_id`
                    INNER JOIN `user_episode` nue ON nue.`id`=us.`next_user_episode_id` AND nue.`air_date` IS NOT NULL
                    $innerJoin
                    LEFT JOIN `series` s ON s.`id`=us.`series_id`
                    LEFT JOIN series_broadcast_schedule sbs ON s.id = sbs.series_id AND sbs.season_number = nue.season_number AND IF(sbs.multi_part, nue.episode_number BETWEEN sbs.season_part_first_episode AND (sbs.season_part_first_episode + sbs.season_part_episode_count - 1), 1)
                    LEFT JOIN `series_broadcast_date` sbd ON sbd.series_broadcast_schedule_id = sbs.id AND sbd.`episode_id`=nue.`episode_id`
                WHERE us.`user_id`=$userId
                    AND IF(sbd.`date`, DATE(sbd.`date`)<=NOW(), nue.`air_date`<=NOW())
                    AND nue.`season_number`>0";
//        dump($sql);
        return $this->getOne($sql);
    }

    public function searchSeries(User $user, mixed $query, string $locale): array
    {
        $userId = $user->getId();
        $sql = "SELECT s.`id`                                     as series_id,
                       s.`tmdb_id`                                as tmdb_id,
                       s.`poster_path`                            as poster_path,
                       IF(sln.name IS NOT NULL, sln.name, s.name) as display_name,
                       IF(sln.name IS NOT NULL, sln.slug, s.slug) as display_slug
                FROM `user_series` us
                         INNER JOIN `series` s ON s.`id` = us.`series_id`
                         LEFT JOIN `series_localized_name` sln ON s.`id` = sln.`series_id` AND sln.locale = '$locale'
                WHERE us.user_id = $userId
                  AND (s.name LIKE '%$query%' OR s.original_name LIKE '%$query%' OR sln.name LIKE '%$query%')
                ORDER BY s.`first_air_date` DESC
                LIMIT 20";

        return $this->getAll($sql);
    }

    public function seriesByCountry(User $user, string $country, string $locale, int $page, int $perPage): array
    {
        $userId = $user->getId();
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT s.id                                                                      as id,
                       s.tmdb_id                                                                 as tmdb_id,
                       IF(sln.`name` IS NOT NULL, CONCAT(sln.`name`, ' - ', s.`name`), s.`name`) as name,
                       IF(sln.`slug` IS NOT NULL, sln.`slug`, s.`slug`)                          as slug,
                       s.`poster_path`                                                           as poster_path,
                       s.`first_air_date`                                                        as final_air_date,
                       us.`progress`                                                             as progress,
                       s.first_air_date <= NOW()                                                 as released,
                       s.status                                                                  as status,
                       (SELECT count(*)
                        FROM user_episode cue
                            LEFT JOIN series_broadcast_schedule sbs ON s.id = sbs.series_id AND sbs.season_number = cue.season_number AND IF(sbs.multi_part, cue.episode_number BETWEEN sbs.season_part_first_episode AND (sbs.season_part_first_episode + sbs.season_part_episode_count - 1), 1)
                            LEFT JOIN series_broadcast_date sbd ON sbd.series_broadcast_schedule_id = sbs.id AND sbd.episode_id = cue.episode_id
                        WHERE cue.user_series_id = us.id
                          AND cue.season_number > 0
                          AND IF(sbs.`override`, DATE(sbd.date) <= CURDATE(), cue.air_date <= CURDATE())
                          AND cue.watch_at IS NOT NULL)                                          as watched_aired_episode_count,
                       (SELECT count(*)
                        FROM user_episode cue
                            LEFT JOIN series_broadcast_schedule sbs ON s.id = sbs.series_id AND sbs.season_number = cue.season_number AND IF(sbs.multi_part, cue.episode_number BETWEEN sbs.season_part_first_episode AND (sbs.season_part_first_episode + sbs.season_part_episode_count - 1), 1)
                            LEFT JOIN series_broadcast_date sbd ON sbd.series_broadcast_schedule_id = sbs.id AND sbd.episode_id = cue.episode_id
                        WHERE cue.user_series_id = us.id
                          AND cue.season_number > 0
                          AND IF(sbs.`override`, DATE(sbd.date) <= CURDATE(), cue.air_date <= CURDATE())
                        )                                                                        as aired_episode_count,
                       (SELECT COUNT(*)
                            FROM `user_episode` ue
                            WHERE ue.`user_series_id`=us.id)                                     as number_of_episode,
                       (SELECT CONCAT(ue.`season_number`, '/',ue.`episode_number`)
                            FROM `user_episode` ue
                            WHERE ue.`user_series_id`=us.id AND ue.`season_number`>0 AND ue.`watch_at` IS NULL
                            ORDER BY ue.`episode_number` LIMIT 1)                                as episode
                FROM `series` s
                    INNER JOIN `user_series` us ON us.user_id=$userId AND us.series_id=s.id
                    LEFT JOIN `series_localized_name` sln ON sln.`series_id`=s.id AND sln.`locale`='$locale'
                WHERE s.origin_country LIKE '%$country%'
                ORDER BY s.`first_air_date` DESC";
        if ($perPage > 0) $sql .= "LIMIT $perPage OFFSET $offset";

        return $this->getAll($sql);
    }

    public function episodesFromTheLast7Days(User $user, string $locale): array
    {
        $userId = $user->getId();
        # Séries vues au cours des 7 derniers jours avec le dernier épisode vu et la date/heure et le nombre d'épisodes vus
        $sql = "SELECT
                        IF(slo.name IS NOT NULL, CONCAT(slo.name, ' - ', s.name), s.name) as name,
                        us.`last_watch_at`                                                as last_watch_at,
                          IF(us.`progress`>100, 100, ROUND(us.`progress`, 2))               as progress,
                          us.`last_season`				                                       as last_season,
                          us.`last_episode`                                                 as last_episode,
                          (SELECT COUNT(*)
                           FROM `user_episode` ue
                           WHERE ue.`user_series_id`=us.`id`
                    AND ue.`watch_at` IS NOT NULL
                    AND ue.`watch_at`>=DATE_SUB(NOW(), INTERVAL 7 DAY)
                           )                                                                as episode_count
                FROM `user_series` us
                INNER JOIN `series` s ON s.id=us.`series_id`
                LEFT JOIN `series_localized_name` slo ON slo.`series_id`=s.`id` AND slo.`locale`='$locale'
                WHERE us.user_id=$userId us.`last_watch_at`>=DATE_SUB(NOW(), INTERVAL 7 DAY)
                ORDER BY us.`last_watch_at` DESC";
        return $this->getAll($sql);
    }

    public function getUserSeriesCountries(User $user): array
    {
        $userId = $user->getId();

        $sql = "SELECT s.`origin_country`
                FROM user_series us
                         INNER JOIN `series` s ON s.`id` = us.`series_id`
                WHERE us.`user_id`=$userId
                GROUP BY s.`origin_country`
                ORDER BY s.`origin_country`";

        return $this->getAll($sql);
    }

    public function getSeriesAround(User $user, int $userSeriesId, string $locale): array
    {
        $userId = $user->getId();
        $sql = "SELECT
                    s.`id` as id,
                    IF(sln.`id`, sln.`name`, s.`name`) as name,
                    IF(sln.`id`, sln.`slug`, s.`slug`) as slug,
                    s.`poster_path` as poster_path,
                    us.progress as progress
                FROM `user_series` us
                    LEFT JOIN `series` s ON s.`id`=us.`series_id`
                    LEFT JOIN `series_localized_name` sln ON sln.`series_id`=s.`id` AND sln.`locale`='$locale'
                WHERE us.`user_id`=$userId
                  AND (us.`id`=(
                                SELECT us1.`id` as previoud_id
                                FROM `user_series` us1
                                WHERE us1.`id`<$userSeriesId AND us1.`user_id`=$userId
                                ORDER BY id DESC
                                LIMIT 1
                            )
                                   
                  OR us.`id`=(
                                SELECT us2.`id` as previoud_id
                                FROM `user_series` us2
                                WHERE us2.`id`>$userSeriesId AND us2.`user_id`=$userId
                                ORDER BY id
                                LIMIT 1
                            ))";

        return $this->getAll($sql);
    }

    public function getUserSeriesProgress(UserSeries $userSeries): ?float
    {
        $userSeriesId = $userSeries->getId();
        $sql = "SELECT
                    (
                     SELECT COUNT(*)
                        FROM `user_episode` ue
                        WHERE ue.`user_series_id`=$userSeriesId AND ue.`season_number`>0 AND ue.previous_occurrence_id IS NULL
                     ) as episodeCount,
                    (
                     SELECT COUNT(*)
                        FROM `user_episode` ue
                        WHERE ue.`user_series_id`=$userSeriesId AND ue.`season_number`>0 AND ue.`watch_at` IS NOT NULL AND ue.previous_occurrence_id IS NULL
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

    public function remove(?UserSeries $userSeries): void
    {
        if ($userSeries) {
            $this->em->remove($userSeries);
            $this->em->flush();
        }
    }
}
