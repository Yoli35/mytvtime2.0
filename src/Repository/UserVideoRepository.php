<?php

namespace App\Repository;

use App\Entity\UserVideo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
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

    public function getUserVideosWithVideos(int $userId, int $categoryId, int $page, int $limit):array
    {
        $offset = ($page - 1) * $limit;
        if ($categoryId) {
            $innerJoin = "INNER JOIN `video_video_category` vvc ON vvc.`video_id`=v.`id` AND vvc.`video_category_id`=$categoryId";
        } else {
            $innerJoin = "";
        }
        $sql = "SELECT uv.`id` as user_video_id, uv.`created_at` as added_at,
	                   v.*,
	                   vc.`thumbnail` as channel_thumbnail, vc.`title` as channel_title, vc.`custom_url` as channel_custom_url
                FROM `user_video` uv
                    INNER JOIN `video` v ON v.`id` = uv.`video_id`
                    $innerJoin
                    LEFT JOIN `video_channel` vc ON vc.`id`=v.`channel_id`
                WHERE uv.`user_id` = $userId
                ORDER BY v.`published_at` DESC LIMIT $limit OFFSET $offset";
        return $this->getAll($sql);
    }

    public function countVideoByCategory(int $userId, int $categoryId): int
    {
        $sql = "SELECT COUNT(uv.`id`) as total
                FROM `user_video` uv
                    INNER JOIN `video` v ON v.`id` = uv.`video_id`
                    INNER JOIN `video_video_category` vvc ON vvc.`video_id`=v.`id`
                WHERE uv.`user_id` = $userId AND vvc.`video_category_id` = $categoryId";
        return (int) $this->getOne($sql);
    }

    public function getVideoCategories(array $ids): array
    {
        $idsPlaceholder = implode(',', array_map('intval', $ids));
        $sql = "SELECT vvc.`video_id` as `video_id`, vc.*
                FROM `video_video_category` vvc
                    LEFT JOIN `video_category` vc ON vvc.`video_category_id`=vc.`id`
                WHERE vvc.`video_id` IN ($idsPlaceholder)";
        return $this->getAll($sql);
    }

    public function getUserVideosTotalDuration(int $userId): int
    {
        $sql = "SELECT SUM(v.`duration`) as total_duration
                FROM `video` v
                    INNER JOIN `user_video` uv ON uv.`video_id` = v.`id`
                WHERE uv.`user_id` = $userId";
        return (int) $this->getOne($sql);
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
