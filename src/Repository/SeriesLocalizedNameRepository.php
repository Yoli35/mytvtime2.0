<?php

namespace App\Repository;

use App\Entity\SeriesLocalizedName;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SeriesLocalizedName>
 *
 * @method SeriesLocalizedName|null find($id, $lockMode = null, $lockVersion = null)
 * @method SeriesLocalizedName|null findOneBy(array $criteria, array $orderBy = null)
 * @method SeriesLocalizedName[]    findAll()
 * @method SeriesLocalizedName[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SeriesLocalizedNameRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SeriesLocalizedName::class);
    }

    //    /**
    //     * @return SeriesLocalizedName[] Returns an array of SeriesLocalizedName objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('s.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?SeriesLocalizedName
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
