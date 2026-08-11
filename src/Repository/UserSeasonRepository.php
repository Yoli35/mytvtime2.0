<?php

namespace App\Repository;

use App\Entity\SeriesBroadcastSchedule;
use App\Entity\UserSeason;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface as MonologLogger;

/**
 * @extends ServiceEntityRepository<UserSeason>
 */
class UserSeasonRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry                         $registry,
        private readonly EntityManagerInterface $em,
        private readonly MonologLogger          $logger,
    )
    {
        parent::__construct($registry, UserSeason::class);
    }

    public function save(UserSeason $userSeason, bool $flush = false): void
    {
        $this->getEntityManager()->persist($userSeason);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(UserSeason $userSeason, bool $flush = false): void
    {
        $this->getEntityManager()->remove($userSeason);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function flush(): void
    {
        $this->getEntityManager()->flush();
    }

    public function isScheduleActive(UserSeason $userSeason, SeriesBroadcastSchedule $schedule)
    {
        $params = [
            'userSeasonId' => $userSeason->getId(),
            'scheduleId' => $schedule->getId(),
        ];
        $types = [
            'userSeasonId' => ParameterType::INTEGER,
            'scheduleId' => ParameterType::INTEGER,
        ];
        $sql = <<< SQL
            SELECT
                IF(COUNT(ussbs.user_season_id) > 0, TRUE, FALSE) AS is_active
            FROM
                user_season_series_broadcast_schedule ussbs
            WHERE
                ussbs.user_season_id = :userSeasonId
                AND ussbs.series_broadcast_schedule_id = :scheduleId;
        SQL;

        return $this->getOne($sql, $params, $types);
    }

    public function getOne($sql, array $params = [], array $types = []): mixed
    {
        try {
            return $this->em->getConnection()->fetchOne($sql, $params, $types);
        } catch (Exception $e) {
            $this->logger->error('Error: ' . $e->getMessage());
            return [];
        }
    }
}
