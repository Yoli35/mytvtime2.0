<?php

namespace App\Entity;

use App\Repository\SeasonLocalizedOverviewRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SeasonLocalizedOverviewRepository::class)]
class SeasonLocalizedOverview
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'seasonLocalizedOverviews')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Series $series;

    #[ORM\Column]
    private ?int $seasonNumber;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $overview;

    #[ORM\Column(length: 8)]
    private ?string $locale;

    #[ORM\ManyToOne(inversedBy: 'seriesAdditionalOverviews')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Source $source;

    public function __construct(Series $series, int $seasonNumber, string $overview, string $locale, ?Source $source = null)
    {
        $this->series = $series;
        $this->seasonNumber = $seasonNumber;
        $this->overview = $overview;
        $this->locale = $locale;
        $this->source = $source;
    }
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSeries(): ?Series
    {
        return $this->series;
    }

    public function setSeries(?Series $series): static
    {
        $this->series = $series;

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

    public function getOverview(): ?string
    {
        return $this->overview;
    }

    public function setOverview(string $overview): static
    {
        $this->overview = $overview;

        return $this;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getSource(): ?Source
    {
        return $this->source;
    }

    public function setSource(?Source $source): void
    {
        $this->source = $source;
    }
}
