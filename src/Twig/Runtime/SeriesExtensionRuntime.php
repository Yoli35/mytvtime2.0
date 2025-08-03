<?php

namespace App\Twig\Runtime;

use App\Entity\Settings;
use App\Entity\User;
use App\Repository\SeriesRepository;
use App\Repository\SettingsRepository;
use App\Repository\UserEpisodeRepository;
use App\Service\DateService;
use App\Service\ImageConfiguration;
use Twig\Extension\RuntimeExtensionInterface;

readonly class SeriesExtensionRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private DateService                   $dateService,
        private ImageConfiguration            $imageConfiguration,
        private SeriesRepository              $seriesRepository,
        private SettingsRepository            $settingsRepository,
        private UserEpisodeRepository         $userEpisodeRepository
    )
    {
        // Inject dependencies if needed
    }

    public function seriesHistory(User $user): array
    {
        $settings = $this->settingsRepository->findOneBy(['user' => $user, 'name' => 'seriesHistory']);
        if (!$settings) {
            $settings = new Settings($user, 'seriesHistory', [
                "list" => "series",
                "last" => 0,
                "count" => 20,
                "page" => 1,
                "vote" => true,
                "device" => true,
                "provider" => true
            ]);
            $this->settingsRepository->save($settings, true);
        }
        $data = $settings->getData();
        $listType = $data['list'];
        $count = intval($data['count']);
        $page = intval($data['page']);
        $vote = $data['vote'];
        $device = $data['device'];
        $provider = $data['provider'];

        $history = array_map(function ($item) use ($user) {
            if (!$item['posterPath']) {
                $posters = $this->seriesRepository->seriesPosters($item['id']);
                if (count($posters)) {
                    $item['posterPath'] = $posters[rand(0, count($posters) - 1)]['image_path'];
                }
            }
            $item['lastWatchAt'] = $this->dateService->newDateImmutable($item['lastWatchAt'], 'UTC')/*->setTimezone(new \DateTimeZone($user->getTimezone() ?? 'Europe/Paris'))*/;
//            $item['providerLogoPath'] = $item['providerLogoPath'] ? $this->imageConfiguration->getCompleteUrl($item['providerLogoPath'], 'logo_sizes', 2) : null;
            if ($item['providerLogoPath']) {
                if (str_starts_with($item['providerLogoPath'], '/')) {
                    $item['providerLogoPath'] = $this->imageConfiguration->getCompleteUrl($item['providerLogoPath'], 'logo_sizes', 2);
                }
                if (str_starts_with($item['providerLogoPath'], '+')) {
                    $item['providerLogoPath'] = '/images/providers' . substr($item['providerLogoPath'], 1);
                }
            }
            return $item;
        }, $this->userEpisodeRepository->seriesHistoryForTwig($user, $user->getPreferredLanguage() ?? 'fr', $listType, $page, $count));

        if (count($history) && $data['last'] !== $history[0]['episodeId']) {
            $data['last'] = $history[0]['episodeId'];
            $settings->setData($data);
            $this->settingsRepository->save($settings, true);
        }
        return [
            'list' => $history,
            'last' => $data['last'],
            'type' => $listType,
            'count' => $count,
            'page' => $page,
            'vote' => $vote,
            'device' => $device,
            'provider' => $provider,
        ];
    }

    public function hasSeriesStartedAiring(int $seriesId): bool
    {
        $date = $this->dateService->newDateImmutable('now', 'UTC')->format('Y-m-d');
        return $this->seriesRepository->hasSeriesStartedAiring($seriesId, $date);
    }

    public function getUserCountrySettings(User $user): string
    {
        $settings = $this->settingsRepository->findOneBy(['user' => $user, 'name' => 'by country']);
        if ($settings) {
            $country = $settings->getData()['country'];
        } else {
            $country = $user->getCountry() ?? 'FR';
        }
        return $country;
    }
}
