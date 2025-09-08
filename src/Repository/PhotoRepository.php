<?php

namespace App\Repository;

use App\Entity\Photo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Photo>
 */
class PhotoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly EntityManagerInterface $em)
    {
        parent::__construct($registry, Photo::class);
    }

    public function save(Photo $photo, bool $flush = false): void
    {
        $this->em->persist($photo);

        if ($flush) {
            $this->em->flush();
        }
    }

    public function photoIdsByDate(int $userId, string $date): array
    {
        $sql = "SELECT *
                FROM `photo` p
                WHERE p.`user_id`=$userId AND DATE(p.`date`)='$date'
                ORDER BY p.`created_at` DESC";

        return $this->getAll($sql);
    }

    public function photoByFilename(int $userId, string $filename): array
    {
        $sql = "SELECT *
                FROM `photo` p
                	LEFT JOIN `photo_album` pa ON pa.`photo_id`=p.`id`
                WHERE p.`user_id`=$userId AND p.`image_path`='$filename'";

        return $this->getAll($sql);
    }

    public function photoByAlbum(int $albumId): array
    {
        $sql = "SELECT *
                FROM `photo` p
                    INNER JOIN `photo_album` pa ON pa.`photo_id`=p.`id`
                WHERE pa.`album_id`=$albumId";

        return $this->getAll($sql);
    }

    public function photoAll(): array
    {
        $sql = "SELECT *
                FROM `photo` p
                WHERE 1";

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

    public function getOne($sql): array|false
    {
        try {
            return $this->em->getConnection()->fetchAssociative($sql);
        } catch (Exception) {
            return [];
        }
    }
}
