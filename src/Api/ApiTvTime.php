<?php

namespace App\Api;

use App\Entity\User;
use App\Repository\MovieRepository;
use App\Repository\SeriesRepository;
use App\Service\ImageConfiguration;
use App\Service\ImageService;
use App\Service\TvTimeService;
use Closure;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\DependencyInjection\Attribute\AutowireMethodOf;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[Route('/api/tv/time', name: 'api_tv_time_')]
readonly class ApiTvTime
{
    public function __construct(
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure            $renderView,
        private ImageConfiguration $imageConfiguration,
        private ImageService       $imageService,
        private MovieRepository    $movieRepository,
        private SeriesRepository   $seriesRepository,
        private TvTimeService      $tvTimeService,
    )
    {
    }

    #[Route('/check', name: 'check', methods: ['POST'])]
    public function check(#[CurrentUser] User $user, Request $request): JsonResponse
    {
        $inputBag = $request->getPayload();
        $tab = $inputBag->getInt('tab');
        $sort = $inputBag->get('sort');
        $kind = $inputBag->get('kind');

        $tabString = match ($tab) {
            1 => 'movies',
            2 => 'coming',
            default => 'series',
        };

        $view = 'No data yet ;p';
        $noVoteView = null;
        $locale = $user->getPreferredLanguage() ?? $request->getLocale();

        if ($sort != null) {
            $this->tvTimeService->setTvTimeSort($user, intval($sort));
        }
        if ($kind != null) {
            $this->tvTimeService->setTvTimeKind($user, $tabString, intval($kind));
        }
        $data = $this->tvTimeService->getTvTimeData($user, $locale);

        if ($data['tab'] == 0) {
            $noVoteView = ($this->renderView)('_blocks/tv_time/_card_series_vote.html.twig', ['seriesArr' => $data['noVoteArr']]);
            $view = ($this->renderView)('_blocks/tv_time/_wrapper_series.html.twig', [
                'tab' => $data['tab'],
                'series' => $data['series']['content'],
                'users' => $data['series']['users'],
                'watchLinks' => $data['series']['watchLinks'],
                'week' => $data['week'],
                'list' => $data['list'],
                'seriesKind' => $data['series-kind'],
                'loadCount' => $data['loadCount'],
            ]);
        }

        if ($data['tab'] == 1) {
            $view = ($this->renderView)('_blocks/tv_time/_wrapper_movies.html.twig', [
                'tab' => $data['tab'],
                'movies' => $data['movies']['results'],
                'moviesCount' => $data['movies']['count'],
                'list' => $data['list'],
                'moviesKind' => $data['movies-kind'],
                'loadCount' => $data['loadCount'],
            ]);
        }

        if ($data['tab'] == 2) {
            $view = ($this->renderView)('_blocks/tv_time/_wrapper_coming.html.twig', [
                'tab' => $data['tab'],
                'list' => $data['list'],
                'comingKind' => $data['coming-kind'],
                'loadCount' => $data['loadCount'],
            ]);
        }

        return new JsonResponse([
            'view' => $view,
            'noVoteView' => $noVoteView,
            'data' => $data,
        ]);
    }

    #[Route('/show-more', name: 'show_more', methods: ['POST'])]
    public function showMore(#[CurrentUser] User $user, Request $request): JsonResponse
    {
        $inputBag = $request->getPayload();
        $content = $inputBag->get('content');
        $offset = $inputBag->getInt('offset');

        $locale = $user->getPreferredLanguage() ?? $request->getLocale();

        if ($content === 'ended') {
            $seriesEnded = $this->tvTimeService->seriesEnded($user, $locale, $offset);
            $watchLinks = $seriesEnded['wp'];
            $block = array_map(function ($item) use ($watchLinks) {
                return ($this->renderView)('_blocks/tv_time/_card_series_ended.html.twig', [
                        'series' => $item,
                        'watchLinks' => $watchLinks
                    ]) . "\n";
            }, $seriesEnded['items']);
            $block = implode('', $block);
            return new JsonResponse([
                'new_episode' => true,
                'block' => $block,
                'count' => count($seriesEnded['items']),
                'total' => $seriesEnded['count']
            ]);
        }
        if ($content === 'not-watched-in-a-while') {
            $series = $this->tvTimeService->seriesNotSeenInAWhile($user, $locale, $offset);

            $watchLinks = $series['wp'];
            $block = array_map(function ($item) use ($watchLinks) {
                return ($this->renderView)('_blocks/tv_time/_card_series.html.twig', [
                        'series' => $item,
                        'watchLinks' => $watchLinks,
                        'next' => false,
                        'while' => true
                    ]) . "\n";
            }, $series['items']);
            $block = implode('', $block);
            return new JsonResponse([
                'new_episode' => true,
                'block' => $block,
                'count' => count($series['items']),
                'total' => $series['count']
            ]);
        }

        return new JsonResponse([
            'new_episode' => false,
            'block' => [],
            'count' => 0,
            'total' => 0
        ]);
    }

    #[Route('/layout', name: 'layout', methods: ['POST'])]
    public function layout(#[CurrentUser] User $user, Request $request): JsonResponse
    {
        $inputBag = $request->getPayload();
        $layout = $inputBag->get('layout');
        $this->tvTimeService->setTvTimeLayout($user, $layout);

        return new JsonResponse(['layout' => $layout]);
    }

    #[Route('/cast', name: 'cast', methods: ['POST'])]
    public function cast(Request $request): JsonResponse
    {
        $inputBag = $request->getPayload();
        $tmdbId = $inputBag->getInt('tmdbId');
        $seasonNumber = $inputBag->getInt('seasonNumber');
        $episodeNumber = $inputBag->getInt('episodeNumber');
        $episodeId = $inputBag->getInt('episodeId');
        $cast = $this->tvTimeService->getTvTimeEpisodeShowCast($tmdbId, $seasonNumber, $episodeNumber);

        $slugger = new AsciiSlugger();
        $profileUrl = $this->imageConfiguration->getUrl('profile_sizes', 2);
        $cast = array_map(function ($cast) use ($slugger, $profileUrl) {
            $cast['profile_path'] = $cast['profile_path'] ? $profileUrl . $cast['profile_path'] : null; // w185
            $cast['preferred_name'] = null;
            $cast['slug'] = $slugger->slug($cast['name'])->lower()->toString();
            if ($cast['slug'] == '') {
                $cast['slug'] = 'person-' . $cast['id'];
            }
            return $cast;
        }, $cast);
        $view = ($this->renderView)('_blocks/tv_time/_cast.html.twig', ['cast' => $cast, 'episodeId' => $episodeId]);
        return new JsonResponse(['block' => $view]);
    }

    #[Route('/week', name: 'week', methods: ['POST'])]
    public function week(#[CurrentUser] User $user, Request $request): JsonResponse
    {
        $inputBag = $request->getPayload();
        $week = $inputBag->getInt('week');
        $this->tvTimeService->setTvTimeWeek($user, $week);

        return new JsonResponse(['week' => $week]);
    }

    #[Route('/tab', name: 'tab', methods: ['POST'])]
    public function tab(#[CurrentUser] User $user, Request $request): JsonResponse
    {
        $inputBag = $request->getPayload();
        $tabIndex = $inputBag->get('tabIndex');
        $this->tvTimeService->setTvTimeTab($user, $tabIndex);

        return new JsonResponse(['tabIndex' => $tabIndex]);
    }

    #[Route('/posters', name: 'posters')]
    public function posters(#[CurrentUser] User $user, Request $request): JsonResponse
    {
        $aspectRatio = floatval($request->query->get('aspectRatio'));
        // Source imposée (renouvellement du lot) ou tirée au sort (séries / films)
        $source = $request->query->get('source');
        if (!in_array($source, ['series', 'movies'], true)) {
            $source = time() % 2 ? 'series' : 'movies';
        }
        $locale = $request->getLocale();
        $userId = $user->getId();

        $type = $aspectRatio > 1 ? 'backdrop' : 'poster';
        $imageUrl = $aspectRatio > 1
            ? $this->imageConfiguration->getUrl('backdrop_sizes', 3)
            : $this->imageConfiguration->getUrl('poster_sizes', 5);

        $totals = [
            'series' => $this->seriesRepository->countSeriesImages($userId, $type),
            'movies' => $this->movieRepository->countMoviesImages($userId, $type),
        ];
        $other = $source === 'series' ? 'movies' : 'series';
        // Source demandée vide : on se rabat sur l'autre
        if (!$totals[$source] && $totals[$other]) {
            [$source, $other] = [$other, $source];
        }
        $total = $totals[$source];
        // Taille du lot selon le stock : un tiers des images disponibles, entre 10 et 50,
        // afin que le renouvellement (stock > 2 × lot) profite au plus grand nombre
        $limit = min(50, max(10, intdiv($total, 3)));

        if ($source === 'series') {
            $arr = $this->seriesRepository->getSeriesImages($userId, $type, $limit, $locale);
            $this->imageService->saveImages($arr, $type, $imageUrl);
        } else {
            $arr = $this->movieRepository->getMoviesImages($userId, $type, $limit, $locale);
            $this->imageService->saveImages($arr, $type, $imageUrl, '/movies/');
        }

        return new JsonResponse([
            'images' => $arr,
            'range' => $arr ? $this->getRange($arr) : '',
            'source' => $source,
            // Lot suivant : l'autre source si elle a des images, sinon la même
            'nextSource' => $totals[$other] ? $other : $source,
            // Renouvellement utile : l'autre source a des images, ou la source courante
            // a assez d'images pour qu'un nouveau tirage apporte de la nouveauté
            'renewable' => $totals[$other] > 0 || $total > 2 * $limit,
        ]);
    }

    private function getRange(array $arr): string
    {
        $min = min(array_column($arr, 'year'));
        $max = max(array_column($arr, 'year'));
        return $min != $max ? $min . ' - ' . $max : $min;
    }
}