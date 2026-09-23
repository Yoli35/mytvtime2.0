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

    public function setTvTimeTab(User $user, int $tabIndex): void
    {
        $data = $this->getSettings($user);
        $data['tab'] = $tabIndex;
        $this->setSettings($user, $data);
    }

    private function getSettings(User $user): array
    {
        $s = $this->settingsRepository->findOneBy(['user' => $user, 'name' => 'tv time']);
        if ($s === null) {
            return ['count' => 0, 'list' => 0, 'tab' => 0, 'sub' => 0];
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

        if ($settings['tab'] === 0) { // series
            $seriesAvailable = $this->userSeriesRepository->findAvailableSeries($userId, $locale);
            $watchLinks = $this->userSeriesRepository->availableSeriesWatchLinks(array_column($seriesAvailable, 'id'));
            $seriesUpToDate = $this->userSeriesRepository->findUpToDateSeries($userId, $locale);
            $providerUrl = $this->imageConfiguration->getUrl('logo_sizes', 3);
            $watchLinks = array_map(function ($wp) use ($providerUrl) {
                $wp['providerLogoPath'] = $this->providerService->getProviderLogoFullPath($wp['providerLogoPath'], $providerUrl);
                return $wp;
            }, array_merge($watchLinks, $this->userSeriesRepository->availableSeriesWatchLinks(array_column($seriesUpToDate, 'id'))));
            $seriesUpToDateIds = array_unique(array_column($seriesUpToDate, 'userEpisodeId'));
            $lastEpisodeWithNoVoteArr = $this->userSeriesRepository->findUpToDateSeriesWithNoVote($seriesUpToDateIds, $locale);
            $tmdbIds = array_unique(array_merge(array_column($seriesAvailable, 'tmdb_id'), array_column($seriesUpToDate, 'tmdb_id')));
            $lastWatchedSeriesId = $this->userSeriesRepository->getLastWatchedSeries($user);
            $series = [
                'available' => $seriesAvailable,
                'upToDate' => $seriesUpToDate,
                'watchLinks' => $watchLinks,
                'tmdbIds' => $tmdbIds,
                'lastWatchedSeriesId' => $lastWatchedSeriesId,
            ];
            $noVoteArr = $lastEpisodeWithNoVoteArr;
        } else {
            $noVoteArr = $this->userSeriesRepository->seriesWithNoVote($locale);
        }

        if ($settings['tab'] === 1) {// movies
            if ($settings['sub'] == 0) {
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
            if ($settings['sub'] == 0) {
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
            'sub' => $settings['sub'],
            'list' => $settings['list'],
        ];
    }
}