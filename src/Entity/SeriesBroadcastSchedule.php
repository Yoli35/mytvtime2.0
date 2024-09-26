<?php

namespace App\Entity;

use App\Repository\SeriesBroadcastScheduleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SeriesBroadcastScheduleRepository::class)]
class SeriesBroadcastSchedule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'seriesBroadcastSchedules')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Series $series = null;

    #[ORM\Column(type: 'json')]
    private array $dayOfWeek = [];

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private ?\DateTimeInterface $airAt = null;

    #[ORM\Column(length: 2)]
    private ?string $country = null;

    #[ORM\Column]
    private ?int $utc = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $firstAirDate = null;

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

    public function getDayOfWeek(): array
    {
        return $this->dayOfWeek;
    }

    public function setDayOfWeek(array $dayOfWeek): static
    {
        $this->dayOfWeek = $dayOfWeek;

        return $this;
    }

    public function getAirAt(): ?\DateTimeInterface
    {
        return $this->airAt;
    }

    public function setAirAt(\DateTimeInterface $airAt): static
    {
        $this->airAt = $airAt;

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

    public function getUtc(): ?int
    {
        return $this->utc;
    }

    public function setUtc(int $utc): static
    {
        $this->utc = $utc;

        return $this;
    }

    public function getFirstAirDate(): ?\DateTimeImmutable
    {
        return $this->firstAirDate;
    }

    public function setFirstAirDate(\DateTimeImmutable $firstAirDate): static
    {
        $this->firstAirDate = $firstAirDate;

        return $this;
    }
}
