<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 *
 * @implements PasswordUpgraderInterface<User>
 *
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry, private readonly EntityManagerInterface $em)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function findUserByUsernameOrEmail($identifier): ?User
    {
        return $this->createQueryBuilder('u')
            ->where('u.username = :val')
            ->orWhere('u.email = :val')
            ->setParameter('val', $identifier)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function adminUsers(int $page, int $limit, string $sort, string $order): array
    {
        $offset = ($page - 1) * $limit;

        $validSorts = ['id', 'username', 'email', 'roles', 'movieCount', 'seriesCount', 'providerCount', 'networkCount', 'episodeCount', 'watchedEpisodeCount'];
        $validOrders = ['ASC', 'DESC'];
        if (!in_array($sort, $validSorts, true)) {
            $sort = 'id';
        }
        if (!in_array(strtoupper($order), $validOrders, true)) {
            $order = 'ASC';
        }

        $sql = "SELECT u.id       as id,
                       u.avatar   as avatar,
                       u.email    as email,
                       u.username as username,
                       u.roles    as roles,
                       (SELECT COUNT(*) FROM user_movie um WHERE um.user_id=u.id)    as movieCount,
                       (SELECT COUNT(*) FROM user_series us WHERE us.user_id=u.id)   as seriesCount,
                       (SELECT COUNT(*) FROM user_watch_provider up WHERE up.user_id=u.id) as providerCount,
                       (SELECT COUNT(*) FROM user_network un WHERE un.user_id=u.id) as networkCount,
                       (SELECT COUNT(*) FROM user_episode ue WHERE ue.user_id=u.id)  as episodeCount,
                       (SELECT COUNT(*) FROM user_episode ue WHERE ue.user_id=u.id AND ue.watch_at IS NOT NULL)  as watchedEpisodeCount
                FROM user u
                ORDER BY $sort $order LIMIT $limit OFFSET $offset";

        return $this->getAll($sql);
    }

    public function getUserNetworkIds($userId): array
    {
        $sql = "SELECT n.network_id
                FROM network n
                INNER JOIN user_network un ON n.id = un.network_id AND un.user_id = $userId";

        return $this->getAll($sql);
    }

    public function getUserProviderIds($userId): array
    {
        $sql = "SELECT p.provider_id
                FROM provider p
                INNER JOIN user_provider up ON p.id = up.provider_id AND up.user_id = $userId";

        return $this->getAll($sql);
    }

    public function getSeriesLanguages(string $locale): array
    {
        $sql = "SELECT `original_language`
                FROM series
                WHERE `original_language` IS NOT NULL
                GROUP BY `original_language`";

        return $this->getAll($sql);
    }

    public    function getMoviesLanguages(string $locale): array
    {
        $sql = "SELECT `original_language`
                FROM movie
                GROUP BY `original_language`";

        return $this->getAll($sql);
    }

    public
    function getAll($sql): array
    {
        try {
            return $this->em->getConnection()->fetchAllAssociative($sql);
        } catch (Exception) {
            return [];
        }
    }
}
