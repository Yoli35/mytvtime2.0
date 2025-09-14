<?php

namespace App\Repository;

use App\Entity\SeriesAdditionalOverview;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
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
    public function __construct(ManagerRegistry $registry, private readonly EntityManagerInterface $em)
    {
        parent::__construct($registry, SeriesAdditionalOverview::class);
    }

    public function save(SeriesAdditionalOverview $seriesAdditionalOverview, bool $flush = false): void
    {
        $this->em->persist($seriesAdditionalOverview);

        if ($flush) {
            $this->em->flush();
        }
    }

    public function remove(?SeriesAdditionalOverview $overview): void
    {
        if ($overview) {
            $this->em->remove($overview);
            $this->em->flush();
        }
    }
}
