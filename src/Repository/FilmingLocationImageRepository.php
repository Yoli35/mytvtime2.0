<?php

namespace App\Repository;

use App\Entity\FilmingLocationImage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FilmingLocationImage>
 */
class FilmingLocationImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly EntityManagerInterface $em)
    {
        parent::__construct($registry, FilmingLocationImage::class);
    }

    public function save(FilmingLocationImage $filmingLocationImage, bool $flush = false): void
    {
        $this->em->persist($filmingLocationImage);

        if ($flush) {
            $this->em->flush();
        }
    }

    public function remove(FilmingLocationImage $filmingLocationImage, bool $flush = false): void
    {
        $this->em->remove($filmingLocationImage);
        if ($flush) {
            $this->em->flush();
        }
    }

    public function flush(): void
    {
        $this->em->flush();
    }
}
