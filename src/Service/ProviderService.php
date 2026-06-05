<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\WatchProvider;
use App\Repository\UserSeriesRepository;
use App\Repository\WatchProviderRepository;

readonly class ProviderService
{
    public function __construct(
        private ImageConfiguration      $imageConfiguration,
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

    public function getOne(int $id): WatchProvider
    {
        return $this->watchProviderRepository->findOneBy(['providerId' => $id]);
    }

    public function getOneWithLogo(?int $id): array
    {
        if (!$id) {
            return [
                'logoPath' => null,
                'providerName' => null,
            ];
        }
        $p = $this->watchProviderRepository->findOneBy(['providerId' => $id]);
        if (!$p) {
            return ['logoPath' => null, 'providerName' => ''];
        }
        $logoUrl = $this->imageConfiguration->getUrl('logo_sizes', 2);
        return [
            'logoPath' => $this->getProviderLogoFullPath($p->getLogoPath(), $logoUrl),
            'providerName' => $p->getProviderName(),
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