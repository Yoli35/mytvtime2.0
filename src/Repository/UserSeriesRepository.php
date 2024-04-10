<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserSeries;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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

    public function getLastAddedSeries(User $user, int $page = 1, int $perPage = 20): mixed
    {
        return $this->createQueryBuilder('us')
            ->where('us.user = :user')
            ->setParameter('user', $user)
            ->orderBy('us.id', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();
    }

    public function getLastWatchedUserSeries(User $user, $locale, int $page = 1, int $perPage = 20): mixed
    {
        $userId = $user->getId();
        $sql = "SELECT s.`id` as id, s.`name` as name, sln.`name` as localized_name, us.`progress` as progress, "
            . "	    us.`last_episode` as last_episode, us.`last_season` as last_season, us.`last_watch_at` as last_watch_at, "
            . "     s.`slug` as slug, sln.`slug` as localized_slug, "
            . "     s.`poster_path` as poster_path "
            . "FROM `user_series` us "
            . "INNER JOIN `series` s ON s.`id`=us.`series_id` "
            . "INNER JOIN `user_episode` ue ON ue.`user_series_id`=us.`id` "
            . "LEFT JOIN `series_localized_name` sln ON sln.`series_id`=s.`id` AND sln.`locale`='$locale' "
            . "WHERE us.`user_id`=$userId "
            . "     AND us.`progress`<100 "
            . "     AND ue.`episode_number`=us.`last_episode` "
            . "     AND ue.`season_number`=us.`last_season` "
            . "ORDER BY us.`last_watch_at` DESC "
            . "LIMIT " . $perPage . " OFFSET " . ($page - 1) * $perPage;

        return $this->em->getConnection()->fetchAllAssociative($sql);
    }

    public function getUserSeriesOfTheDay(User $user, string $dateString, string $locale): mixed
    {
        $userId = $user->getId();
        $sql = "SELECT s.`id` as id, s.`name` as name, sln.`name` as localized_name, us.`progress` as progress, "
            . "     us.`last_episode` as last_episode, us.`last_season` as last_season,	"
            . "     s.`slug` as slug, sln.`slug` as localized_slug, "
            . "     s.`poster_path` as poster_path	"
            . "FROM `series` s	"
            . "INNER JOIN `user_series` us ON s.`id`=us.`series_id`	"
            . "LEFT JOIN `series_localized_name` sln ON sln.`series_id`=s.`id` AND sln.`locale`='$locale' "
            . "WHERE us.`user_id`=$userId	"
            . "AND s.`next_episode_air_date`='$dateString'";

        return $this->em->getConnection()->fetchAllAssociative($sql);
    }

    public function getUserSeriesOfTheWeek(User $user, string $locale): mixed
    {
        $userId = $user->getId();
        $sql = "SELECT s.`id` as id, s.`name` as name, sln.`name` as localized_name, us.`progress` as progress, "
            . "	us.`last_episode` as last_episode, us.`last_season` as last_season, "
            . "     s.`slug` as slug, sln.`slug` as localized_slug, "
            . "	 s.`poster_path` as poster_path, s.`next_episode_air_date` as date "
            . "FROM `series` s "
            . "INNER JOIN `user_series` us ON s.`id`=us.`series_id` "
            . "LEFT JOIN `series_localized_name` sln ON sln.`series_id`=s.`id` AND sln.`locale`='$locale' "
            . "WHERE us.`user_id`=$userId "
            . "AND s.`next_episode_air_date` <= ADDDATE(CURDATE(), INTERVAL (6 - WEEKDAY(CURDATE())) DAY) AND DATE(s.`next_episode_air_date`) >= SUBDATE(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY) "
            . "ORDER BY s.`next_episode_air_date` ASC";

        return $this->em->getConnection()->fetchAllAssociative($sql);
    }

    public function getUserSeries(User $user, $locale, int $page = 1, int $perPage = 20): mixed
    {
        $sql = "SELECT s.`id` as id, s.`name` as name, s.`poster_path` as poster_path, "
            . "     s.`tmdb_id` as tmdbId, s.`slug` as slug, us.`user_id` as user_id, "
            . "     us.`progress` as progress, us.`favorite` as favorite, "
            . "     sln.`name` as localized_name, sln.`slug` as localized_slug "
            . "FROM `user_series` us "
            . "INNER JOIN `series` s ON s.`id` = us.`series_id` "
            . "LEFT JOIN `series_localized_name` sln ON s.`id` = sln.`series_id` AND sln.locale='" . $locale . "' "
            . "WHERE us.user_id=" . $user->getId() . " "
            . "ORDER BY s.`first_date_air` DESC "
            . "LIMIT " . $perPage . " OFFSET " . ($page - 1) * $perPage;

        return $this->em->getConnection()->fetchAllAssociative($sql);
    }

    public function remove(?UserSeries $userSeries): void
    {
        if ($userSeries) {
            $this->em->remove($userSeries);
            $this->em->flush();
        }
    }
}
