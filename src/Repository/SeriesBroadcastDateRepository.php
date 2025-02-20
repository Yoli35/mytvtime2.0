<?php

namespace App\Repository;

use App\Entity\SeriesBroadcastDate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SeriesBroadcastDate>
 */
class SeriesBroadcastDateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly EntityManagerInterface $em)
    {
        parent::__construct($registry, SeriesBroadcastDate::class);
    }

    public function save(SeriesBroadcastDate $entity, bool $flush = false): void
    {
        $this->em->persist($entity);

        if ($flush) {
            $this->em->flush();
        }
    }

    public function flush(): void
    {
        $this->em->flush();
    }

    public function remove(?SeriesBroadcastDate $seriesBroadcastDate, $flush = false): void
    {
        if ($seriesBroadcastDate) {
            $this->em->remove($seriesBroadcastDate);
            if ($flush) {
                $this->em->flush();
            }
        }
    }
}
