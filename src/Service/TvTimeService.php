<?php

namespace App\Service;

use App\Entity\Settings;
use App\Entity\User;
use App\Repository\SettingsRepository;
use App\Repository\UserMovieRepository;
use App\Repository\UserSeriesRepository;

readonly class TvTimeService
{
    public function __construct(
        private DateService          $dateService,
        private UserMovieRepository  $userMovieRepository,
        private UserSeriesRepository $userSeriesRepository,
        private ImageConfiguration   $imageConfiguration,
        private ProviderService      $providerService,
        private SettingsRepository   $settingsRepository,
    )
    {
    }

    public function getTvTimeData(User $user): array
    {
        $settings = $this->getSettings($user);
        $settings['count']++;
        $this->setSettings($user, $settings);
        return $settings;
    }

    public function setTvTimeLayout(User $user, int $layout): void
    {
        $data = $this->getSettings($user);
        $data['list'] = $layout;
        $this->setSettings($user, $data);
    }

    public function setTvTimeSpecials(User $user, int $specials): void
    {
        $data = $this->getSettings($user);
        $data['specials'] = $specials;
        $this->setSettings($user, $data);
    }

    public function setTvTimeTab(User $user, int $tabIndex): void
    {
        $data = $this->getSettings($user);
        $data['tab'] = $tabIndex;
        $this->setSettings($user, $data);
    }

    public function setTvTimeSort(User $user, int $sort): void
    {
        $data = $this->getSettings($user);
        $data['sort'] = $sort;
        $this->setSettings($user, $data);
    }

    private function getSettings(User $user): array
    {
        $s = $this->settingsRepository->findOneBy(['user' => $user, 'name' => 'tv time']);
        if ($s === null) {
            $data = [
                'count' => 0,           // Nombre d'appel à l'API
                'list' => 0,            // Affichage en mode liste ou grille
                'sort' => 0,            // Affichage par jours restants / derniers vus / date de diffusion
                'specials' => 0,        // Affichage des épisodes spéciaux
                'sub' => 0,             // Onglet Séries, Films et séries et films à venir
                'tab' => 0,             // Sous-onglet
            ];
            $se = new Settings($user, 'tv time', $data);
            $this->settingsRepository->save($se, true);
        }
        return $s->getData();
    }

    private function setSettings(User $user, array $data): void
    {
        $s = $this->settingsRepository->findOneBy(['user' => $user, 'name' => 'tv time']);
        if ($s === null) {
            $s = new Settings($user, 'tv time', $data);
        } else {
            $s->setData($data);
        }
        $this->settingsRepository->save($s, true);
    }

    public function getData(?User $user, string $locale): array
    {
        $userId = $user->getId();
        $settings = $this->getTvTimeData($user);
        /*$settings['tab'] = 1;*/
        $series = [
            'available' => [],
            'upToDate' => [],
            'watchLinks' => [],
            'tmdbIds' => [],
            'lastWatchedSeriesId' => 0,
        ];
        $movies = [];
        $coming = [];

        // Reload page at midnight plus 1 to 60 secondes
        $now = $this->dateService->getNow($user->getTimezone() ?? 'Europe/Paris');
        $reloadAt = $this->dateService->newDate('tomorrow 00:00:00', $user->getTimezone() ?? 'Europe/Paris');
        $remainingSecondes = rand(1, 60) + $reloadAt->getTimestamp() - $now->getTimestamp();

        if ($settings['tab'] === 0) { // series
            $seriesAvailable = $this->userSeriesRepository->findAvailableSeries($userId, $settings['specials'], $locale);
            $episodesAvailable = array_map(fn($series) => [
                'id' => $series['tmdb_id'],
                'name' => $series['name'],
                'poster_path' => $series['poster_path'],
                'episodeId' => $series['episode_id'],
                'esn' => $series['esn_name'],
                'episodeNumber' => $series['episode_number'],
                'seasonNumber' => $series['season_number']
            ], $seriesAvailable);
            $seriesUpToDate = $this->userSeriesRepository->findUpToDateSeries($userId, $settings['sort'], $locale);
            $todaySeries = array_filter($seriesUpToDate, fn($series) => $series['remainingDays'] == 0);
            $airAtArr = array_unique(array_column($todaySeries, 'nextEpisodeAirAtDate'));
            sort($airAtArr);
            $reloadAt = array_first($airAtArr);
            if ($reloadAt) {
                $reloadAt = $this->dateService->newDate($reloadAt, $user->getTimezone() ?? 'Europe/Paris');
                $remainingSecondes = rand(1, 60) + $reloadAt->getTimestamp() - $now->getTimestamp();
            }
            $seriesUpToDateInAWhile = $this->userSeriesRepository->findUpToDateSeriesInAWhile($userId, $settings['sort'], $locale);
            $seriesUpToDateInAWhileCount = $this->userSeriesRepository->countUpToDateSeriesInAWhile($userId);
            $providerUrl = $this->imageConfiguration->getUrl('logo_sizes', 3);
            $watchLinks = array_map(function ($wp) use ($providerUrl) {
                $wp['providerLogoPath'] = $this->providerService->getProviderLogoFullPath($wp['providerLogoPath'], $providerUrl);
                return $wp;
            }, array_merge(
                $this->userSeriesRepository->availableSeriesWatchLinks(array_column($seriesAvailable, 'id')),
                $this->userSeriesRepository->availableSeriesWatchLinks(array_column($seriesUpToDate, 'id')),
                $this->userSeriesRepository->availableSeriesWatchLinks(array_column($seriesUpToDateInAWhile, 'id')),
            ));
            $seriesUpToDateIds = array_unique(array_column($seriesUpToDate, 'userEpisodeId'));
            $lastEpisodeWithNoVoteArr = $this->userSeriesRepository->findUpToDateSeriesWithNoVote($seriesUpToDateIds, $locale);
            $tmdbIds = array_unique(array_merge(array_column($seriesAvailable, 'tmdb_id'), array_column($seriesUpToDate, 'tmdb_id'), array_column($seriesUpToDateInAWhile, 'tmdb_id')));
            $lastWatchedSeriesId = $this->userSeriesRepository->getLastWatchedSeries($user);
            $series = [
                'available' => $seriesAvailable,
                'upToDate' => $seriesUpToDate,
                'upToDateInAWhile' => $seriesUpToDateInAWhile,
                'upToDateInAWhileCount' => $seriesUpToDateInAWhileCount,
                'watchLinks' => $watchLinks,
                'tmdbIds' => $tmdbIds,
                'episodesAvailable' => $episodesAvailable,
                'lastWatchedSeriesId' => $lastWatchedSeriesId,
            ];
            $noVoteArr = $lastEpisodeWithNoVoteArr;
        } else {
            $noVoteArr = $this->userSeriesRepository->seriesWithNoVote($locale);
        }

        if ($settings['tab'] === 1) {// movies
            if ($settings['sub-1'] == 0) {
                $movies = $this->userMovieRepository->moviesToSee($user, $locale);
                $movieCount = $this->userMovieRepository->moviesToSeeCount($user);
            } else {
                $movies = $this->userMovieRepository->moviesSeen($user, $locale);
                $movieCount = $this->userMovieRepository->moviesSeenCount($user);
            }
            $movies = [
                'movies' => $movies,
                'movieCount' => $movieCount,
            ];
        }
        if ($settings['tab'] === 2) {// coming
            if ($settings['sub-2'] == 0) {
                $media = [];
                $actors = [];
            } else {
                $media = [];
                $actors = [];
            }
            $coming = [
                'media' => $media,
                'actors' => $actors,
            ];
        }
        return [
            'series' => $series,
            'movies' => $movies,
            'coming' => $coming,
            'noVoteArr' => $noVoteArr,
            'loadCount' => $settings['count'],
            'tab' => $settings['tab'],
            'sub_0' => $settings['sub-0'],
            'sub_1' => $settings['sub-1'],
            'sub_2' => $settings['sub-2'],
            'list' => $settings['list'],
            'sort' => $settings['sort'],
            'specials' => $settings['specials'],
            'remainingSecondes' => $remainingSecondes,
        ];
    }
}