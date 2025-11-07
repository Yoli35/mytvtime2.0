<?php

namespace App\Api;

use App\Entity\EpisodeLocalizedOverview;
use App\Entity\User;
use App\Repository\EpisodeLocalizedOverviewRepository;
use App\Repository\EpisodeStillRepository;
use App\Repository\EpisodeSubstituteNameRepository;
use App\Repository\UserEpisodeRepository;
use App\Repository\WatchProviderRepository;
use App\Service\DateService;
use App\Service\ImageConfiguration;
use App\Service\TMDBService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/** @method User|null getUser() */
#[Route('/api/series', name: 'api_series_')]
class SeriesSeasonEpisodes extends AbstractController
{
    public function __construct(
        private readonly EpisodeStillRepository             $episodeStillRepository,
        private readonly EpisodeLocalizedOverviewRepository $episodeLocalizedOverviewRepository,
        private readonly EpisodeSubstituteNameRepository    $episodeSubstituteNameRepository,
        private readonly ImageConfiguration                 $imageConfiguration,
        private readonly DateService                        $dateService,
        private readonly TMDBService                        $tmdbService,
        private readonly TranslatorInterface                $translator,
        private readonly UserEpisodeRepository              $userEpisodeRepository,
        private readonly WatchProviderRepository            $watchProviderRepository,
    )
    {
    }

    #[Route('/season/episode/stills/{id}/{tmdbId}/{seasonNumber}/{slug}', name: 'seasons_episodes', requirements: ['id' => '\d+', 'tmdbId' => '\d+', 'season_number' => '\d+', 'slug' => '.+'], methods: ['GET'])]
    public function next(Request $request, int $id, int $tmdbId, int $seasonNumber, string $slug): JsonResponse
    {
        $user = $this->getUser();
        $logoUrl = $this->imageConfiguration->getUrl('logo_sizes', 2);
        $stillUrl = $this->imageConfiguration->getUrl('still_sizes', 3);
        $locale = $request->getLocale();
        $timezone = $user?->getTimezone() ?? 'Europe/Paris';

        $season = json_decode($this->tmdbService->getTvSeason($tmdbId, $seasonNumber, $user->getPreferredLanguage() ?? $request->getLocale()), true);
        $episodeIds = array_column($season['episodes'] ?? [], 'id');
        $dbEpisodeSubstituteNames = $this->episodeSubstituteNameRepository->findBy(['episodeId' => $episodeIds]);
        $dbEpisodeLocalizedOverviews = $this->episodeLocalizedOverviewRepository->findBy(['episodeId' => $episodeIds]);
        $dbEpisodeStills = $this->episodeStillRepository->findBy(['episodeId' => $episodeIds]);
        $userEpisodes = $this->userEpisodeRepository->findBy([
            'user' => $user,
            'episodeId' => $episodeIds,
        ]);
        $watchProviderPaths = [];
        $watchProviderNames = [];
        $providerIds = array_unique(array_map(function ($ue) {
            return $ue->getProviderId();
        }, array_filter($userEpisodes, function ($ue) {
            return $ue->getProviderId() !== null;
        })));
        if (count($providerIds) > 0) {
            $watchProviders = $this->watchProviderRepository->findBy(['providerId' => $providerIds]);
            foreach ($watchProviders as $wp) {
                $path = $wp->getLogoPath();
                if (str_starts_with($path, '/')) {
                    $path = $logoUrl . $path;
                } else {
                    $path = '/images/providers' . substr($path, 1);
                }
                $watchProviderPaths[$wp->getProviderId()] = $path;
                $watchProviderNames[$wp->getProviderId()] = $wp->getProviderName();
            }
        }

        $episodes = array_map(function ($episode) use ($id, $slug, $seasonNumber, $dbEpisodeSubstituteNames, $dbEpisodeLocalizedOverviews, $dbEpisodeStills, $userEpisodes, $watchProviderPaths, $watchProviderNames, $locale, $timezone, $stillUrl) {
            //
            // Substitute Name
            //
            $substituteName = array_find($dbEpisodeSubstituteNames, function ($esn) use ($episode) {
                return $esn->getEpisodeId() === $episode['id'];
            });
            if ($substituteName) {
                $episode['name'] .= " - " . $substituteName->getName();
            }
            //
            // Overview
            //
            if ($episode['overview'] === '') {
                // Si overview est vide, on vérifie si on a une version 'fr' (ou 'en', 'ko') dans la base de données.
                $localizedOverview = array_find($dbEpisodeLocalizedOverviews, function ($eo) use ($episode, $locale) {
                    return $eo->getEpisodeId() === $episode['id'] && $eo->getLocale() === $locale;
                });
                if (!$localizedOverview) {
                    // Si overview est vide, on vérifie si on a une version 'en' en base
                    $localizedOverview = array_find($dbEpisodeLocalizedOverviews, function ($eo) use ($episode) {
                        return $eo->getEpisodeId() === $episode['id'] && $eo->getLocale() === 'en';
                    });
                }
                if ($localizedOverview) {
                    $episode['overview'] = $localizedOverview->getOverview();
                }
            }
            if ($episode['overview'] === '') {
                $episodeUS = json_decode($this->tmdbService->getTvEpisode($episode['show_id'], $seasonNumber, $episode['episode_number'], 'en-US'), true);
                $episode['overview'] = $episodeUS['overview'] ?? '';
                if (strlen($episode['overview']) > 0) {
                    $localizedOverview = new EpisodeLocalizedOverview($episode['id'], $episode['overview'], 'en');
                    $this->episodeLocalizedOverviewRepository->save($localizedOverview, true);
                }
            }
            //
            // Still
            //
            if ($episode['still_path'] === null) {
                $episodeStill = array_find($dbEpisodeStills, function ($es) use ($episode) {
                    return $es->getEpisodeId() === $episode['id'];
                });
                if ($episodeStill) {
                    $episode['still_path'] = "/series/stills" . $episodeStill->getPath();
                }
            } else {
                $episode['still_path'] = $stillUrl . $episode['still_path'];
            }
            //
            // User Episode
            //
            $watchedAt = null;
            $providerPath = null;
            $providerName = null;
            $device = null;
            $vote = null;
            if (count($userEpisodes)) {
                $userEpisode = array_find($userEpisodes, function ($ue) use ($episode) {
                    return $ue->getEpisodeId() === $episode['id'];
                });
                $watchedAt = $userEpisode ? $userEpisode->getWatchAt() : false;
                if ($watchedAt) {
                    $providerPath = $userEpisode->getProviderId() ? $watchProviderPaths[$userEpisode->getProviderId()] : null;
                    $providerName = $userEpisode->getProviderId() ? $watchProviderNames[$userEpisode->getProviderId()] : null;
                    $device = $userEpisode->getDeviceId();
                    $vote = $userEpisode->getVote();
                }
            }
            return [
                'air_date' => $episode['air_date'] ? ucfirst($this->dateService->formatDateRelativeLong($episode['air_date'], $timezone, $locale)) : $this->translator->trans('No date'),
                'episode_number' => $episode['episode_number'],
                'link' => "/$locale/series/season/$id-$slug/$seasonNumber#episode-$seasonNumber-" . $episode['episode_number'],
                'name' => $episode['name'],
                'overview' => $episode['overview'],
                'still' => $episode['still_path'],
                'watchedAt' => $watchedAt ? ucfirst($this->dateService->formatDateRelativeLong($watchedAt->format("Y-m-d H:i"), $timezone, $locale)) : null,
                'provider_path' => $providerPath,
                'provider_name' => $providerName,
                'device' => $device,
                'vote' => $vote,
            ];
        }, $season['episodes'] ?? []);

        $episodeCards = array_map(function ($episode) {
            return $this->renderView('_blocks/series/_season_episode_card.html.twig', [
                'episode' => $episode,
            ]);
        }, $episodes);

        return new JsonResponse([
            'episodeCards' => implode("\n", $episodeCards),
        ]);
    }
}