<?php

namespace App\Repository;

use App\Entity\MovieLocalizedName;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MovieLocalizedName>
 */
class MovieLocalizedNameRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly EntityManagerInterface $em)
    {
        parent::__construct($registry, MovieLocalizedName::class);
    }

    public function save(MovieLocalizedName $movieLocalizedName, bool $flush = false): void
    {
        $this->em->persist($movieLocalizedName);
        if ($flush) {
            $this->em->flush();
        }
    }
}
