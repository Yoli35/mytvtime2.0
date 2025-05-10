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

        $sql = "SELECT s.* 
                FROM user_series us 
                    INNER JOIN series s ON us.series_id = s.id 
                    LEFT JOIN series_localized_name sln ON s.id = sln.series_id
                WHERE us.user_id = $userId AND (s.name LIKE '%$query%' OR sln.name LIKE '%$query%') ";
        if ($firstAirDateYear) {
            $sql .= "AND YEAR(s.first_air_date) LIKE $firstAirDateYear ";
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

    public function adminSeries(string $locale, int $page, string $sort, string $order, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT
                    s.first_air_date,
                    s.id,
                    s.name,
                    s.number_of_episode,
                    s.number_of_season,
                    s.origin_country,
                    s.poster_path,
                    s.status,
                    s.tmdb_id,
                    sln.name as localized_name,
                    (SELECT CONCAT(wp.`logo_path`, '|', wp.`provider_name`)
                     FROM `watch_provider` wp
                     	  INNER JOIN `series_watch_link` swl ON s.id = swl.series_id
                     WHERE wp.provider_id = swl.provider_id
                     LIMIT 1) as logo1,
                    (SELECT CONCAT(wp.`logo_path`, '|', wp.`provider_name`)
                     FROM `watch_provider` wp
                     	  INNER JOIN `series_broadcast_schedule` sbs ON sbs.`series_id` = s.id
                     WHERE wp.provider_id = sbs.provider_id
                     LIMIT 1) as logo2
                FROM series s
                        LEFT JOIN series_localized_name sln ON s.id = sln.series_id AND sln.locale = '$locale'
                ORDER BY $sort $order
                LIMIT $perPage OFFSET $offset";

        return $this->getAll($sql);
    }

    public function adminSeriesTmdbId(): array
    {
        $sql = "SELECT s.tmdb_id
                FROM series s";

        return $this->getAll($sql);
    }

    public function adminSeriesById(int $id): array
    {
        $sql = "SELECT *
                FROM series s
                WHERE s.id=$id";

        return $this->getOne($sql);
    }

    public function adminSeriesByTmdbId(int $id): array
    {
        $sql = "SELECT *
                FROM series s
                WHERE s.tmdb_id=$id";

        return $this->getOne($sql);
    }


    // 'series_additional_overview'
    public function seriesAdditionalOverviews(int $seriesId): array
    {
        $sql = "SELECT *
                FROM series_additional_overview sao
                WHERE series_id=$seriesId";

        return $this->getAll($sql);
    }

    // 'series_broadcast_date'
    public function seriesBroadcastDates(int $sbsId): array
    {
        $sql = "SELECT *
                FROM series_broadcast_date sbd
                WHERE sbd.series_broadcast_schedule_id = $sbsId";

        return $this->getAll($sql);
    }

    // 'series_broadcast_schedule'
    public function seriesBroadcastSchedules(int $seriesId): array
    {
        $sql = "SELECT sbs.*,
                       wp.provider_name as provider_name,
                       wp.logo_path as provider_logo
                FROM series_broadcast_schedule sbs
                LEFT JOIN watch_provider wp ON sbs.provider_id = wp.provider_id
                WHERE sbs.series_id=$seriesId";

        return $this->getAll($sql);
    }

    // 'series_image'
    public function seriesImagesById(int $seriesId): array
    {
        $sql = "SELECT *
                FROM series_image si
                WHERE si.series_id=$seriesId";

        return $this->getAll($sql);
    }

    // 'series_localized_name'
    public function seriesLocalizedNames(int $seriesId): array
    {
        $sql = "SELECT *
                FROM series_localized_name sln
                WHERE sln.series_id=$seriesId";

        return $this->getAll($sql);
    }

    // 'series_localized_overview'
    public function seriesLocalizedOverviews(int $seriesId): array
    {
        $sql = "SELECT *
                FROM series_localized_overview slo
                WHERE slo.series_id=$seriesId";

        return $this->getAll($sql);
    }

    // 'series_network'
    public function seriesNetworks(int $seriesId): array
    {
        $sql = "SELECT n.*
                FROM series_network sn
                LEFT JOIN network n ON sn.network_id = n.id
                WHERE sn.series_id=$seriesId";

        return $this->getAll($sql);
    }

    // 'series_watch_link'
    public function seriesWatchLinks(int $seriesId): array
    {
        $sql = "SELECT swl.*,
                       wp.provider_name as provider_name,
                       wp.logo_path as provider_logo
                FROM series_watch_link swl
                LEFT JOIN watch_provider wp ON swl.provider_id = wp.provider_id
                WHERE swl.series_id=$seriesId";

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

    public function getOne($sql): array
    {
        try {
            return $this->em->getConnection()->fetchAssociative($sql);
        } catch (Exception) {
            return [];
        }
    }
}
