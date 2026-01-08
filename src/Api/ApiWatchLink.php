<?php

namespace App\Api;

use App\Entity\MovieDirectLink;
use App\Entity\SeriesWatchLink;
use App\Entity\UserMovie;
use App\Repository\MovieDirectLinkRepository;
use App\Repository\MovieRepository;
use App\Repository\SeriesRepository;
use App\Repository\SeriesWatchLinkRepository;
use App\Repository\WatchProviderRepository;
use App\Service\ImageConfiguration;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/watch/link', name: 'api_watch_link_')]
class ApiWatchLink extends AbstractController
{
    public function __construct(
        private readonly ImageConfiguration        $imageConfiguration,
        private readonly MovieRepository           $movieRepository,
        private readonly MovieDirectLinkRepository $movieDirectLinkRepository,
        private readonly SeriesRepository          $seriesRepository,
        private readonly SeriesWatchLinkRepository $seriesWatchLinkRepository,
        private readonly WatchProviderRepository   $watchProviderRepository,
    )
    {
    }

    #[Route('/series/create', name: 'series_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);
        $url = $data['url'];
        $name = $data['name'];
        $seriesId = $data['mediaId'];
        $seasonNumber = intval($data['seasonNumber']) ?? -1;
        $providerId = $data['provider'];
        if ($providerId == "") $providerId = null;
        $series = $this->seriesRepository->findOneBy(['id' => $seriesId]);

        $watchLink = new SeriesWatchLink($url, $name, $series, $seasonNumber, $providerId);
        $this->seriesWatchLinkRepository->save($watchLink, true);

        return $this->json([
            'ok' => true,
            'link' => $this->getLink($watchLink),
        ]);
    }

    #[Route('/series/read/{id}', name: 'series_read', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    public function read(SeriesWatchLink $seriesWatchLink): Response
    {
        return $this->json([
            'ok' => true,
            'link' => $this->getLink($seriesWatchLink),
        ]);
    }

    #[Route('/series/update/{id}', name: 'series_update', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function update(Request $request, SeriesWatchLink $watchLink): Response
    {
        $data = json_decode($request->getContent(), true);
        $url = $data['url'];
        $name = $data['name'];
        $seasonNumber = intval($data['seasonNumber']) ?? -1;
        $providerId = $data['provider'];
        if ($providerId == "") $providerId = null;

        $watchLink->setUrl($url);
        $watchLink->setName($name);
        $watchLink->setSeasonNumber($seasonNumber);
        $watchLink->setProviderId($providerId);
        $this->seriesWatchLinkRepository->save($watchLink, true);

        return $this->json([
            'ok' => true,
        ]);
    }

    #[Route('/series/delete/{id}', name: 'series_delete', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function delete(SeriesWatchLink $watchLink): Response
    {
        $this->seriesWatchLinkRepository->delete($watchLink);

        return $this->json([
            'ok' => true,
        ]);
    }

    #[Route('/movie/create', name: 'movie_create', methods: ['POST'])]
    public function addWatchLink(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);
        $url = $data['url'];
        $title = $data['name'];
        $movieId = $data['mediaId'];
        $providerId = $data['provider'];
        if ($providerId == "") $providerId = null;

        $movie = $this->movieRepository->findOneBy(['id' => $movieId]);

        $watchLink = new MovieDirectLink($url, $title, $movie, $providerId);
        $this->movieDirectLinkRepository->save($watchLink, true);

        return $this->json([
            'ok' => true,
        ]);
    }

    #[Route('/movie/read/{id}', name: 'movie_read', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    public function readDirectLink(MovieDirectLink $link): Response
    {
        return $this->json([
            'ok' => true,
            'link' => $this->getLink($link),
        ]);
    }

    #[Route('/movie/update/{id}', name: 'movie_update', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function updateWatchLink(Request $request, MovieDirectLink $link): Response
    {
        $data = json_decode($request->getContent(), true);
        $url = $data['url'];
        $name = $data['name'];
        $providerId = $data['provider'];
        if ($providerId == "") $providerId = null;

        $link->setUrl($url);
        $link->setName($name);
        $link->setProviderId($providerId);
        $this->movieDirectLinkRepository->save($link, true);

        return $this->json([
            'ok' => true,
        ]);
    }

    #[Route('/movie/delete/{id}', name: 'movie_delete', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function deleteWatchLink(MovieDirectLink $link): Response
    {
        $this->movieDirectLinkRepository->delete($link);

        return $this->json([
            'ok' => true,
        ]);
    }


    private function getLink(SeriesWatchLink|MovieDirectLink $link): array
    {
        if ($link instanceof MovieDirectLink) {
            return [
                'id' => $link->getId(),
                'name' => $link->getName(),
                'provider' => $this->getProvider($link->getProviderId()),
                'url' => $link->getUrl(),
            ];
        }
        return [
            'id' => $link->getId(),
            'name' => $link->getName(),
            'provider' => $this->getProvider($link->getProviderId()),
            'seasonNumber' => $link->getSeasonNumber(),
            'url' => $link->getUrl(),
        ];
    }

    public function getWatchProviders($watchRegion): array
    {
        // May be unavailable - when Youtube was added for example
        // TODO: make a command to regularly update db
//        $providers = json_decode($this->tmdbService->getTvWatchProviderList($language, $watchRegion), true);
//        $providers = $providers['results'];
//        if (count($providers) == 0) {
        $providers = $this->watchProviderRepository->getWatchProviderList($watchRegion);
//        }
        $watchProviders = [];
        foreach ($providers as $provider) {
            $watchProviders[$provider['provider_name']] = $provider['provider_id'];
        }
        $watchProviderNames = [];
        foreach ($providers as $provider) {
            $watchProviderNames[$provider['provider_id']] = $provider['provider_name'];
        }
        $watchProviderLogos = [];
        $logoUrl = $this->imageConfiguration->getUrl('logo_sizes', 2);
        foreach ($providers as $provider) {
            $watchProviderLogos[$provider['provider_id']] = $this->getProviderLogoFullPath($provider['logo_path'], $logoUrl);
        }
        uksort($watchProviders, function ($a, $b) {
            return strcasecmp($a, $b);
        });
        $list = [];
        foreach ($watchProviders as $key => $value) {
            $list[] = ['provider_id' => $value, 'provider_name' => $key, 'logo_path' => $watchProviderLogos[$value]];
        }

        return [
            'select' => $watchProviders,
            'logos' => $watchProviderLogos,
            'names' => $watchProviderNames,
            'list' => $list,
        ];
    }

    private function getProvider($providerId): array
    {
        if ($providerId === null) {
            return [
                'id' => -1,
                'name' => null,
                'logoPath' => null,
            ];
        }
        $provider = $this->watchProviderRepository->findOneBy(['providerId' => $providerId]);

        return [
            'id' => $provider->getProviderId(),
            'name' => $provider->getProviderName(),
            'logoPath' => $this->getProviderLogoFullPath($provider->getLogoPath()),
        ];
    }

    public function getProviderLogoFullPath(?string $path): ?string
    {
        if (!$path) return null;
        if (str_starts_with($path, '/')) {
            return $this->imageConfiguration->getCompleteUrl($path, 'logo_sizes', 2);
        }
        return '/images/providers' . substr($path, 1);
    }
}