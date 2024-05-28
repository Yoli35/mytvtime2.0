<?php

namespace App\Repository;

use App\Entity\SeriesLocalizedOverview;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
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
    public function __construct(ManagerRegistry $registry, private readonly EntityManagerInterface $em)
    {
        parent::__construct($registry, SeriesLocalizedOverview::class);
    }

    public function save(SeriesLocalizedOverview $seriesLocalizedOverview, bool $flush = false): void
    {
        $this->em->persist($seriesLocalizedOverview);

        if ($flush) {
            $this->em->flush();
        }
    }

    public function remove(?SeriesLocalizedOverview $seriesLocalizedName): void
    {
        if ($seriesLocalizedName) {
            $this->em->remove($seriesLocalizedName);
            $this->em->flush();
        }
    }
}
