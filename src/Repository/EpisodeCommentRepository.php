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
        $sql = <<<SQL
                    SELECT s.`id`                                        AS id,
                        count(ec.`series_id`)                            AS count,
                        if(sln.`name` IS NOT NULL, sln.`name`, s.`name`) AS name,
                        ec.`season_number`                               AS sn,
                        (SELECT c.`created_at`
                         FROM `episode_comment` c
                         WHERE c.`series_id`=s.`id` AND c.`season_number`=ec.`season_number`
                         ORDER BY c.`created_at` DESC
                         LIMIT 1) AS last_comment
                    FROM `episode_comment` ec
                        LEFT JOIN `series` s ON s.`id`=ec.`series_id`
                        LEFT JOIN `series_localized_name` sln ON sln.`series_id`=s.`id`
                    GROUP BY ec.`series_id`, s.`id`, sln.`id`, ec.`season_number`
                    ORDER BY last_comment DESC
                SQL;

        return $this->getAll($sql);
    }

    public function lastCommentId(): int
    {
        $sql = <<<SQL
                    SELECT c.`id` FROM `episode_comment` c  ORDER BY c.`id` DESC LIMIT 1
                SQL;

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

    public function getOne($sql): int
    {
        try {
            return $this->em->getConnection()->fetchOne($sql);
        } catch (Exception) {
            return 0;
        }
    }
}
