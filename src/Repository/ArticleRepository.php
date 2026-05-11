<?php

namespace App\Repository;

use App\Entity\Article;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Article>
 */
class ArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly EntityManagerInterface $em)
    {
        parent::__construct($registry, Article::class);
    }

    public function save(Article $article, bool $flush = false): void
    {
        $this->em->persist($article);
        if ($flush) {
            $this->em->flush();
        }
    }

    public function remove(Article $article, bool $flush = false): void
    {
        $this->em->remove($article);
        if ($flush) {
            $this->em->flush();
        }
    }
}
