<?php

namespace App\Repository;

use App\Entity\UserVideo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserVideo>
 */
class UserVideoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly EntityManagerInterface $em)
    {
        parent::__construct($registry, UserVideo::class);
    }

    public function save(UserVideo $userVideo, bool $flush = false): void
    {
        $this->em->persist($userVideo);

        if ($flush) {
            $this->em->flush();
        }
    }

    public function getUserVideosWithVideos(int $userId, int $categoryId, int $page, int $limit): array
    {
        $params = [
            'userId' => $userId,
            'limit' => $limit,
            'offset' => ($page - 1) * $limit,
        ];
        $types = [
            'userId' => ParameterType::INTEGER,
            'limit' => ParameterType::INTEGER,
            'offset' => ParameterType::INTEGER,
        ];
        if ($categoryId) {
            $innerJoin = "INNER JOIN `video_video_category` vvc ON vvc.`video_id`=v.`id` AND vvc.`video_category_id`=$categoryId";
        } else {
            $innerJoin = "";
        }

        $sql = <<<SQL
                SELECT
                    uv.`id`          AS user_video_id,
                    uv.`created_at`  AS added_at,
                    v.`id`           AS id,
                    v.`title`        AS title,
                    v.`link`         AS link,
                    v.`thumbnail`    AS thumbnail,
                    v.`published_at` AS published_at,
                    v.`updated_at`   AS updated_at,
                    v.`duration`     AS duration,
                    vc.`thumbnail`   AS channel_thumbnail,
                    vc.`title`       AS channel_title,
                    vc.`custom_url`  AS channel_custom_url
                FROM `user_video` uv
                    INNER JOIN `video` v ON v.`id` = uv.`video_id`
                    $innerJoin
                    LEFT JOIN `video_channel` vc ON vc.`id`=v.`channel_id`
                WHERE uv.`user_id` = :userId
                ORDER BY v.`published_at` DESC LIMIT :limit OFFSET :offset
            SQL;

        return $this->getAll($sql, $params, $types);
    }

    public function countVideoByCategory(int $userId, int $categoryId): int
    {
        $params = [
            'userId' => $userId,
            'categoryId' => $categoryId,
        ];
        $types = [
            'userId' => ParameterType::INTEGER,
            'categoryId' => ParameterType::INTEGER,
        ];

        $sql = <<<SQL
                SELECT COUNT(uv.`id`) AS total
                FROM `user_video` uv
                    INNER JOIN `video` v ON v.`id` = uv.`video_id`
                    INNER JOIN `video_video_category` vvc ON vvc.`video_id`=v.`id`
                WHERE uv.`user_id` = :userId AND vvc.`video_category_id` = :categoryId
            SQL;
        return (int)$this->getOne($sql, $params, $types);
    }

    public function getVideoCategories(array $ids): array
    {
        $params = [
            'ids' => $ids,
        ];
        $types = [
            'ids' => ArrayParameterType::INTEGER,
        ];

        $sql = <<<SQL
                SELECT vvc.`video_id` AS `video_id`, vc.*
                FROM `video_video_category` vvc
                    LEFT JOIN `video_category` vc ON vvc.`video_category_id`=vc.`id`
                WHERE vvc.`video_id` IN (:ids)
            SQL;

        return $this->getAll($sql, $params, $types);
    }

    public function getUserVideosTotalDuration(int $userId): int
    {
        $sql = <<<SQL
                SELECT SUM(v.`duration`) AS total_duration
                FROM `video` v
                    INNER JOIN `user_video` uv ON uv.`video_id` = v.`id`
                WHERE uv.`user_id` = :userId
            SQL;

        return (int)$this->getOne($sql, ['userId' => $userId], ['userId' => ParameterType::INTEGER]);
    }

    public function getAll(string $sql, array $params = [], array $types = []): array
    {
        try {
            return $this->em->getConnection()->fetchAllAssociative($sql, $params, $types);
        } catch (Exception) {
            return [];
        }
    }

    public function getOne($sql, array $params = [], array $types = []): mixed
    {
        try {
            return $this->em->getConnection()->fetchOne($sql, $params, $types);
        } catch (Exception) {
            return [];
        }
    }
}
