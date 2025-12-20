<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserList;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserList>
 */
class UserListRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly EntityManagerInterface $em)
    {
        parent::__construct($registry, UserList::class);
    }


    public function save(UserList $userList, bool $flush = false): void
    {
        $this->em->persist($userList);

        if ($flush) {
            $this->em->flush();
        }
    }

    public function getUserLists(User $user): array
    {
        $userId = $user->getId();
        $sql = "SELECT *
                FROM `user_list` ul
                WHERE ul.`user_id`=$userId
                ORDER BY ul.`name`";

        return $this->getAll($sql);
    }

    public function getListContent(User $user, int $id, string $locale): array
    {
        $userId = $user->getId();
        $sql = "SELECT
                    s.`id` as id, 
                    s.`tmdb_id` as tmdb_id, 
                    s.`name` as name, 
                    sln.`name` as localized_name,
                    s.`slug` as slug,
                    sln.`slug` as localized_slug,
                    s.`poster_path` as poster_path,
                    s.first_air_date as first_air_date,
                    YEAR(s.`first_air_date`) as air_year
                FROM `series` s
                    INNER JOIN `user_list_series` uls ON uls.`user_list_id`=$id AND s.`id`=uls.`series_id`
                     LEFT JOIN `user_series` us ON us.`series_id`=s.`id` AND us.`user_id`=$userId
                     LEFT JOIN `series_localized_name` sln ON sln.`series_id`=s.`id` AND sln.`locale`='$locale'
                ORDER BY us.`added_at` DESC";

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

    public function getOne($sql): mixed
    {
        try {
            return $this->em->getConnection()->fetchOne($sql);
        } catch (Exception) {
            return [];
        }
    }
}
