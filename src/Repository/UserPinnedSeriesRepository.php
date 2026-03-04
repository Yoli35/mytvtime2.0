<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserPinnedSeries;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
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

    public function add(UserPinnedSeries $entity, bool $flush = false): void
    {
        $this->em->persist($entity);

        if ($flush) {
            $this->em->flush();
        }
    }

    public function remove(UserPinnedSeries $entity, bool $flush = false): void
    {
        $this->em->remove($entity);

        if ($flush) {
            $this->em->flush();
        }
    }

    public function getPinnedSeriesByUser(User $user, string $locale): array
    {

        $params = [
            'userId' => $user->getId(),
            'locale' => $locale,
        ];
        $types = [
            'userId' => ParameterType::INTEGER,
            'locale' => ParameterType::STRING,
        ];
        $sql = <<<SQL
            SELECT s.`id`                                             AS id,
                       s.`poster_path`                                AS posterPath,
                       ups.`created_at`                               AS createdAt,
                       (IF(sln.`name` IS NULL, s.`name`, sln.`name`)) AS name,
                       (IF(sln.`name` IS NULL, s.`slug`, sln.`slug`)) AS slug,
                       swl.`name`                                     AS linkName,
                       wp.`logo_path`                                 AS providerLogoPath,
                       wp.`provider_name`                             AS providerName
            FROM `user_pinned_series` ups
                INNER JOIN `user_series` us ON ups.`user_id` = :userId AND ups.`user_series_id` = us.`id`
                INNER JOIN `series` s ON us.`series_id` = s.`id`
                LEFT JOIN series_localized_name sln ON s.id = sln.series_id AND sln.locale = :locale
                LEFT JOIN `series_watch_link` swl ON swl.`series_id`=s.`id`
                LEFT JOIN `watch_provider` wp ON wp.`provider_id`=swl.`provider_id` 
            ORDER BY ups.`created_at` DESC
        SQL;

        return $this->getAll($sql, $params, $types);
    }

    public function getAll(string $sql, array $params = [], array $types = []): array
    {
        try {
            return $this->em->getConnection()->fetchAllAssociative($sql, $params, $types);
        } catch (Exception) {
            return [];
        }
    }
}
