<?php

namespace App\Entity;

use App\Repository\SeriesBroadcastScheduleRepository;
use DateTimeImmutable;
use DateTimeInterface;
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

    #[ORM\Column(length: 2)]
    private ?string $country = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $firstAirDate = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private ?DateTimeInterface $airAt = null;

    #[ORM\Column(type: 'json')]
    private array $daysOfWeek = [];

    #[ORM\Column(nullable: true)]
    private ?int $providerId = null;

    #[ORM\Column(nullable: true)]
    private ?int $frequency = null;

    #[ORM\Column(nullable: true)]
    private ?bool $override = null;

    #[ORM\Column(nullable: true)]
    private ?int $seasonNumber = null;

    #[ORM\Column(nullable: true)]
    private ?int $seasonPart = null;

    #[ORM\Column(nullable: true)]
    private ?int $seasonPartFirstEpisode = null;

    #[ORM\Column(nullable: true)]
    private ?int $seasonPartEpisodeCount = null;

    #[ORM\Column(nullable: true)]
    private ?bool $multiPart = null;

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

    public function getDaysOfWeek(): array
    {
        return $this->daysOfWeek;
    }

    public function setDaysOfWeek(array $daysOfWeek): static
    {
        $this->daysOfWeek = $daysOfWeek;

        return $this;
    }

    public function getAirAt(): ?DateTimeInterface
    {
        return $this->airAt;
    }

    public function setAirAt(DateTimeInterface $airAt): static
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

    public function getFirstAirDate(): ?DateTimeImmutable
    {
        return $this->firstAirDate;
    }

    public function setFirstAirDate(DateTimeImmutable $firstAirDate): static
    {
        $this->firstAirDate = $firstAirDate;

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

    public function getFrequency(): ?int
    {
        return $this->frequency;
    }

    public function setFrequency(?int $frequency): static
    {
        $this->frequency = $frequency;

        return $this;
    }

    public function isOverride(): ?bool
    {
        return $this->override;
    }

    public function setOverride(?bool $override): static
    {
        $this->override = $override;

        return $this;
    }

    public function getSeasonNumber(): ?int
    {
        return $this->seasonNumber;
    }

    public function setSeasonNumber(?int $seasonNumber): static
    {
        $this->seasonNumber = $seasonNumber;

        return $this;
    }

    public function getSeasonPart(): ?int
    {
        return $this->seasonPart;
    }

    public function setSeasonPart(?int $seasonPart): static
    {
        $this->seasonPart = $seasonPart;

        return $this;
    }

    public function getSeasonPartFirstEpisode(): ?int
    {
        return $this->seasonPartFirstEpisode;
    }

    public function setSeasonPartFirstEpisode(?int $seasonPartFirstEpisode): static
    {
        $this->seasonPartFirstEpisode = $seasonPartFirstEpisode;

        return $this;
    }

    public function getSeasonPartEpisodeCount(): ?int
    {
        return $this->seasonPartEpisodeCount;
    }

    public function setSeasonPartEpisodeCount(?int $seasonPartEpisodeCount): static
    {
        $this->seasonPartEpisodeCount = $seasonPartEpisodeCount;

        return $this;
    }

    public function isMultiPart(): ?bool
    {
        return $this->multiPart;
    }

    public function setMultiPart(?bool $multiPart): static
    {
        $this->multiPart = $multiPart;

        return $this;
    }
}
