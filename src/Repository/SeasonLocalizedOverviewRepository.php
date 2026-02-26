<?php

namespace App\Repository;

use App\Entity\SeasonLocalizedOverview;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SeasonLocalizedOverview>
 */
class SeasonLocalizedOverviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly EntityManagerInterface $em)
    {
        parent::__construct($registry, SeasonLocalizedOverview::class);
    }

    public function save(SeasonLocalizedOverview $seasonLocalizedOverview, bool $flush): void
    {
        $this->em->persist($seasonLocalizedOverview);
        if ($flush) {
            $this->em->flush();
        }
    }

    public function remove(SeasonLocalizedOverview $overview): void
    {
        $this->em->remove($overview);
        $this->em->flush();
    }

    public function getSeasonLocalizedOverview(int $seriesId, int $seasonNumber, string $locale): array|bool
    {
        $params = [
            'seriesId' => $seriesId,
            'seasonNumber' => $seasonNumber,
            'locale' => $locale,
        ];
        $types = [
            'seriesId' => ParameterType::INTEGER,
            'seasonNumber' => ParameterType::INTEGER,
            'locale' => ParameterType::STRING,
        ];
        $sql = <<<SQL
            SELECT slo.*
            FROM `season_localized_overview` slo
            WHERE slo.series_id = :seriesId AND slo.season_number = :seasonNumber AND slo.locale = :locale
        SQL;

        return $this->getOne($sql, $params, $types);
    }

    public function getAll(string $sql, array $params = [], array $types = []): array
    {
        try {
            return $this->em->getConnection()->fetchAllAssociative($sql, $params, $types);
        } catch (Exception) {
            return [];
        }
    }

    public function getOne(string $sql, array $params = [], array $types = []): array|bool
    {
        try {
            return $this->em->getConnection()->fetchAssociative($sql, $params, $types);
        } catch (Exception) {
            return [];
        }
    }
}
