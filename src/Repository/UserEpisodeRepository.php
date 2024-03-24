<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserEpisode;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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

        return $this->em->getConnection()->fetchAllAssociative($sql);
    }

    public function historySeries(User $user, $locale, $page, $perPage): array
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
            . "ORDER BY us.`last_watch_at` DESC "
            . "LIMIT $perPage OFFSET " . ($page - 1) * $perPage;

        return $this->em->getConnection()->fetchAllAssociative($sql);
    }

    public function historyEpisode(User $user, $locale, $page, $perPage): array
    {
        $sql = "SELECT "
            . "  s.id as id, s.tmdb_id as tmdbId,"
            . "	 s.`name` as name, s.`slug` as slug, sln.`name` as localizedName, sln.`slug` as localizedSlug, s.`poster_path` as posterPath, "
            . "	 ue.`watch_at` as watchAt, ue.`quick_watch_day` as qDay, ue.`quick_watch_week` as qWeek, "
            . "  us.`favorite` as favorite, us.`progress` as progress, "
            . "  ue.`episode_number` as episodeNumber, ue.`season_number` as seasonNumber, "
            . "	 p.`name` as providerName, p.`logo_path` as providerLogoPath "
            . "FROM `user_episode` ue "
            . "INNER JOIN `user_series` us ON us.`id` = ue.`user_series_id` "
            . "INNER JOIN `series` s ON s.`id` = us.`series_id` "
            . "LEFT JOIN `provider` p ON p.`provider_id`=ue.`provider_id` "
            . "LEFT JOIN `series_localized_name` sln ON sln.`series_id`=s.`id` AND sln.`locale`='$locale' "
            . "WHERE ue.`user_id`= " . $user->getId() . " "
            . "ORDER BY ue.`watch_at` DESC "
            . "LIMIT $perPage OFFSET " . ($page - 1) * $perPage;

        return $this->em->getConnection()->fetchAllAssociative($sql);
    }

    public function getLastWatchedEpisodes($userId, $limit): array
    {
        $sql = "SELECT ue.`episode_number`, ue.`season_number`, ue.`user_series_id`, "
            . "FROM `user_episode` ue "
            . "WHERE ue.`user_id`=$userId "
            . "ORDER BY ue.`watch_at` DESC "
            . "LIMIT $limit";
        return $this->em->getConnection()->fetchAllAssociative($sql);
    }
}
