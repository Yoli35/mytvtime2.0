<?php

namespace App\Twig\Runtime;

use App\Entity\User;
use App\Repository\UserPinnedSeriesRepository;
use App\Service\ImageConfiguration;
use Twig\Extension\RuntimeExtensionInterface;

readonly class PinnedSeriesRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private ImageConfiguration         $imageConfiguration,
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
        $result = array_map(function ($series) {
            $series['posterPath'] = $series['posterPath'] ? $this->imageConfiguration->getCompleteUrl($series['posterPath'], 'poster_sizes', 5) : null;
            return $series;
        }, $this->userPinnedSeriesRepository->getPinnedSeriesByUser($user, $locale));
        dump($result);
        return $result;
    }
}
