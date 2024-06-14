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

    public function usersQB(): array
    {
        return $this->createQueryBuilder('u')
            ->select('u.id', 'u.avatar', 'u.email', 'u.username', 'COUNT(u.providers)')
            ->addSelect('(SELECT COUNT(us) FROM App\Entity\UserSeries us WHERE us.user=u) as seriesCount')
            ->addSelect('(SELECT COUNT(ue) FROM App\Entity\UserEpisode ue WHERE ue.user=u) as episodeCount')
            ->orderBy('u.id')
            ->getQuery()
            ->getArrayResult();
    }

    public function users(): array
    {
        $sql = "SELECT u.id       as id,
                       u.avatar   as avatar,
                       u.email    as email,
                       u.username as username,
                       u.roles    as roles,
                       (SELECT COUNT(*) FROM user_series us WHERE us.user_id=u.id)   as seriesCount,
                       (SELECT COUNT(*) FROM user_provider up WHERE up.user_id=u.id) as providerCount,
                       (SELECT COUNT(*) FROM user_episode ue WHERE ue.user_id=u.id)  as episodeCount
                FROM user u
                ORDER BY u.id";

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
