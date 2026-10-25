<?php

namespace App\Repository;

use App\Entity\MovieCommentImage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use Psr\Log\LoggerInterface as MonologLogger;

/**
 * @extends ServiceEntityRepository<MovieCommentImage>
 */
class MovieCommentImageRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly EntityManagerInterface $em,
        private readonly MonologLogger          $logger,
    )
    {
        parent::__construct($registry, MovieCommentImage::class);
    }

    public function save(MovieCommentImage $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByMovieCommentIds(array $ids): array
    {
        if (!count($ids)) {
            return [];
        }
        $params = [
            'ids' => $ids,
        ];
        $types = [
            'ids' => ArrayParameterType::INTEGER,
        ];
        $sql = <<<SQL
            SELECT mci.`movie_comment_id`, mci.`path`
            FROM movie_comment_image mci
            WHERE mci.movie_comment_id IN (:ids)
        SQL;

        return $this->getAll($sql, $params, $types);
    }

    public function getAll($sql, array $params = [], array $types = []): array
    {
        try {
            return $this->em->getConnection()->fetchAllAssociative($sql, $params, $types);
        } catch (Exception $e) {
            $this->logger->error('Error: ' . $e->getMessage());
            return [];
        }
    }
}
