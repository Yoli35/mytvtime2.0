<?php

namespace App\Repository;

use App\Entity\EpisodeSubstituteName;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EpisodeSubstituteName>
 *
 * @method EpisodeSubstituteName|null find($id, $lockMode = null, $lockVersion = null)
 * @method EpisodeSubstituteName|null findOneBy(array $criteria, array $orderBy = null)
 * @method EpisodeSubstituteName[]    findAll()
 * @method EpisodeSubstituteName[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EpisodeSubstituteNameRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EpisodeSubstituteName::class);
    }

    //    /**
    //     * @return EpisodeSubstituteName[] Returns an array of EpisodeSubstituteName objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('e')
    //            ->andWhere('e.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('e.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?EpisodeSubstituteName
    //    {
    //        return $this->createQueryBuilder('e')
    //            ->andWhere('e.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
