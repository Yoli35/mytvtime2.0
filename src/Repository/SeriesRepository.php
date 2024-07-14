<?php

namespace App\Repository;

use App\Entity\Series;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Series>
 *
 * @method Series|null find($id, $lockMode = null, $lockVersion = null)
 * @method Series|null findOneBy(array $criteria, array $orderBy = null)
 * @method Series[]    findAll()
 * @method Series[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SeriesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly EntityManagerInterface $em)
    {
        parent::__construct($registry, Series::class);
    }

    public function save(Series $series, bool $flush = false): void
    {
        $this->em->persist($series);

        if ($flush) {
            $this->em->flush();
        }
    }

    public function search(User $user, string $query, ?int $firstAirDateYear, int $page = 1): array
    {
        $userId = $user->getId();
        $offset = ($page - 1) * 20;

        $sql = "SELECT s.* "
            . "  FROM user_series us "
            . "  JOIN series s ON us.series_id = s.id "
            . "  WHERE us.user_id = $userId AND s.name LIKE '%$query%' ";
        if ($firstAirDateYear) {
            $sql .= "AND s.first_air_date LIKE '$firstAirDateYear%' ";
        }
        $sql .= "  LIMIT 20 OFFSET $offset";

        return $this->em->getConnection()->fetchAllAssociative($sql);
    }

    public function userSeriesInfos(User $user): array
    {
        $userId = $user->getId();
        $sql = "SELECT s.tmdb_id as id,
                       sln.name as localized_name,
                       us.progress as progress,
                       us.rating as rating,
                       us.favorite as favorite
                FROM series s
                         INNER JOIN user_series us ON s.id = us.series_id
                         LEFT JOIN series_localized_name sln ON s.id = sln.series_id
                WHERE us.user_id=$userId";

        return $this->getAll($sql);
    }

    public function getAll($sql): array
    {
        try {
            return $this->em->getConnection()->fetchAllAssociative($sql);
        } catch (Exception) {
            return [];
        }
    }
}
