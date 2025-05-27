<?php

namespace App\Repository;

use App\Entity\History;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<History>
 */
class HistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly EntityManagerInterface $em)
    {
        parent::__construct($registry, History::class);
    }

    public function save(History $log, bool $flush = false): void
    {
        $this->em->persist($log);

        if ($flush) {
            $this->em->flush();
        }
    }

    public function getLastVisited(User $user): ?History
    {
        return $this->findOneBy(['user' => $user], ['date' => 'DESC']);
    }

    public function getLastVisitedBeforeError(User $user): ?History
    {
        // SELECT *
        //FROM `history` h
        //WHERE h.`link` != '/error'
        //ORDER BY h.`id` DESC
        //LIMIT 1
        return $this->createQueryBuilder('h')
            ->where('h.user = :user')
            ->andWhere('h.link != :errorLink')
            ->setParameter('user', $user)
            ->setParameter('errorLink', '/error')
            ->orderBy('h.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
