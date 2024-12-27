<?php

namespace App\Entity;

use App\Repository\CountryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CountryRepository::class)]
class Country
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 2)]
    private ?string $code;

    #[ORM\Column(length: 32)]
    private ?string $english_name;

    private string $displayName;

    #[ORM\Column]
    private ?float $lat1;

    #[ORM\Column]
    private ?float $lng1;

    #[ORM\Column]
    private ?float $lat2;

    #[ORM\Column]
    private ?float $lng2;

    public function __construct(string $code, string $english_name, float $lat1, float $lng1, float $lat2, float $lng2)
    {
        $this->code = $code;
        $this->english_name = $english_name;
        $this->lat1 = $lat1;
        $this->lng1 = $lng1;
        $this->lat2 = $lat2;
        $this->lng2 = $lng2;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getEnglishName(): ?string
    {
        return $this->english_name;
    }

    public function setEnglishName(string $english_name): static
    {
        $this->english_name = $english_name;

        return $this;
    }

    public function getLat1(): ?float
    {
        return $this->lat1;
    }

    public function setLat1(float $lat1): static
    {
        $this->lat1 = $lat1;

        return $this;
    }

    public function getLng1(): ?float
    {
        return $this->lng1;
    }

    public function setLng1(float $lng1): static
    {
        $this->lng1 = $lng1;

        return $this;
    }

    public function getLat2(): ?float
    {
        return $this->lat2;
    }

    public function setLat2(float $lat2): static
    {
        $this->lat2 = $lat2;

        return $this;
    }

    public function getLng2(): ?float
    {
        return $this->lng2;
    }

    public function setLng2(float $lng2): static
    {
        $this->lng2 = $lng2;

        return $this;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): void
    {
        $this->displayName = $displayName;
    }
}
