<?php

namespace App\Repository;

use App\Entity\MovieLocalizedOverview;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MovieLocalizedOverview>
 */
class MovieLocalizedOverviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly EntityManagerInterface $em)
    {
        parent::__construct($registry, MovieLocalizedOverview::class);
    }

    public function save(MovieLocalizedOverview $movieLocalizedOverview, bool $flush = false): void
    {
        $this->em->persist($movieLocalizedOverview);
        if ($flush) {
            $this->em->flush();
        }
    }

    public function remove(?MovieLocalizedOverview $overview)
    {
        if ($overview) {
            $this->em->remove($overview);
            $this->em->flush();
        }
    }
}
