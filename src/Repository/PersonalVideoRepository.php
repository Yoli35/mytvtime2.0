<?php

namespace App\Repository;

use App\Entity\PersonalVideo;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PersonalVideo>
 */
class PersonalVideoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly EntityManagerInterface $em)
    {
        parent::__construct($registry, PersonalVideo::class);
    }

    public function save(PersonalVideo $video, bool $flush = false): void
    {
        $this->em->persist($video);

        if ($flush) {
            $this->em->flush();
        }
    }

    public function remove(PersonalVideo $video, bool $flush = false): void
    {
        $this->em->remove($video);

        if ($flush) {
            $this->em->flush();
        }
    }

    /** @return PersonalVideo[] */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('v')
            ->where('v.user = :user')
            ->setParameter('user', $user)
            ->orderBy('v.recordedAt', 'DESC')
            ->addOrderBy('v.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findNextPending(): ?PersonalVideo
    {
        return $this->findOneBy(['status' => PersonalVideo::STATUS_PENDING], ['createdAt' => 'ASC']);
    }

    /** @return PersonalVideo[] */
    public function findProcessingSince(DateTimeImmutable $before): array
    {
        return $this->createQueryBuilder('v')
            ->where('v.status = :status')
            ->andWhere('v.updatedAt < :before')
            ->setParameter('status', PersonalVideo::STATUS_PROCESSING)
            ->setParameter('before', $before)
            ->getQuery()
            ->getResult();
    }
}
