<?php

namespace App\Api;

use App\Entity\Series;
use App\Repository\EpisodeLocalizedOverviewRepository;
use App\Repository\SeasonRepository;
use App\Repository\SeriesRepository;
use App\Service\SeasonService;
use Closure;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\DependencyInjection\Attribute\AutowireMethodOf;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/tv/time', name: 'api_tv_time_')]
readonly class ApiEpisodeCheck
{
    public function __construct(
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure                            $getUser,
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure                            $json,
        private EpisodeLocalizedOverviewRepository $episodeLocalisedOverviewRepository,
        private SeasonRepository                   $seasonRepository,
        private SeasonService                      $seasonService,
        private SeriesRepository                   $seriesRepository,
    )
    {
    }

    #[Route('/episode/check', name: 'episode_check', methods: ['POST'])]
    public function check(Request $request): Response
    {
        $user = ($this->getUser)();
        $locale = $user->getPreferredLanguage() ?? $request->getLocale();

        if ($locale === 'ko') {
            return ($this->json)([
                'ok' => true,
                'updates' => [],
                'messages' => ['Episode name check skipped'],
                'count' => 0,
            ]);
        }

        $data = json_decode($request->getContent(), true);
        $episodeRefs = $data['episodeRefs'] ?? null;
        if (!is_array($episodeRefs))
            return ($this->json)([
                'ok' => false,
                'updates' => [],
                'messages' => ['Invalid episode data'],
                'count' => 0,
            ]);
        // data-ref : "{tmdb_id}-{episode_id}-{season_number}-{episode_number}-{esn_name}"
        $episodeData = array_map(function ($ep) {
            $data = explode('-', $ep, 5);
            return [
                'id' => (int)$data[0],
                'episodeId' => (int)$data[1],
                'seasonNumber' => (int)$data[2],
                'episodeNumber' => (int)$data[3],
                'esn' => ($data[4] ?? '') ?: null,
            ];
        }, $episodeRefs);

        // Séries locales indexées par leur id TMDB
        $seriesByTmdbId = [];
        $seriesTmdbIds = array_values(array_unique(array_column($episodeData, 'id')));
        foreach ($this->seriesRepository->findBy(['tmdbId' => $seriesTmdbIds]) as $series) {
            /** @var Series $series */
            $seriesByTmdbId[$series->getTmdbId()] = $series;
        }

        // Saisons concernées, avec leurs épisodes, indexées par "{seriesId}-{seasonNumber}"
        $seasonKeys = [];
        foreach ($episodeData as $ep) {
            $series = $seriesByTmdbId[$ep['id']] ?? null;
            if ($series) {
                $key = $series->getId() . '-' . $ep['seasonNumber'];
                $seasonKeys[$key] = ['series' => $series, 'seriesId' => $series->getId(), 'seasonNumber' => $ep['seasonNumber']];
            }
        }
        $seasons = $this->seasonRepository->findWithEpisodes($seasonKeys, $locale);

        // Récupérer les résumés traduits (EpisodeLocalisedOverview)
        // 1- Récupérer les tmdb ids des episodes
        $episodeTmdbIds = array_unique(array_column($episodeData, 'episodeId'));
        // 2- Récupérer les résumés traduits (episodeId → overview)
        $arr = $this->episodeLocalisedOverviewRepository->findBy(['episodeId' => $episodeTmdbIds, 'locale' => $locale]);
        $episodeLocalisedOverviews = [];
        foreach ($arr as $episodeLocalisedOverview) {
            $episodeLocalisedOverviews[$episodeLocalisedOverview->getEpisodeId()] = $episodeLocalisedOverview->getOverview();
        }

        // Un seul appel TheMovieDB par saison absente ou trop ancienne
        $tmdbCalls = 0;
        $messages = [];
        $t0 = microtime(true);
        foreach ($seasonKeys as $key => $seasonKey) {
            $season = $seasons[$key] ?? null;
            if ($this->seasonService->isFresh($season)) {
                continue;
            }
            if (microtime(true) - $t0 > 25) {
                $messages[] = 'Time limit reached';
                break;
            }
            $season = $this->seasonService->refreshSeason($seasonKey['series'], $seasonKey['seasonNumber'], $locale, $season);
            $tmdbCalls++;
            if ($season) {
                $seasons[$key] = $season;
            }
        }
        $this->seasonRepository->flush();

        $updates = [];
        foreach ($episodeData as $ep) {
            $episodeId = $ep['episodeId'];
            $series = $seriesByTmdbId[$ep['id']] ?? null;
            $season = $series ? $seasons[$series->getId() . '-' . $ep['seasonNumber']] ?? null : null;
            $episode = $season?->getEpisode($episodeId);
            dump($episode);
            if (!$episode) {
                $reason = match (true) {
                    !$series => '*** Series not found ***',
                    !$season => '*** Season not available ***',
                    default => '*** Episode not found ***',
                };
                $reason .= ' -';
                foreach ($ep as $key => $value) $reason .= ' ' . $key . '→' . $value;
                $updates[] = [
                    'episode_id' => $episodeId,
                    'content' => ['status' => 'error', 'reason' => $reason],
                ];
                continue;
            }

            $ref = 'ref: ';
            foreach ($ep as $key => $value) $ref .= ' ' . $key . '→' . $value;

            $updates[] = [
                'episode_id' => $episodeId,
                'content' => [
                    'status' => 'success',
                    'name' => ($episode->getName() ?? '') . ($ep['esn'] ? ' - ' . $ep['esn'] : ''),
                    'overview' => $episode->getOverview() ?? $episodeLocalisedOverviews[$episodeId] ?? '',
                    'runtime' => $episode->getRuntime(),
                    'ref' => $ref,
                ],
            ];
        }

        return ($this->json)([
            'ok' => true,
            'updates' => $updates,
            'messages' => $messages,
            'count' => $tmdbCalls,
        ]);
    }
}
