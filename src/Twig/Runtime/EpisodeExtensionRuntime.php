<?php

namespace App\Twig\Runtime;

use App\Entity\User;
use App\Repository\EpisodeNotificationRepository;
use App\Repository\UserEpisodeRepository;
use App\Service\DateService;
use Twig\Extension\RuntimeExtensionInterface;

readonly class EpisodeExtensionRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private DateService                   $dateService,
        private EpisodeNotificationRepository $episodeNotificationRepository,
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

    public function listEpisodeOfTheDay(User $user, int $interval, string $country = 'FR', string $locale = 'fr'): array
    {
        $date = $this->dateService->newDateImmutable('now', $user->getTimezone() ?? 'Europe/Paris');

        if ($interval > 0)
            $date = $date->modify(sprintf('+%d day', $interval))->format('Y-m-d');
        if ($interval === 0)
            $date = $date->format('Y-m-d');
        if ($interval < 0)
            $date = $date->modify(sprintf('%d day', $interval))->format('Y-m-d');

        $arr = $this->userEpisodeRepository->episodesOfTheDayForTwig($user, $date, $country, $locale);
        $seriesArr = [];
        $totalEpisodeCount = 0;
        foreach ($arr as $item) {
            if (!$this->seriesInArray($seriesArr, $item)) {
                $item['episodes'] = [$item['episodeNumber']];
                $item['posterPath'] = $item['posterPath'] ? '/series/posters' . $item['posterPath'] : null;
                $item['episodesWatched'] = $item['watchAt'] === null ? 0 : 1;
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
                if ($locale === 'en')
                    $display = sprintf('S%02dE%02d to S%02dE%02d', $seasonNumber, $start, $seasonNumber, $end);
                else
                    $display = sprintf('S%02dE%02d à S%02dE%02d', $seasonNumber, $start, $seasonNumber, $end);
                $seriesArr[$key]['firstEpisodeNumber'] = $start;
            } elseif (count($item['episodes']) > 1) {
                $start = $this->minNumberInArray($item['episodes']);
                $end = $this->maxNumberInArray($item['episodes']);
                $display = sprintf('S%02dE%02d & S%02dE%02d', $seasonNumber, $start, $seasonNumber, $end);
                $seriesArr[$key]['firstEpisodeNumber'] = $start;
            } else {
                $episodeNumber = $item['episodes'][0];
                $display = sprintf('S%02dE%02d', $seasonNumber, $episodeNumber);
                $seriesArr[$key]['firstEpisodeNumber'] = $episodeNumber;
            }
            $seriesArr[$key]['display'] = $item['displayName'] . ' ' . $display;
            $seriesArr[$key]['episodeCount'] = count($item['episodes']);
            $totalEpisodeCount += count($item['episodes']);
        }

        $results = array_map(function ($item) {
            return [
                'display' => $item['display'],
                'airAt' => $item['airAt'],
                'episodeCount' => $item['episodeCount'],
                'episodesWatched' => $item['episodesWatched'],
                'firstEpisodeNumber' => $item['firstEpisodeNumber'],
                'id' => $item['id'],
                'name' => $item['displayName'],
                'posterPath' => $item['posterPath'],
                'progress' => 100 * $item['episodesWatched'] / $item['episodeCount'],
                'seasonNumber' => $item['seasonNumber'],
                'slug' => $item['localizedSlug'] ?? $item['slug'],
            ];
        }, $seriesArr);

        usort($results, function ($a, $b) {
            return $a['airAt'] <=> $b['airAt'];
        });

        return [
            'totalEpisodeCount' => $totalEpisodeCount,
            'results' => $results,
        ];
    }

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
