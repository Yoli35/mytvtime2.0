<?php

namespace App\Repository;

use App\Entity\SeriesWatchLink;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SeriesWatchLink>
 *
 * @method SeriesWatchLink|null find($id, $lockMode = null, $lockVersion = null)
 * @method SeriesWatchLink|null findOneBy(array $criteria, array $orderBy = null)
 * @method SeriesWatchLink[]    findAll()
 * @method SeriesWatchLink[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SeriesWatchLinkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly EntityManagerInterface $em)
    {
        parent::__construct($registry, SeriesWatchLink::class);
    }

    public function save(SeriesWatchLink $seriesWatchLink, bool $flush = false): void
    {
        $this->em->persist($seriesWatchLink);
        if ($flush) $this->em->flush();
    }

    public function delete(?SeriesWatchLink $watchLink): void
    {
        if ($watchLink) {
            $this->em->remove($watchLink);
            $this->em->flush();
        }
    }
}
