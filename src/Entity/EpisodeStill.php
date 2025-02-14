<?php

namespace App\Entity;

use App\Repository\EpisodeStillRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EpisodeStillRepository::class)]
class EpisodeStill
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $episodeId = null;

    #[ORM\Column(length: 255)]
    private ?string $path = null;

    public function __construct(int $episodeId, string $path)
    {
        $this->episodeId = $episodeId;
        $this->path = $path;
    }
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEpisodeId(): ?int
    {
        return $this->episodeId;
    }

    public function setEpisodeId(int $episodeId): static
    {
        $this->episodeId = $episodeId;

        return $this;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(string $path): static
    {
        $this->path = $path;

        return $this;
    }
}
