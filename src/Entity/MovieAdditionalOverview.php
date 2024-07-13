<?php

namespace App\Entity;

use App\Repository\MovieAdditionalOverviewRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MovieAdditionalOverviewRepository::class)]
class MovieAdditionalOverview
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'movieAdditionalOverviews')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Movie $movie = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $overview = null;

    #[ORM\Column(length: 8)]
    private ?string $locale = null;

    #[ORM\ManyToOne(inversedBy: 'movieAdditionalOverviews')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Source $source = null;

    public function __construct(Movie $movie, string $overview, string $locale, Source $source)
    {
        $this->movie = $movie;
        $this->overview = $overview;
        $this->locale = $locale;
        $this->source = $source;
    }
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMovie(): ?Movie
    {
        return $this->movie;
    }

    public function setMovie(?Movie $movie): static
    {
        $this->movie = $movie;

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

    public function setSource(?Source $source): static
    {
        $this->source = $source;

        return $this;
    }
}
