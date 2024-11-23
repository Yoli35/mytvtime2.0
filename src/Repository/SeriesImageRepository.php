<?php

namespace App\Repository;

use App\Entity\SeriesImage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SeriesImage>
 *
 * @method SeriesImage|null find($id, $lockMode = null, $lockVersion = null)
 * @method SeriesImage|null findOneBy(array $criteria, array $orderBy = null)
 * @method SeriesImage[]    findAll()
 * @method SeriesImage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SeriesImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly EntityManagerInterface $em)
    {
        parent::__construct($registry, SeriesImage::class);
    }

    public function save(SeriesImage $seriesImage, bool $flush = false): void
    {
        $this->em->persist($seriesImage);
        if ($flush) $this->em->flush();
    }

    public function remove(SeriesImage $seriesImage): void
    {
        $this->em->remove($seriesImage);
        $this->em->flush();
    }
}
