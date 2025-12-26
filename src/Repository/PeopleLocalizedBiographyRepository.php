<?php

namespace App\Repository;

use App\Entity\PeopleLocalizedBiography;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PeopleLocalizedBiography>
 */
class PeopleLocalizedBiographyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly EntityManagerInterface $em)
    {
        parent::__construct($registry, PeopleLocalizedBiography::class);
    }

    public function save(PeopleLocalizedBiography $localizedBio, bool $flush = false): void
    {
        $this->em->persist($localizedBio);
        if ($flush) {
            $this->em->flush();
        }
    }
}
