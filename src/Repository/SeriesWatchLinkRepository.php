<?php

namespace App\Repository;

use App\Entity\SeriesWatchLink;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SeriesWatchLink>
 *
 * @method SeriesWatchLink|null find($id, $lockMode = null, $lockVersion = null)
 * @method SeriesWatchLink|null findOneBy(array $criteria, array $orderBy = null)
 * @method SeriesWatchLink[]    findAll()
 * @method SeriesWatchLink[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SeriesWatchLinkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SeriesWatchLink::class);
    }

    //    /**
    //     * @return SeriesWatchLink[] Returns an array of SeriesWatchLink objects
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

    //    public function findOneBySomeField($value): ?SeriesWatchLink
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
