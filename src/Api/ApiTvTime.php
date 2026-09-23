<?php

namespace App\Api;

use App\Entity\User;
use App\Repository\UserSeriesRepository;
use App\Service\ImageConfiguration;
use App\Service\ProviderService;
use App\Service\TvTimeService;
use Closure;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\DependencyInjection\Attribute\AutowireMethodOf;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/tv/time', name: 'api_tv_time_')]
readonly class ApiTvTime
{
    public function __construct(
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure              $renderView,
        private TvTimeService        $tvTimeService,
    )
    {
    }

    #[Route('/check', name: 'check', methods: ['POST'])]
    public function check(#[CurrentUser] User $user, Request $request): JsonResponse
    {
        $view = 'No data yet ;p';
        $noVoteView = '';
        $locale = $user->getPreferredLanguage() ?? $request->getLocale();

        $data = $this->tvTimeService->getData($user, $locale);

        if ($data['tab'] == 0) {
            $noVoteView = ($this->renderView)('_blocks/tv_time/_card_series_vote.html.twig', ['seriesArr' => $data['noVoteArr']]);
            $view = ($this->renderView)('_blocks/tv_time/_wrapper_series.html.twig', [
                'seriesAvailable' => $data['series']['available'],
                'seriesUpToDate' => $data['series']['upToDate'],
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

    #[Route('/tab', name: 'tab', methods: ['POST'])]
    public function tab(#[CurrentUser] User $user, Request $request): JsonResponse
    {
        $inputBag = $request->getPayload();
        $tabIndex = $inputBag->get('tabIndex');
        $this->tvTimeService->setTvTimeTab($user, $tabIndex);

        return new JsonResponse(['tabIndex' => $tabIndex]);
    }
}