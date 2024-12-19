<?php

namespace App\Repository;

use App\Entity\FilmingLocation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FilmingLocation>
 */
class FilmingLocationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly EntityManagerInterface $em)
    {
        parent::__construct($registry, FilmingLocation::class);
    }

    public function allLocations(): array
    {
        $sql = "SELECT fl.id, fl.title, fl.latitude, fl.longitude, fl.description, fli.path as still_path
                FROM filming_location fl
                    LEFT JOIN filming_location_image fli ON fl.`still_id` = fli.`id`
                WHERE fl.is_series = 1";

        return $this->getAll($sql);
    }

    public function locations(?int $tmdbId): array
    {
        $sql = "SELECT fl.id, fl.title, fl.latitude, fl.longitude, fl.description, fli.path as still_path
                FROM filming_location fl
                    LEFT JOIN filming_location_image fli ON fl.`still_id` = fli.`id`
                WHERE tmdb_id = $tmdbId";

        return $this->getAll($sql);
    }

    public function locationImages(array $filmingLocationIds): array
    {
        $filmingLocationIds = implode(',', $filmingLocationIds);
        $sql = "SELECT fli.filming_location_id, fli.path as path
                FROM `filming_location_image` fli
                WHERE fli.filming_location_id IN ($filmingLocationIds)";

        return $this->getAll($sql);
    }

    public function save(FilmingLocation $filmingLocation, bool $flush= false): void
    {
        $this->em->persist($filmingLocation);

        if ($flush) {
            $this->em->flush();
        }
    }

    public function flush(): void
    {
        $this->em->flush();
    }

    public function getAll($sql): array
    {
        try {
            return $this->em->getConnection()->fetchAllAssociative($sql);
        } catch (Exception) {
            return [];
        }
    }

    public function getOne($sql): array
    {
        try {
            return $this->em->getConnection()->fetchAssociative($sql);
        } catch (Exception) {
            return [];
        }
    }
}
