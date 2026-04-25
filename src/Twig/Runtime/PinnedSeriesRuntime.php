<?php

namespace App\Twig\Runtime;

use App\Controller\SeriesController;
use App\Entity\User;
use App\Repository\UserPinnedSeriesRepository;
use App\Service\ImageConfiguration;
use App\Service\ProviderService;
use Twig\Extension\RuntimeExtensionInterface;

readonly class PinnedSeriesRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private ImageConfiguration         $imageConfiguration,
        private ProviderService            $providerService,
        private UserPinnedSeriesRepository $userPinnedSeriesRepository
    )
    {
        // Inject dependencies if needed
    }

    public function pinnedSeries(?User $user, string $locale): array
    {
        if (!$user) {
            return [];
        }
        $posterUrl = $this->imageConfiguration->getUrl('poster_sizes', 5);
        $logoUrl = $this->imageConfiguration->getUrl('logo_sizes', 2);
        $arr = array_map(function ($series) use ($logoUrl, $posterUrl) {
            $series['posterPath'] = $series['posterPath'] ? $posterUrl. $series['posterPath'] : null;
            $series['providerLogoPath'] = $this->providerService->getProviderLogoFullPath($series['providerLogoPath'], $logoUrl);
            return $series;
        }, $this->userPinnedSeriesRepository->getPinnedSeriesByUser($user, $locale));
        $pinnedSeriesArr = [];
        foreach ($arr as $item) {
            $index = $item['id'];
            if (!key_exists($index, $pinnedSeriesArr)) {
                $pinnedSeriesArr[$index] = $item;
                $pinnedSeriesArr[$index]['providers'] = [];
            }
            $provider['providerLogoPath'] = $item['providerLogoPath'];
            $provider['providerName'] = $item['providerName'];
            $pinnedSeriesArr[$index]['providers'][] = $provider;
        }
        dump($pinnedSeriesArr);
        return $pinnedSeriesArr;
    }
}
