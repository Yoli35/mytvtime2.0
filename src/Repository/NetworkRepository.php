<?php

namespace App\Repository;

use App\Entity\Network;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Network>
 */
class NetworkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly EntityManagerInterface $em)
    {
        parent::__construct($registry, Network::class);
    }

    public function save(Network $network): void
    {
        $this->em->persist($network);
        $this->em->flush();
    }

    public function networkLogoPaths(): array
    {
        $sql = "SELECT id, logo_path FROM network";
        return $this->getAll($sql);
    }

    public function getNetworkList():array
    {
        $sql = "SELECT network_id, name, logo_path FROM network";
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
