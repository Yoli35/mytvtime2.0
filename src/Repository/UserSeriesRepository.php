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

    public function getLastWatchedUserSeries(User $user, $locale, int $page = 1, int $perPage = 20): array
    {
        $userId = $user->getId();
        $sql = "SELECT s.`id` as id, s.`name` as name, sln.`name` as localized_name, us.`progress` as progress, "
            . "	    ue.`episode_number` as last_episode, ue.`season_number` as last_season, ue.`watch_at` as last_watch_at, "
            . "     s.`slug` as slug, sln.`slug` as localized_slug, "
            . "     s.`poster_path` as poster_path "
            . "FROM `user_series` us "
            . "INNER JOIN `series` s ON s.`id`=us.`series_id` "
            . "INNER JOIN `user_episode` ue ON ue.`user_series_id`=us.`id` "
            . "LEFT JOIN `series_localized_name` sln ON sln.`series_id`=s.`id` AND sln.`locale`='$locale' "
            . "WHERE us.`user_id`=$userId "
            . "     AND ue.`watch_at` IS NOT NULL "
            . "ORDER BY ue.`watch_at` DESC "
            . "LIMIT " . $perPage . " OFFSET " . ($page - 1) * $perPage;

        return $this->getAll($sql);
    }

    public function getUserSeriesOfTheDay(User $user, string $country, string $locale): array
    {
        $userId = $user->getId();
        $sql = "SELECT s.`id` as id, s.`name` as name, sln.`name` as localized_name, us.`progress` as progress,
                    us.`last_episode` as last_episode, us.`last_season` as last_season,
                    s.`slug` as slug, sln.`slug` as localized_slug,
                    s.`poster_path` as poster_path
                FROM `series` s
                    INNER JOIN `user_series` us ON s.`id`=us.`series_id`
                    LEFT JOIN series_day_offset sdo ON s.id = sdo.series_id AND sdo.country = '$country'
                    LEFT JOIN `series_localized_name` sln ON sln.`series_id`=s.`id` AND sln.`locale`='$locale'
                WHERE us.`user_id`=$userId
                    AND (
                        ((sdo.offset IS NULL OR sdo.offset = 0) AND ue.`air_date` = CURDATE())
                     OR ((sdo.offset > 0) AND ue.`air_date` = DATE_SUB(CURDATE(), INTERVAL sdo.offset DAY))
                     OR ((sdo.offset < 0) AND ue.`air_date` = DATE_ADD(CURDATE(), INTERVAL ABS(sdo.offset) DAY))
                        )";

        return $this->getAll($sql);
    }

    public function getUserSeriesOfTheNext7Days(User $user, string $country, string $locale): array
    {
        $userId = $user->getId();
        $sql = "SELECT
                    s.`id` as id, s.`name` as name, sln.`name` as localized_name, us.`progress` as progress,
                    CASE 
                        WHEN sdo.offset IS NULL OR sdo.offset = 0 THEN ue.`air_date`
                        WHEN sdo.offset > 0 THEN DATE_ADD(ue.`air_date`, INTERVAL sdo.offset DAY)
                        ELSE DATE_SUB(ue.`air_date`, INTERVAL ABS(sdo.offset) DAY)
                    END as air_date,
                    ue.`season_number` as season_number, ue.`episode_number` as episode_number,
                    ue.watch_at as watch_at,
                    sdo.offset as day_offset,
                    us.`last_episode` as last_episode, us.`last_season` as last_season,
                    s.`slug` as slug, sln.`slug` as localized_slug,
                    s.`poster_path` as poster_path
                FROM `series` s
                    INNER JOIN `user_series` us ON s.`id`=us.`series_id`
                    INNER JOIN `user_episode` ue on us.`id` = ue.`user_series_id`
                    LEFT JOIN series_day_offset sdo ON s.id = sdo.series_id AND sdo.country = '$country'
                    LEFT JOIN `series_localized_name` sln ON sln.`series_id`=s.`id` AND sln.`locale`='$locale'
                WHERE us.`user_id`=$userId
                    AND (
                        ((sdo.offset IS NULL OR sdo.offset = 0) AND ue.`air_date` > CURDATE() AND ue.`air_date` <= ADDDATE(CURDATE(), INTERVAL 7 DAY))
                     OR ((sdo.offset > 0) AND ue.`air_date` > DATE_SUB(CURDATE(), INTERVAL sdo.offset DAY) AND ue.`air_date` <= SUBDATE(ADDDATE(CURDATE(), INTERVAL 7 DAY), INTERVAL sdo.offset DAY))
                     OR ((sdo.offset < 0) AND ue.`air_date` > DATE_ADD(CURDATE(), INTERVAL ABS(sdo.offset) DAY) AND ue.`air_date` <= ADDDATE(CURDATE(), INTERVAL (sdo.offset+7) DAY))
                        )
                ORDER BY air_date";

        return $this->getAll($sql);
    }

    public function getUserSeries(User $user, $locale, int $page = 1, int $perPage = 20): array
    {
        $sql = "SELECT s.`id` as id, s.`name` as name, s.`poster_path` as poster_path, "
            . "     s.`tmdb_id` as tmdbId, s.`slug` as slug, us.`user_id` as user_id, "
            . "     us.`progress` as progress, us.`favorite` as favorite, "
            . "     sln.`name` as localized_name, sln.`slug` as localized_slug "
            . "FROM `user_series` us "
            . "INNER JOIN `series` s ON s.`id` = us.`series_id` "
            . "LEFT JOIN `series_localized_name` sln ON s.`id` = sln.`series_id` AND sln.locale='" . $locale . "' "
            . "WHERE us.user_id=" . $user->getId() . " "
            . "ORDER BY s.`first_air_date` DESC "
            . "LIMIT " . $perPage . " OFFSET " . ($page - 1) * $perPage;

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

    public function remove(?UserSeries $userSeries): void
    {
        if ($userSeries) {
            $this->em->remove($userSeries);
            $this->em->flush();
        }
    }
}
