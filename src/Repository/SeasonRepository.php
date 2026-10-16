<?php

namespace App\Repository;

use App\Entity\Season;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Season>
 */
class SeasonRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly EntityManagerInterface $em,
    )
    {
        parent::__construct($registry, Season::class);
    }

    public function save(Season $entity, bool $flush = false): void
    {
        $this->em->persist($entity);

        if ($flush) {
            $this->em->flush();
        }
    }

    public function flush(): void
    {
        $this->em->flush();
    }

    /**
     * Saisons (avec leurs épisodes) correspondant aux couples série / numéro de saison.
     *
     * @param array<array{seriesId: int, seasonNumber: int}> $keys
     * @return array<string, Season> Saisons indexées par "{seriesId}-{seasonNumber}"
     */
    public function findWithEpisodes(array $keys, string $locale): array
    {
        if (!count($keys)) {
            return [];
        }
        $qb = $this->createQueryBuilder('s')
            ->addSelect('e')
            ->leftJoin('s.episodes', 'e')
            ->where('s.locale = :locale')
            ->setParameter('locale', $locale);
        $or = $qb->expr()->orX();
        foreach (array_values($keys) as $i => $key) {
            $or->add("(IDENTITY(s.series) = :series$i AND s.seasonNumber = :number$i)");
            $qb->setParameter("series$i", $key['seriesId'])
                ->setParameter("number$i", $key['seasonNumber']);
        }
        $qb->andWhere($or);

        $seasons = [];
        foreach ($qb->getQuery()->getResult() as $season) {
            /** @var Season $season */
            $seasons[$season->getSeries()->getId() . '-' . $season->getSeasonNumber()] = $season;
        }
        return $seasons;
    }
}
