<?php

namespace App\Entity;

use App\Repository\PointOfInterestRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PointOfInterestRepository::class)]
class PointOfInterest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $address = null;

    #[ORM\Column(length: 255)]
    private ?string $city = null;

    #[ORM\Column(length: 2)]
    private ?string $originCountry = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private ?float $latitude = null;

    #[ORM\Column]
    private ?float $longitude = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    private ?PointOfInterestImage $still = null;

    /**
     * @var Collection<int, PointOfInterestImage>
     */
    #[ORM\OneToMany(targetEntity: PointOfInterestImage::class, mappedBy: 'pointOfInterest', orphanRemoval: true)]
    private Collection $pointOfInterestImages;

    public function __construct(string $name, string $address, ?string $description, float $latitude, float $longitude, string $originCountry, DateTimeImmutable $now)
    {
        $this->name = $name;
        $this->address = $address;
        $this->description = $description;
        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->originCountry = $originCountry;
        $this->pointOfInterestImages = new ArrayCollection();
        $this->createdAt = $now;
        $this->updatedAt = $now;
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

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(string $address): static
    {
        $this->address = $address;

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

    public function getLongitude(): ?string
    {
        return $this->longitude;
    }

    public function setLongitude(string $longitude): static
    {
        $this->longitude = $longitude;

        return $this;
    }

    public function getOriginCountry(): ?string
    {
        return $this->originCountry;
    }

    public function setOriginCountry(string $originCountry): static
    {
        $this->originCountry = $originCountry;

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

    /**
     * @return Collection<int, PointOfInterestImage>
     */
    public function getPointOfInterestImages(): Collection
    {
        return $this->pointOfInterestImages;
    }

    public function addPointOfInterestImage(PointOfInterestImage $image): static
    {
        if (!$this->pointOfInterestImages->contains($image)) {
            $this->pointOfInterestImages->add($image);
            $image->setPointOfInterest($this);
        }

        return $this;
    }

    public function removePointOfInterestImage(PointOfInterestImage $image): static
    {
        if ($this->pointOfInterestImages->removeElement($image)) {
            // set the owning side to null (unless already changed)
            if ($image->getPointOfInterest() === $this) {
                $image->setPointOfInterest(null);
            }
        }

        return $this;
    }

    public function getStill(): ?PointOfInterestImage
    {
        return $this->still;
    }

    public function setStill(PointOfInterestImage $still): static
    {
        $this->still = $still;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = $city;

        return $this;
    }
}
