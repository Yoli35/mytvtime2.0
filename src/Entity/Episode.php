<?php

namespace App\Entity;

use App\Repository\EpisodeRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Cache local des données d'un épisode renvoyées par TheMovieDB (/tv/{id}/season/{n}, clé "episodes").
 * Les données (name, overview) dépendant de la langue de la requête, un épisode est stocké par locale.
 */
#[ORM\Entity(repositoryClass: EpisodeRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_episode_tmdb_id_locale', columns: ['tmdb_id', 'locale'])]
#[ORM\Index(name: 'idx_episode_series_season', columns: ['series_id', 'season_number', 'episode_number'])]
class Episode
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Series $series = null;

    #[ORM\ManyToOne(inversedBy: 'episodes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Season $season = null;

    #[ORM\Column]
    private ?int $tmdbId = null;

    #[ORM\Column(length: 8)]
    private ?string $locale = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $airDate = null;

    #[ORM\Column]
    private ?int $seasonNumber = null;

    #[ORM\Column]
    private ?int $episodeNumber = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $episodeType = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $overview = null;

    #[ORM\Column(nullable: true)]
    private ?int $runtime = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stillPath = null;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?DateTimeImmutable $updatedAt = null;

    public function __construct(Season $season, int $tmdbId, DateTimeImmutable $now)
    {
        $this->series = $season->getSeries();
        $this->season = $season;
        $this->tmdbId = $tmdbId;
        $this->locale = $season->getLocale();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    /**
     * Met à jour l'épisode à partir de la réponse de TheMovieDB.
     * TheMovieDB renvoie "" pour les valeurs non renseignées : elles sont stockées à null.
     */
    public function update(array $tmdbEpisode, DateTimeImmutable $now): static
    {
        $airDate = $tmdbEpisode['air_date'] ?? null;

        $this->airDate = $airDate ? DateTimeImmutable::createFromFormat('!Y-m-d', $airDate) ?: null : null;
        $this->seasonNumber = $tmdbEpisode['season_number'];
        $this->episodeNumber = $tmdbEpisode['episode_number'];
        $this->episodeType = ($tmdbEpisode['episode_type'] ?? '') ?: null;
        $this->name = ($tmdbEpisode['name'] ?? '') ?: null;
        $this->overview = ($tmdbEpisode['overview'] ?? '') ?: null;
        $this->runtime = $tmdbEpisode['runtime'] ?? null;
        $this->stillPath = ($tmdbEpisode['still_path'] ?? '') ?: null;
        $this->updatedAt = $now;

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSeries(): ?Series
    {
        return $this->series;
    }

    public function getSeason(): ?Season
    {
        return $this->season;
    }

    public function getTmdbId(): ?int
    {
        return $this->tmdbId;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function getAirDate(): ?DateTimeImmutable
    {
        return $this->airDate;
    }

    public function getSeasonNumber(): ?int
    {
        return $this->seasonNumber;
    }

    public function getEpisodeNumber(): ?int
    {
        return $this->episodeNumber;
    }

    public function getEpisodeType(): ?string
    {
        return $this->episodeType;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getOverview(): ?string
    {
        return $this->overview;
    }

    public function getRuntime(): ?int
    {
        return $this->runtime;
    }

    public function getStillPath(): ?string
    {
        return $this->stillPath;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
