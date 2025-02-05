<?php

namespace App\Repository;

use App\Entity\MovieImage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MovieImage>
 */
class MovieImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly EntityManagerInterface $em)
    {
        parent::__construct($registry, MovieImage::class);
    }

    public function save(MovieImage $entity, bool $flush = false): void
    {
        $this->em->persist($entity);

        if ($flush) {
            $this->em->flush();
        }
    }
}
