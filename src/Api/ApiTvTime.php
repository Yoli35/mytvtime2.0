<?php

namespace App\Api;

use App\Entity\User;
use App\Service\ImageConfiguration;
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
        private Closure              $renderView,
        private ImageConfiguration   $imageConfiguration,
        private TvTimeService        $tvTimeService,
    )
    {
    }

    #[Route('/check', name: 'check', methods: ['POST'])]
    public function check(#[CurrentUser] User $user, Request $request): JsonResponse
    {
        $inputBag = $request->getPayload();
        $sort = $inputBag->get('sort');

        $view = 'No data yet ;p';
        $noVoteView = null;
        $locale = $user->getPreferredLanguage() ?? $request->getLocale();

        if ($sort != null) {
            $this->tvTimeService->setTvTimeSort($user, intval($sort));
        }
        $data = $this->tvTimeService->getData($user, $locale);

        if ($data['tab'] == 0) {
            $noVoteView = ($this->renderView)('_blocks/tv_time/_card_series_vote.html.twig', ['seriesArr' => $data['noVoteArr']]);
            $view = ($this->renderView)('_blocks/tv_time/_wrapper_series.html.twig', [
                'seriesAvailable' => $data['series']['available'],
                'specialEpisodes' => $data['series']['specialEpisodes'],
                'specials' => $data['specials'],
                'week' => $data['week'],
                'seriesUpToDate' => $data['series']['upToDate'],
                'upToDateInAWhile' => $data['series']['upToDateInAWhile'],
                'upToDateInAWhileCount' => $data['series']['upToDateInAWhileCount'],
                'watchLinks' => $data['series']['watchLinks'],
                'list' => $data['list'],
                'loadCount' => $data['loadCount'],
            ]);
        }

        if ($data['tab'] == 1) {
            $view = ($this->renderView)('_blocks/tv_time/_wrapper_movies.html.twig', [
                'moviesToSee' => $data['movies']['toSee'],
                'moviesSeen' => $data['movies']['seen'],
                'moviesToSeeCount' => $data['movies']['toSeeCount'],
                'moviesSeenCount' => $data['movies']['seenCount'],
                'list' => $data['list'],
                'sub' => $data['sub'],
                'loadCount' => $data['loadCount'],
            ]);
        }

        return new JsonResponse([
            'new_episode' => true,
            'view' => $view,
            'noVoteView' => $noVoteView,
            'data' => $data,
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
        $cast = $this->tvTimeService->setTvTimeShowCast($tmdbId, $seasonNumber);

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
        dump($cast);
        $view = ($this->renderView)('_blocks/tv_time/_cast.html.twig', ['cast' => $cast]);
        dump($view);
        return new JsonResponse(['block' => $view]);
    }

    #[Route('/specials', name: 'specials', methods: ['POST'])]
    public function specials(#[CurrentUser] User $user, Request $request): JsonResponse
    {
        $inputBag = $request->getPayload();
        $specials = $inputBag->getInt('specials');
        $this->tvTimeService->setTvTimeSpecials($user, $specials);

        return new JsonResponse(['specials' => $specials]);
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
}