<?php

namespace App\Repository;

use App\Entity\TimezoneBookmark;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface as MonologLogger;

/**
 * @extends ServiceEntityRepository<TimezoneBookmark>
 */
class TimezoneBookmarkRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry                         $registry,
        private readonly EntityManagerInterface $em,
        private readonly MonologLogger          $logger,
    )
    {
        parent::__construct($registry, TimezoneBookmark::class);
    }

    public function save(TimezoneBookmark $timezoneBookmark, bool $flush = false): void
    {
        $this->em->persist($timezoneBookmark);
        if ($flush) $this->em->flush();
    }

    public function remove(TimezoneBookmark $timezoneBookmark, bool $flush = false): void
    {
        $this->em->remove($timezoneBookmark);
        if ($flush) $this->em->flush();
    }

    public function listAll(): array
    {
        $sql = <<<SQL
            SELECT * FROM timezone_bookmark ORDER BY name
        SQL;

        return $this->getAll($sql);
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
}
