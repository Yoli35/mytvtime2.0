<?php

namespace App\Repository;

use App\Entity\EpisodeStill;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EpisodeStill>
 */
class EpisodeStillRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly EntityManagerInterface $em)
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
        $ids = implode(',', $episodeIds);
        $sql = "SELECT 
                    es.`episode_id` as episode_id, 
                    es.`path` as path
                FROM `episode_still` es 
                WHERE es.`episode_id` IN ($ids)";

        return $this->getAll($sql);
    }

    public function getAll($sql): array
    {
        try {
            return $this->em->getConnection()->fetchAllAssociative($sql);
        } catch (Exception $e) {
            return [];
        }
    }
}
