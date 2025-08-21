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

    public function allLocations(string $order = 'title', int $page = 1, int $perPage = 50): array
    {
        $sql = "SELECT fl.*, fli.path as still_path
                FROM filming_location fl
                    LEFT JOIN filming_location_image fli ON fl.`still_id` = fli.`id`
                WHERE fl.is_series = 1";

        match ($order) {
            'creation' => $sql .= " ORDER BY fl.created_at DESC LIMIT $perPage OFFSET " . ($page - 1) * $perPage,
            'update' => $sql .= " ORDER BY fl.updated_at DESC LIMIT $perPage OFFSET " . ($page - 1) * $perPage,
            default => $sql .= " ORDER BY fl.title, fl.still_id",
        };

        return $this->getAll($sql);
    }

    public function locations(?int $tmdbId): array
    {
        $sql = "SELECT fl.*, fli.path as still_path
                FROM filming_location fl
                    LEFT JOIN filming_location_image fli ON fl.`still_id` = fli.`id`
                WHERE tmdb_id = $tmdbId
                ORDER BY fl.season_number, fl.episode_number";

        return $this->getAll($sql);
    }

    public function location(?int $tmdbId): array|false
    {
        $sql = "SELECT fl.*, fli.path as still_path
                FROM filming_location fl
                    LEFT JOIN filming_location_image fli ON fl.`still_id` = fli.`id`
                WHERE tmdb_id = $tmdbId";

        return $this->getOne($sql);
    }

    public function locationImages(array $filmingLocationIds): array
    {
        $filmingLocationIds = implode(',', $filmingLocationIds);
        $sql = "SELECT fli.id as id, fli.filming_location_id as filming_location_id, fli.path as path
                FROM `filming_location_image` fli
                WHERE fli.filming_location_id IN ($filmingLocationIds)";

        return $this->getAll($sql);
    }

    public function seriesCount(): int
    {
        $sql = "SELECT COUNT(*)
                FROM `filming_location` fl
                GROUP BY fl.`tmdb_id`";

        return count($this->getAll($sql));
    }

    public function adminLocations(int $page, string $sort, string $order, int $limit): array
    {
        $offset = ($page - 1) * $limit;
        $sql = "SELECT fl.`id`, fl.`tmdb_id`, fl.`title`, fl.`location`, fl.`origin_country`, fl.`created_at`, fl.`updated_at`, fli.path as still_path
                FROM filming_location fl
                    LEFT JOIN filming_location_image fli ON fl.`still_id` = fli.`id`
                WHERE fl.is_series = 1
                ORDER BY $sort $order LIMIT $limit OFFSET $offset";

        return $this->getAll($sql);
    }

    public function getAll($sql): array
    {
        try {
            return $this->em->getConnection()->fetchAllAssociative($sql);
        } catch (Exception) {
            return [];
        }
    }

    public function getOne($sql): array|false
    {
        try {
            return $this->em->getConnection()->fetchAssociative($sql);
        } catch (Exception) {
            return [];
        }
    }
}
