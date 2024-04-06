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
    private ?\DateTimeImmutable $firstDateAir = null;

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

    public function __construct()
    {
        $this->seriesLocalizedNames = new ArrayCollection();
        $this->seriesWatchLinks = new ArrayCollection();
        $this->seriesImages = new ArrayCollection();
        $this->seriesBroadcastSchedules = new ArrayCollection();
        $this->updates = [];
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

    public function getFirstDateAir(): ?\DateTimeImmutable
    {
        return $this->firstDateAir;
    }

    public function setFirstDateAir(?\DateTimeImmutable $firstDateAir): static
    {
        $this->firstDateAir = $firstDateAir;

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

    public function toArray(): array
    {
        return [
            'backdropPath' => $this->getBackdropPath(),
            'createdAt' => $this->getCreatedAt(),
            'firstDateAir' => $this->getFirstDateAir(),
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
}
