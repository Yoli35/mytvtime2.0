<?php

namespace App\Entity;

use App\Repository\UserMovieRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserMovieRepository::class)]
class UserMovie
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'userMovies')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user;

    #[ORM\ManyToOne(inversedBy: 'userMovies')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Movie $movie;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAT;

    #[ORM\Column]
    private ?bool $favorite;

    #[ORM\Column]
    private ?int $rating;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastViewedAt;

    #[ORM\Column]
    private array $viewArray = [];

    #[ORM\Column(nullable: true)]
    private ?bool $viewed = null;

    public function __construct(User $user, Movie $movie, DateTimeImmutable $createdAT, bool $favorite = false, int $rating = 0)
    {
        $this->user = $user;
        $this->movie = $movie;
        $this->createdAT = $createdAT;
        $this->favorite = $favorite;
        $this->rating = $rating;
        $this->lastViewedAt = null;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
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

    public function getCreatedAT(): ?DateTimeImmutable
    {
        return $this->createdAT;
    }

    public function setCreatedAT(DateTimeImmutable $createdAT): static
    {
        $this->createdAT = $createdAT;

        return $this;
    }

    public function isFavorite(): ?bool
    {
        return $this->favorite;
    }

    public function setFavorite(bool $favorite): static
    {
        $this->favorite = $favorite;

        return $this;
    }

    public function getRating(): ?int
    {
        return $this->rating;
    }

    public function setRating(int $rating): static
    {
        $this->rating = $rating;

        return $this;
    }

    public function getLastViewedAt(): ?\DateTimeImmutable
    {
        return $this->lastViewedAt;
    }

    public function setLastViewedAt(?\DateTimeImmutable $lastViewedAt): static
    {
        $this->lastViewedAt = $lastViewedAt;

        return $this;
    }

    public function getViewArray(): array
    {
        return $this->viewArray;
    }

    public function setViewArray(array $viewArray): static
    {
        $this->viewArray = $viewArray;

        return $this;
    }

    public function addUser(User $user): void
    {
        if (!$this->user->contains($user)) {
            $this->user[] = $user;
        }
    }

    public function isViewed(): ?bool
    {
        return $this->viewed;
    }

    public function setViewed(?bool $viewed): static
    {
        $this->viewed = $viewed;

        return $this;
    }
}
