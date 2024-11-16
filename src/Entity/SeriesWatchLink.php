<?php

namespace App\Entity;

use App\Repository\SeriesWatchLinkRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SeriesWatchLinkRepository::class)]
class SeriesWatchLink
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $url;

    #[ORM\Column(length: 255)]
    private ?string $name;

    #[ORM\ManyToOne(inversedBy: 'seriesWatchLinks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Series $series;

    #[ORM\Column(nullable: true)]
    private ?int $seasonNumber = null;

    #[ORM\Column(nullable: true)]
    private ?int $providerId;

    public function __construct(string $url, string $name, Series $series, ?int $seasonNumber, ?int $providerId)
    {
        $this->url = $url;
        $this->name = $name;
        $this->series = $series;
        $this->seasonNumber = $seasonNumber;
        $this->providerId = $providerId;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->url = $url;

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

    public function getSeries(): ?Series
    {
        return $this->series;
    }

    public function setSeries(?Series $series): static
    {
        $this->series = $series;

        return $this;
    }

    public function getProviderId(): ?int
    {
        return $this->providerId;
    }

    public function setProviderId(?int $providerId): static
    {
        $this->providerId = $providerId;

        return $this;
    }

    public function getSeasonNumber(): ?int
    {
        return $this->seasonNumber;
    }

    public function setSeasonNumber(?int $seasonNumber): static
    {
        $this->seasonNumber = $seasonNumber ?? -1;

        return $this;
    }
}
