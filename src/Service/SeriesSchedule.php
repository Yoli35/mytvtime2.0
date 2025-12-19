<?php

namespace App\Service;

use App\Controller\SeriesController;
use App\Entity\User;
use App\Repository\MovieRepository;
use App\Repository\UserEpisodeRepository;
use DateInvalidTimeZoneException;
use DateMalformedStringException;
use DateTimeZone;

readonly class SeriesSchedule
{
    public function __construct(
        private ImageConfiguration    $imageConfiguration,
        private MovieRepository       $movieRepository,
        private SeriesController      $seriesController,
        private UserEpisodeRepository $userEpisodeRepository
    )
    {
        // Inject dependencies if needed
    }

    public function getSchedule(User $user, string $start, string $end, string $locale = 'fr'): array
    {
        $intervalArr = ["start" => $start, "end" => $end];
        $providerUrl = $this->imageConfiguration->getUrl('logo_sizes', 2);
        try {
            $timezone = new DateTimeZone($user->getTimezone() ?? "Europe/Paris");
        } catch (DateInvalidTimeZoneException) {
            $timezone = new DateTimeZone("Europe/Paris");
        }

        $now = date_create_immutable("now", $timezone)->setTime(0, 0);
        try {
            $startDate = $now->modify($start)->format('Y-m-d');
        } catch (DateMalformedStringException) {
            $startDate = $now->format('Y-m-d');
        }
        try {
            $endDate = $now->modify($end)->format('Y-m-d');
        } catch (DateMalformedStringException) {
            $endDate = $now->format('Y-m-d');
        }

        $sArr = $this->userEpisodeRepository->episodesOfTheIntervalForTwig($user, $startDate, $endDate, $locale);
        $mArr = $this->movieRepository->moviesOfTheIntervalForTwig($user, $startDate, $endDate, $locale);

        $lastViewedEpisodeId = $this->userEpisodeRepository->lastViewedEpisodeId($user);
        $intervalArr['lastViewedEpisodeId'] = $lastViewedEpisodeId;

        $startIndex = intval($start);
        $endIndex = intval($end);
        for ($index = $startIndex; $index <= $endIndex; $index++) {
            $intervalArr[$index] = [
                'index' => $index,
                'totalEpisodeCount' => 0,
                'results' => [],
            ];
        }

        $seriesArr = [];
        foreach ($sArr as $item) {
            if ($item['override'] && $item['customDate'] == null) {
                continue;
            }
            $index = $item['days'];
            if (!key_exists($index, $seriesArr)) {
                $seriesArr[$index] = [];
            }
            $seriesSeasonIndex = $item['id'] . '-' . $item['seasonNumber'];
            if (!$this->seriesInArray($seriesArr[$index], $item, $seriesSeasonIndex)) {
                $item['episodes'] = [$item['episodeNumber']];
                $item['episodeIds'] = [$item['episodeId']];
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
                $seriesArr[$index][$seriesSeasonIndex] = $item;
            } else {
                if (!$this->episodeInArray($seriesArr[$index], $item)) {
                    $seriesArr[$index][$seriesSeasonIndex]['episodeIds'][] = $item['episodeId'];
                    $seriesArr[$index][$seriesSeasonIndex]['episodes'][] = $item['episodeNumber'];
                    $seriesArr[$index][$seriesSeasonIndex]['episodesWatched'] += $item['watchAt'] === null ? 0 : 1;
                }
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
                $episodeIdArr = array_unique($item['episodeIds']);
                $item['episodeIds'] = implode('-', $episodeIdArr);
                $item['display'] = $item['displayName'] . ' ' . $display;
                $item['episodeCount'] = count($episodeIdArr);

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
            $index = intval($diff->days * ($diff->invert ? -1 : 1));
            if (!key_exists($index, $seriesArr)) {
                $seriesArr[$index] = [];
            }
            $seriesArr[$index][] = $item;
        }
        ksort($seriesArr);

        foreach ($seriesArr as $indexKey => $itemArr) {
            $totalEpisodeCount = array_reduce($itemArr, function ($carry, $item) {
                return $carry + $item['episodeCount'];
            }, 0);
            $results = array_map(function ($item) {
                return [
                    'type' => $item['type'],
                    'display' => $item['display'],
                    'displayName' => $item['displayName'],
                    'airAt' => $item['airAt'],
                    'customDate' => $item['customDate'],
                    'episodeId' => $item['episodeId'] ?? null,
                    'episodeIds' => $item['episodeIds'] ?? null,
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
                    'premiere' => $item['seasonNumber'] === 1 && $item['firstEpisodeNumber'] === 1,
                    'last_episode' => $item['last_episode'] ?? false,
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

    private function seriesInArray(array $arr, array $item, string $idx): bool
    {
//        return key_exists($item['id'], $seriesArr);
        $id = $item['id'];
        $sn = $item['seasonNumber'];
        if (!key_exists($idx, $arr)) {
            return false;
        }
        $itemAlreadyInArray = array_find($arr, function ($i) use ($id, $sn) {
            return $i['id'] === $id && $i['seasonNumber'] === $sn;
        });
        return $itemAlreadyInArray !== null;
    }

    private function episodeInArray($arr, $item): bool
    {
        $episodeId = $item['episodeId'];
        $itemAlreadyInArray = array_find($arr, function ($item) use ($episodeId) {
            return $item['episodeId'] === $episodeId;
        });
        return $itemAlreadyInArray !== null;
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