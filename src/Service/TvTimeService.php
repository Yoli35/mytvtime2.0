<?php

namespace App\Service;

use App\Entity\Settings;
use App\Entity\User;
use App\Repository\SettingsRepository;
use App\Repository\UserMovieRepository;
use App\Repository\UserSeriesRepository;

readonly class TvTimeService
{

    private array $specialEpisodes;

    public function __construct(
        private DateService          $dateService,
        private UserMovieRepository  $userMovieRepository,
        private UserSeriesRepository $userSeriesRepository,
        private ImageConfiguration   $imageConfiguration,
        private ProviderService      $providerService,
        private SettingsRepository   $settingsRepository,
        private TMDBService          $tmdbService
    )
    {
        $this->specialEpisodes = [
            'fr' => 'Épisode spécial #',
            'en' => 'Special episode #',
            'ko' => '특별편 #'
        ];
    }

    public function getTvTimeSettings(User $user): array
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

    public function setTvTimeWeek(User $user, int $week): void
    {
        $data = $this->getSettings($user);
        $data['week'] = $week;
        $this->setSettings($user, $data);
    }

    public function setTvTimeTab(User $user, int $tabIndex): void
    {
        $data = $this->getSettings($user);
        $data['tab'] = $tabIndex;
        $this->setSettings($user, $data);
    }

    public function getTvTimeSort(User $user): string
    {
        $data = $this->getSettings($user);
        return $data['sort'] ?? 0;
    }

    public function setTvTimeSort(User $user, int $sort): void
    {
        $data = $this->getSettings($user);
        $data['sort'] = $sort;
        $this->setSettings($user, $data);
    }

    /*public function getTvTimeKind(User $user, string $tab): string
    {
        $data = $this->getSettings($user);
        return $data[$tab . '-kind'] ?? 0;
    }*/

    public function setTvTimeKind(User $user, string $tab, int $kind): void
    {
        $data = $this->getSettings($user);
        $data[$tab . '-kind'] = $kind;
        $this->setSettings($user, $data);
    }

    private function getSettings(User $user): array
    {
        $defaults = [
            'tab' => 0,             // Tab "Séries", "Films" et "À venir"
            'count' => 0,           // Nombre d'appels à l'API
            'list' => 0,            // Affichage en mode liste ou grille
            'series-kind' => 0,     // Affichage des séries disponibles / terminées / épisodes spéciaux
            'movies-kind' => 0,     // Affichage des films à voir / vus
            'coming-kind' => 0,
            'sort' => 0,            // Affichage par jours restants / derniers vus / date de diffusion
            'week' => 52,           // Nombre de semaines avant de considérer un épisode spécial comme ancien
        ];
        $s = $this->settingsRepository->findOneBy(['user' => $user, 'name' => 'tv time']);
        if ($s === null) {
            $s = new Settings($user, 'tv time', $defaults);
            $this->settingsRepository->save($s, true);
            return $defaults;
        }
        $data = $s->getData();
        // Anciens réglages : reprise de 'kind' et 'sub-1' dans les nouvelles clés
        if (!isset($data['series-kind']) && isset($data['kind'])) {
            $data['series-kind'] = $data['kind'];
        }
        if (!isset($data['movies-kind']) && isset($data['sub-1'])) {
            $data['movies-kind'] = $data['sub-1'];
        }
        return $data + $defaults;
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

    public function getTvTimeData(?User $user, string $locale): array
    {
        $userId = $user->getId();
        $settings = $this->getTvTimeSettings($user);
        $providerUrl = $this->imageConfiguration->getUrl('logo_sizes', 3);

        // Reload page at midnight plus 1 to 60 secondes
        $now = $this->dateService->getNow($user->getTimezone() ?? 'Europe/Paris');
        $reloadAt = $this->dateService->newDate('tomorrow 00:00:00', $user->getTimezone() ?? 'Europe/Paris');
        $remainingSecondes = rand(1, 60) + $reloadAt->getTimestamp() - $now->getTimestamp();

        if ($settings['tab'] === 0) { // series
            $sort = $settings['sort'] ?? 0;
            $kind = $settings['series-kind'] ?? 0;
            $reloadAt = null;
            $content = [];
            $ids = [];
            $tmdbIds = [];

            $upToDateSeries = $this->userSeriesRepository->findUpToDateSeries($userId, $sort, $locale);

            if ($kind === 0) {
                $content['available']['items'] = $this->userSeriesRepository->findAvailableSeries($userId, $locale);
                $content['available']['count'] = count($content['available']['items']);
                $content['upToDate']['items'] = $upToDateSeries;
                $content['upToDate']['count'] = count($upToDateSeries);
                $content['ended']['items'] = array_map(function ($ep) use ($providerUrl) {
                    $ep['providerLogoPath'] = $this->providerService->getProviderLogoFullPath($ep['providerLogoPath'], $providerUrl);
                    return $ep;
                }, $this->userSeriesRepository->findUpToDateEndedSeries($userId, $sort, $locale));
                $content['ended']['count'] = count($content['ended']['items']);
                $content['upToDateInAWhile']['items'] = $this->userSeriesRepository->findUpToDateSeriesInAWhile($userId, $sort, $locale);
                $content['upToDateInAWhile']['count'] = $this->userSeriesRepository->countUpToDateSeriesInAWhile($userId);
                $ids = array_unique(
                    array_merge(
                        array_column($content['available']['items'], 'id'),
                        array_column($content['upToDate']['items'], 'id'),
                        array_column($content['upToDateInAWhile']['items'], 'id')
                    )
                );

                $todaySeries = array_filter($content['upToDate']['items'], fn($s) => $s['remainingDays'] == 0);
                if (!empty($todaySeries)) {
                    $airAtArr = array_unique(array_column($todaySeries, 'nextEpisodeAirAtDate'));
                    sort($airAtArr);
                    $reloadAt = array_first($airAtArr);
                    if ($reloadAt) {
                        $reloadAt = $this->dateService->newDate($reloadAt, $user->getTimezone() ?? 'Europe/Paris');
                        $remainingSecondes = rand(1, 60) + $reloadAt->getTimestamp() - $now->getTimestamp();
                    }
                }

                $tmdbIds = array_unique(
                    array_merge(
                        array_column($content['available']['items'], 'tmdb_id'),
                        array_column($upToDateSeries, 'tmdb_id'),
                        array_column($content['ended']['items'], 'tmdbId'),
                        array_column($content['upToDateInAWhile']['items'], 'tmdb_id')
                    )
                );
            }

            if ($kind == 1) {
                $content['ended']['items'] = $this->userSeriesRepository->seriesEnded($user->getId(), $locale);
                $content['ended']['count'] = $this->userSeriesRepository->countSeriesEnded($user->getId());
                $ids = array_column($content['ended']['items'], 'id');
            }

            if ($kind === 2) {
                $content['specialEpisodes']['items'] = $this->userSeriesRepository->findSpecialEpisodes($userId, $settings['week'], $this->specialEpisodes[$locale], $locale);
                $content['specialEpisodes']['count'] = count($content['specialEpisodes']['items']);
                $ids = array_column($content['specialEpisodes']['items'], 'id');
            }

            $watchLinks = array_map(function ($wp) use ($providerUrl) {
                $wp['providerLogoPath'] = $this->providerService->getProviderLogoFullPath($wp['providerLogoPath'], $providerUrl);
                return $wp;
            }, $this->userSeriesRepository->availableSeriesWatchLinks($ids));

            $seriesUpToDateIds = array_unique(array_column($upToDateSeries, 'userEpisodeId'));
            $lastEpisodeWithNoVoteArr = array_map(function ($wp) use ($providerUrl) {
                $wp['provider_logo_path'] = $this->providerService->getProviderLogoFullPath($wp['provider_logo_path'], $providerUrl);
                return $wp;
            }, $this->userSeriesRepository->findUpToDateSeriesWithNoVote($userId, $seriesUpToDateIds, $locale));

            $series = [
                'content' => $content,
                'watchLinks' => $watchLinks,
                'tmdbIds' => $tmdbIds,
            ];
            $noVoteArr = $lastEpisodeWithNoVoteArr;
        } else {
            $noVoteArr = $this->userSeriesRepository->seriesWithNoVote($locale);
            $series = [
                'content' => [],
                'watchLinks' => [],
                'tmdbIds' => [],
            ];
        }

        if ($settings['tab'] === 1) {// movies
            if ($settings['movies-kind'] == 0) {
                $movies = $this->userMovieRepository->moviesToSee($user, $locale);
                $movieCount = $this->userMovieRepository->moviesToSeeCount($user);
            } else {
                $movies = $this->userMovieRepository->moviesSeen($user, $locale);
                $movieCount = $this->userMovieRepository->moviesSeenCount($user);
            }
            $movies = [
                'results' => $movies,
                'count' => $movieCount,
            ];
        }
        if ($settings['tab'] === 2) {// coming
            if ($settings['coming-kind'] == 0) {
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
            'movies' => $movies ?? ['results' => [], 'count' => 0],
            'coming' => $coming ?? ['media' => [], 'actors' => []],
            'noVoteArr' => $noVoteArr,
            'loadCount' => $settings['count'],
            'tab' => $settings['tab'],
            'list' => $settings['list'],
            'sort' => $settings['sort'],
            'series-kind' => $settings['series-kind'],
            'movies-kind' => $settings['movies-kind'],
            'coming-kind' => $settings['coming-kind'],
            'week' => $settings['week'],
            'remainingSecondes' => $remainingSecondes,
        ];
    }

    public function seriesNotSeenInAWhile(User $user, string $locale, int $offset = 0): array
    {
        $userId = $user->getId();
        $sort = $this->getTvTimeSort($user);
        $series = $this->userSeriesRepository->findUpToDateSeriesInAWhile($userId, $sort, $locale, $offset);
        $seriesCount = $this->userSeriesRepository->countUpToDateSeriesInAWhile($userId);
        $watchLinks = $this->getWatchLinks(array_column($series, 'id'));
        return [
            'items' => $series,
            'count' => $seriesCount,
            'wp' => $watchLinks
        ];
    }

    public function seriesEnded(User $user, string $locale, int $offset = 0): array
    {
        $series = $this->userSeriesRepository->seriesEnded($user->getId(), $locale, $offset);
        $seriesCount = $this->userSeriesRepository->countSeriesEnded($user->getId());
        $watchLinks = $this->getWatchLinks(array_column($series, 'id'));
        return [
            'items' => $series,
            'count' => $seriesCount,
            'wp' => $watchLinks
        ];
    }

    public function getWatchLinks(array $ids): array
    {
        $providerUrl = $this->imageConfiguration->getUrl('logo_sizes', 3);
        return array_map(function ($wp) use ($providerUrl) {
            $wp['providerLogoPath'] = $this->providerService->getProviderLogoFullPath($wp['providerLogoPath'], $providerUrl);
            return $wp;
        }, $this->userSeriesRepository->availableSeriesWatchLinks(array_unique($ids)));
    }

    /*public function getTvTimeSeasonShowCast(int $tmdbId, int $seasonNumber): array
    {
        $credits = json_decode($this->tmdbService->getTvSeasonCredits($tmdbId, $seasonNumber, 'en-US'), true);
        return $credits['cast'];
    }*/

    public function getTvTimeEpisodeShowCast(int $tmdbId, int $seasonNumber, int $episodeNumber): array
    {
        $credits = json_decode($this->tmdbService->getTvEpisodeCredits($tmdbId, $seasonNumber, $episodeNumber, 'en-US'), true);
        return array_merge($credits['cast'], $credits['guest_stars']);
    }
}