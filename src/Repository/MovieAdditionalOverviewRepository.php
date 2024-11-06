<?php

namespace App\Repository;

use App\Entity\MovieAdditionalOverview;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MovieAdditionalOverview>
 */
class MovieAdditionalOverviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly EntityManagerInterface $em)
    {
        parent::__construct($registry, MovieAdditionalOverview::class);
    }

    public function save(MovieAdditionalOverview $movieAdditionalOverview, bool $flush = false): void
    {
        $this->em->persist($movieAdditionalOverview);
        if ($flush) {
            $this->em->flush();
        }
    }
}
