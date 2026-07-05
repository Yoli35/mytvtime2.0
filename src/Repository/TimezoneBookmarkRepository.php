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

    public function listAll(string $locale = 'fr'): array
    {
        switch ($locale) {
            case 'fr':
                $sql = <<<SQL
                    SELECT code, name_fr AS name FROM timezone_bookmark ORDER BY name_fr
                SQL;
                break;
            case 'ko':
                $sql = <<<SQL
                    SELECT code, name_ko AS name FROM timezone_bookmark ORDER BY name_ko
                SQL;
                break;
            default:
                $sql = <<<SQL
                    SELECT code, name_en AS name FROM timezone_bookmark ORDER BY name_en
                SQL;
        }

        return $this->getAll($sql, [], ['name' => 'string']);
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
