<?php

namespace App\Repository;

use App\Entity\VideoChannel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VideoChannel>
 */
class VideoChannelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly EntityManagerInterface $em)
    {
        parent::__construct($registry, VideoChannel::class);
    }

    public function save(VideoChannel $channel, bool $flush = false): void
    {
        $this->em->persist($channel);

        if ($flush) {
            $this->em->flush();
        }
    }
}
