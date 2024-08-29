<?php

namespace App\Repository;

use App\Entity\SeriesDayOffset;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SeriesDayOffset>
 *
 * @method SeriesDayOffset|null find($id, $lockMode = null, $lockVersion = null)
 * @method SeriesDayOffset|null findOneBy(array $criteria, array $orderBy = null)
 * @method SeriesDayOffset[]    findAll()
 * @method SeriesDayOffset[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SeriesDayOffsetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly EntityManagerInterface $em)
    {
        parent::__construct($registry, SeriesDayOffset::class);
    }

    public function save(SeriesDayOffset $seriesDayOffset): void
    {
        $this->em->persist($seriesDayOffset);
        $this->em->flush();
    }}
