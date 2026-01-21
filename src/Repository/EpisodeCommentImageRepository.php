<?php

namespace App\Repository;

use App\Entity\EpisodeCommentImage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EpisodeCommentImage>
 */
class EpisodeCommentImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
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

    public function findByEpisodeCommentIds(array $ids)
    {
        $sql = 'SELECT eci FROM App\Entity\EpisodeCommentImage eci WHERE eci.episodeComment IN (:ids)';
        $query = $this->getEntityManager()->createQuery($sql);
        $query->setParameter('ids', $ids);
        return $query->getResult();
    }
}
