<?php

namespace App\Repository;

use App\Entity\SeriesBroadcastSchedule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
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
    public function __construct(ManagerRegistry $registry, private readonly EntityManagerInterface $em)
    {
        parent::__construct($registry, SeriesBroadcastSchedule::class);
    }

    public function save(SeriesBroadcastSchedule $seriesBroadcastSchedule): void
    {
        $this->em->persist($seriesBroadcastSchedule);
        $this->em->flush();
    }
}
