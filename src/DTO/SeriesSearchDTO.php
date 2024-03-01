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
    private int $firstAirDateYear;
}
