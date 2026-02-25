<?php

namespace App\Repository;

use App\Entity\EpisodeCommentImage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use Psr\Log\LoggerInterface as MonologLogger;

/**
 * @extends ServiceEntityRepository<EpisodeCommentImage>
 */
class EpisodeCommentImageRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly EntityManagerInterface $em,
        private readonly MonologLogger          $logger,
    )
    {
        parent::__construct($registry, EpisodeCommentImage::class);
    }

    public function save(EpisodeCommentImage $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByEpisodeCommentIds(array $ids): array
    {
        $params = [
            'ids' => $ids,
        ];
        $types = [
            'ids' => ArrayParameterType::INTEGER,
        ];
        $sql = <<<SQL
            SELECT eci.`episode_comment_id`, eci.`path`
            FROM episode_comment_image eci
            WHERE eci.episode_comment_id IN (:ids)
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
