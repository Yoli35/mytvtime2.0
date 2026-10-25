<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Throwable;

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
        $params = [
            'offset' => $offset,
            'limit' => $limit,
            'sort' => $sort,
        ];
        $types = [
            'offset' => ParameterType::INTEGER,
            'limit' => ParameterType::INTEGER,
            'sort' => ParameterType::STRING,
        ];

        $sql = <<<SQL
                SELECT u.id       AS id,
                       u.avatar   AS avatar,
                       u.email    AS email,
                       u.username AS username,
                       u.roles    AS roles,
                       (SELECT COUNT(*) FROM user_movie um WHERE um.user_id=u.id)          AS movieCount,
                       (SELECT COUNT(*) FROM user_series us WHERE us.user_id=u.id)         AS seriesCount,
                       (SELECT COUNT(*) FROM user_watch_provider up WHERE up.user_id=u.id) AS providerCount,
                       (SELECT COUNT(*) FROM user_network un WHERE un.user_id=u.id)        AS networkCount,
                       (SELECT COUNT(*) FROM user_episode ue WHERE ue.user_id=u.id)        AS episodeCount,
                       (SELECT COUNT(*) FROM user_episode ue WHERE ue.user_id=u.id AND ue.watched_at IS NOT NULL)  AS watchedEpisodeCount
                FROM user u
                ORDER BY :sort $order LIMIT :limit OFFSET :offset
            SQL;

        return $this->getAll($sql, $params, $types);
    }

    public function getUserNetworkIds($userId): array
    {
        $sql = <<<SQL
                SELECT n.network_id
                FROM network n
                INNER JOIN user_network un ON n.id = un.network_id AND un.user_id = :userId
            SQL;

        return $this->getAll($sql, ['user_id' => $userId], ['userId' => ParameterType::INTEGER]);
    }

    public function getUserProviderIds($userId): array
    {
        $sql = <<<SQL
                SELECT wp.provider_id
                FROM watch_provider wp
                INNER JOIN user_watch_provider uwp ON wp.id = uwp.watch_provider_id AND uwp.user_id = :userId
            SQL;

        return $this->getAll($sql, ['user_id' => $userId], ['userId' => ParameterType::INTEGER]);
    }

    public function getSeriesLanguages(): array
    {
        $sql = <<<SQL
                SELECT DISTINCT `original_language`
                FROM series
                WHERE `original_language` IS NOT NULL
            SQL;

        return $this->getAll($sql);
    }

    public function getMoviesLanguages(): array
    {
        $sql = <<<SQL
                SELECT DISTINCT `original_language`
                FROM movie
            SQL;

        return $this->getAll($sql);
    }

    /**
     * Supprime un utilisateur et toutes ses données en SQL direct (les relations « orphanRemoval »
     * de l'entité User obligeraient Doctrine à charger des dizaines de milliers d'entités).
     * L'ordre respecte les clés étrangères (NO ACTION) ; user_device, user_network,
     * user_watch_provider, user_oauth_account, user_list_series, photo_album et
     * user_season_series_broadcast_schedule sont supprimées par ON DELETE CASCADE.
     *
     * Retourne les chemins des images de commentaires pour supprimer les fichiers ensuite.
     *
     * @throws Exception
     */
    public function deleteAccount(int $userId): array
    {
        $connection = $this->em->getConnection();
        $params = ['userId' => $userId];
        $types = ['userId' => ParameterType::INTEGER];

        $commentImagePaths = $connection->fetchFirstColumn(
            'SELECT eci.`path` FROM episode_comment_image eci INNER JOIN episode_comment ec ON ec.id = eci.episode_comment_id WHERE ec.user_id = :userId',
            $params, $types
        );
        $commentImagePaths = array_merge($commentImagePaths, $connection->fetchFirstColumn(
            'SELECT mci.`path` FROM movie_comment_image mci INNER JOIN movie_comment mc ON mc.id = mci.movie_comment_id WHERE mc.user_id = :userId',
            $params, $types
        ));

        $statements = [
            // Références croisées user_series ↔ user_episode
            'UPDATE user_series SET last_user_episode_id = NULL, next_user_episode_id = NULL WHERE user_id = :userId',
            'UPDATE user_episode SET previous_occurrence_id = NULL WHERE user_id = :userId',
            'DELETE FROM user_pinned_series WHERE user_id = :userId',
            'DELETE FROM user_episode WHERE user_id = :userId',
            'DELETE us FROM user_season us INNER JOIN user_series s ON s.id = us.user_series_id WHERE s.user_id = :userId',
            'DELETE FROM user_series WHERE user_id = :userId',
            'DELETE FROM user_episode_notification WHERE user_id = :userId',
            'DELETE FROM user_movie WHERE user_id = :userId',
            'DELETE FROM user_video WHERE user_id = :userId',
            'DELETE FROM user_list WHERE user_id = :userId',
            'DELETE FROM history WHERE user_id = :userId',
            'DELETE FROM settings WHERE user_id = :userId',
            'DELETE FROM people_user_rating WHERE user_id = :userId',
            'DELETE FROM people_user_preferred_name WHERE user_id = :userId',
            // Les réponses (y compris celles des autres utilisateurs) aux commentaires supprimés deviennent des commentaires de premier niveau
            'UPDATE episode_comment reply INNER JOIN episode_comment parent ON parent.id = reply.reply_to_id SET reply.reply_to_id = NULL WHERE parent.user_id = :userId',
            'DELETE eci FROM episode_comment_image eci INNER JOIN episode_comment ec ON ec.id = eci.episode_comment_id WHERE ec.user_id = :userId',
            'DELETE FROM episode_comment WHERE user_id = :userId',
            // Commentaires de films : les images (ON DELETE CASCADE) suivent, les réponses des autres utilisateurs deviennent de premier niveau (ON DELETE SET NULL)
            'DELETE FROM movie_comment WHERE user_id = :userId',
            'DELETE FROM photo WHERE user_id = :userId',
            'DELETE FROM album WHERE user_id = :userId',
            'DELETE FROM `user` WHERE id = :userId',
        ];

        try {
            $connection->transactional(function ($connection) use ($statements, $params, $types) {
                foreach ($statements as $sql) {
                    $connection->executeStatement($sql, $params, $types);
                }
            });
        } catch (Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }

        return [
            'success' => true,
            'commentImagePaths' => $commentImagePaths,
        ];
    }

    public function getAll(string $sql, array $params = [], array $types = []): array
    {
        try {
            return $this->em->getConnection()->fetchAllAssociative($sql, $params, $types);
        } catch (Exception) {
            return [];
        }
    }
}
