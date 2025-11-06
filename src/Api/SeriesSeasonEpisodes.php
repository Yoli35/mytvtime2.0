<?php

namespace App\Api;

use App\Entity\Series;
use App\Entity\User;
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
        private readonly ImageConfiguration  $imageConfiguration,
        private readonly DateService         $dateService,
        private readonly TMDBService         $tmdbService,
        private readonly TranslatorInterface $translator,
    )
    {
    }

    #[Route('/season/episode/stills/{id}/{tmdbId}/{seasonNumber}/{slug}', name: 'seasons_episodes', requirements: ['id' => '\d+', 'tmdbId' => '\d+', 'season_number' => '\d+', 'slug' => '.+'], methods: ['GET'])]
    public function next(Request $request, int $id, int $tmdbId, int $seasonNumber, string $slug): JsonResponse
    {
        $user = $this->getUser();
        $stillUrl = $this->imageConfiguration->getUrl('still_sizes', 3);
        $locale = $request->getLocale();
        $timezone = $user?->getTimezone() ?? 'Europe/Paris';

        $season = json_decode($this->tmdbService->getTvSeason($tmdbId, $seasonNumber, $user->getPreferredLanguage() ?? $request->getLocale()), true);
        $episodes = array_map(function ($episode) use ($id, $slug, $seasonNumber, $locale, $timezone, $stillUrl) {
            if ($episode['overview'] === '') {
                $episodeUS = json_decode($this->tmdbService->getTvEpisode($episode['show_id'], $seasonNumber, $episode['episode_number'], 'en-US'), true);
                $episode['overview'] = $episodeUS['overview'] ?? '';
            }
            return [
                'air_date' => $episode['air_date'] ? ucfirst($this->dateService->formatDateRelativeLong($episode['air_date'], $timezone, $locale)) : $this->translator->trans('No date'),
                'episode_number' => $episode['episode_number'],
                'link' => "/$locale/series/season/$id-$slug/$seasonNumber#episode-$seasonNumber-" . $episode['episode_number'],
                'name' => $episode['name'],
                'overview' => $episode['overview'],
                'still' => $episode['still_path'] ? $stillUrl . $episode['still_path'] : null,
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