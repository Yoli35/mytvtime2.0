<?php

namespace App\Repository;

use App\Entity\Series;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Series>
 *
 * @method Series|null find($id, $lockMode = null, $lockVersion = null)
 * @method Series|null findOneBy(array $criteria, array $orderBy = null)
 * @method Series[]    findAll()
 * @method Series[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SeriesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly EntityManagerInterface $em)
    {
        parent::__construct($registry, Series::class);
    }

    public function save(Series $series, bool $flush = false): void
    {
        $this->em->persist($series);

        if ($flush) {
            $this->em->flush();
        }
    }

    public function flush(): void
    {
        $this->em->flush();
    }

    public function search(User $user, string $query, ?int $firstAirDateYear, int $page = 1): array
    {
        $userId = $user->getId();
        $offset = ($page - 1) * 20;

        $sql = "SELECT s.* "
            . "  FROM user_series us "
            . "  JOIN series s ON us.series_id = s.id "
            . "  WHERE us.user_id = $userId AND s.name LIKE '%$query%' ";
        if ($firstAirDateYear) {
            $sql .= "AND s.first_air_date LIKE '$firstAirDateYear%' ";
        }
        $sql .= "  LIMIT 20 OFFSET $offset";

        return $this->getAll($sql);
    }

    public function getLocalizedNames(array $seriesIds, string $locale): array
    {
        $ids = implode(',', $seriesIds);
        $sql = "SELECT series_id, name
                FROM series_localized_name
                WHERE series_id IN ($ids) AND locale='$locale'";

        return $this->getAll($sql);
    }

    public function userSeriesInfos(User $user): array
    {
        $userId = $user->getId();
        $sql = "SELECT s.tmdb_id as id,
                       sln.name as localized_name,
                       us.progress as progress,
                       us.rating as rating,
                       us.favorite as favorite
                FROM series s
                         INNER JOIN user_series us ON s.id = us.series_id
                         LEFT JOIN series_localized_name sln ON s.id = sln.series_id
                WHERE us.user_id=$userId";

        return $this->getAll($sql);
    }

    public function seriesLocations(User $user, string $locale): array
    {
        $userId = $user->getId();
        $sql = "SELECT s.`id`                                                        as id,
                       s.`tmdb_id`                                                   as tmdbId,
                       IF(sln.name IS NULL, s.name, CONCAT(sln.name, ' - ', s.name)) as name,
                       IF(sln.slug IS NULL, s.slug, sln.slug)                        as slug,
                       s.locations                                                   as locations
                FROM `series` s
                         INNER JOIN user_series us ON s.id = us.series_id
                         LEFT JOIN `series_localized_name` as sln ON sln.`series_id` = s.`id` AND sln.locale='$locale'
                WHERE s.`locations` IS NOT NULL AND us.user_id=$userId
                ORDER BY name";

        $arr = $this->getAll($sql);
        return array_map(function ($item) {
            $decoded = json_decode($item['locations'], true);
            $item['locations'] = $decoded['locations'];
            return $item;
        }, $arr);
    }

    public function oneSeriesLocations(Series $series, string $locale): array
    {
        $seriesId = $series->getId();
        $sql = "SELECT s.`id`                                                        as id,
                       IF(sln.name IS NULL, s.name, CONCAT(sln.name, ' - ', s.name)) as name,
                       s.locations                                                   as locations
                FROM `series` s
                         LEFT JOIN `series_localized_name` as sln ON sln.`series_id` = s.`id` AND sln.locale='$locale'
                WHERE s.`id`=$seriesId";

        $item = $this->getOne($sql);
//        dump($item);
        if (!$item['locations']) {
            return [];
        }
        $decoded = json_decode($item['locations'], true);
        $item['locations'] = $decoded['locations'] ?? [];
        return $item;
    }

    public function seriesImages(Series $series): array
    {
        $seriesId = $series->getId();
        $sql = "SELECT type, image_path
                FROM series_image si
                WHERE series_id=$seriesId";

        return $this->getAll($sql);
    }

    public function seriesPosters(int $seriesId): array
    {
        $sql = "SELECT image_path
                FROM series_image si
                WHERE series_id=$seriesId AND type='poster'";

        return $this->getAll($sql);
    }

    public function hasSeriesStartedAiring(int $seriesId, string $date): bool
    {
        $sql = "SELECT COUNT(*) as count
                FROM series s
                WHERE s.id=$seriesId AND s.first_air_date <= '$date'";

        $result = $this->getOne($sql);
        return count($result) > 0;
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
