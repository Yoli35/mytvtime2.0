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
        $sql = "select s.`id` as id, count(ec.`series_id`) as count, if(sln.`name` is not NULL, sln.`name`, s.`name`) as name, ec.`season_number` as sn
                from `episode_comment` ec
                    left join `series` s ON s.`id`=ec.`series_id`
                    left join `series_localized_name` sln ON sln.`series_id`=s.`id`
                group by ec.`series_id`, s.`id`, sln.`id`, ec.`season_number`
                order by count desc";

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
