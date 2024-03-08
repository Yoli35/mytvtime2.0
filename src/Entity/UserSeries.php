<?php

namespace App\Entity;

use App\Repository\UserSeriesRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Clock\ClockInterface;

#[ORM\Entity(repositoryClass: UserSeriesRepository::class)]
class UserSeries
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'series')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Series $series = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $addedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastWatchAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $lastSeason = null;

    #[ORM\Column(nullable: true)]
    private ?int $lastEpisode = null;

    #[ORM\Column(options: ['default' => 0])]
    private ?int $viewedEpisodes = null;

    #[ORM\Column(options: ['default' => 0])]
    private ?float $progress = null;

    public function __construct(User $user, Series $serie, $date)
    {
        $this->user = $user;
        $this->series = $serie;
        $this->addedAt = $date;
        $this->viewedEpisodes = 0;
        $this->progress = 0;
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

    public function getSeries(): ?Series
    {
        return $this->series;
    }

    public function setSeries(?Series $series): static
    {
        $this->series = $series;

        return $this;
    }

    public function getAddedAt(): ?\DateTimeImmutable
    {
        return $this->addedAt;
    }

    public function setAddedAt(\DateTimeImmutable $addedAt): static
    {
        $this->addedAt = $addedAt;

        return $this;
    }

    public function getLastWatchAt(): ?\DateTimeImmutable
    {
        return $this->lastWatchAt;
    }

    public function setLastWatchAt(?\DateTimeImmutable $lastWatchAt): static
    {
        $this->lastWatchAt = $lastWatchAt;

        return $this;
    }

    public function getLastSeason(): ?int
    {
        return $this->lastSeason;
    }

    public function setLastSeason(?int $lastSeason): static
    {
        $this->lastSeason = $lastSeason;

        return $this;
    }

    public function getLastEpisode(): ?int
    {
        return $this->lastEpisode;
    }

    public function setLastEpisode(?int $lastEpisode): static
    {
        $this->lastEpisode = $lastEpisode;

        return $this;
    }

    public function getViewedEpisodes(): ?int
    {
        return $this->viewedEpisodes;
    }

    public function setViewedEpisodes(int $viewedEpisodes): static
    {
        $this->viewedEpisodes = $viewedEpisodes;

        return $this;
    }

    public function getProgress(): ?float
    {
        return $this->progress;
    }

    public function setProgress(float $progress): static
    {
        $this->progress = $progress;

        return $this;
    }

    public function homeArray(): array
    {
        return [
            'id' => $this->getSeries()->getId(),
            'name' => $this->getSeries()->getName(),
            'poster_path' => $this->getSeries()->getPosterPath(),
            'tmdbId' => $this->getSeries()->getTmdbId(),
            'slug' => $this->getSeries()->getSlug(),
            'user_id' => $this->getUser()->getId(),
            'progress' => $this->getProgress(),
        ];
    }
}
