<?php /** @noinspection PhpMultipleClassDeclarationsInspection */

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserEpisode;
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

    public function remove(UserEpisode $userEpisode): void
    {
        $this->em->remove($userEpisode);
        $this->em->flush();
    }

    public function lastAddedSeries(User $user, $locale, $page, $perPage): array
    {
        $sql = "SELECT "
            . "  s.id as id, s.tmdb_id as tmdbId,"
            . "	 s.`name` as name, s.`slug` as slug, sln.`name` as localizedName, sln.`slug` as localizedSlug, s.`poster_path` as posterPath, "
            . "  us.`favorite` as favorite, us.`progress` as progress, "
            . "  us.`last_episode` as episodeNumber, us.`last_season` as seasonNumber "
            . "FROM `user_series` us "
            . "INNER JOIN `series` s ON s.`id` = us.`series_id` "
            . "LEFT JOIN `series_localized_name` sln ON sln.`series_id`=s.`id` AND sln.`locale`='$locale' "
            . "WHERE us.`user_id`= " . $user->getId() . " "
            . "ORDER BY us.`added_at` DESC "
            . "LIMIT $perPage OFFSET " . ($page - 1) * $perPage;

        return $this->getAll($sql);
    }

    public function historySeries(User $user, $locale, $page, $perPage): array
    {
        $sql = "SELECT "
            . "  s.id as id, s.tmdb_id as tmdbId,"
            . "	 s.`name` as name, s.`slug` as slug, sln.`name` as localizedName, sln.`slug` as localizedSlug, s.`poster_path` as posterPath, "
            . "  us.`favorite` as favorite, us.`progress` as progress, "
            . "  us.`last_episode` as episodeNumber, us.`last_season` as seasonNumber, "
            . "      (SELECT count(*) "
            . "       FROM user_episode ue "
            . "       WHERE ue.user_series_id = us.id "
            . "         AND ue.season_number > 0 "
            . "         AND ue.air_date <= CURDATE() "
            . "         AND ue.watch_at IS NOT NULL) as watched_aired_episode_count, "
            . "      (SELECT count(*) "
            . "       FROM user_episode ue "
            . "       WHERE ue.user_series_id = us.id "
            . "         AND ue.season_number > 0 "
            . "         AND ue.air_date <= CURDATE()) as aired_episode_count "
            . "FROM `user_series` us "
            . "INNER JOIN `series` s ON s.`id` = us.`series_id` "
            . "LEFT JOIN `series_localized_name` sln ON sln.`series_id`=s.`id` AND sln.`locale`='$locale' "
            . "WHERE us.`user_id`= " . $user->getId() . " "
            . "ORDER BY us.`last_watch_at` DESC "
            . "LIMIT $perPage OFFSET " . ($page - 1) * $perPage;

        return $this->getAll($sql);
    }

    public function episodesOfTheDay(User $user, string $country = 'FR', string $locale='fr'): array
    {
        $userId = $user->getId();
        $sql = "SELECT "
            . "       s.id                                   as id, "
            . "       s.name                                 as name, "
            . "       s.slug                                 as slug, "
            . "       sln.name                               as localizedName, "
            . "       sln.slug                               as localizedSlug, "
            . "       s.poster_path                          as posterPath, "
            . "       us.favorite                            as favorite, "
            . "       us.progress                            as progress, "
            . "       ue.`episode_number`                    as episodeNumber, "
            . "       ue.`season_number`                     as seasonNumber, "
            . "       ue.`watch_at`                          as watchAt, "
            . "       (SELECT count(*) "
            . "        FROM user_episode ue "
            . "        WHERE ue.user_series_id = us.id "
            . "            AND ue.season_number > 0 "
            . "            AND ue.air_date <= CURDATE() "
            . "            AND ue.watch_at IS NOT NULL)      as watched_aired_episode_count, "
            . "       (SELECT count(*) "
            . "        FROM user_episode ue "
            . "        WHERE ue.user_series_id = us.id "
            . "            AND ue.season_number > 0 "
            . "            AND ue.air_date <= CURDATE())     as aired_episode_count, "
            . "       us.last_watch_at                       as last_watch_at, "
            . "       ue.episode_number                      as episodeNumber, "
            . "       ue.season_number                       as seasonNumber "
            . "FROM series s "
            . "       INNER JOIN user_series us ON s.id = us.series_id "
            . "       INNER JOIN user_episode ue ON us.id = ue.user_series_id "
            . "       LEFT JOIN series_day_offset sdo ON s.id = sdo.series_id AND sdo.country = '$country'"
            . "       LEFT JOIN series_localized_name sln ON s.id = sln.series_id AND sln.locale = '$locale'"
            . "WHERE us.user_id = $userId "
            . "       AND ue.season_number > 0 "
            . "       AND ( "
            . "           ((sdo.offset IS NULL OR sdo.offset = 0) AND ue.air_date = CURDATE()) "
            . "        OR ((sdo.offset > 0) AND ue.air_date = DATE_SUB(CURDATE(), INTERVAL sdo.offset DAY)) "
            . "        OR ((sdo.offset < 0) AND ue.air_date = DATE_ADD(CURDATE(), INTERVAL ABS(sdo.offset) DAY)) "
            . "           ) "
            . "ORDER BY us.last_watch_at DESC ";

        return $this->getAll($sql);
    }

    public function historyEpisode(User $user, int $dayCount, string $locale): array
    {
        $sql = "SELECT "
            . "      s.id                                   as id,"
            . "	     s.tmdb_id                              as tmdbId,"
            . "      s.`name`                               as name,"
            . "      s.`slug`                               as slug,"
            . "      sln.`name`                             as localizedName,"
            . "      sln.`slug`                             as localizedSlug,"
            . "      s.`poster_path`                        as posterPath, "
            . "      ue.`watch_at`                          as watchAt,"
            . "      ue.`quick_watch_day`                   as qDay,"
            . "      ue.`quick_watch_week`                  as qWeek, "
            . "      us.`favorite`                          as favorite,"
            . "      us.`progress`                          as progress, "
            . "      ue.`episode_number`                    as episodeNumber,"
            . "      ue.`season_number`                     as seasonNumber, "
            . "      p.`name`                               as providerName,"
            . "      p.`logo_path`                          as providerLogoPath, "
            . "      (SELECT count(*) "
            . "       FROM user_episode ue "
            . "       WHERE ue.user_series_id = us.id "
            . "         AND ue.season_number > 0 "
            . "         AND ue.air_date <= CURDATE() "
            . "         AND ue.watch_at IS NOT NULL)        as watched_aired_episode_count, "
            . "      (SELECT count(*) "
            . "       FROM user_episode ue "
            . "       WHERE ue.user_series_id = us.id "
            . "         AND ue.season_number > 0 "
            . "         AND ue.air_date <= CURDATE())       as aired_episode_count "
            . " FROM `user_episode` ue "
            . "      INNER JOIN `user_series` us ON us.`id` = ue.`user_series_id` "
            . "      INNER JOIN `series` s ON s.`id` = us.`series_id` "
            . "      LEFT JOIN `provider` p ON p.`provider_id`=ue.`provider_id` "
            . "      LEFT JOIN `series_localized_name` sln ON sln.`series_id`=s.`id` AND sln.`locale`='$locale' "
            . " WHERE ue.`user_id`= " . $user->getId()
            . "      AND ue.`watch_at` IS NOT NULL "
            . "      AND ue.`watch_at` >= DATE_SUB(NOW(), INTERVAL $dayCount DAY) "
            . " ORDER BY ue.`watch_at` DESC";

        return $this->getAll($sql);
    }

    public function getSubstituteName(int $id): mixed
    {
        $sql = "SELECT `name` "
            . "FROM `episode_substitute_name` "
            . "WHERE `episode_id`=$id";
        return $this->getOne($sql);
    }

    public function getEpisodeListBetweenIds($userId, $startId, $endId): array
    {
        $sql = "SELECT ue.`episode_number`, ue.`season_number`, ue.`user_series_id`, ue.`watch_at` "
            . "FROM `user_episode` ue "
            . "WHERE ue.`user_id`=$userId AND ue.`id` BETWEEN $startId AND $endId "
            . "ORDER BY ue.`watch_at` DESC";
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
