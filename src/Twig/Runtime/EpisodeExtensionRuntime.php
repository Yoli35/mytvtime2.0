<?php

namespace App\Twig\Runtime;

use App\Entity\Settings;
use App\Entity\User;
use App\Repository\EpisodeNotificationRepository;
use App\Repository\SettingsRepository;
use App\Repository\UserEpisodeRepository;
use App\Service\SeriesSchedule;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\RuntimeExtensionInterface;

readonly class EpisodeExtensionRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private EpisodeNotificationRepository $episodeNotificationRepository,
        private SeriesSchedule                $seriesSchedule,
        private SettingsRepository            $settingsRepository,
        private TranslatorInterface           $translator,
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

    public function listEpisodeOfTheInterval(User $user, string $locale = 'fr'): array
    {
        $settings = $this->settingsRepository->findOneBy(['user' => $user, 'name' => 'schedule_range']);
        if (!$settings) {
            $settings = new Settings($user, 'schedule_range', ['start' => "-2", 'end' => "2", 'default_start' => "-2", 'default_end' => "2"]);
            $this->settingsRepository->save($settings, true);
        }
        $startDay = (int)$settings->getData()['start'];
        $endDay = (int)$settings->getData()['end'];
        $start = $startDay . " day";;
        $end = $endDay . " day";
        return [
            "interval" => $this->seriesSchedule->getSchedule($user, $start, $end, $locale),
            "startDay" => $startDay,
            "endDay" => $endDay,
        ];

    }

    public function relativeDayStrings(int $index): string
    {
        $translations = [
            -7 => $this->translator->trans("Seven days ago"),
            -6 => $this->translator->trans("Six days ago"),
            -5 => $this->translator->trans("Five days ago"),
            -4 => $this->translator->trans("Four days ago"),
            -3 => $this->translator->trans("Three days ago"),
            -2 => $this->translator->trans("Two days ago"),
            -1 => $this->translator->trans("Yesterday"),
            0 => $this->translator->trans("Today"),
            1 => $this->translator->trans("Tomorrow"),
            2 => $this->translator->trans("In two days"),
            3 => $this->translator->trans("In three days"),
            4 => $this->translator->trans("In four days"),
            5 => $this->translator->trans("In five days"),
            6 => $this->translator->trans("In six days"),
            7 => $this->translator->trans("In seven days"),
        ];
        return $translations[$index] ?? '';
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

    public function getLastEpisodeOfTheDayId(User $user): int
    {
        return $this->userEpisodeRepository->lastViewedEpisodeId($user) ?? -1;
    }
}
