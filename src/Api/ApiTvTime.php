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
        private ImageConfiguration   $imageConfiguration,
        private ProviderService      $providerService,
        private TvTimeService        $tvTimeService,
        private UserSeriesRepository $userSeriesRepository
    )
    {
    }

    #[Route('/check', name: 'check', methods: ['POST'])]
    public function check(#[CurrentUser] User $user, Request $request): JsonResponse
    {
        /*$inputBag = $request->getPayload();
        $lastId = $inputBag->get('lastId');
        $lastWatchedSeriesId = $this->userSeriesRepository->getLastWatchedSeries($user);
        if ($lastId == $lastWatchedSeriesId) {
            return new JsonResponse(['new_episode' => false]);
        }*/

        $locale = $user->getPreferredLanguage() ?? $request->getLocale();
        /*$settings = $this->tvTimeService->getTvTimeData($user);

        $userId = $user->getId();
        $seriesAvailable = $this->userSeriesRepository->findAvailableSeries($userId, $locale);
        $watchLinks = $this->userSeriesRepository->availableSeriesWatchLinks(array_column($seriesAvailable, 'id'));
        $seriesUpToDate = $this->userSeriesRepository->findUpToDateSeries($userId, $locale);
        $providerUrl = $this->imageConfiguration->getUrl('logo_sizes', 3);
        $watchLinks = array_map(function($wp) use ($providerUrl)  {
            $wp['providerLogoPath'] = $this->providerService->getProviderLogoFullPath($wp['providerLogoPath'], $providerUrl);
            return $wp;
        }, array_merge($watchLinks, $this->userSeriesRepository->availableSeriesWatchLinks(array_column($seriesUpToDate, 'id'))));
        $seriesUpToDateIds = array_unique(array_column($seriesUpToDate, 'userEpisodeId'));
        $lastEpisodeWithNoVoteArr = $this->userSeriesRepository->findUpToDateSeriesWithNoVote($seriesUpToDateIds, $locale);*/
        $data = $this->tvTimeService->getData($user, $locale);
        $noVoteView = ($this->renderView)('_blocks/tv_time/_card_series_vote.html.twig', ['seriesArr' => $data['noVoteArr']]);

        $view = ($this->renderView)('_blocks/tv_time/_wrapper_series.html.twig', [
            'seriesAvailable' => $data['seriesAvailable'],
            'seriesUpToDate' => $data['seriesUpToDate'],
            'watchLinks' => $data['watchLinks'],
            'list' => $data['list'],
            'loadCount' => $data['count'],
        ]);

        return new JsonResponse([
            'new_episode' => true,
            'view' => $view,
            'noVoteView' => $noVoteView,
            /*'lastWatchedEpisodeId' => $lastWatchedSeriesId,*/
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