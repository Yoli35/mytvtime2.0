<?php

namespace App\Entity;

use App\Repository\FilmingLocationRepository;
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

    public function __construct(string $uuid, int $tmdbId, string $title, string $location, string $description, float $latitude, float $longitude, bool $isSeries = false)
    {
        $this->uuid = $uuid;
        $this->tmdbId = $tmdbId;
        $this->title = $title;
        $this->location = $location;
        $this->description = $description;
        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->isSeries = $isSeries;
        $this->filmingLocationImages = new ArrayCollection();
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
}
