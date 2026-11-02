<?php

namespace App\Repository;

use App\Entity\SeriesBroadcastSchedule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface as MonologLogger;

/**
 * @extends ServiceEntityRepository<SeriesBroadcastSchedule>
 *
 * @method SeriesBroadcastSchedule|null find($id, $lockMode = null, $lockVersion = null)
 * @method SeriesBroadcastSchedule|null findOneBy(array $criteria, array $orderBy = null)
 * @method SeriesBroadcastSchedule[]    findAll()
 * @method SeriesBroadcastSchedule[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SeriesBroadcastScheduleRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly EntityManagerInterface $em,
        private readonly MonologLogger          $logger,
    )
    {
        parent::__construct($registry, SeriesBroadcastSchedule::class);
    }

    public function save(SeriesBroadcastSchedule $seriesBroadcastSchedule): void
    {
        $this->em->persist($seriesBroadcastSchedule);
        $this->em->flush();
    }

    public function findBySeason(int $seriesId, int $seasonNumber): array
    {
        $sql = <<<SQL
                SELECT CONCAT(sbs.first_air_date, ' ', sbs.air_at) AS air_date
                FROM series_broadcast_schedule sbs
                WHERE sbs.series_id = :seriesId
                  AND sbs.season_number = :seasonNumber
            SQL;
        return $this->getAll($sql, ['seriesId' => $seriesId, 'seasonNumber' => $seasonNumber], ['seriesId'=> ParameterType::INTEGER, 'seasonNumber'=> ParameterType::INTEGER]);
    }

    public function getAll($sql, array $params = [], array $types = []): array
    {
        try {
            return $this->em->getConnection()->fetchAllAssociative($sql, $params, $types);
        } catch (Exception $e) {
            $this->logger->error('Error: ' . $e->getMessage());
            return [];
        }
    }

    public function getAssociative($sql, array $params = [], array $types = []): array
    {
        try {
            return $this->em->getConnection()->fetchAssociative($sql, $params, $types);
        } catch (Exception $e) {
            $this->logger->error('Error: ' . $e->getMessage());
            return [];
        }
    }
}
