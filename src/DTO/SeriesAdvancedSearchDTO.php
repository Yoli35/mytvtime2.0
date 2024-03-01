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
    private int $airDateGTE = 0;
    #[Assert\GreaterThanOrEqual(1900)]
    #[Assert\LessThanOrEqual(2100)]
    private int $airDateLTE = 0;
    #[Assert\GreaterThanOrEqual(1900)]
    #[Assert\LessThanOrEqual(2100)]
    private int $firstAirDateYear = 0;
    private DateTimeImmutable $firstAirDateGTE;
    private DateTimeImmutable $firstAirDateLTE;

    // Language and provider
    #[Assert\Country]
    private string $withOriginCountry = '';
    #[Assert\Language]
    private string $withOriginalLanguage = '';
    #[Assert\Choice(['flatrate', 'free', 'ads', 'rent', 'buy'])]
    private string $withWatchMonetizationTypes = '';
    private string $withWatchProviders = '';

    // Runtime, status and type
    #[Assert\GreaterThanOrEqual(0)]
    private int $withRuntimeGTE = 0;
    #[Assert\GreaterThanOrEqual(0)]
    private int $withRuntimeLTE = 0;
    #[Assert\Regex('/^[0-5\|,]*$/')]
    private string $withStatus = '';
    #[Assert\Regex('/^[0-6\|,]*$/')]
    private string $withType = '';

    // Sort and pagination
    #[Assert\Choice(['popularity.desc', 'popularity.asc', 'vote_average.desc', 'vote_average.asc', 'first_air_date.desc', 'first_air_date.asc', 'original_name.desc', 'original_name.asc', 'name.desc', 'name.asc', 'vote_average.desc', 'vote_average.asc', 'vote_count.desc', 'vote_count.asc'])]
    private string $sortBy = 'popularity.desc';
    #[Assert\GreaterThanOrEqual(1)]
    #[Assert\LessThanOrEqual(500)]
    private int $page = 1;
}