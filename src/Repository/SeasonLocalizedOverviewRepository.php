<?php

namespace App\Repository;

use App\Entity\SeasonLocalizedOverview;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
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

    public function getSeasonLocalizedOverview(int $serieId, int $seasonNumber, string $locale): array|bool
    {
        $sql = "SELECT slo.*
                FROM `season_localized_overview` slo
                WHERE slo.series_id = $serieId AND slo.season_number = $seasonNumber AND slo.locale = '$locale'";

        return $this->getOne($sql);
    }

    public function getAll($sql): array
    {
        try {
            return $this->em->getConnection()->fetchAllAssociative($sql);
        } catch (Exception) {
            return [];
        }
    }

    public function getOne($sql): array|bool
    {
        try {
            return $this->em->getConnection()->fetchAssociative($sql);
        } catch (Exception) {
            return [];
        }
    }
}
