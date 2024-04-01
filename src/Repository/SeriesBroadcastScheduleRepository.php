<?php

namespace App\Repository;

use App\Entity\SeriesBroadcastSchedule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SeriesBroadcastSchedule>
 *
 * @method SeriesBroadcastSchedule|null find($id, $lockMode = null, $lockVersion = null)
 * @method SeriesBroadcastSchedule|null findOneBy(array $criteria, array $orderBy = null)
 * @method SeriesBroadcastSchedule[]    findAll()
 * @method SeriesBroadcastSchedule[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SeriesBroadcastScheduleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SeriesBroadcastSchedule::class);
    }

    //    /**
    //     * @return SeriesBroadcastSchedule[] Returns an array of SeriesBroadcastSchedule objects
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

    //    public function findOneBySomeField($value): ?SeriesBroadcastSchedule
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
