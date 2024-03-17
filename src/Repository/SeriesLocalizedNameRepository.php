<?php

namespace App\Repository;

use App\Entity\SeriesLocalizedName;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SeriesLocalizedName>
 *
 * @method SeriesLocalizedName|null find($id, $lockMode = null, $lockVersion = null)
 * @method SeriesLocalizedName|null findOneBy(array $criteria, array $orderBy = null)
 * @method SeriesLocalizedName[]    findAll()
 * @method SeriesLocalizedName[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SeriesLocalizedNameRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly EntityManagerInterface $em)
    {
        parent::__construct($registry, SeriesLocalizedName::class);
    }

    public function save(SeriesLocalizedName $seriesLocalizedName, bool $flush = false): void
    {
        $this->em->persist($seriesLocalizedName);
        if ($flush) {
            $this->em->flush();
        }
    }
}
