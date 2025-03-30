<?php

namespace App\Twig\Runtime;

use App\Controller\SeriesController;
use App\Entity\User;
use App\Repository\EpisodeNotificationRepository;
use App\Repository\MovieRepository;
use App\Repository\UserEpisodeRepository;
use App\Service\DateService;
use App\Service\ImageConfiguration;
use DateTimeZone;
use Symfony\Component\Validator\Constraints\Timezone;
use Twig\Extension\RuntimeExtensionInterface;

readonly class EpisodeExtensionRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private DateService                   $dateService,
        private EpisodeNotificationRepository $episodeNotificationRepository,
        private ImageConfiguration            $imageConfiguration,
        private MovieRepository               $movieRepository,
        private SeriesController              $seriesController,
        private UserEpisodeRepository         $userEpisodeRepository
    )
    {
        // Inject dependencies if needed
    }

    public function countNewEpisodeNotifications(User $user): int
    {
        $count = $this->episodeNotificationRepository->episodeNewNotificationCount($user);
        return $count[0]['count'];
    }

    public function countEpisodeNotifications(User $user): int
    {
        $count = $this->episodeNotificationRepository->episodeNotificationCount($user);
        return $count[0]['count'];
    }

    public function listEpisodeNotifications(User $user): array
    {
        return $this->episodeNotificationRepository->episodeNotificationList($user);
    }

    public function listEpisodeOfTheInterval(User $user, string $start, string $end, string $locale = 'fr'): array
    {
        $arr = [];
        $intervalArr = [];
        $providerUrl = $this->imageConfiguration->getUrl('logo_sizes', 2);
        $timezone = new DateTimeZone($user->getTimezone() ?? 'Europe/Paris');

        $now = date_create_immutable("now", $timezone)->setTime(0,0);
        $startDate = $now->modify($start)->format('Y-m-d');
        $endDate = $now->modify($end)->format('Y-m-d');

        $sArr = $this->userEpisodeRepository->episodesOfTheIntervalForTwig($user, $startDate, $endDate, $locale);
        $mArr = $this->movieRepository->moviesOfTheIntervalForTwig($user, $startDate, $endDate, $locale);

        $startIndex = intval($start);
        $endIndex = intval($end);
        for ($index= $startIndex; $index <= $endIndex; $index++) {
            $intervalArr[$index] = [
                'index' => $index,
                'totalEpisodeCount' => 0,
                'results' => [],
            ];
        }

        $seriesArr = [];
        foreach ($sArr as $item) {
            $index = $item['days'];
            if (!key_exists($index, $seriesArr)) {
                $seriesArr[$index] = [];
            }
            if (!$this->seriesInArray($seriesArr[$index], $item)) {
                $item['episodes'] = [$item['episodeNumber']];
                if ($item['posterPath'] === null) {
                    $item['posterPath'] = $this->seriesController->getAlternatePosterPath($item['id']);
                }
                $item['posterPath'] = $item['posterPath'] ? '/series/posters' . $item['posterPath'] : null;
                if ($item['providerLogoPath']) {
                    if (str_starts_with($item['providerLogoPath'], '/')) {
                        $item['providerLogoPath'] = $providerUrl . $item['providerLogoPath'];
                    }
                    if (str_starts_with($item['providerLogoPath'], '+')) {
                        $item['providerLogoPath'] = '/images/providers' . substr($item['providerLogoPath'], 1);
                    }
                }
                $item['episodesWatched'] = $item['watchAt'] === null ? 0 : 1;
                $seriesArr[$index][$item['id']] = $item;
            } else {
                $seriesArr[$index][$item['id']]['episodes'][] = $item['episodeNumber'];
                $seriesArr[$index][$item['id']]['episodesWatched'] += $item['watchAt'] === null ? 0 : 1;
            }
        }

        foreach ($seriesArr as $index => $dayArr) {
            foreach ($dayArr as $key => $item) {
                $seasonNumber = $item['seasonNumber'];
                if (count($item['episodes']) > 2) {
                    $start = $this->minNumberInArray($item['episodes']);
                    $end = $this->maxNumberInArray($item['episodes']);
                    if ($locale === 'en') {
                        if ($seasonNumber)
                            $display = sprintf('S%02dE%02d to S%02dE%02d', $seasonNumber, $start, $seasonNumber, $end);
                        else
                            $display = sprintf('Specials #%02d to #%02d', $start, $end);
                    } else {
                        if ($seasonNumber)
                            $display = sprintf('S%02dE%02d à S%02dE%02d', $seasonNumber, $start, $seasonNumber, $end);
                        else
                            $display = sprintf('Épisodes spéciaux #%02d à #%02d', $start, $end);
                    }
                    $item['firstEpisodeNumber'] = $start;
                } elseif (count($item['episodes']) > 1) {
                    $start = $this->minNumberInArray($item['episodes']);
                    $end = $this->maxNumberInArray($item['episodes']);
                    if ($seasonNumber)
                        $display = sprintf('S%02dE%02d & S%02dE%02d', $seasonNumber, $start, $seasonNumber, $end);
                    else
                        $display = sprintf('Specials #%02d & #%02d', $start, $end);
                    $item['firstEpisodeNumber'] = $start;
                } else {
                    $episodeNumber = $item['episodes'][0];
                    if ($seasonNumber)
                        $display = sprintf('S%02dE%02d', $seasonNumber, $episodeNumber);
                    else
                        $display = sprintf('Special #%02d', $episodeNumber);
                    $item['firstEpisodeNumber'] = $episodeNumber;
                }
                $item['display'] = $item['displayName'] . ' ' . $display;
                $item['episodeCount'] = count($item['episodes']);

                $dayArr[$key] = $item;
            }
            $seriesArr[$index] = $dayArr;
        }

        $movieArr = [];
        foreach ($mArr as $item) {
            $item['posterPath'] = $item['posterPath'] ? '/movies/posters' . $item['posterPath'] : null;
            if ($item['providerLogoPath']) {
                if (str_starts_with($item['providerLogoPath'], '/')) {
                    $item['providerLogoPath'] = $providerUrl . $item['providerLogoPath'];
                }
                if (str_starts_with($item['providerLogoPath'], '+')) {
                    $item['providerLogoPath'] = '/images/providers' . substr($item['providerLogoPath'], 1);
                }
            }
            $item['episodesWatched'] = $item['watchAt'] === null ? 0 : 1;
            $item['display'] = $item['localizedName'] ?? $item['name'];
            $item['customDate'] = null;
            $item['airAt'] = "00:00:00";
            $item['seasonNumber'] = null;
            $item['episodeCount'] = 1;
            $item['firstEpisodeNumber'] = 1;
            $item['localizedSlug'] = $item['slug'] = '';
            $movieArr[$item['id']] = $item;
        }

        foreach ($movieArr as $item) {
            $airDate = $item['airDate'];
            $diff = $now->diff(date_create_immutable($airDate, $timezone));
            $index = $diff->days * ($diff->invert ? -1 : 1);
            if (!key_exists($index, $arr)) {
                $seriesArr[$index] = [];
            }
            $seriesArr[$index][] = $item;
        }
        ksort($seriesArr);

        foreach ($seriesArr as $indexKey => $itemArr) {
            $totalEpisodeCount = array_reduce($itemArr, function ($carry, $item) {;
                return $carry + $item['episodeCount'];
            }, 0);
            $results = array_map(function ($item) {
                return [
                    'type' => $item['type'],
                    'display' => $item['display'],
                    'airAt' => $item['airAt'],
                    'customDate' => $item['customDate'],
                    'episodeCount' => $item['episodeCount'],
                    'episodesWatched' => $item['episodesWatched'],
                    'firstEpisodeNumber' => $item['firstEpisodeNumber'],
                    'id' => $item['id'],
                    'name' => $item['displayName'],
                    'posterPath' => $item['posterPath'],
                    'providerLogoPath' => $item['providerLogoPath'],
                    'providerName' => $item['providerName'],
                    'progress' => 100 * $item['episodesWatched'] / $item['episodeCount'],
                    'seasonNumber' => $item['seasonNumber'],
                    'slug' => $item['localizedSlug'] ?? $item['slug'],
                ];
            }, array_values($itemArr));
            usort($results, function ($a, $b) {
                return $a['airAt'] <=> $b['airAt'];
            });
            $intervalArr[$indexKey] = [
                'index' => $indexKey,
                'totalEpisodeCount' => $totalEpisodeCount,
                'results' => $results,
            ];
        }
        return $intervalArr;
    }

    /*public function listEpisodeOfTheDay(User $user, int $interval, string $locale = 'fr'): array
    {
        $date = $this->dateService->newDateImmutable('now', $user->getTimezone() ?? 'Europe/Paris');

        if ($interval > 0)
            $date = $date->modify(sprintf('+%d day', $interval))->format('Y-m-d');
        if ($interval === 0)
            $date = $date->format('Y-m-d');
        if ($interval < 0)
            $date = $date->modify(sprintf('%d day', $interval))->format('Y-m-d');

        $sArr = $this->userEpisodeRepository->episodesOfTheDayForTwig($user, $date, $locale);
        $mArr = $this->movieRepository->moviesOfTheDayForTwig($user, $date, $locale);
//        dump(['date' => $date, 'movies' => $mArr]);
        $seriesArr = [];
        $totalEpisodeCount = 0;
        foreach ($sArr as $item) {
            if (!$this->seriesInArray($seriesArr, $item)) {
                $item['episodes'] = [$item['episodeNumber']];
                if ($item['posterPath'] === null) {
                    $item['posterPath'] = $this->seriesController->getAlternatePosterPath($item['id']);
                }
                $item['posterPath'] = $item['posterPath'] ? '/series/posters' . $item['posterPath'] : null;
                if ($item['providerLogoPath']) {
                    if (str_starts_with($item['providerLogoPath'], '/')) {
                        $item['providerLogoPath'] = $this->imageConfiguration->getCompleteUrl($item['providerLogoPath'], 'logo_sizes', 2);
                    }
                    if (str_starts_with($item['providerLogoPath'], '+')) {
                        $item['providerLogoPath'] = '/images/providers' . substr($item['providerLogoPath'], 1);
                    }
                }
//                $item['providerLogoPath'] = $item['providerLogoPath'] ? $this->imageConfiguration->getCompleteUrl($item['providerLogoPath'], 'logo_sizes', 2) : null;
                $item['episodesWatched'] = $item['watchAt'] === null ? 0 : 1;
                $item['type'] = 'series';
                $seriesArr[$item['id']] = $item;
            } else {
                $seriesArr[$item['id']]['episodes'][] = $item['episodeNumber'];
                $seriesArr[$item['id']]['episodesWatched'] += $item['watchAt'] === null ? 0 : 1;
            }
        }
        foreach ($seriesArr as $key => $item) {
            $seasonNumber = $item['seasonNumber'];
            if (count($item['episodes']) > 2) {
                $start = $this->minNumberInArray($item['episodes']);
                $end = $this->maxNumberInArray($item['episodes']);
                if ($locale === 'en') {
                    if ($seasonNumber)
                        $display = sprintf('S%02dE%02d to S%02dE%02d', $seasonNumber, $start, $seasonNumber, $end);
                    else
                        $display = sprintf('Specials #%02d to #%02d', $start, $end);
                } else {
                    if ($seasonNumber)
                        $display = sprintf('S%02dE%02d à S%02dE%02d', $seasonNumber, $start, $seasonNumber, $end);
                    else
                        $display = sprintf('Épisodes spéciaux #%02d à #%02d', $start, $end);
                }
                $seriesArr[$key]['firstEpisodeNumber'] = $start;
            } elseif (count($item['episodes']) > 1) {
                $start = $this->minNumberInArray($item['episodes']);
                $end = $this->maxNumberInArray($item['episodes']);
                if ($seasonNumber)
                    $display = sprintf('S%02dE%02d & S%02dE%02d', $seasonNumber, $start, $seasonNumber, $end);
                else
                    $display = sprintf('Specials #%02d & #%02d', $start, $end);
                $seriesArr[$key]['firstEpisodeNumber'] = $start;
            } else {
                $episodeNumber = $item['episodes'][0];
                if ($seasonNumber)
                    $display = sprintf('S%02dE%02d', $seasonNumber, $episodeNumber);
                else
                    $display = sprintf('Special #%02d', $episodeNumber);
                $seriesArr[$key]['firstEpisodeNumber'] = $episodeNumber;
            }
            $seriesArr[$key]['display'] = $item['displayName'] . ' ' . $display;
            $seriesArr[$key]['episodeCount'] = count($item['episodes']);
            $totalEpisodeCount += count($item['episodes']);
        }
        $moviesArr = [];
        foreach ($mArr as $item) {
            $item['type'] = 'movie';
            $item['posterPath'] = $item['posterPath'] ? '/movies/posters' . $item['posterPath'] : null;
            if ($item['providerLogoPath']) {
                if (str_starts_with($item['providerLogoPath'], '/')) {
                    $item['providerLogoPath'] = $this->imageConfiguration->getCompleteUrl($item['providerLogoPath'], 'logo_sizes', 2);
                }
                if (str_starts_with($item['providerLogoPath'], '+')) {
                    $item['providerLogoPath'] = '/images/providers' . substr($item['providerLogoPath'], 1);
                }
            }
            $item['episodesWatched'] = $item['watchAt'] === null ? 0 : 1;
            $item['display'] = $item['localizedName'] ?? $item['name'];
            $item['customDate'] = null;
            $item['airAt'] = "00:00:00";
            $item['seasonNumber'] = null;
            $item['episodeCount'] = 1;
            $item['firstEpisodeNumber'] = 1;
            $item['localizedSlug'] = $item['slug'] = '';
            $moviesArr[$item['id']] = $item;
        }
//        dump(['seriesArr' => $seriesArr, 'moviesArr' => $moviesArr]);
        $results = array_map(function ($item) {
            return [
                'type' => $item['type'],
                'display' => $item['display'],
                'airAt' => $item['airAt'],
                'customDate' => $item['customDate'],
                'episodeCount' => $item['episodeCount'],
                'episodesWatched' => $item['episodesWatched'],
                'firstEpisodeNumber' => $item['firstEpisodeNumber'],
                'id' => $item['id'],
                'name' => $item['displayName'],
                'posterPath' => $item['posterPath'],
                'providerLogoPath' => $item['providerLogoPath'],
                'providerName' => $item['providerName'],
                'progress' => 100 * $item['episodesWatched'] / $item['episodeCount'],
                'seasonNumber' => $item['seasonNumber'],
                'slug' => $item['localizedSlug'] ?? $item['slug'],
            ];
        }, array_merge($seriesArr, $moviesArr));
//        dump(['results' => $results]);
        usort($results, function ($a, $b) {
            return $a['airAt'] <=> $b['airAt'];
        });

        return [
            'totalEpisodeCount' => $totalEpisodeCount,
            'results' => $results,
        ];
    }*/

    public function inProgressSeries(User $user, string $locale = 'fr'): array
    {
        $arr = $this->userEpisodeRepository->inProgressSeriesForTwig($user, $locale);
        $inProgress = $arr[0] ?? [];
        $ok = $inProgress['id'] ?? null;

        if (!$ok) {
            return [
                'ok' => null,
            ];
        }
        $id = $inProgress['id'];
        return [
            'ok' => true,
            'id' => $id,
            'name' => $inProgress['name'],
            'slug' => $inProgress['slug'],
            'posterPath' => $inProgress['posterPath'] ? '/series/posters' . $inProgress['posterPath'] : null,
            'episodeId' => $inProgress['episodeId'],
            'episodeCount' => $inProgress['seasonEpisodeCount'],
            'seasonNumber' => $inProgress['nextEpisodeSeason'],
            'nextEpisode' => $inProgress['nextEpisodeNumber'],
            'progress' => 100 * $inProgress['seasonViewedEpisodeCount'] / $inProgress['seasonEpisodeCount'],
        ];
    }


    private function seriesInArray($seriesArr, $item): bool
    {
        return key_exists($item['id'], $seriesArr);
    }

    private function minNumberInArray($arr): int
    {
        $min = 1000;
        foreach ($arr as $item) {
            if ($item < $min) {
                $min = $item;
            }
        }
        return $min;
    }

    private function maxNumberInArray($arr): int
    {
        $max = 0;
        foreach ($arr as $item) {
            if ($item > $max) {
                $max = $item;
            }
        }
        return $max;
    }
}
