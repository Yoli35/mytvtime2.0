<?php

namespace App\Entity;

use App\Repository\SeriesDayOffsetRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SeriesDayOffsetRepository::class)]
class SeriesDayOffset
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'seriesDayOffsets')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Series $series = null;

    #[ORM\Column(options: ['default' => 0])]
    private ?int $offset = null;

    #[ORM\Column(length: 2)]
    private ?string $country = null;

    public function __construct(Series $series, int $offset, string $country)
    {
        $this->series = $series;
        $this->offset = $offset;
        $this->country = $country;
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

    public function getOffset(): ?int
    {
        return $this->offset;
    }

    public function setOffset(int $offset): static
    {
        $this->offset = $offset;

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
}
