<?php

namespace App\Repository;

use App\Entity\Photo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
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

    public function photoByFilename(int $userId, string $filename): array
    {
        $p = [
            'userId' => $userId,
            'filename' => $filename
        ];
        $t = [
            'userId' => ParameterType::INTEGER,
            'filename' => ParameterType::STRING,
        ];

        $sql = <<<SQL
            SELECT *
                FROM `photo` p
                	LEFT JOIN `photo_album` pa ON pa.`photo_id`=p.`id`
                WHERE p.`user_id` = :userId AND p.`image_path` = :filename
            SQL;

        return $this->getAll($sql, $p, $t);
    }

    public function photoAll(): array
    {
        $sql = <<<SQL
            SELECT *
                FROM `photo` p
                WHERE 1
            SQL;

        return $this->getAll($sql);
    }

    public function getAll(string $sql, array $p=[], array $t=[]): array
    {
        try {
            return $this->em->getConnection()->fetchAllAssociative($sql, $p, $t);
        } catch (Exception) {
            return [];
        }
    }

    public function getOne($sql, array $p=[], array $t=[]): array|false
    {
        try {
            return $this->em->getConnection()->fetchAssociative($sql, $p, $t);
        } catch (Exception) {
            return [];
        }
    }
}
