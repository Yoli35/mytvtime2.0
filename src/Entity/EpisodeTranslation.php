<?php

namespace App\Entity;

use App\Repository\EpisodeTranslationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EpisodeTranslationRepository::class)]
class EpisodeTranslation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $seriesId;

    #[ORM\Column]
    private ?int $seasonId;

    #[ORM\Column]
    private ?int $episodeId;

    #[ORM\Column(length: 255)]
    private ?string $name;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $overview;

    #[ORM\Column(length: 2)]
    private ?string $language;

    #[ORM\Column(length: 2)]
    private ?string $country;

    public function __construct(int $seriesId, int $seasonId, int $episodeId, string $name, ?string $overview, string $language, string $country)
    {
        $this->seriesId = $seriesId;
        $this->seasonId = $seasonId;
        $this->episodeId = $episodeId;
        $this->name = $name;
        $this->overview = $overview;
        $this->language = $language;
        $this->country = $country;
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getOverview(): ?string
    {
        return $this->overview;
    }

    public function setOverview(string $overview): static
    {
        $this->overview = $overview;

        return $this;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function setLanguage(string $language): static
    {
        $this->language = $language;

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(string $country): static
    {
        $this->country = $country;

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

    public function getSeasonId(): ?int
    {
        return $this->seasonId;
    }

    public function setSeasonId(int $seasonId): static
    {
        $this->seasonId = $seasonId;

        return $this;
    }
}
