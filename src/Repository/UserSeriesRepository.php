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

    public function getUserSeriesOfTheDay(User $user, string $country, string $locale): array
    {
        $userId = $user->getId();
        $sql = "SELECT s.`id`                      as id,
                       s.`name`                    as name,
                       sln.`name`                  as localized_name,
                       us.`progress`               as progress,
                       us.`last_episode`           as last_episode,
                       us.`last_season`            as last_season,
                       s.`slug`                    as slug,
                       sln.`slug`                  as localized_slug,
                       s.`poster_path`             as poster_path,
                       (s.first_air_date <= NOW()) as released,
                       s.`status`                  as status,
                       sdo.offset                  as day_offset,
                       ue.air_date                 as air_date,
                       CASE
                           WHEN sdo.offset IS NULL THEN ue.`air_date`
                           WHEN sdo.offset = 0 THEN ue.`air_date`
                           WHEN sdo.offset > 0 THEN DATE_ADD(ue.`air_date`, INTERVAL sdo.offset DAY)
                           WHEN sdo.offset < 0 THEN DATE_SUB(ue.`air_date`, INTERVAL ABS(sdo.offset) DAY)
                           END           as final_air_date
                FROM `series` s
                         INNER JOIN `user_series` us ON s.`id` = us.`series_id`
                         INNER JOIN `user_episode` ue on us.`id` = ue.`user_series_id`
                         LEFT JOIN series_day_offset sdo ON s.id = sdo.series_id AND sdo.country = '$country'
                         LEFT JOIN `series_localized_name` sln ON sln.`series_id` = s.`id` AND sln.`locale` = '$locale'
                WHERE us.`user_id` = $userId
                  AND (
                    ((sdo.offset IS NULL OR sdo.offset = 0) AND ue.`air_date` = CURDATE())
                        OR ((sdo.offset > 0) AND ue.`air_date` = DATE_SUB(CURDATE(), INTERVAL sdo.offset DAY))
                        OR ((sdo.offset < 0) AND ue.`air_date` = DATE_ADD(CURDATE(), INTERVAL ABS(sdo.offset) DAY))
                    )
                GROUP BY s.id, s.name, sln.name, us.progress, us.last_episode, us.last_season, s.slug, sln.slug, s.poster_path, sdo.offset, ue.air_date";

        return $this->getAll($sql);
    }

    public function getUserSeriesOfTheNext7Days(User $user, string $country, string $locale): array
    {
        $userId = $user->getId();
        $sql = "SELECT
                    s.`id` as id,
                    s.`name` as name,
                    sln.`name` as localized_name,
                    us.`progress` as progress,
                    CASE 
                        WHEN sdo.offset IS NULL OR sdo.offset = 0 THEN ue.`air_date`
                        WHEN sdo.offset > 0 THEN DATE_ADD(ue.`air_date`, INTERVAL sdo.offset DAY)
                        ELSE DATE_SUB(ue.`air_date`, INTERVAL ABS(sdo.offset) DAY)
                    END as air_date,
                    ue.`air_date` as original_air_date,
                    ue.`season_number` as season_number,
                    ue.`episode_number` as episode_number,
                    ue.watch_at as watch_at,
                    sdo.offset as day_offset,
                    us.`last_episode` as last_episode, us.`last_season` as last_season,
                    s.`slug` as slug, sln.`slug` as localized_slug,
                    s.`poster_path` as poster_path,
                    (s.first_air_date <= NOW()) as released,
                    s.`status` as status
                FROM `series` s
                    INNER JOIN `user_series` us ON s.`id`=us.`series_id`
                    INNER JOIN `user_episode` ue on us.`id` = ue.`user_series_id`
                    LEFT JOIN series_day_offset sdo ON s.id = sdo.series_id AND sdo.country = '$country'
                    LEFT JOIN `series_localized_name` sln ON sln.`series_id`=s.`id` AND sln.`locale`='$locale'
                WHERE us.`user_id`=$userId
                    AND  ue.`season_number` > 0
                    AND (
                        ((sdo.offset IS NULL OR sdo.offset = 0) AND ue.`air_date` > CURDATE() AND ue.`air_date` <= ADDDATE(CURDATE(), INTERVAL 7 DAY))
                     OR ((sdo.offset > 0) AND ue.`air_date` > DATE_SUB(CURDATE(), INTERVAL sdo.offset DAY) AND ue.`air_date` <= SUBDATE(ADDDATE(CURDATE(), INTERVAL 7 DAY), INTERVAL sdo.offset DAY))
                     OR ((sdo.offset < 0) AND ue.`air_date` > DATE_ADD(CURDATE(), INTERVAL ABS(sdo.offset) DAY) AND ue.`air_date` <= ADDDATE(CURDATE(), INTERVAL (sdo.offset+7) DAY))
                        )
                ORDER BY air_date";

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

    public function getAllSeries(
        User $user,
        array $localisation,
        array $filters,
        array $progress = []): array
    {
        $page = intval($filters['page'] ?? 1);
        $perPage = intval($filters['perPage'] ?? 20);
        $sort = $filters['sort'] ?? 'firstAirDate';
        $order = $filters['order'] ?? 'ASC';
        $network = $filters['network'];

        $sort = match ($sort) {
            'lastWatched' => 'us.`last_watch_at`',
            'episodeAirDate' => 'ue.`air_date`',
            'name' => 's.`name`',
            'addedAt' => 'us.`added_at`',
            default => 's.`first_air_date`',
        };
        $filterString = array_map(fn($filter) => "AND $filter", $progress);
        $filterString = implode(' ', $filterString);
        if ($network !== 'all') {
            $innerJoin = " INNER JOIN series_network sn ON sn.`network_id` = $network AND sn.`series_id` = s.`id` ";
        } else {
            $innerJoin = '';
        }
        $userId = $user->getId();
        $country = $localisation['country'];
        $locale = $localisation['locale'];
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT 
                    s.`id`                        as id,
                    s.`name`                      as name,
                    s.`poster_path`               as poster_path, 
                    s.`tmdb_id`                   as tmdbId,
                    s.`slug`                      as slug,
                    s.`status`                    as status,
                    (s.first_air_date <= NOW())   as released,
                    us.`user_id`                  as user_id, 
                    us.`added_at`                 as added_at,
                    us.`progress`                 as progress,
                    us.`favorite`                 as favorite, 
                    sln.`name`                    as localized_name,
                    sln.`slug`                    as localized_slug,
                    ue.air_date                   as next_episode_air_date,
                    ue.season_number              as next_episode_season_number,
                    ue.episode_number             as next_episode_episode_number,
                    sdo.offset                    as day_offset,
                    (SELECT COUNT(*)
                    FROM user_episode ue2
                    WHERE ue2.user_series_id = us.id
                      AND ue2.season_number > 0
                      AND ue2.`watch_at` IS NULL) as remainingEpisodes,
                    CASE
                        WHEN sdo.offset IS NULL OR sdo.offset = 0 THEN ue.`air_date`
                        WHEN sdo.offset > 0 THEN DATE_ADD(ue.`air_date`, INTERVAL sdo.offset DAY)
                        ELSE DATE_SUB(ue.`air_date`, INTERVAL ABS(sdo.offset) DAY)
                    END                           as final_air_date
                FROM `user_series` us 
                    INNER JOIN user_episode ue ON ue.`user_series_id` = us.`id` 
                    LEFT JOIN `series` s ON s.`id` = us.`series_id` 
                    $innerJoin
                    LEFT JOIN series_day_offset sdo ON s.id = sdo.series_id AND sdo.country = '$country'
                    LEFT JOIN `series_localized_name` sln ON s.`id` = sln.`series_id` AND sln.locale='$locale ' 
                WHERE us.user_id=$userId $filterString
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
            ORDER BY $sort $order 
            LIMIT $perPage OFFSET $offset";
//        dump($sql);
        return $this->getAll($sql);
    }


    public function countAllSeries(
        User $user,
        array $localisation,
        array $filters,
        array $progress = []): int
    {
        $network = $filters['network'];
        $filterString = array_map(fn($filter) => "AND $filter", $progress);
        $filterString = implode(' ', $filterString);
        if ($network !== 'all') {
            $innerJoin = " INNER JOIN series_network sn ON sn.`network_id` = $network AND sn.`series_id` = s.`id` ";
        } else {
            $innerJoin = '';
        }
        $userId = $user->getId();
        $country = $localisation['country'];

        $sql = "SELECT COUNT(s.id)
                FROM `user_series` us 
                    INNER JOIN user_episode ue ON ue.`user_series_id` = us.`id`
                    LEFT JOIN `series` s ON s.`id` = us.`series_id` 
                    $innerJoin
                    LEFT JOIN series_day_offset sdo ON s.id = sdo.series_id AND sdo.country = '$country'
                WHERE us.user_id=$userId $filterString
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
                             LIMIT 1)";
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
