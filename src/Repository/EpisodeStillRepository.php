<?php

namespace App\Repository;

use App\Entity\EpisodeStill;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface as MonologLogger;

/**
 * @extends ServiceEntityRepository<EpisodeStill>
 */
class EpisodeStillRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly EntityManagerInterface $em,
        private readonly MonologLogger          $logger,
    )
    {
        parent::__construct($registry, EpisodeStill::class);
    }

    public function save(EpisodeStill $entity, bool $flush = false): void
    {
        $this->em->persist($entity);

        if ($flush) {
            $this->em->flush();
        }
    }

    public function flush(): void
    {
        $this->em->flush();
    }

    public function getSeasonStills(array $episodeIds): array
    {
        $params = [
            'ids' => $episodeIds,
        ];
        $types = [
            'ids' => ArrayParameterType::INTEGER,
        ];
        $sql = "SELECT 
                    es.`episode_id` as episode_id, 
                    es.`path` as path
                FROM `episode_still` es 
                WHERE es.`episode_id` IN (:ids)";

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
