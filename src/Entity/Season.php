<?php

namespace App\Entity;

use App\Repository\SeasonRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SortDirection;

/**
 * Cache local des données d'une saison renvoyées par TheMovieDB (/tv/{id}/season/{n}).
 * Les données (name, overview) dépendant de la langue de la requête, une saison est stockée par locale.
 */
#[ORM\Entity(repositoryClass: SeasonRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_season_tmdb_id_locale', columns: ['tmdb_id', 'locale'])]
#[ORM\Index(name: 'idx_season_series_number_locale', columns: ['series_id', 'season_number', 'locale'])]
class Season
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Series $series = null;

    #[ORM\Column]
    private ?int $tmdbId = null;

    #[ORM\Column(length: 8)]
    private ?string $locale = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $airDate = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $overview = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $posterPath = null;

    #[ORM\Column]
    private ?int $seasonNumber = null;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?DateTimeImmutable $updatedAt = null;

    #[ORM\OneToMany(targetEntity: Episode::class, mappedBy: 'season', cascade: ['persist'], orphanRemoval: true, indexBy: 'tmdbId')]
    #[ORM\OrderBy(['episodeNumber' => SortDirection::Ascending])]
    private Collection $episodes;

    public function __construct(Series $series, int $tmdbId, string $locale, DateTimeImmutable $now)
    {
        $this->series = $series;
        $this->tmdbId = $tmdbId;
        $this->locale = $locale;
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->episodes = new ArrayCollection();
    }

    /**
     * Met à jour la saison et ses épisodes à partir de la réponse de TheMovieDB.
     * Les épisodes qui ne figurent plus dans la réponse sont supprimés.
     */
    public function update(array $tmdbSeason, DateTimeImmutable $now): static
    {
        $airDate = $tmdbSeason['air_date'] ?? null;

        $this->airDate = $airDate ? DateTimeImmutable::createFromFormat('!Y-m-d', $airDate) ?: null : null;
        $this->name = ($tmdbSeason['name'] ?? '') ?: null;
        $this->overview = ($tmdbSeason['overview'] ?? '') ?: null;
        $this->posterPath = ($tmdbSeason['poster_path'] ?? '') ?: null;
        $this->seasonNumber = $tmdbSeason['season_number'];
        $this->updatedAt = $now;

        $tmdbIds = [];
        foreach ($tmdbSeason['episodes'] ?? [] as $tmdbEpisode) {
            $tmdbIds[] = $tmdbEpisode['id'];
            $episode = $this->getEpisode($tmdbEpisode['id']);
            if (!$episode) {
                $episode = new Episode($this, $tmdbEpisode['id'], $now);
                $this->episodes->set($tmdbEpisode['id'], $episode);
            }
            $episode->update($tmdbEpisode, $now);
        }
        foreach ($this->episodes->toArray() as $episode) {
            if (!in_array($episode->getTmdbId(), $tmdbIds, true)) {
                $this->episodes->removeElement($episode);
            }
        }

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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getOverview(): ?string
    {
        return $this->overview;
    }

    public function getPosterPath(): ?string
    {
        return $this->posterPath;
    }

    public function getSeasonNumber(): ?int
    {
        return $this->seasonNumber;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return Collection<int, Episode> Épisodes indexés par leur id TMDB
     */
    public function getEpisodes(): Collection
    {
        return $this->episodes;
    }

    public function getEpisode(int $tmdbId): ?Episode
    {
        return $this->episodes->get($tmdbId);
    }
}
