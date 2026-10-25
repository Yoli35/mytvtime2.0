<?php

namespace App\Repository;

use App\Entity\Movie;
use App\Entity\MovieComment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MovieComment>
 */
class MovieCommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MovieComment::class);
    }

    public function save(MovieComment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(MovieComment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Commentaires d'un film, du plus ancien au plus récent : un commentaire parent est toujours renvoyé avant ses réponses.
     * Tri par id et non par createdAt : les dates sont enregistrées en heure locale, l'heure répétée du passage à l'heure d'hiver pourrait inverser l'ordre.
     *
     * @return MovieComment[]
     */
    public function findByMovie(Movie $movie): array
    {
        return $this->findBy(['movie' => $movie], ['id' => 'ASC']);
    }
}
