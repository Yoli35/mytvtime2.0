<?php

namespace App\Entity;

use App\Repository\SeriesBroadcastDateRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SeriesBroadcastDateRepository::class)]
class SeriesBroadcastDate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $episodeId;

    #[ORM\Column]
    private ?int $seasonNumber;

    #[ORM\Column]
    private ?int $episodeNumber;

    #[ORM\Column]
    private ?DateTimeImmutable $date;

    #[ORM\ManyToOne(inversedBy: 'customDates')]
    #[ORM\JoinColumn(nullable: false)]
    private ?SeriesBroadcastSchedule $seriesBroadcastSchedule;

    #[ORM\Column]
    private ?int $seasonPart = null;

    public function __construct(SeriesBroadcastSchedule $seriesBroadcastSchedule, int $episodeId, int $seasonNumber, int $seasonPart, int $episodeNumber, DateTimeImmutable $date)
    {
        $this->seriesBroadcastSchedule = $seriesBroadcastSchedule;
        $this->episodeId = $episodeId;
        $this->seasonNumber = $seasonNumber;
        $this->seasonPart = $seasonPart;
        $this->episodeNumber = $episodeNumber;
        $this->date = $date;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEpisodeId(): ?int
    {
        return $this->episodeId;
    }

    public function setEpisodeId(int $episodeId): static
    {
        $this->episodeId = $episodeId;

        return $this;
    }

    public function getSeasonNumber(): ?int
    {
        return $this->seasonNumber;
    }

    public function setSeasonNumber(int $seasonNumber): static
    {
        $this->seasonNumber = $seasonNumber;

        return $this;
    }

    public function getEpisodeNumber(): ?int
    {
        return $this->episodeNumber;
    }

    public function setEpisodeNumber(int $episodeNumber): static
    {
        $this->episodeNumber = $episodeNumber;

        return $this;
    }

    public function getDate(): ?DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(DateTimeImmutable $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getSeriesBroadcastSchedule(): ?SeriesBroadcastSchedule
    {
        return $this->seriesBroadcastSchedule;
    }

    public function setSeriesBroadcastSchedule(?SeriesBroadcastSchedule $seriesBroadcastSchedule): static
    {
        $this->seriesBroadcastSchedule = $seriesBroadcastSchedule;

        return $this;
    }

    public function getSeasonPart(): ?int
    {
        return $this->seasonPart;
    }

    public function setSeasonPart(int $seasonPart): static
    {
        $this->seasonPart = $seasonPart;

        return $this;
    }
}
