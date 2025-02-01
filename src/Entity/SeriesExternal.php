<?php

namespace App\Entity;

use App\Repository\SeriesExternalRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SeriesExternalRepository::class)]
class SeriesExternal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $logoPath = null;

    #[ORM\Column(length: 255)]
    private ?string $baseUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $searchQuery = null;

    public ?string $fullUrl = null {
        get {
            return $this->fullUrl;
        }
        set {
            $this->fullUrl = $value ? $this->baseUrl . $this->searchQuery . $value : $this->baseUrl;
        }
    }

    #[ORM\Column]
    private array $countries = [];

    #[ORM\Column]
    private array $keywords = [];

    #[ORM\Column(length: 1, nullable: true)]
    private ?string $searchSeparator = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $searchType = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getLogoPath(): ?string
    {
        return $this->logoPath;
    }

    public function setLogoPath(?string $logoPath): static
    {
        $this->logoPath = $logoPath;

        return $this;
    }

    public function getBaseUrl(): ?string
    {
        return $this->baseUrl;
    }

    public function setBaseUrl(string $baseUrl): static
    {
        $this->baseUrl = $baseUrl;

        return $this;
    }

    public function getSearchQuery(): ?string
    {
        return $this->searchQuery;
    }

    public function setSearchQuery(?string $searchQuery): static
    {
        $this->searchQuery = $searchQuery;

        return $this;
    }

    public function getCountries(): array
    {
        return $this->countries;
    }

    public function setCountries(array $countries): static
    {
        $this->countries = $countries;

        return $this;
    }

    public function getSearchSeparator(): ?string
    {
        return $this->searchSeparator;
    }

    public function setSearchSeparator(?string $searchSeparator): static
    {
        $this->searchSeparator = $searchSeparator;

        return $this;
    }

    public function getSearchType(): ?string
    {
        return $this->searchType;
    }

    public function setSearchType(?string $searchType): static
    {
        $this->searchType = $searchType;

        return $this;
    }

    public function getKeywords(): array
    {
        return $this->keywords;
    }

    public function setKeywords(array $keywords): void
    {
        $this->keywords = $keywords;
    }
}
