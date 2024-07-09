<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserPinnedSeries;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserPinnedSeries>
 */
class UserPinnedSeriesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly EntityManagerInterface $em)
    {
        parent::__construct($registry, UserPinnedSeries::class);
    }

    public function getPinnedSeriesByUser(User $user, string $locale): array
    {
        $userId = $user->getId();
        $sql = "SELECT s.`id`           as id,
                       s.`name`         as name,
                       s.`slug`         as slug,
                       s.`poster_path`  as posterPath,
                       ups.`created_at` as createdAt,
                       (IF(sln.`name` IS NULL, s.`name`, sln.`name`)) as displayName
                FROM `user_pinned_series` ups
                         INNER JOIN `series` s ON ups.`user_id` =$userId AND ups.`user_series_id` = s.`id`
                         LEFT JOIN series_localized_name sln ON s.id = sln.series_id AND sln.locale = '$locale'
                ORDER BY ups.`created_at` DESC";
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
