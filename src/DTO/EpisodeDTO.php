<?php

namespace App\DTO;

use App\Entity\Series;
use App\Entity\User;
use DateTimeImmutable;
use Symfony\Component\Validator\Constraints as Assert;
class EpisodeDTO
{
    private User $user;
    private Series $series;
    private int $seasonNumber;
    private int $episodeNumber;
    private DateTimeImmutable $watchedAt;
    private int $providerId;
    private int $deviceId;
    private int $vote;

    public function __construct(User $user, Series $series, int $seasonNumber, int $episodeNumber, DateTimeImmutable $watchedAt)
    {
        $this->user = $user;
        $this->series = $series;
        $this->seasonNumber = $seasonNumber;
        $this->episodeNumber = $episodeNumber;
        $this->watchedAt = $watchedAt;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): void
    {
        $this->user = $user;
    }

    public function getSeries(): Series
    {
        return $this->series;
    }

    public function setSeries(Series $series): void
    {
        $this->series = $series;
    }

    public function getSeasonNumber(): int
    {
        return $this->seasonNumber;
    }

    public function setSeasonNumber(int $seasonNumber): void
    {
        $this->seasonNumber = $seasonNumber;
    }

    public function getEpisodeNumber(): int
    {
        return $this->episodeNumber;
    }

    public function setEpisodeNumber(int $episodeNumber): void
    {
        $this->episodeNumber = $episodeNumber;
    }

    public function getWatchedAt(): DateTimeImmutable
    {
        return $this->watchedAt;
    }

    public function setWatchedAt(DateTimeImmutable $watchedAt): void
    {
        $this->watchedAt = $watchedAt;
    }

    public function getProviderId(): int
    {
        return $this->providerId;
    }

    public function setProviderId(int $providerId): void
    {
        $this->providerId = $providerId;
    }

    public function getDeviceId(): int
    {
        return $this->deviceId;
    }

    public function setDeviceId(int $deviceId): void
    {
        $this->deviceId = $deviceId;
    }

    public function getVote(): int
    {
        return $this->vote;
    }

    public function setVote(int $vote): void
    {
        $this->vote = $vote;
    }
}