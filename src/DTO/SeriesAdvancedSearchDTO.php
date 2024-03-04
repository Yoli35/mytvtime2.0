<?php

namespace App\DTO;
use Symfony\Component\Validator\Constraints as Assert;

use DateTimeImmutable;

class SeriesAdvancedSearchDTO
{
    // User settings
    #[Assert\Language]
    private string $language;
    #[Assert\Timezone]
    private string $timezone;
    #[Assert\Country]
    private string $watchRegion;

    // Search settings
    // Air date
    #[Assert\GreaterThanOrEqual(1900)]
    #[Assert\LessThanOrEqual(2100)]
    private ?int $firstAirDateYear = null;
    private ?DateTimeImmutable $firstAirDateGTE;
    private ?DateTimeImmutable $firstAirDateLTE;

    // Language and provider
    #[Assert\Country]
    private ?string $withOriginCountry = null;
    #[Assert\Language]
    private ?string $withOriginalLanguage = null;
    #[Assert\Choice(['flatrate', 'free', 'ads', 'rent', 'buy'])]
    private ?string $withWatchMonetizationTypes = '';
    private ?string $withWatchProviders = '';
    private array $watchProviders = [];

    // Keywords
    private ?string $withKeywords = null;
    private array $keywords = [];

    // Runtime, status and type
    #[Assert\GreaterThanOrEqual(0)]
    private ?int $withRuntimeGTE = null;
    #[Assert\GreaterThanOrEqual(0)]
    private ?int $withRuntimeLTE = null;
    #[Assert\Regex('/^[0-5\|,]*$/')]
    private ?string $withStatus = null;
    #[Assert\Regex('/^[0-6\|,]*$/')]
    private ?string $withType = null;

    // Sort and pagination
    #[Assert\Choice(['popularity.desc', 'popularity.asc', 'vote_average.desc', 'vote_average.asc', 'first_air_date.desc', 'first_air_date.asc', 'original_name.desc', 'original_name.asc', 'name.desc', 'name.asc', 'vote_average.desc', 'vote_average.asc', 'vote_count.desc', 'vote_count.asc'])]
    private string $sortBy = 'popularity.desc';
    #[Assert\GreaterThanOrEqual(1)]
    #[Assert\LessThanOrEqual(500)]
    private int $page = 1;

    public function __construct($locale = 'fr', $watchRegion = 'FR', $timezone = 'Europe/Paris', $page = 1)
    {
        $this->language = $locale;
        $this->timezone = $timezone;
        $this->watchRegion = $watchRegion;
        $this->page = $page;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function getTimezone(): string
    {
        return $this->timezone;
    }

    public function getWatchRegion(): string
    {
        return $this->watchRegion;
    }

    public function getFirstAirDateYear(): ?int
    {
        return $this->firstAirDateYear;
    }

    public function getFirstAirDateGTE(): ?DateTimeImmutable
    {
        return $this->firstAirDateGTE;
    }

    public function getFirstAirDateLTE(): ?DateTimeImmutable
    {
        return $this->firstAirDateLTE;
    }

    public function getWithOriginCountry(): ?string
    {
        return $this->withOriginCountry;
    }

    public function getWithOriginalLanguage(): ?string
    {
        return $this->withOriginalLanguage;
    }

    public function getWithWatchMonetizationTypes(): ?string
    {
        return $this->withWatchMonetizationTypes;
    }

    public function getWithWatchProviders(): ?string
    {
        return $this->withWatchProviders;
    }

    public function getWatchProviders(): array
    {
        return $this->watchProviders;
    }

    public function getWithRuntimeGTE(): ?int
    {
        return $this->withRuntimeGTE;
    }

    public function getWithRuntimeLTE(): ?int
    {
        return $this->withRuntimeLTE;
    }

    public function getWithStatus(): ?string
    {
        return $this->withStatus;
    }

    public function getWithType(): ?string
    {
        return $this->withType;
    }

    public function getSortBy(): string
    {
        return $this->sortBy;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function setLanguage(string $language): void
    {
        $this->language = $language;
    }

    public function setTimezone(string $timezone): void
    {
        $this->timezone = $timezone;
    }

    public function setWatchRegion(string $watchRegion): void
    {
        $this->watchRegion = $watchRegion;
    }

    public function setFirstAirDateYear(?int $firstAirDateYear): void
    {
        $this->firstAirDateYear = $firstAirDateYear;
    }

    public function setFirstAirDateGTE(?DateTimeImmutable $firstAirDateGTE): void
    {
        $this->firstAirDateGTE = $firstAirDateGTE;
    }

    public function setFirstAirDateLTE(?DateTimeImmutable $firstAirDateLTE): void
    {
        $this->firstAirDateLTE = $firstAirDateLTE;
    }

    public function setWithOriginCountry(?string $withOriginCountry): void
    {
        $this->withOriginCountry = $withOriginCountry;
    }

    public function setWithOriginalLanguage(?string $withOriginalLanguage): void
    {
        $this->withOriginalLanguage = $withOriginalLanguage;
    }

    public function setWithWatchMonetizationTypes(?string $withWatchMonetizationTypes): void
    {
        $this->withWatchMonetizationTypes = $withWatchMonetizationTypes;
    }

    public function setWithWatchProviders(?string $withWatchProviders): void
    {
        $this->withWatchProviders = $withWatchProviders;
    }

    public function setWatchProviders(array $watchProviders): void
    {
        $this->watchProviders = $watchProviders;
    }

    public function setWithRuntimeGTE(?int $withRuntimeGTE): void
    {
        $this->withRuntimeGTE = $withRuntimeGTE;
    }

    public function setWithRuntimeLTE(?int $withRuntimeLTE): void
    {
        $this->withRuntimeLTE = $withRuntimeLTE;
    }

    public function setWithStatus(?string $withStatus): void
    {
        $this->withStatus = $withStatus;
    }

    public function setWithType(?string $withType): void
    {
        $this->withType = $withType;
    }

    public function setSortBy(string $sortBy): void
    {
        $this->sortBy = $sortBy;
    }

    public function setPage(int $page): void
    {
        $this->page = $page;
    }

    public function getWithKeywords(): ?string
    {
        return $this->withKeywords;
    }

    public function setWithKeywords(?string $withKeywords): void
    {
        $this->withKeywords = $withKeywords;
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