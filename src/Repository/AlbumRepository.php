<?php

namespace App\Repository;

use App\Entity\Album;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Album>
 */
class AlbumRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry,  private readonly EntityManagerInterface $em)
    {
        parent::__construct($registry, Album::class);
    }

    public function save(Album $album, bool $flush = false): void
    {
        $this->em->persist($album);

        if ($flush) {
            $this->em->flush();
        }
    }

    public function flush(): void
    {
        $this->em->flush();
    }

    public function getNextAlbumId(Album $album): array|false
    {
        $params = [
            'userId' => $album->getUser()->getId(),
            'albumId' => $album->getId(),
            'publishedAt' => $album->getCreatedAt()->format('Y-m-d H:i:s'),
        ];
        $types = [
            'userId' => ParameterType::INTEGER,
            'albumId' => ParameterType::INTEGER,
            'publishedAt' => ParameterType::STRING,
        ];

        $sql = "SELECT a.id
                FROM album a
                WHERE a.created_at <= :publishedAt AND a.id != :albumId AND a.user_id = :userId
                ORDER BY a.created_at DESC
                LIMIT 1";

        return $this->getOne($sql, $params, $types);
    }

    public function getPreviousAlbumId(Album $album): array|false
    {
        $params = [
            'userId' => $album->getUser()->getId(),
            'albumId' => $album->getId(),
            'publishedAt' => $album->getCreatedAt()->format('Y-m-d H:i:s'),
        ];
        $types = [
            'userId' => ParameterType::INTEGER,
            'albumId' => ParameterType::INTEGER,
            'publishedAt' => ParameterType::STRING,
        ];

        $sql = "SELECT a.id
                FROM album a
                WHERE a.created_at >= :publishedAt AND a.id != :albumId AND a.user_id = :userId
                ORDER BY a.created_at
                LIMIT 1";

        return $this->getOne($sql, $params, $types);
    }

    public function getOne(string $sql, array $params, array $types): array|false
    {
        try {
            return $this->em->getConnection()->fetchAssociative($sql, $params, $types);
        } catch (Exception) {
            return [];
        }
    }
}
