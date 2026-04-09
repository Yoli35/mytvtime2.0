<?php

namespace App\Entity;

use App\Repository\SeasonPosterRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SeasonPosterRepository::class)]
class SeasonPoster
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $posterPath = null;

    #[ORM\Column]
    private ?int $seasonId = null;

    #[ORM\Column]
    private ?int $seasonNumber = null;

    #[ORM\Column]
    private ?int $seriesId = null;

    #[ORM\Column]
    private ?int $tvId = null;

    public function __construct(string $posterPath, int $seasonId, int $seasonNumber, int $seriesId, int $tvId)
    {
        $this->posterPath = $posterPath;
        $this->seasonId = $seasonId;
        $this->seasonNumber = $seasonNumber;
        $this->seriesId = $seriesId;
        $this->tvId = $tvId;
    }
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPosterPath(): ?string
    {
        return $this->posterPath;
    }

    public function setPosterPath(string $posterPath): static
    {
        $this->posterPath = $posterPath;

        return $this;
    }

    public function getSeasonId(): ?int
    {
        return $this->seasonId;
    }

    public function setSeasonId(int $seasonId): static
    {
        $this->seasonId = $seasonId;

        return $this;
    }

    public function getSeasonNumber(): ?int
    {
        return $this->seasonNumber;
    }

    public function setSeasonNumber(int $seasonNumber): static
    {
        $this->seasonNumber = $seasonNumber;

        return $this;
    }

    public function getSeriesId(): ?int
    {
        return $this->seriesId;
    }

    public function setSeriesId(int $seriesId): static
    {
        $this->seriesId = $seriesId;

        return $this;
    }

    public function getTvId(): ?int
    {
        return $this->tvId;
    }

    public function setTvId(int $tvId): static
    {
        $this->tvId = $tvId;

        return $this;
    }
}
