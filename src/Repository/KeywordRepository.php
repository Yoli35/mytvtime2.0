<?php

namespace App\Repository;

use App\Entity\Keyword;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Keyword>
 *
 * @method Keyword|null find($id, $lockMode = null, $lockVersion = null)
 * @method Keyword|null findOneBy(array $criteria, array $orderBy = null)
 * @method Keyword[]    findAll()
 * @method Keyword[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class KeywordRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Keyword::class);
    }
    public function save(Keyword $keyword): void
    {
        $this->getEntityManager()->persist($keyword);
    }

    public function flush(): void
    {
        $this->getEntityManager()->flush();
    }

    public function get(string $letter): array
    {
        // $letter == 'other' means keyword begins with non-latin character
        if ($letter === 'other') {
            $letter = '\\P{Latin}\\D';
        }
        elseif (strlen($letter) > 2) {
            $letter = '[' . $letter . ']';
        }
        $params['letter'] = '^' . $letter;
        $types['letter'] = ParameterType::STRING;
        $sql = <<<SQL
            SELECT k.`id` AS id, k.`name` AS name, k.`keyword_id` AS keyword_id
            FROM `keyword` k
            WHERE k.`name` REGEXP :letter
            ORDER BY k.`name`
        SQL;
        try {
            return $this->getEntityManager()->getConnection()->fetchAllAssociative($sql, $params, $types);
        } catch (Exception) {
            return [];
        }
    }
}
