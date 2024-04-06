<?php

namespace App\Entity;

use App\Repository\UserSeriesRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

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
    private ?DateTimeImmutable $addedAt;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $lastWatchAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $lastSeason = null;

    #[ORM\Column(nullable: true)]
    private ?int $lastEpisode = null;

    #[ORM\Column(options: ['default' => 0])]
    private ?int $viewedEpisodes;

    #[ORM\Column(options: ['default' => 0])]
    private ?float $progress;

    #[ORM\Column(nullable: true)]
    private ?bool $favorite;

    #[ORM\Column(nullable: true)]
    private ?bool $marathoner;

    #[ORM\Column(nullable: true, options: ['default' => 0])]
    private ?int $rating;

    #[ORM\OneToMany(targetEntity: UserEpisode::class, mappedBy: 'userSeries', fetch: 'EXTRA_LAZY', orphanRemoval: true)]
    #[ORM\OrderBy(['seasonNumber' => 'ASC', 'episodeNumber' => 'ASC'])]
    private Collection $userEpisodes;

    #[ORM\Column(nullable: true)]
    private ?bool $binge = null;

    public function __construct(User $user, Series $serie, DateTimeImmutable $date)
    {
        $this->user = $user;
        $this->series = $serie;
        $this->addedAt = $date;
        $this->viewedEpisodes = 0;
        $this->progress = 0;
        $this->favorite = false;
        $this->marathoner = false;
        $this->rating = 0;
        $this->userEpisodes = new ArrayCollection();
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

    public function getAddedAt(): ?DateTimeImmutable
    {
        return $this->addedAt;
    }

    public function setAddedAt(DateTimeImmutable $addedAt): static
    {
        $this->addedAt = $addedAt;

        return $this;
    }

    public function getLastWatchAt(): ?DateTimeImmutable
    {
        return $this->lastWatchAt;
    }

    public function setLastWatchAt(?DateTimeImmutable $lastWatchAt): static
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
            'favorite' => $this->isFavorite(),
            'marathoner' => $this->getMarathoner(),
        ];
    }

    public function isFavorite(): ?bool
    {
        return $this->favorite;
    }

    public function setFavorite(?bool $favorite): static
    {
        $this->favorite = $favorite;

        return $this;
    }

    public function getRating(): ?int
    {
        return $this->rating;
    }

    public function setRating(?int $rating): static
    {
        $this->rating = $rating;

        return $this;
    }

    /**
     * @return Collection<int, UserEpisode>
     */
    public function getUserEpisodes(): Collection
    {
        return $this->userEpisodes;
    }

    public function addUserEpisode(UserEpisode $userEpisode): static
    {
        if (!$this->userEpisodes->contains($userEpisode)) {
            $this->userEpisodes->add($userEpisode);
            $userEpisode->setUserSeries($this);
        }

        return $this;
    }

    public function removeUserEpisode(UserEpisode $userEpisode): static
    {
        if ($this->userEpisodes->removeElement($userEpisode)) {
            // set the owning side to null (unless already changed)
            if ($userEpisode->getUserSeries() === $this) {
                $userEpisode->setUserSeries(null);
            }
        }

        return $this;
    }

    public function getEpisode(int $episodeId): ?UserEpisode
    {
        foreach ($this->getUserEpisodes() as $userEpisode) {
            if ($userEpisode->getEpisodeId() === $episodeId) {
                return $userEpisode;
            }
        }
        return null;
    }

    public function getMarathoner(): ?bool
    {
        return $this->marathoner;
    }

    public function setMarathoner(?bool $marathoner): void
    {
        $this->marathoner = $marathoner;
    }

    public function isBinge(): ?bool
    {
        return $this->binge;
    }

    public function setBinge(?bool $binge): static
    {
        $this->binge = $binge;

        return $this;
    }
}
