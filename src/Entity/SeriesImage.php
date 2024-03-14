<?php

namespace App\Entity;

use App\Repository\SeriesImageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SeriesImageRepository::class)]
class SeriesImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 16)]
    private ?string $type = null;

    #[ORM\Column(length: 255)]
    private ?string $image_path = null;

    #[ORM\ManyToOne(inversedBy: 'seriesImages')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Series $series = null;

    public function __construct(Series $series, string $type, string $image_path)
    {
        $this->series = $series;
        $this->type = $type;
        $this->image_path = $image_path;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getImagePath(): ?string
    {
        return $this->image_path;
    }

    public function setImagePath(string $image_path): static
    {
        $this->image_path = $image_path;

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
}
