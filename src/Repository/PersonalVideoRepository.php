<?php

namespace App\Repository;

use App\Entity\PersonalVideo;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface as MonologLogger;

/**
 * @extends ServiceEntityRepository<PersonalVideo>
 */
class PersonalVideoRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly EntityManagerInterface $em,
        private readonly MonologLogger          $logger,
    )
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

    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('v')
            ->where('v.user = :user')
            ->setParameter('user', $user)
            ->orderBy('v.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findPreviousByUser(User $user, int $id): array|false
    {
        $ready = PersonalVideo::STATUS_READY;
        $sql = <<<SQL
                -- Previous video
                SELECT *, pv.status = :ready AS ready
                FROM `personal_video` pv 
                WHERE pv.`user_id`=:user AND pv.`id`>:id
                ORDER BY pv.`id`
                LIMIT 1;
                SQL;

        return $this->getAssociative($sql, ['user' => $user->getId(), 'id' => $id, 'ready' => $ready], ['user'=>ParameterType::INTEGER, 'ready'=>ParameterType::STRING, 'id'=>ParameterType::INTEGER]);
    }

    public function findPNextByUser(User $user, int $id): array|false
    {
        $ready = PersonalVideo::STATUS_READY;
        $sql = <<<SQL
                -- Next video
                SELECT *, pv.status = :ready AS ready
                FROM `personal_video` pv 
                WHERE pv.`user_id`=:user AND pv.`id`<:id
                ORDER BY pv.`id` DESC
                LIMIT 1;
                SQL;

        return $this->getAssociative($sql, ['user' => $user->getId(), 'id' => $id, 'ready' => $ready], ['user'=>ParameterType::INTEGER, 'ready'=>ParameterType::STRING, 'id'=>ParameterType::INTEGER]);
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

    public function getAll($sql, array $params = [], array $types = []): array
    {
        try {
            return $this->em->getConnection()->fetchAllAssociative($sql, $params, $types);
        } catch (Exception $e) {
            $this->logger->error('Error: ' . $e->getMessage());
            return [];
        }
    }

    public function getAssociative($sql, array $params = [], array $types = []): array|false
    {
        try {
            return $this->em->getConnection()->fetchAssociative($sql, $params, $types);
        } catch (Exception $e) {
            $this->logger->error('Error: ' . $e->getMessage());
            return [];
        }
    }
}
