<?php

namespace App\Entity;

use App\Repository\UserEpisodeRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserEpisodeRepository::class)]
class UserEpisode
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'userEpisodes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user;

    #[ORM\ManyToOne(inversedBy: 'userEpisodes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?UserSeries $userSeries;

    #[ORM\Column]
    private ?int $episodeId;

    #[ORM\Column]
    private ?int $seasonNumber;

    #[ORM\Column]
    private ?int $episodeNumber;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $airDate = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $watchAt;

    #[ORM\Column(nullable: true)]
    private ?int $providerId = null;

    #[ORM\Column(nullable: true)]
    private ?int $deviceId = null;

    #[ORM\Column(nullable: true)]
    private ?int $vote = null;

    #[ORM\Column(nullable: true)]
    private ?bool $quickWatchDay;

    #[ORM\Column(nullable: true)]
    private ?bool $quickWatchWeek;

    #[ORM\OneToOne(cascade: ['persist'])]
    private ?UserEpisode $previousOccurrence = null;

    private ?DateTimeImmutable $alternativeAirDate = null;

    public function __construct(UserSeries $userSeries, int $episodeId, int $seasonNumber, int $episodeNumber, ?DateTimeImmutable $watchAt)
    {
        $this->user = $userSeries->getUser();
        $this->userSeries = $userSeries;
        $this->episodeId = $episodeId;
        $this->seasonNumber = $seasonNumber;
        $this->episodeNumber = $episodeNumber;
        $this->watchAt = $watchAt;
        $this->quickWatchDay = false;
        $this->quickWatchWeek = false;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getUserSeries(): ?UserSeries
    {
        return $this->userSeries;
    }

    public function setUserSeries(?UserSeries $userSeries): static
    {
        $this->userSeries = $userSeries;

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

    public function getWatchAt(): ?DateTimeImmutable
    {
        return $this->watchAt;
    }

    public function setWatchAt(?DateTimeImmutable $watchAt): static
    {
        $this->watchAt = $watchAt;

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

    public function getDeviceId(): ?int
    {
        return $this->deviceId;
    }

    public function setDeviceId(?int $deviceId): static
    {
        $this->deviceId = $deviceId;

        return $this;
    }

    public function getVote(): ?int
    {
        return $this->vote;
    }

    public function setVote(?int $vote): static
    {
        $this->vote = $vote;

        return $this;
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

    public function isQuickWatchDay(): ?bool
    {
        return $this->quickWatchDay;
    }

    public function setQuickWatchDay(?bool $quickWatchDay): static
    {
        $this->quickWatchDay = $quickWatchDay;

        return $this;
    }

    public function isQuickWatchWeek(): ?bool
    {
        return $this->quickWatchWeek;
    }

    public function setQuickWatchWeek(?bool $quickWatchWeek): static
    {
        $this->quickWatchWeek = $quickWatchWeek;

        return $this;
    }

    public function getAirDate(): ?DateTimeImmutable
    {
        return $this->airDate;
    }

    public function setAirDate(?DateTimeImmutable $airDate): static
    {
        $this->airDate = $airDate;

        return $this;
    }

    public function getPreviousOccurrence(): ?UserEpisode
    {
        return $this->previousOccurrence;
    }

    public function setPreviousOccurrence(?UserEpisode $previousOccurrence): static
    {
        $this->previousOccurrence = $previousOccurrence;

        return $this;
    }

    public function getAlternativeAirDate(): ?DateTimeImmutable
    {
        return $this->alternativeAirDate;
    }

    public function setAlternativeAirDate(?DateTimeImmutable $alternativeAirDate): void
    {
        $this->alternativeAirDate = $alternativeAirDate;
    }
}
