<?php

namespace App\Repository;

use App\Entity\ContactMessage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContactMessage>
 */
class ContactMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct($registry, ContactMessage::class);
    }

    public function save(ContactMessage $entity, bool $flush = false): void
    {
        $this->entityManager->persist($entity);

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    public function remove(ContactMessage $message, bool $flush = false): void
    {
        $this->entityManager->remove($message);

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    public function getPreviousMessage(?int $getId): array
    {
        $params = ['getId' => $getId];
        $types = ['getId' => ParameterType::INTEGER];
        $sql = <<<SQL
                    SELECT id
                    FROM contact_message
                    WHERE id < :getId
                    ORDER BY id DESC
                    LIMIT 1
                SQL;
        try {
            $result = $this->entityManager->getConnection()->fetchAssociative($sql, $params, $types);
        } catch (Exception) {
            $result = [];
        }
        return $result;
    }

    public function getNextMessage(?int $getId): array
    {
        $params = ['getId' => $getId];
        $types = ['getId' => ParameterType::INTEGER];
        $sql = <<<SQL
                    SELECT id
                    FROM contact_message
                    WHERE id > :getId
                    ORDER BY id
                    LIMIT 1
                SQL;
        try {
            $result = $this->entityManager->getConnection()->fetchAssociative($sql, $params, $types);
        } catch (Exception) {
            $result = [];
        }
        return $result;
    }
}
