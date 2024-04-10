<?php

namespace App\Repository;

use App\Entity\SeriesAdditionalOverview;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SeriesAdditionalOverview>
 *
 * @method SeriesAdditionalOverview|null find($id, $lockMode = null, $lockVersion = null)
 * @method SeriesAdditionalOverview|null findOneBy(array $criteria, array $orderBy = null)
 * @method SeriesAdditionalOverview[]    findAll()
 * @method SeriesAdditionalOverview[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SeriesAdditionalOverviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SeriesAdditionalOverview::class);
    }

    //    /**
    //     * @return SeriesAdditionalOverview[] Returns an array of SeriesAdditionalOverview objects
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

    //    public function findOneBySomeField($value): ?SeriesAdditionalOverview
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
