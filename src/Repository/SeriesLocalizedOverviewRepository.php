<?php

namespace App\Repository;

use App\Entity\SeriesLocalizedOverview;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SeriesLocalizedOverview>
 *
 * @method SeriesLocalizedOverview|null find($id, $lockMode = null, $lockVersion = null)
 * @method SeriesLocalizedOverview|null findOneBy(array $criteria, array $orderBy = null)
 * @method SeriesLocalizedOverview[]    findAll()
 * @method SeriesLocalizedOverview[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SeriesLocalizedOverviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SeriesLocalizedOverview::class);
    }

    //    /**
    //     * @return SeriesLocalizedOverview[] Returns an array of SeriesLocalizedOverview objects
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

    //    public function findOneBySomeField($value): ?SeriesLocalizedOverview
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
