<?php

namespace App\Service;

use App\Entity\Season;
use App\Entity\SeasonPoster;
use App\Entity\Series;
use App\Repository\SeasonPosterRepository;
use App\Repository\SeasonRepository;
use DateTimeImmutable;
use SortDirection;

readonly class SeasonService
{
    // Délai (en secondes) au-delà duquel une saison stockée est mise à jour depuis TheMovieDB
    public const int REFRESH_DELAY = 6 * 3600;

    public function __construct(
        private ImageService           $imageService,
        private SeasonPosterRepository $seasonPosterRepository,
        private SeasonRepository       $seasonRepository,
        private TMDBService            $tmdbService,
    )
    {
    }

    /**
     * Saison (et ses épisodes) depuis la table season, mise à jour depuis TheMovieDB si absente ou trop ancienne.
     */
    public function getSeason(Series $series, int $seasonNumber, string $locale): ?Season
    {
        $key = $series->getId() . '-' . $seasonNumber;
        $season = $this->seasonRepository->findWithEpisodes([['seriesId' => $series->getId(), 'seasonNumber' => $seasonNumber]], $locale)[$key] ?? null;

        if (!$this->isFresh($season)) {
            $season = $this->refreshSeason($series, $seasonNumber, $locale, $season);
            $this->seasonRepository->flush();
        }
        return $season;
    }

    public function isFresh(?Season $season): bool
    {
        return $season && (time() - $season->getUpdatedAt()->getTimestamp() < self::REFRESH_DELAY);
    }

    /**
     * Récupère la saison sur TheMovieDB et crée / met à jour la saison et ses épisodes (sans flush).
     * En cas d'erreur TheMovieDB, la saison stockée (éventuellement null) est renvoyée telle quelle.
     */
    public function refreshSeason(Series $series, int $seasonNumber, string $locale, ?Season $season): ?Season
    {
        $tmdbSeason = json_decode($this->tmdbService->getTvSeason($series->getTmdbId(), $seasonNumber, $locale), true);
        if (!$tmdbSeason || isset($tmdbSeason['error']) || !isset($tmdbSeason['id'])) {
            return $season;
        }

        $now = new DateTimeImmutable();
        if (!$season) {
            $season = new Season($series, $tmdbSeason['id'], $locale, $now);
        }
        $season->update($tmdbSeason, $now);
        $this->seasonRepository->save($season);

        return $season;
    }

    public function posters(array $seasons, int $seriesId, int $tvId, string $posterUrl): array
    {
        $new = 0;
        $seasons = array_map(function ($season) use ($posterUrl, $seriesId, $tvId, &$new) {
            $seasonPoster = $this->seasonPosterRepository->findOneBy(['seasonId' => $season['id']], ['id' => SortDirection::Descending]);
            $localPath = "/series/season_posters/";
            // Le poster est stocké en local au format webp : "/xxx.jpg" → "xxx.webp"
            $webpName = $season['poster_path'] ? pathinfo($season['poster_path'], PATHINFO_FILENAME) . '.webp' : null;
            if ($seasonPoster && (!$webpName || $seasonPoster->getPosterPath() === $webpName)) {
                $season['poster_path'] = $localPath . $seasonPoster->getPosterPath();
                return $season;
            }
            if (!$webpName) return $season;

            $webp = $this->imageService->seasonPosterToWebp($season['poster_path'], $posterUrl);
            if ($webp) {
                $seasonPoster = new SeasonPoster($webp, $season['id'], $season['season_number'], $seriesId, $tvId);
                $this->seasonPosterRepository->save($seasonPoster);
                $new++;
                $season['poster_path'] = $localPath . $webp;
            }
            return $season;
        }, $seasons);
        if ($new) {
            $this->seasonPosterRepository->flush();
        }
        return $seasons;
    }
}