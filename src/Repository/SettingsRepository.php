<?php

namespace App\Repository;

use App\Entity\Settings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Settings>
 */
class SettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Settings::class);
    }

    public function save(Settings $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function getSettingsByName(int $userId, $like): array
    {
        $sql = <<<SQL
                    SELECT data
                    FROM settings
                    WHERE user_id = :userId AND name LIKE :like
                SQL;
        $params = ['userId' => $userId, 'like' => '%' . $like . '%'];
        $types = [
            'userId' => ParameterType::INTEGER,
            'like' => ParameterType::STRING,
        ];
        try {
            $result = $this->getEntityManager()->getConnection()->executeQuery($sql, $params, $types);
        } catch (Exception $e) {
            return [];
        }
        try {
            $arr = $result->fetchAllAssociative();
        } catch (Exception $e) {
            return [];
        }

        return $arr;
    }
}
