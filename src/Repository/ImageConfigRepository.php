<?php

namespace App\Repository;

use App\Entity\ImageConfig;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ImageConfig|null find($id, $lockMode = null, $lockVersion = null)
 * @method ImageConfig|null findOneBy(array $criteria, array $orderBy = null)
 * @method ImageConfig[]    findAll()
 * @method ImageConfig[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ImageConfigRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ImageConfig::class);
    }
}
