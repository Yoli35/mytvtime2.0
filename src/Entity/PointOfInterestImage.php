<?php

namespace App\Entity;

use App\Repository\PointOfInterestImageRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PointOfInterestImageRepository::class)]
class PointOfInterestImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'pointOfInterestImages')]
    #[ORM\JoinColumn(nullable: false)]
    private ?PointOfInterest $pointOfInterest = null;

    #[ORM\Column(length: 255)]
    private ?string $path = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $caption = null;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    public function __construct(PointOfInterest $pointOfInterest, string $path, ?string $caption, DateTimeImmutable $createdAt)
    {
        $this->pointOfInterest = $pointOfInterest;
        $this->path = $path;
        $this->caption = $caption;
        $this->createdAt = $createdAt;
    }
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPointOfInterest(): ?PointOfInterest
    {
        return $this->pointOfInterest;
    }

    public function setPointOfInterest(?PointOfInterest $pointOfInterest): static
    {
        $this->pointOfInterest = $pointOfInterest;

        return $this;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    public function getCaption(): ?string
    {
        return $this->caption;
    }

    public function setCaption(?string $caption): static
    {
        $this->caption = $caption;

        return $this;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
