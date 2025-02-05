<?php

namespace App\Entity;

use App\Repository\MovieImageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MovieImageRepository::class)]
class MovieImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'movieImages')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Movie $movie = null;

    #[ORM\Column(length: 16)]
    private ?string $type = null;

    #[ORM\Column(length: 255)]
    private ?string $imagePath = null;

    public function __construct(Movie $movie, string $type, string $imagePath)
    {
        $this->movie = $movie;
        $this->type = $type;
        $this->imagePath = $imagePath;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMovie(): ?Movie
    {
        return $this->movie;
    }

    public function setMovie(?Movie $movie): static
    {
        $this->movie = $movie;

        return $this;
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
        return $this->imagePath;
    }

    public function setImagePath(string $imagePath): static
    {
        $this->imagePath = $imagePath;

        return $this;
    }
}
