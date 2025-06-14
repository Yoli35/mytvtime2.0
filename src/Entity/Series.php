<?php

namespace App\Entity;

use App\Repository\SeriesRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\UX\Turbo\Attribute\Broadcast;

#[ORM\Entity(repositoryClass: SeriesRepository::class)]
class Series
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $posterPath = null;

    #[ORM\Column]
    private ?int $tmdbId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $originalName = null;

    #[ORM\Column(length: 255)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $overview = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $backdropPath = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $firstAirDate = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private ?int $visitNumber = null;

    #[ORM\OneToMany(targetEntity: SeriesLocalizedName::class, mappedBy: 'series', orphanRemoval: true)]
    private Collection $seriesLocalizedNames;

    #[ORM\OneToMany(targetEntity: SeriesWatchLink::class, mappedBy: 'series', orphanRemoval: true)]
    private Collection $seriesWatchLinks;

    #[ORM\OneToMany(targetEntity: SeriesImage::class, mappedBy: 'series', orphanRemoval: true)]
    private Collection $seriesImages;

    #[ORM\OneToMany(targetEntity: SeriesBroadcastSchedule::class, mappedBy: 'series', orphanRemoval: true)]
    private Collection $seriesBroadcastSchedules;

    private array $updates;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $nextEpisodeAirDate = null;

    /**
     * @var Collection<int, SeriesLocalizedOverview>
     */
    #[ORM\OneToMany(targetEntity: SeriesLocalizedOverview::class, mappedBy: 'series', orphanRemoval: true)]
    private Collection $seriesLocalizedOverviews;

    /**
     * @var Collection<int, SeriesAdditionalOverview>
     */
    #[ORM\OneToMany(targetEntity: SeriesAdditionalOverview::class, mappedBy: 'series', orphanRemoval: true)]
    private Collection $seriesAdditionalOverviews;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $status = null;

    /**
     * @var Collection<int, SeasonLocalizedOverview>
     */
    #[ORM\OneToMany(targetEntity: SeasonLocalizedOverview::class, mappedBy: 'series', orphanRemoval: true)]
    private Collection $seasonLocalizedOverviews;

    /**
     * @var Collection<int, Network>
     */
    #[ORM\ManyToMany(targetEntity: Network::class)]
    private Collection $networks;

    /**
     * @var Collection<int, WatchProvider>
     */
    #[ORM\ManyToMany(targetEntity: WatchProvider::class)]
    private Collection $watchProviders;

    #[ORM\Column(nullable: true)]
    private ?array $originCountry = null;

    #[ORM\Column(length: 2, nullable: true)]
    private ?string $originalLanguage = null;

    #[ORM\Column(nullable: true)]
    private ?int $numberOfEpisode = null;

    #[ORM\Column(nullable: true)]
    private ?int $numberOfSeason = null;

    /**
     * @var Collection<int, SeriesVideo>
     */
    #[ORM\OneToMany(targetEntity: SeriesVideo::class, mappedBy: 'series', orphanRemoval: true)]
    private Collection $seriesVideos;

    public function __construct()
    {
        $this->networks = new ArrayCollection();
        $this->seasonLocalizedOverviews = new ArrayCollection();
        $this->seriesAdditionalOverviews = new ArrayCollection();
        $this->seriesBroadcastSchedules = new ArrayCollection();
        $this->seriesImages = new ArrayCollection();
        $this->seriesLocalizedNames = new ArrayCollection();
        $this->seriesLocalizedOverviews = new ArrayCollection();
        $this->seriesWatchLinks = new ArrayCollection();
        $this->updates = [];
        $this->seriesVideos = new ArrayCollection();
        $this->watchProviders = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getPosterPath(): ?string
    {
        return $this->posterPath;
    }

    public function setPosterPath(?string $posterPath): static
    {
        $this->posterPath = $posterPath;

        return $this;
    }

    public function getTmdbId(): ?int
    {
        return $this->tmdbId;
    }

    public function setTmdbId(int $tmdbId): static
    {
        $this->tmdbId = $tmdbId;

        return $this;
    }

    public function getOriginalName(): ?string
    {
        return $this->originalName;
    }

    public function setOriginalName(?string $originalName): static
    {
        $this->originalName = $originalName;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getOverview(): ?string
    {
        return $this->overview;
    }

    public function setOverview(?string $overview): static
    {
        $this->overview = $overview;

        return $this;
    }

    public function getBackdropPath(): ?string
    {
        return $this->backdropPath;
    }

    public function setBackdropPath(?string $backdropPath): static
    {
        $this->backdropPath = $backdropPath;

        return $this;
    }

    public function getFirstAirDate(): ?\DateTimeImmutable
    {
        return $this->firstAirDate;
    }

    public function setFirstAirDate(?\DateTimeImmutable $firstAirDate): static
    {
        $this->firstAirDate = $firstAirDate;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getVisitNumber(): ?int
    {
        return $this->visitNumber;
    }

    public function setVisitNumber(int $visitNumber): static
    {
        $this->visitNumber = $visitNumber;

        return $this;
    }

    public function toArray(string $country = "FR"): array
    {
        return [
            'backdropPath' => $this->getBackdropPath(),
            'createdAt' => $this->getCreatedAt(),
            'firstAirDate' => $this->getFirstAirDate(),
            'id' => $this->getId(),
            'images' => $this->getSeriesImages()->toArray(),
            'localizedNames' => $this->getSeriesLocalizedNames()->toArray(),
            'name' => $this->getName(),
            'nextEpisodeAirDate' => $this->getNextEpisodeAirDate(),
            'originalName' => $this->getOriginalName(),
            'overview' => $this->getOverview(),
            'posterPath' => $this->getPosterPath(),
            'schedules' => $this->getSeriesBroadcastSchedules()->toArray(),
            'slug' => $this->getSlug(),
            'tmdbId' => $this->getTmdbId(),
            'updatedAt' => $this->getUpdatedAt(),
            'updates' => $this->getUpdates(),
            'visitNumber' => $this->getVisitNumber(),
            'watchLinks' => $this->getSeriesWatchLinks()->toArray(),
        ];
    }

    /**
     * @return Collection<int, SeriesLocalizedName>
     */
    public function getSeriesLocalizedNames(): Collection
    {
        return $this->seriesLocalizedNames;
    }

    public function getLocalizedName($locale): ?SeriesLocalizedName
    {
        foreach ($this->seriesLocalizedNames as $seriesLocalizedName) {
            if ($seriesLocalizedName->getLocale() === $locale) {
                return $seriesLocalizedName;
            }
        }
        return null;
    }

    public function addSeriesLocalizedName(SeriesLocalizedName $seriesLocalizedName): static
    {
        if (!$this->seriesLocalizedNames->contains($seriesLocalizedName)) {
            $this->seriesLocalizedNames->add($seriesLocalizedName);
            $seriesLocalizedName->setSeries($this);
        }

        return $this;
    }

    public function removeSeriesLocalizedName(SeriesLocalizedName $seriesLocalizedName): static
    {
        if ($this->seriesLocalizedNames->removeElement($seriesLocalizedName)) {
            // set the owning side to null (unless already changed)
            if ($seriesLocalizedName->getSeries() === $this) {
                $seriesLocalizedName->setSeries(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, SeriesWatchLink>
     */
    public function getSeriesWatchLinks(): Collection
    {
        return $this->seriesWatchLinks;
    }

    public function addSeriesWatchLink(SeriesWatchLink $seriesWatchLink): static
    {
        if (!$this->seriesWatchLinks->contains($seriesWatchLink)) {
            $this->seriesWatchLinks->add($seriesWatchLink);
            $seriesWatchLink->setSeries($this);
        }

        return $this;
    }

    public function removeSeriesWatchLink(SeriesWatchLink $seriesWatchLink): static
    {
        if ($this->seriesWatchLinks->removeElement($seriesWatchLink)) {
            // set the owning side to null (unless already changed)
            if ($seriesWatchLink->getSeries() === $this) {
                $seriesWatchLink->setSeries(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, SeriesImage>
     */
    public function getSeriesImages(): Collection
    {
        return $this->seriesImages;
    }

    public function addSeriesImage(SeriesImage $seriesImage): static
    {
        if (!$this->seriesImages->contains($seriesImage)) {
            $this->seriesImages->add($seriesImage);
            $seriesImage->setSeries($this);
        }

        return $this;
    }

    public function removeSeriesImage(SeriesImage $seriesImage): static
    {
        if ($this->seriesImages->removeElement($seriesImage)) {
            // set the owning side to null (unless already changed)
            if ($seriesImage->getSeries() === $this) {
                $seriesImage->setSeries(null);
            }
        }

        return $this;
    }

    public function getUpdates(): array
    {
        return $this->updates;
    }

    public function addUpdate(string $update): void
    {
        $this->updates[] = $update;
    }

    public function setUpdates(array $updates): void
    {
        $this->updates = $updates;
    }

    /**
     * @return Collection<int, SeriesBroadcastSchedule>
     */
    public function getSeriesBroadcastSchedules(): Collection
    {
        return $this->seriesBroadcastSchedules;
    }

    public function addSeriesBroadcastSchedule(SeriesBroadcastSchedule $seriesBroadcastSchedule): static
    {
        if (!$this->seriesBroadcastSchedules->contains($seriesBroadcastSchedule)) {
            $this->seriesBroadcastSchedules->add($seriesBroadcastSchedule);
            $seriesBroadcastSchedule->setSeries($this);
        }

        return $this;
    }

    public function removeSeriesBroadcastSchedule(SeriesBroadcastSchedule $seriesBroadcastSchedule): static
    {
        if ($this->seriesBroadcastSchedules->removeElement($seriesBroadcastSchedule)) {
            // set the owning side to null (unless already changed)
            if ($seriesBroadcastSchedule->getSeries() === $this) {
                $seriesBroadcastSchedule->setSeries(null);
            }
        }

        return $this;
    }

    public function getNextEpisodeAirDate(): ?\DateTimeImmutable
    {
        return $this->nextEpisodeAirDate;
    }

    public function setNextEpisodeAirDate(?\DateTimeImmutable $nextEpisodeAirDate): static
    {
        $this->nextEpisodeAirDate = $nextEpisodeAirDate;

        return $this;
    }

    /**
     * @return Collection<int, SeriesLocalizedOverview>
     */
    public function getSeriesLocalizedOverviews(): Collection
    {
        return $this->seriesLocalizedOverviews;
    }

    public function getLocalizedOverview($locale): ?SeriesLocalizedOverview
    {
        foreach ($this->seriesLocalizedOverviews as $seriesLocalizedOverview) {
            if ($seriesLocalizedOverview->getLocale() === $locale) {
                return $seriesLocalizedOverview;
            }
        }
        return null;
    }

    public function getLocalizedOverviews($locale): Collection
    {
        $localizedOverviews = new ArrayCollection();
        foreach ($this->seriesLocalizedOverviews as $seriesLocalizedOverview) {
            if ($seriesLocalizedOverview->getLocale() === $locale) {
                $localizedOverviews->add($seriesLocalizedOverview);
            }
        }
        return $localizedOverviews;
    }

    public function addSeriesLocalizedOverview(SeriesLocalizedOverview $seriesLocalizedOverview): static
    {
        if (!$this->seriesLocalizedOverviews->contains($seriesLocalizedOverview)) {
            $this->seriesLocalizedOverviews->add($seriesLocalizedOverview);
            $seriesLocalizedOverview->setSeries($this);
        }

        return $this;
    }

    public function removeSeriesLocalizedOverview(SeriesLocalizedOverview $seriesLocalizedOverview): static
    {
        if ($this->seriesLocalizedOverviews->removeElement($seriesLocalizedOverview)) {
            // set the owning side to null (unless already changed)
            if ($seriesLocalizedOverview->getSeries() === $this) {
                $seriesLocalizedOverview->setSeries(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, SeriesAdditionalOverview>
     */
    public function getSeriesAdditionalOverviews(): Collection
    {
        return $this->seriesAdditionalOverviews;
    }

    public function getSeriesAdditionalLocaleOverviews($locale): array
    {
        $additionalLocaleOverviews = [];
        foreach ($this->seriesAdditionalOverviews as $seriesAdditionalOverview) {
            if ($seriesAdditionalOverview->getLocale() === $locale) {
                $additionalLocaleOverviews[] = $seriesAdditionalOverview;
            }
        }
        return $additionalLocaleOverviews;
    }

    public function addSeriesAdditionalOverview(SeriesAdditionalOverview $seriesAdditionalOverview): static
    {
        if (!$this->seriesAdditionalOverviews->contains($seriesAdditionalOverview)) {
            $this->seriesAdditionalOverviews->add($seriesAdditionalOverview);
            $seriesAdditionalOverview->setSeries($this);
        }

        return $this;
    }

    public function removeSeriesAdditionalOverview(SeriesAdditionalOverview $seriesAdditionalOverview): static
    {
        if ($this->seriesAdditionalOverviews->removeElement($seriesAdditionalOverview)) {
            // set the owning side to null (unless already changed)
            if ($seriesAdditionalOverview->getSeries() === $this) {
                $seriesAdditionalOverview->setSeries(null);
            }
        }

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): static
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @return Collection<int, SeasonLocalizedOverview>
     */
    public function getSeasonLocalizedOverviews(): Collection
    {
        return $this->seasonLocalizedOverviews;
    }

    public function addSeasonLocalizedOverview(SeasonLocalizedOverview $seasonLocalizedOverview): static
    {
        if (!$this->seasonLocalizedOverviews->contains($seasonLocalizedOverview)) {
            $this->seasonLocalizedOverviews->add($seasonLocalizedOverview);
            $seasonLocalizedOverview->setSeries($this);
        }

        return $this;
    }

    public function removeSeasonLocalizedOverview(SeasonLocalizedOverview $seasonLocalizedOverview): static
    {
        if ($this->seasonLocalizedOverviews->removeElement($seasonLocalizedOverview)) {
            // set the owning side to null (unless already changed)
            if ($seasonLocalizedOverview->getSeries() === $this) {
                $seasonLocalizedOverview->setSeries(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Network>
     */
    public function getNetworks(): Collection
    {
        return $this->networks;
    }

    public function addNetwork(Network $network): static
    {
        if (!$this->networks->contains($network)) {
            $this->networks->add($network);
        }

        return $this;
    }

    public function removeNetwork(Network $network): static
    {
        $this->networks->removeElement($network);

        return $this;
    }

    public function getOriginCountry(): ?array
    {
        return $this->originCountry;
    }

    public function setOriginCountry(?array $originCountry): static
    {
        $this->originCountry = $originCountry;

        return $this;
    }

    public function getNumberOfEpisode(): ?int
    {
        return $this->numberOfEpisode;
    }

    public function setNumberOfEpisode(?int $numberOfEpisode): static
    {
        $this->numberOfEpisode = $numberOfEpisode;

        return $this;
    }

    public function getNumberOfSeason(): ?int
    {
        return $this->numberOfSeason;
    }

    public function setNumberOfSeason(?int $numberOfSeason): static
    {
        $this->numberOfSeason = $numberOfSeason;

        return $this;
    }

    /**
     * @return Collection<int, SeriesVideo>
     */
    public function getSeriesVideos(): Collection
    {
        return $this->seriesVideos;
    }

    public function addSeriesVideo(SeriesVideo $seriesVideo): static
    {
        if (!$this->seriesVideos->contains($seriesVideo)) {
            $this->seriesVideos->add($seriesVideo);
            $seriesVideo->setSeries($this);
        }

        return $this;
    }

    public function removeSeriesVideo(SeriesVideo $seriesVideo): static
    {
        if ($this->seriesVideos->removeElement($seriesVideo)) {
            // set the owning side to null (unless already changed)
            if ($seriesVideo->getSeries() === $this) {
                $seriesVideo->setSeries(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, WatchProvider>
     */
    public function getWatchProviders(): Collection
    {
        return $this->watchProviders;
    }

    public function addWatchProvider(WatchProvider $watchProvider): static
    {
        if (!$this->watchProviders->contains($watchProvider)) {
            $this->watchProviders->add($watchProvider);
        }

        return $this;
    }

    public function removeWatchProvider(WatchProvider $watchProvider): static
    {
        $this->watchProviders->removeElement($watchProvider);

        return $this;
    }

    public function getOriginalLanguage(): ?string
    {
        return $this->originalLanguage;
    }

    public function setOriginalLanguage(?string $originalLanguage): static
    {
        $this->originalLanguage = $originalLanguage;

        return $this;
    }
}
