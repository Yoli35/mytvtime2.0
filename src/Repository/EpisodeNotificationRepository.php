<?php /** @noinspection PhpMultipleClassDeclarationsInspection */

namespace App\Repository;

use App\Entity\EpisodeNotification;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EpisodeNotification>
 */
class EpisodeNotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly EntityManagerInterface $em)
    {
        parent::__construct($registry, EpisodeNotification::class);
    }

    public function save(EpisodeNotification $episodeNotification, bool $flush = false): void
    {
        $this->em->persist($episodeNotification);
        if ($flush) {
            $this->em->flush();
        }
    }

    public function flush(): void
    {
        $this->em->flush();
    }

    public function episodeNotificationCount(User $user): array
    {
        $sql = "SELECT COUNT(*) as count "
            . "FROM episode_notification en "
            . "INNER JOIN user_episode_notification uen ON en.id = uen.episode_notification_id AND uen.user_id = :user_id "
            . "AND uen.validated_at IS NULL";

        return $this->getAll($user, $sql);
    }

    public function episodeNotificationList(User $user): array
    {
        $sql = "SELECT uen.id as id, en.message as message, en.create_at as created_at, uen.validated_at as validated_at "
            . "FROM episode_notification en "
            . "INNER JOIN user_episode_notification uen ON en.id = uen.episode_notification_id AND uen.user_id = :user_id "
//            . "AND uen.validated_at IS NULL "
            . "ORDER BY en.create_at DESC "
            . "LIMIT 50";

        return $this->getAll($user, $sql);
    }

    public function getAll(User $user, string $sql): array
    {
        try {
            return $this->em->getConnection()
                ->executeQuery($sql, ['user_id' => $user->getId()])
                ->fetchAllAssociative();
        } catch (Exception) {
            return [];
        }
    }
}
