<?php

namespace App\Repository;

use App\Entity\WatchProvider;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface as MonologLogger;

/**
 * @extends ServiceEntityRepository<WatchProvider>
 *
 * @method WatchProvider|null find($id, $lockMode = null, $lockVersion = null)
 * @method WatchProvider|null findOneBy(array $criteria, array $orderBy = null)
 * @method WatchProvider[]    findAll()
 * @method WatchProvider[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class WatchProviderRepository extends ServiceEntityRepository
{
    public function __construct(
        readonly ManagerRegistry        $registry,
        private readonly EntityManagerInterface $em,
        private readonly MonologLogger          $logger,
    )
    {
        parent::__construct($registry, WatchProvider::class);
    }

    public function save(WatchProvider $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function flush(): void
    {
        $this->getEntityManager()->flush();
    }

    public function getAllProviders(): array
    {
        $sql = <<<SQL
            SELECT * FROM `watch_provider` ORDER BY `provider_name`
        SQL;

        return $this->getAll($sql);
    }

    public function getLocalProviderList(string $country): array
    {
        $sql = <<<SQL
            SELECT wp.`id` AS id, wp.`provider_name` AS providerName, wp.`logo_path` AS logoPath, wp.`provider_id` AS providerId
            FROM `watch_provider` wp
            WHERE wp.`display_priorities` LIKE :country
            ORDER BY wp.`provider_name`
        SQL;

        return $this->getAll($sql, ['country' => '%' . $country . '%'], ['country' => ParameterType::STRING]);
    }

    public function getWatchProviderList(string $country): array
    {
        $sql = <<<SQL
            SELECT wp.`provider_name` as provider_name, wp.`logo_path` as logo_path, wp.`provider_id` as provider_id
            FROM `watch_provider` wp
            WHERE wp.`display_priorities` LIKE :country
            ORDER BY wp.`provider_name`
        SQL;

        return $this->getAll($sql, ['country' => '%' . $country . '%'], ['country' => ParameterType::STRING]);
    }

    public function getNameAndLogo(int $id): array
    {
        $sql = <<<SQL
            SELECT wp.`provider_name` as provider_name, wp.`logo_path` as logo_path, wp.`provider_id` as provider_id
            FROM `watch_provider` wp
            WHERE wp.`provider_id`=:id
        SQL;

        return $this->getAssociative($sql, ['id' => $id], ['id' => ParameterType::INTEGER]);
    }

    public function adminProviders(int $page, string $sort, string $order, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;
        $params = [
            'limit' => $limit,
            'offset' => $offset,
        ];
        $sql = <<<SQL
            SELECT *
            FROM watch_provider wp
            ORDER BY $sort $order
            LIMIT :limit OFFSET :offset
        SQL;

        return $this->getAll($sql, $params, ['limit' => ParameterType::INTEGER, 'offset' => ParameterType::INTEGER]);
    }

    public function adminProviderById(int $id): ?array
    {
        $sql = <<<SQL
            SELECT *
                FROM watch_provider wp
                WHERE wp.id=:id
            SQL;

        return $this->getAssociative($sql, ['id' => $id], ['id' => ParameterType::INTEGER]);
    }

    public function providerIds(): array
    {
        $sql = <<<SQL
            SELECT wp.provider_id as id
                FROM watch_provider wp
            SQL;

        return $this->getAll($sql);
    }

    private function getAll(string $sql, array $params = [], array $types = []): array
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
