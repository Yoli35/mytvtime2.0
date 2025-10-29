<?php

namespace App\Repository;

use App\Entity\SeriesCast;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SeriesCast>
 */
class SeriesCastRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly EntityManagerInterface $em)
    {
        parent::__construct($registry, SeriesCast::class);
    }

    public function save(SeriesCast $seriesCast, bool $flush = false): void
    {
        $this->em->persist($seriesCast);

        if ($flush) {
            $this->em->flush();
        }
    }

    public function getSeriesCatsBySeriesId(int $seriesId): array
    {
        $sql = "SELECT
                    p.`adult` as adult,
                    p.`gender` as genre,
                    p.`tmdb_id` as id,
                    p.`known_for_department` as known_for_department,
                    p.`name` as name,
                    sc.`character_name` as character_name,
                    p.`profile_path` as profile_path
                FROM people p
                    INNER JOIN `series_cast` sc ON sc.`series_id`=$seriesId AND sc.`people_id`=p.`id`";
        return $this->getAll($sql);
    }

    private function getAll($sql): array
    {
        try {
            return $this->em->getConnection()->fetchAllAssociative($sql);
        } catch (Exception) {
            return [];
        }
    }
}
