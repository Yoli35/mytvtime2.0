<?php

namespace App\Repository;

use App\Entity\EpisodeLocalizedOverview;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EpisodeLocalizedOverview>
 *
 * @method EpisodeLocalizedOverview|null find($id, $lockMode = null, $lockVersion = null)
 * @method EpisodeLocalizedOverview|null findOneBy(array $criteria, array $orderBy = null)
 * @method EpisodeLocalizedOverview[]    findAll()
 * @method EpisodeLocalizedOverview[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EpisodeLocalizedOverviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly EntityManagerInterface $em)
    {
        parent::__construct($registry, EpisodeLocalizedOverview::class);
    }

    public function save(EpisodeLocalizedOverview $episodeLocalizedOverview, bool $flush = false): void
    {
        $this->em->persist($episodeLocalizedOverview);
        if ($flush) {
            $this->em->flush();
        }
    }

    public function remove(EpisodeLocalizedOverview $elo, bool $flush = false)
    {
        $this->em->remove($elo);
        if ($flush) {
            $this->em->flush();
        }
    }
}
