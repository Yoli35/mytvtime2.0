<?php

namespace App\Repository;

use App\Entity\EpisodeComment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EpisodeComment>
 */
class EpisodeCommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly EntityManagerInterface $em)
    {
        parent::__construct($registry, EpisodeComment::class);
    }

    public function save(EpisodeComment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(EpisodeComment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function commentCountBySeries(): array
    {
        $sql = "SELECT s.`id`                                        AS id,
                    count(ec.`series_id`)                            AS count,
                    if(sln.`name` IS NOT NULL, sln.`name`, s.`name`) AS name,
                    ec.`season_number`                               AS sn
                FROM `episode_comment` ec
                    LEFT JOIN `series` s ON s.`id`=ec.`series_id`
                    LEFT JOIN `series_localized_name` sln ON sln.`series_id`=s.`id`
                GROUP BY ec.`series_id`, s.`id`, sln.`id`, ec.`season_number`
                ORDER BY count DESC";

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
}
