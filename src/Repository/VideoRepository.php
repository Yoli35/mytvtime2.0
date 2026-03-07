<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\Video;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;

/**
 * @extends ServiceEntityRepository<Video>
 */
class VideoRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry                         $registry,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface        $logger,
    )
    {
        parent::__construct($registry, Video::class);
    }

    public function save(Video $video, bool $flush = false): void
    {
        $this->em->persist($video);

        if ($flush) {
            $this->em->flush();
        }
    }

    public function getPreviousVideo(Video $video, User $user): array|false
    {
        $params = [
            'videoId' => $video->getId(),
            'publishedAt' => $video->getPublishedAt()->format('Y-m-d H:i:s'),
            'userId' => $user->getId(),
        ];
        $types = [
            'videoId' => ParameterType::INTEGER,
            'publishedAt' => ParameterType::STRING,
            'userId' => ParameterType::INTEGER,
        ];

        $sql = <<<SQL
                SELECT v.*, uv.id AS user_video_id
                FROM video v
                    INNER JOIN user_video uv ON v.id = uv.video_id AND uv.user_id = :userId
                WHERE v.published_at <= :publishedAt AND v.id != :videoId
                ORDER BY v.published_at DESC
                LIMIT 1
             SQL;

        $r = $this->getOne($sql, $params, $types);

        return is_iterable($r) ? $r[0] : false;
    }

    public function getNextVideo(Video $video, User $user): array|false
    {
        $params = [
            'videoId' => $video->getId(),
            'publishedAt' => $video->getPublishedAt()->format('Y-m-d H:i:s'),
            'userId' => $user->getId(),
        ];
        $types = [
            'videoId' => ParameterType::INTEGER,
            'publishedAt' => ParameterType::STRING,
            'userId' => ParameterType::INTEGER,
        ];

        $sql = <<<SQL
                SELECT v.*, uv.id AS user_video_id
                FROM video v
                    INNER JOIN user_video uv ON v.id = uv.video_id AND uv.user_id = :userId
                WHERE v.published_at >= :publishedAt AND v.id != :videoId
                ORDER BY v.published_at
                LIMIT 1
             SQL;

        $r = $this->getOne($sql, $params, $types);

        return is_iterable($r) ? $r[0] : false;
    }

    public function adminVideos(int $page, string $sort, string $order, int $limit): array
    {
        $params = [
            'offset' => ($page - 1) * $limit,
            'limit' => $limit,
        ];
        $types = [
            'offset' => ParameterType::INTEGER,
            'limit' => ParameterType::INTEGER,
        ];
        $sql = <<<SQL
                SELECT v.*, vc.title AS channel_title, vc.custom_url AS channel_custom_url, vc.thumbnail AS channel_thumbnail
                FROM video v
                    LEFT JOIN video_channel vc ON vc.id = v.channel_id
                ORDER BY v.$sort $order
                LIMIT :limit OFFSET :offset
             SQL;

        return $this->getAll($sql, $params, $types);
    }

//    public function adminVideo(int $id): array|false
//    {
//        $sql = <<<SQL
//                SELECT v.*, vc.title AS channel_title, vc.custom_url AS channel_custom_url, vc.thumbnail AS channel_thumbnail
//                FROM video v
//                    LEFT JOIN video_channel vc ON vc.id = v.channel_id
//                WHERE v.id = :id
//             SQL;
//
//        return $this->getOne($sql, ['id' => $id], ['id' => ParameterType::INTEGER]);
//    }

    public function getAll(string $sql, array $params = [], array $types = []): array
    {
        try {
            return $this->em->getConnection()->fetchAllAssociative($sql, $params, $types);
        } catch (Exception) {
            $this->logger->error('Failed to execute SQL query: ' . $sql, ['params' => $params, 'types' => $types]);
            return [];
        }
    }

    public function getOne($sql, array $params = [], array $types = []): array|int|null
    {
        try {
            return $this->em->getConnection()->fetchAssociative($sql, $params, $types);
        } catch (Exception) {
            $this->logger->error('Failed to execute SQL query: ' . $sql, ['params' => $params, 'types' => $types]);
            return [];
        }
    }
}
