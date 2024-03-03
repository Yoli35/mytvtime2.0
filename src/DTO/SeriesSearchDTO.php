<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class SeriesSearchDTO
{
    #[Assert\NotBlank]
    private string $query;
    #[Assert\GreaterThanOrEqual(1)]
    #[Assert\LessThanOrEqual(500)]
    private int $page;
    #[Assert\Length(min: 2, max: 5)]
    private string $language;
    #[Assert\GreaterThanOrEqual(1900)]
    #[Assert\LessThanOrEqual(2100)]
    private ?int $firstAirDateYear;

    public function __construct($locale = 'en', $page = 1)
    {
        $this->query = '';
        $this->language = $locale;
        $this->page = $page;
        $this->firstAirDateYear = null;
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function getFirstAirDateYear(): ?int
    {
        return $this->firstAirDateYear;
    }

    public function setQuery(string $query): void
    {
        $this->query = $query;
    }

    public function setFirstAirDateYear(?int $firstAirDateYear): void
    {
        $this->firstAirDateYear = $firstAirDateYear;
    }

    public function setPage(int $page): void
    {
        $this->page = $page;
    }

    public function setLanguage(string $language): void
    {
        $this->language = $language;
    }
}
