<?php

namespace App\Repository;

use App\Entity\SeriesDayOffset;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SeriesDayOffset>
 *
 * @method SeriesDayOffset|null find($id, $lockMode = null, $lockVersion = null)
 * @method SeriesDayOffset|null findOneBy(array $criteria, array $orderBy = null)
 * @method SeriesDayOffset[]    findAll()
 * @method SeriesDayOffset[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SeriesDayOffsetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SeriesDayOffset::class);
    }

    //    /**
    //     * @return SeriesDayOffset[] Returns an array of SeriesDayOffset objects
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

    //    public function findOneBySomeField($value): ?SeriesDayOffset
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
