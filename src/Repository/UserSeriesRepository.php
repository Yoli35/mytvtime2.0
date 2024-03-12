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

    public function getUserSeries(User $user, int $page = 1, int $perPage = 20): mixed
    {
        $sql = "SELECT s.`id` as id, s.`name` as name, s.`poster_path` as poster_path, s.`tmdb_id` as tmdbId, s.`slug` as slug, us.`user_id` as user_id, us.`progress` as progress, us.`favorite` as favorite " .
            "FROM `user_series` us " .
            "INNER JOIN `series` s ON s.`id` = us.`series_id` " .
            "WHERE us.user_id=" . $user->getId() . " " .
            "ORDER BY s.`first_date_air` DESC " .
            "LIMIT " . $perPage . " OFFSET " . ($page - 1) * $perPage;

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
