<?php

namespace App\Repository;

use App\Entity\Series;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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

    public function search(User $user, string $query, ?int $firstAirDateYear, int $page = 1)
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
}
