<?php

namespace App\Entity;

use App\Repository\FilmingLocationImageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FilmingLocationImageRepository::class)]
class FilmingLocationImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'filmingLocationImages')]
    #[ORM\JoinColumn(nullable: false)]
    private ?FilmingLocation $filmingLocation;

    #[ORM\Column(length: 255)]
    private ?string $path;

    public function __construct(FilmingLocation $filmingLocation, string $path)
    {
        $this->filmingLocation = $filmingLocation;
        $this->path = $path;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFilmingLocation(): ?FilmingLocation
    {
        return $this->filmingLocation;
    }

    public function setFilmingLocation(?FilmingLocation $filmingLocation): static
    {
        $this->filmingLocation = $filmingLocation;

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
}
