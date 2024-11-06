<?php

namespace App\Entity;

use App\Repository\MovieRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MovieRepository::class)]
class Movie
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $backdropPath = null;

    #[ORM\ManyToOne(inversedBy: 'movies')]
    private ?MovieCollection $collection = null;

    #[ORM\Column]
    private ?int $tmdbId = null;

    #[ORM\Column]
    private array $originCountry = [];

    #[ORM\Column(length: 2)]
    private ?string $originalLanguage = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $originalTitle = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $overview = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $posterPath = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?DateTimeInterface $releaseDate = null;

    #[ORM\Column(nullable: true)]
    private ?int $runtime = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $status = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $tagline = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(nullable: true)]
    private ?float $voteAverage = null;

    #[ORM\Column(nullable: true)]
    private ?int $voteCount = null;

    /**
     * @var Collection<int, UserMovie>
     */
    #[ORM\OneToMany(targetEntity: UserMovie::class, mappedBy: 'movie', orphanRemoval: true)]
    private Collection $userMovies;

    /**
     * @var Collection<int, MovieDirectLink>
     */
    #[ORM\OneToMany(targetEntity: MovieDirectLink::class, mappedBy: 'movie', orphanRemoval: true)]
    private Collection $movieDirectLinks;

    /**
     * @var Collection<int, MovieAdditionalOverview>
     */
    #[ORM\OneToMany(targetEntity: MovieAdditionalOverview::class, mappedBy: 'movie')]
    private Collection $movieAdditionalOverviews;

    /**
     * @var Collection<int, MovieLocalizedName>
     */
    #[ORM\OneToMany(targetEntity: MovieLocalizedName::class, mappedBy: 'movie', orphanRemoval: true)]
    private Collection $movieLocalizedNames;

    /**
     * @var Collection<int, MovieLocalizedOverview>
     */
    #[ORM\OneToMany(targetEntity: MovieLocalizedOverview::class, mappedBy: 'movie', orphanRemoval: true)]
    private Collection $movieLocalizedOverviews;

    #[ORM\Column(nullable: true)]
    private ?array $additionalInfos = null;

    public function __toString(): string
    {
        return $this->title;
    }

    public function __construct(array $movie)
    {
        $this->setBackdropPath($movie['backdrop_path']);
        $this->setOriginCountry($movie['origin_country']);
        $this->setOriginalLanguage($movie['original_language']);
        $this->setOriginalTitle($movie['original_title']);
        $this->setOverview($movie['overview']);
        $this->setPosterPath($movie['poster_path']);
        $this->setReleaseDate($movie['release_date'] ? new DateTime($movie['release_date']) : null);
        $this->setRuntime($movie['runtime']);
        $this->setStatus($movie['status']);
        $this->setTagline($movie['tagline']);
        $this->setTitle($movie['title']);
        $this->setTmdbId($movie['id']);
        $this->setVoteAverage($movie['vote_average']);
        $this->setVoteCount($movie['vote_count']);
        $this->userMovies = new ArrayCollection();
        $this->movieDirectLinks = new ArrayCollection();
        $this->movieAdditionalOverviews = new ArrayCollection();
        $this->movieLocalizedNames = new ArrayCollection();
        $this->movieLocalizedOverviews = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getCollection(): ?MovieCollection
    {
        return $this->collection;
    }

    public function setCollection(?MovieCollection $collection): static
    {
        $this->collection = $collection;

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

    public function getOriginCountry(): array
    {
        return $this->originCountry;
    }

    public function setOriginCountry(array $originCountry): static
    {
        $this->originCountry = $originCountry;

        return $this;
    }

    public function getOriginalLanguage(): ?string
    {
        return $this->originalLanguage;
    }

    public function setOriginalLanguage(string $originalLanguage): static
    {
        $this->originalLanguage = $originalLanguage;

        return $this;
    }

    public function getOriginalTitle(): ?string
    {
        return $this->originalTitle;
    }

    public function setOriginalTitle(?string $originalTitle): static
    {
        $this->originalTitle = $originalTitle;

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

    public function getPosterPath(): ?string
    {
        return $this->posterPath;
    }

    public function setPosterPath(?string $posterPath): static
    {
        $this->posterPath = $posterPath;

        return $this;
    }

    public function getReleaseDate(): ?DateTimeInterface
    {
        return $this->releaseDate;
    }

    public function setReleaseDate(?DateTimeInterface $releaseDate): static
    {
        $this->releaseDate = $releaseDate;

        return $this;
    }

    public function getRuntime(): ?int
    {
        return $this->runtime;
    }

    public function setRuntime(?int $runtime): static
    {
        $this->runtime = $runtime;

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

    public function getTagline(): ?string
    {
        return $this->tagline;
    }

    public function setTagline(?string $tagline): static
    {
        $this->tagline = $tagline;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getVoteAverage(): ?float
    {
        return $this->voteAverage;
    }

    public function setVoteAverage(?float $voteAverage): static
    {
        $this->voteAverage = $voteAverage;

        return $this;
    }

    public function getVoteCount(): ?int
    {
        return $this->voteCount;
    }

    public function setVoteCount(?int $voteCount): static
    {
        $this->voteCount = $voteCount;

        return $this;
    }

    /**
     * @return Collection<int, UserMovie>
     */
    public function getUserMovies(): Collection
    {
        return $this->userMovies;
    }

    public function addUserMovie(UserMovie $userMovie): static
    {
        if (!$this->userMovies->contains($userMovie)) {
            $this->userMovies->add($userMovie);
            $userMovie->setMovie($this);
        }

        return $this;
    }

    public function removeUserMovie(UserMovie $userMovie): static
    {
        if ($this->userMovies->removeElement($userMovie)) {
            // set the owning side to null (unless already changed)
            if ($userMovie->getMovie() === $this) {
                $userMovie->setMovie(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, MovieDirectLink>
     */
    public function getMovieDirectLinks(): Collection
    {
        return $this->movieDirectLinks;
    }

    public function addMovieDirectLink(MovieDirectLink $movieDirectLink): static
    {
        if (!$this->movieDirectLinks->contains($movieDirectLink)) {
            $this->movieDirectLinks->add($movieDirectLink);
            $movieDirectLink->setMovie($this);
        }

        return $this;
    }

    public function removeMovieDirectLink(MovieDirectLink $movieDirectLink): static
    {
        if ($this->movieDirectLinks->removeElement($movieDirectLink)) {
            // set the owning side to null (unless already changed)
            if ($movieDirectLink->getMovie() === $this) {
                $movieDirectLink->setMovie(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, MovieAdditionalOverview>
     */
    public function getMovieAdditionalOverviews(): Collection
    {
        return $this->movieAdditionalOverviews;
    }

    public function addMovieAdditionalOverview(MovieAdditionalOverview $movieAdditionalOverview): static
    {
        if (!$this->movieAdditionalOverviews->contains($movieAdditionalOverview)) {
            $this->movieAdditionalOverviews->add($movieAdditionalOverview);
            $movieAdditionalOverview->setMovie($this);
        }

        return $this;
    }

    public function removeMovieAdditionalOverview(MovieAdditionalOverview $movieAdditionalOverview): static
    {
        if ($this->movieAdditionalOverviews->removeElement($movieAdditionalOverview)) {
            // set the owning side to null (unless already changed)
            if ($movieAdditionalOverview->getMovie() === $this) {
                $movieAdditionalOverview->setMovie(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, MovieLocalizedName>
     */
    public function getMovieLocalizedNames(): Collection
    {
        return $this->movieLocalizedNames;
    }

    public function addMovieLocalizedName(MovieLocalizedName $movieLocalizedName): static
    {
        if (!$this->movieLocalizedNames->contains($movieLocalizedName)) {
            $this->movieLocalizedNames->add($movieLocalizedName);
            $movieLocalizedName->setMovie($this);
        }

        return $this;
    }

    public function removeMovieLocalizedName(MovieLocalizedName $movieLocalizedName): static
    {
        if ($this->movieLocalizedNames->removeElement($movieLocalizedName)) {
            // set the owning side to null (unless already changed)
            if ($movieLocalizedName->getMovie() === $this) {
                $movieLocalizedName->setMovie(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, MovieLocalizedOverview>
     */
    public function getMovieLocalizedOverviews(): Collection
    {
        return $this->movieLocalizedOverviews;
    }

    public function addMovieLocalizedOverview(MovieLocalizedOverview $movieLocalizedOverview): static
    {
        if (!$this->movieLocalizedOverviews->contains($movieLocalizedOverview)) {
            $this->movieLocalizedOverviews->add($movieLocalizedOverview);
            $movieLocalizedOverview->setMovie($this);
        }

        return $this;
    }

    public function removeMovieLocalizedOverview(MovieLocalizedOverview $movieLocalizedOverview): static
    {
        if ($this->movieLocalizedOverviews->removeElement($movieLocalizedOverview)) {
            // set the owning side to null (unless already changed)
            if ($movieLocalizedOverview->getMovie() === $this) {
                $movieLocalizedOverview->setMovie(null);
            }
        }

        return $this;
    }

    public function getAdditionalInfos(): ?array
    {
        return $this->additionalInfos;
    }

    public function setAdditionalInfos(?array $additionalInfos): static
    {
        $this->additionalInfos = $additionalInfos;

        return $this;
    }
}
