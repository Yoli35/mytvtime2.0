<?php

namespace App\Repository;

use App\DTO\SeriesAdvancedDbSearchDTO;
use App\Entity\Series;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use RuntimeException;

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
    public function __construct(
        ManagerRegistry $registry,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger
    )
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

    public function search(User $user, string $query, ?int $date, int $page = 1, int $limit = 20): array
    {
        $userId = $user->getId();
        $offset = ($page - 1) * $limit;

        $params = [
            'userId' => $userId,
            'query' => "%$query%",
            'date' => $date,
            'offset' => $offset,
        ];
        $types = [
            'userId' => ParameterType::INTEGER,
            'query' => ParameterType::STRING,
            'date' => ParameterType::STRING,
            'offset' => ParameterType::INTEGER,
        ];
        if ($date) {
            $sql = <<<SQL
                SELECT s.* 
                FROM user_series us 
                    INNER JOIN series s ON us.series_id = s.id 
                    LEFT JOIN series_localized_name sln ON s.id = sln.series_id
                WHERE us.user_id = :userId AND (s.name LIKE :query OR sln.name LIKE :query) AND YEAR(s.first_air_date) LIKE :date
                LIMIT 20 OFFSET :offset
            SQL;
        } else {
            $sql = <<<SQL
                SELECT s.* 
                FROM user_series us 
                    INNER JOIN series s ON us.series_id = s.id 
                    LEFT JOIN series_localized_name sln ON s.id = sln.series_id
                WHERE us.user_id = :userId AND (s.name LIKE :query OR sln.name LIKE :query)
                LIMIT 20 OFFSET :offset
            SQL;
        }

        return $this->getAll($sql, $params, $types);
    }

    public function searchCount(User $user, string $query, ?int $firstAirDateYear): array
    {
        $userId = $user->getId();

        $sql = "SELECT COUNT(*) as count
                FROM user_series us 
                    INNER JOIN series s ON us.series_id = s.id 
                    LEFT JOIN series_localized_name sln ON s.id = sln.series_id
                WHERE us.user_id = $userId AND (s.name LIKE '%$query%' OR sln.name LIKE '%$query%') ";
        if ($firstAirDateYear) {
            $sql .= "AND YEAR(s.first_air_date) LIKE $firstAirDateYear ";
        }

        return $this->getOne($sql);
    }

    public function getLocalizedNames(array $seriesIds, string $locale): array
    {
        $params = [
            'locale' => $locale,
            'seriesIds' => $seriesIds,
        ];
        $types = [
            'locale' => ParameterType::STRING,
            'seriesIds' => ArrayParameterType::INTEGER,
        ];
        $sql = <<<SQL
            SELECT series_id, name
            FROM series_localized_name
            WHERE series_id IN (:ids) AND locale = :locale
        SQL;

        return $this->getAll($sql, $params, $types);
    }

    public function userSeriesInfos(User $user): array
    {
        $userId = $user->getId();
        $sql = <<<SQL
            SELECT s.tmdb_id as id,
                   sln.name as localized_name,
                   us.progress as progress,
                   us.rating as rating,
                   us.favorite as favorite
            FROM series s
                     INNER JOIN user_series us ON s.id = us.series_id
                     LEFT JOIN series_localized_name sln ON s.id = sln.series_id
            WHERE us.user_id = :userId
        SQL;

        return $this->getAll($sql, ['userId' => $userId], ['userId' => ParameterType::INTEGER]);
    }

    public function seriesPosters(int $seriesId): array
    {
        $sql = <<<SQL
            SELECT image_path
            FROM series_image si
            WHERE series_id = :seriesId AND type='poster'
        SQL;

        return $this->getAll($sql, ['seriesId' => $seriesId], ['seriesId' => ParameterType::INTEGER]);
    }

    public function hasSeriesStartedAiring(int $seriesId, string $date): bool
    {
        $params = [
            'seriesId' => $seriesId,
            'date' => $date,
        ];
        $types = [
            'seriesId' => ParameterType::INTEGER,
            'date' => ParameterType::STRING,
        ];
        $sql = <<<SQL
            SELECT COUNT(*) as count
            FROM series s
            WHERE s.id=:seriesId AND s.first_air_date <= :date
        SQL;

        $result = $this->getOne($sql, $params, $types);
        return count($result) > 0;
    }

    public function adminSeries(string $locale, int $page, string $sort, string $order, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;
        $params = [
            'sort' => $sort,
            'limit' => $limit,
            'offset' => $offset,
            'locale' => $locale,
        ];
        $types = [
            'sort' => ParameterType::STRING,
            'limit' => ParameterType::INTEGER,
            'offset' => ParameterType::INTEGER,
            'locale' => ParameterType::STRING,
        ];
        $sql = <<<SQL
            SELECT
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
                    LEFT JOIN series_localized_name sln ON s.id = sln.series_id AND sln.locale = :locale
            ORDER BY :sort $order
            LIMIT :limit OFFSET :offset
        SQL;

        return $this->getAll($sql, $params, $types);
    }

    public function getSeriesIdsForUpdates(): array
    {
        $sql = <<<SQL
            SELECT s.id, s.tmdb_id
            FROM series s
        SQL;

        return $this->getAll($sql);
    }

    public function adminSeriesTmdbId(): array
    {
        $sql = <<<SQL
            SELECT s.tmdb_id
            FROM series s
        SQL;

        return $this->getAll($sql);
    }

    public function adminSeriesById(int $id): array
    {
        $sql = <<<SQL
            SELECT *
            FROM series s
            WHERE s.id = :id
        SQL;

        return $this->getOne($sql, ['id' => $id], ['id' => ParameterType::INTEGER]);
    }

    public function adminSeriesByTmdbId(int $id): array
    {
        $sql = <<<SQL
            SELECT *
            FROM series s
            WHERE s.tmdb_id = :id
        SQL;

        return $this->getOne($sql, ['id' => $id], ['id' => ParameterType::INTEGER]);
    }


    // 'series_additional_overview'
    public function seriesAdditionalOverviews(int $seriesId): array
    {
        $sql = <<<SQL
            SELECT *
            FROM series_additional_overview sao
            WHERE series_id = :seriesId
        SQL;

        return $this->getAll($sql, ['seriesId' => $seriesId], ['seriesId' => ParameterType::INTEGER]);
    }

    // 'series_broadcast_date'
    public function seriesBroadcastDates(int $id): array
    {
        $sql = <<<SQL
            SELECT *
            FROM series_broadcast_date sbd
            WHERE sbd.series_broadcast_schedule_id = :id
        SQL;

        return $this->getAll($sql, ['id' => $id], ['id' => ParameterType::INTEGER]);
    }

    // 'series_broadcast_schedule'
    public function seriesBroadcastSchedules(int $seriesId): array
    {
        $sql = <<<SQL
            SELECT sbs.*,
                   wp.provider_name as provider_name,
                   wp.logo_path as provider_logo
            FROM series_broadcast_schedule sbs
            LEFT JOIN watch_provider wp ON sbs.provider_id = wp.provider_id
            WHERE sbs.series_id = :seriesId
        SQL;

        return $this->getAll($sql, ['seriesId' => $seriesId], ['seriesId' => ParameterType::INTEGER]);
    }

    // 'series_image'
    public function seriesImagesById(int $seriesId): array
    {
        $sql = <<<SQL
            SELECT *
            FROM series_image si
            WHERE si.series_id = :seriesId
        SQL;

        return $this->getAll($sql, ['seriesId' => $seriesId], ['seriesId' => ParameterType::INTEGER]);
    }

    // 'series_localized_name'
    public function seriesLocalizedNames(int $seriesId): array
    {
        $sql = <<<SQL
            SELECT *
            FROM series_localized_name sln
            WHERE sln.series_id = :seriesId
        SQL;

        return $this->getAll($sql, ['seriesId' => $seriesId], ['seriesId' => ParameterType::INTEGER]);
    }

    // 'series_localized_overview'
    public function seriesLocalizedOverviews(int $seriesId): array
    {
        $sql = <<<SQL
            SELECT *
                FROM series_localized_overview slo
                WHERE slo.series_id = :seriesId
            SQL;

        return $this->getAll($sql, ['seriesId' => $seriesId], ['seriesId' => ParameterType::INTEGER]);
    }

    // 'series_network'
    public function seriesNetworks(int $seriesId): array
    {
        $sql = <<<SQL
            SELECT n.*
                FROM series_network sn
                LEFT JOIN network n ON sn.network_id = n.id
                WHERE sn.series_id = :seriesId
            SQL;

        return $this->getAll($sql, ['seriesId' => $seriesId], ['seriesId' => ParameterType::INTEGER]);
    }

    // 'series_watch_link'
    public function seriesWatchLinks(int $seriesId): array
    {
        $sql = <<<SQL
            SELECT swl.*,
                       wp.provider_name as provider_name,
                       wp.logo_path as provider_logo
                FROM series_watch_link swl
                LEFT JOIN watch_provider wp ON swl.provider_id = wp.provider_id
                WHERE swl.series_id = :seriesId
            SQL;

        return $this->getAll($sql, ['seriesId' => $seriesId], ['seriesId' => ParameterType::INTEGER]);
    }

    public function advancedSearchCountries(User $user): array
    {
        $userId = $user->getId();
        $sql = <<<SQL
                SELECT DISTINCT oc.code
                FROM user_series us
                JOIN series s ON s.id = us.series_id
                JOIN JSON_TABLE(
                  s.origin_country,
                  '$[*]' COLUMNS(code VARCHAR(2) PATH '$')
                ) oc
                WHERE us.user_id = :userId
               SQL;
        $rows = $this->getAll($sql, ['userId' => $userId], ['userId' => ParameterType::INTEGER]);

        return array_values(array_map(static fn(array $r) => $r['code'], $rows));
    }

    public function getAll(string $sql, array $params = [], array $types = []): array
    {
        try {
            return $this->em->getConnection()->fetchAllAssociative($sql, $params, $types);
        } catch (Exception) {
            $this->logger->error('Failed to execute SQL query: ' . $sql, ['params' => $params, 'types' => $types]);
            return [];
        }
    }

    public function getOne($sql, array $params = [], array $types = []): array|int|null
    {
        try {
            return $this->em->getConnection()->fetchAssociative($sql, $params, $types);
        } catch (Exception) {
            $this->logger->error('Failed to execute SQL query: ' . $sql, ['params' => $params, 'types' => $types]);
            return [];
        }
    }

    private function parseKeywordTmdbIds(?string $withKeywords, string $separator): array
    {
        if (!$withKeywords) {
            return [];
        }

        $parts = array_map('trim', explode($separator, $withKeywords));
        $parts = array_filter($parts, static fn(string $v) => $v !== '');

        $ids = array_map('intval', $parts);
        return array_values(array_unique(array_filter($ids, static fn(int $v) => $v > 0)));
    }

    /**
     * @return array{0:string,1:array,2:array} [$sql, $params, $types]
     */
    private function advancedDbSearchSQL(User $user, SeriesAdvancedDbSearchDTO $seriesSearch, bool $limit = true): array
    {
        $params = [
            'userId' => $user->getId(),
            'locale' => $user->getPreferredLanguage() ?? 'fr',
        ];
        $types = [
            'userId' => ParameterType::INTEGER,
            'locale' => ParameterType::STRING,
        ];

        $sortArr = explode('|', $seriesSearch->getSortBy());
        $sort = $sortArr[0] ?? 's.first_air_date';
        $order = strtoupper($sortArr[1] ?? 'DESC');

        // Whitelist pour éviter l'injection via ORDER BY
        $allowedSort = [
            's.first_air_date',
            's.original_name',
            'display_name',
            'us.added_at',
        ];
        if (!in_array($sort, $allowedSort, true)) {
            $sort = 's.first_air_date';
        }
        if (!in_array($order, ['ASC', 'DESC'], true)) {
            $order = 'DESC';
        }

        $offset = ($seriesSearch->getPage() - 1) * 20;

        // ---- Keywords mode ----
        $separator = $seriesSearch->getKeywordSeparator(); // ',' => AND ; '|' => OR
        $kwTmdbIds = $this->parseKeywordTmdbIds($seriesSearch->getWithKeywords(), $separator);
        $hasKeywords = count($kwTmdbIds) > 0;

        // SELECT fields
        if ($limit) {
            // En mode OR, DISTINCT évite les doublons dus aux jointures.
            // En mode AND (GROUP BY), DISTINCT est inutile, mais ne gêne pas.
            $fields = 'DISTINCT s.backdrop_path as backdrop_path, s.tmdb_id as id, s.origin_country as country, s.original_language as original_language, s.overview as overview, s.poster_path as poster_path, s.first_air_date as first_air_date, us.added_at as added_at, s.name as name, IF(sln.id, sln.name, s.name) as display_name';
        } else {
            // Le COUNT sera géré plus bas (subquery si AND).
            $fields = 's.id';
        }

        $sql = <<<SQL
            SELECT $fields
            FROM series s
            INNER JOIN user_series us ON us.series_id = s.id
            LEFT JOIN series_localized_name sln ON sln.series_id = s.id AND sln.locale = :locale
        SQL;

        $where = ['us.user_id = :userId'];

        // origin_country JSON (MySQL)
        if ($seriesSearch->getWithOriginCountry()) {
            $where[] = 'JSON_CONTAINS(s.origin_country, JSON_QUOTE(:originCountry))';
            $params['originCountry'] = $seriesSearch->getWithOriginCountry();
            $types['originCountry'] = ParameterType::STRING;
        }

        if ($seriesSearch->getWithOriginalLanguage()) {
            $where[] = 's.original_language = :originalLanguage';
            $params['originalLanguage'] = $seriesSearch->getWithOriginalLanguage();
            $types['originalLanguage'] = ParameterType::STRING;
        }

        if ($seriesSearch->getFirstAirDateYear()) {
            $where[] = 'YEAR(s.first_air_date) = :firstAirDateYear';
            $params['firstAirDateYear'] = $seriesSearch->getFirstAirDateYear();
            $types['firstAirDateYear'] = ParameterType::INTEGER;
        }

        if ($seriesSearch->getFirstAirDateGTE()) {
            $where[] = 'DATE(s.first_air_date) >= :firstAirDateGTE';
            $params['firstAirDateGTE'] = $seriesSearch->getFirstAirDateGTE()->format('Y-m-d');
            $types['firstAirDateGTE'] = ParameterType::STRING;
        }

        if ($seriesSearch->getFirstAirDateLTE()) {
            $where[] = 'DATE(s.first_air_date) <= :firstAirDateLTE';
            $params['firstAirDateLTE'] = $seriesSearch->getFirstAirDateLTE()->format('Y-m-d');
            $types['firstAirDateLTE'] = ParameterType::STRING;
        }

        if ($seriesSearch->getWithStatus()) {
            $where[] = 's.status = :status';
            $params['status'] = $seriesSearch->getWithStatus();
            $types['status'] = ParameterType::STRING;
        }

        $groupBy = '';
        $having = '';

        if ($hasKeywords) {
            $params['kwTmdbIds'] = $kwTmdbIds;
            $types['kwTmdbIds'] = ArrayParameterType::INTEGER;

            if ($separator === ',') {
                // Tous les mots-clés
                $params['kwCount'] = count($kwTmdbIds);
                $types['kwCount'] = ParameterType::INTEGER;

                $where[] = 's.id IN (
                            SELECT sk2.series_id
                            FROM series_keyword sk2
                            INNER JOIN keyword k2 ON k2.id = sk2.keyword_id
                            WHERE k2.keyword_id IN (:kwTmdbIds)
                            GROUP BY sk2.series_id
                            HAVING COUNT(DISTINCT k2.keyword_id) = :kwCount
                        )';
            } else {
                // Au moins un mot-clé
                $where[] = 'EXISTS (
                            SELECT 1
                            FROM series_keyword sk2
                            INNER JOIN keyword k2 ON k2.id = sk2.keyword_id
                            WHERE sk2.series_id = s.id
                              AND k2.keyword_id IN (:kwTmdbIds)
                        )';
            }
        }

        $sql .= "\nWHERE " . implode("\n  AND ", $where);
        $sql .= $groupBy . $having;

        if ($limit) {
            $sql .= "\nORDER BY $sort $order";
            $sql .= "\nLIMIT 20 OFFSET :offset";
            $params['offset'] = $offset;
            $types['offset'] = ParameterType::INTEGER;

            return [$sql, $params, $types];
        }

        // ---- COUNT ----
        // Pour AND (GROUP BY/HAVING), il faut compter le nombre de lignes (s.id) après groupement.
        // Pour OR (ou sans keywords), COUNT(DISTINCT s.id) suffit.
        if ($hasKeywords && $separator === ',') {
            $countSql = "SELECT COUNT(*) as n FROM ($sql) t";
            return [$countSql, $params, $types];
        }

        // On extrait la partie "FROM ..."
// (supporte: espaces avant FROM, CRLF, etc.)
        if (!preg_match('/\bFROM\b.*$/is', $sql, $m)) {
            throw new RuntimeException('Unable to build count query: FROM clause not found.');
        }
        $fromAndAfter = $m[0];
        $countSql = "SELECT COUNT(DISTINCT s.id) as n\n" . $fromAndAfter;

        return [$countSql, $params, $types];
    }

    public function advancedDbSearch(User $user, SeriesAdvancedDbSearchDTO $seriesSearch): array
    {
        [$sql, $params, $types] = $this->advancedDbSearchSQL($user, $seriesSearch);
        return $this->getAll($sql, $params, $types);
    }

    public function advancedDbSearchCount(User $user, SeriesAdvancedDbSearchDTO $seriesSearch): int
    {
        [$sql, $params, $types] = $this->advancedDbSearchSQL($user, $seriesSearch, false);
        $row = $this->getOne($sql, $params, $types);
        return (int)($row['n'] ?? 0);
    }
}
