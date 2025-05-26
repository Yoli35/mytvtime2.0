<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\Video;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Video>
 */
class VideoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry,  private readonly EntityManagerInterface $em)
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
        $videoId = $video->getId();
        $publishedAt = $video->getPublishedAt()->format('Y-m-d H:i:s');
        $userId = $user->getId();

        $sql = "SELECT v.*, uv.id as user_video_id
                FROM video v
                    INNER JOIN user_video uv ON v.id = uv.video_id AND uv.user_id = $userId
                WHERE v.published_at <= '$publishedAt' AND v.id != $videoId
                ORDER BY v.published_at DESC
                LIMIT 1";
        return $this->getOne($sql);
    }

    public function getNextVideo(Video $video, User $user): array|false
    {
        $videoId = $video->getId();
        $publishedAt = $video->getPublishedAt()->format('Y-m-d H:i:s');
        $userId = $user->getId();

        $sql = "SELECT v.*, uv.id as user_video_id
                FROM video v
                    INNER JOIN user_video uv ON v.id = uv.video_id AND uv.user_id = $userId
                WHERE v.published_at >= '$publishedAt' AND v.id != $videoId
                ORDER BY v.published_at
                LIMIT 1";
        return $this->getOne($sql);
    }

    public function getAll($sql): array
    {
        try {
            return $this->em->getConnection()->fetchAllAssociative($sql);
        } catch (Exception) {
            return [];
        }
    }

    public function getOne($sql): array|false
    {
        try {
            return $this->em->getConnection()->fetchAssociative($sql);
        } catch (Exception) {
            return [];
        }
    }
}
