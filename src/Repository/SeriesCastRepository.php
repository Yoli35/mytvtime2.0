<?php

namespace App\Repository;

use App\Entity\SeriesCast;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
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
        $params = [
            'seriesId' => $seriesId,
        ];
        $types = [
            'seriesId' => ParameterType::INTEGER,
        ];
        $sql = <<<SQL
            SELECT p.`adult`             AS adult,
                p.`gender`               AS genre,
                p.`tmdb_id`              AS id,
                p.`known_for_department` AS known_for_department,
                p.`name`                 AS name,
                sc.`character_name`      AS character_name,
                p.`profile_path`         AS profile_path
            FROM people p
                INNER JOIN `series_cast` sc ON sc.`series_id` = :seriesId AND sc.`people_id`=p.`id`
        SQL;
        return $this->getAll($sql, $params, $types);
    }

    private function getAll(string $sql, array $params = [], array $types = []): array
    {
        try {
            return $this->em->getConnection()->fetchAllAssociative($sql, $params, $types);
        } catch (Exception) {
            return [];
        }
    }
}
