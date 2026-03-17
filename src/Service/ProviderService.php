<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserSeriesRepository;
use App\Repository\WatchProviderRepository;

readonly class ProviderService
{
    public function __construct(
        private UserSeriesRepository    $userSeriesRepository,
        private WatchProviderRepository $watchProviderRepository,
    )
    {
    }

    public function get(User $user): array
    {
        return [
            'all' => $this->watchProviderRepository->getAllProviders(),
            'userProviders' => $this->userSeriesRepository->userSeriesProviders($user),
        ];
    }

    public function seriesWithoutProviders(User $user, string $locale, int $page, int $limit): array
    {
        $count = count($this->userSeriesRepository->userSeriesWithoutProviderCount($user));
        return [
            'results' => $this->userSeriesRepository->userSeriesWithoutProvider($user, $locale, $page, $limit),
            'count' => $count,
            'page' => $page,
            'pages' => ceil($count / $limit),
        ];
    }

    public function seriesByProvider(User $user, int $provider, string $locale, int $page, int $limit): array
    {
        $count = count($this->userSeriesRepository->userSeriesByProviderCount($user, $provider));
        return [
            'results' => $this->userSeriesRepository->userSeriesByProvider($user, $provider, $locale, $page, $limit),
            'count' => $count,
            'page' => $page,
            'pages' => ceil($count / $limit),
        ];
    }

    public function getProviderLogoFullPath(?string $path, string $tmdbUrl): ?string
    {
        if (!$path) return null;
        if (str_starts_with($path, '/')) {
            return $tmdbUrl . $path;
        }
        return '/images/providers' . substr($path, 1);
    }
}