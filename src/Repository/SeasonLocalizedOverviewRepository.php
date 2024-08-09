<?php

namespace App\Repository;

use App\Entity\SeasonLocalizedOverview;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SeasonLocalizedOverview>
 */
class SeasonLocalizedOverviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly EntityManagerInterface $em)
    {
        parent::__construct($registry, SeasonLocalizedOverview::class);
    }

    public function save(SeasonLocalizedOverview $seasonLocalizedOverview, bool $flush): void
    {
        $this->em->persist($seasonLocalizedOverview);
        if ($flush) {
            $this->em->flush();
        }
    }
}
