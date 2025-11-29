<?php

namespace App\Twig\Runtime;

use App\Entity\User;
use App\Repository\EpisodeNotificationRepository;
use App\Repository\UserEpisodeRepository;
use App\Service\SeriesSchedule;
use Twig\Extension\RuntimeExtensionInterface;

readonly class EpisodeExtensionRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private EpisodeNotificationRepository $episodeNotificationRepository,
        private SeriesSchedule                $seriesSchedule,
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
        return $this->seriesSchedule->getSchedule($user, $start, $end, $locale);
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
        ];}

    public function getLastEpisodeOfTheDayId(User $user): int
    {
        return $this->userEpisodeRepository->lastViewedEpisodeId($user) ?? -1;
    }
}
