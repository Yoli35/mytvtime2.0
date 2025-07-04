<?php

namespace App\Entity;

use App\Repository\FilmingLocationRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FilmingLocationRepository::class)]
class FilmingLocation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?bool $isSeries;

    #[ORM\Column]
    private ?int $tmdbId;

    #[ORM\Column(length: 255)]
    private ?string $title;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $location;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description;

    #[ORM\Column]
    private ?float $latitude;

    #[ORM\Column]
    private ?float $longitude;

    /**
     * @var Collection<int, FilmingLocationImage>
     */
    #[ORM\OneToMany(targetEntity: FilmingLocationImage::class, mappedBy: 'filmingLocation', orphanRemoval: true)]
    private Collection $filmingLocationImages;

    #[ORM\Column(length: 36, nullable: true)]
    private ?string $uuid = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    private ?FilmingLocationImage $still = null;

    #[ORM\Column(nullable: true)]
    private ?array $originCountry = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $episodeNumber = null;

    #[ORM\Column(nullable: true)]
    private ?int $seasonNumber = null;

    #[ORM\Column(nullable: true)]
    private ?float $radius = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sourceName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sourceUrl = null;

    public function __construct(string $uuid, int $tmdbId, string $title, string $location, string $description, float $latitude, float $longitude, ?float $radius, int $seasonNumber, int $episodeNumber, ?string $sourceName, ?string $sourceUrl, DateTimeImmutable $now, bool $isSeries = false)
    {
        $this->createdAt = $now;
        $this->description = $description;
        $this->episodeNumber = $episodeNumber;
        $this->filmingLocationImages = new ArrayCollection();
        $this->isSeries = $isSeries;
        $this->latitude = $latitude;
        $this->location = $location;
        $this->longitude = $longitude;
        $this->radius = $radius;
        $this->seasonNumber = $seasonNumber;
        $this->sourceName = $sourceName;
        $this->sourceUrl = $sourceUrl;
        $this->title = $title;
        $this->tmdbId = $tmdbId;
        $this->updatedAt = $now;
        $this->uuid = $uuid;
    }

    public function update(string $title, string $location, string $description, float $latitude, float $longitude, ?float $radius, int $seasonNumber, int $episodeNumber, ?string $sourceName, ?string $sourceUrl, DateTimeImmutable $updateAt): void
    {
        $this->description = $description;
        $this->episodeNumber = $episodeNumber;
        $this->latitude = $latitude;
        $this->location = $location;
        $this->longitude = $longitude;
        $this->radius = $radius;
        $this->seasonNumber = $seasonNumber;
        $this->sourceName = $sourceName;
        $this->sourceUrl = $sourceUrl;
        $this->title = $title;
        $this->updatedAt = $updateAt;
    }

    public function toArray(): array
    {
        return [
            'created_at' => $this->getCreatedAt()?->format('Y-m-d H:i:s'),
            'description' => $this->getDescription(),
            'episode_number' => $this->getEpisodeNumber(),
            'filming_location_images' => [],
            'id' => $this->getId(),
            'is_series' => $this->isSeries(),
            'latitude' => $this->getLatitude(),
            'location' => $this->getLocation(),
            'longitude' => $this->getLongitude(),
            'origin_country' => $this->getOriginCountry(),
            'season_number' => $this->getSeasonNumber(),
            'title' => $this->getTitle(),
            'tmdb_id' => $this->getTmdbId(),
            'updated_at' => $this->getUpdatedAt()?->format('Y-m-d H:i:s'),
            'uuid' => $this->getUuid(),
        ];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isSeries(): ?bool
    {
        return $this->isSeries;
    }

    public function setSeries(bool $isSeries): static
    {
        $this->isSeries = $isSeries;

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

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(float $latitude): static
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(float $longitude): static
    {
        $this->longitude = $longitude;

        return $this;
    }

    /**
     * @return Collection<int, FilmingLocationImage>
     */
    public function getFilmingLocationImages(): Collection
    {
        return $this->filmingLocationImages;
    }

    public function addFilmingLocationImage(FilmingLocationImage $filmingLocationImage): static
    {
        if (!$this->filmingLocationImages->contains($filmingLocationImage)) {
            $this->filmingLocationImages->add($filmingLocationImage);
            $filmingLocationImage->setFilmingLocation($this);
        }

        return $this;
    }

    public function removeFilmingLocationImage(FilmingLocationImage $filmingLocationImage): static
    {
        if ($this->filmingLocationImages->removeElement($filmingLocationImage)) {
            // set the owning side to null (unless already changed)
            if ($filmingLocationImage->getFilmingLocation() === $this) {
                $filmingLocationImage->setFilmingLocation(null);
            }
        }

        return $this;
    }

    public function getUuid(): ?string
    {
        return $this->uuid;
    }

    public function setUuid(?string $uuid): static
    {
        $this->uuid = $uuid;

        return $this;
    }

    public function getStill(): ?FilmingLocationImage
    {
        return $this->still;
    }

    public function setStill(?FilmingLocationImage $still): static
    {
        $this->still = $still;

        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): void
    {
        $this->location = $location;
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

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getEpisodeNumber(): ?int
    {
        return $this->episodeNumber;
    }

    public function setEpisodeNumber(?int $episodeNumber): static
    {
        $this->episodeNumber = $episodeNumber;

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

    public function getRadius(): ?float
    {
        return $this->radius;
    }

    public function setRadius(?float $radius): static
    {
        $this->radius = $radius;

        return $this;
    }

    public function getSourceName(): ?string
    {
        return $this->sourceName;
    }

    public function setSourceName(?string $sourceName): static
    {
        $this->sourceName = $sourceName;

        return $this;
    }

    public function getSourceUrl(): ?string
    {
        return $this->sourceUrl;
    }

    public function setSourceUrl(?string $sourceUrl): static
    {
        $this->sourceUrl = $sourceUrl;

        return $this;
    }
}
