<?php

namespace App\Api;

use App\Entity\User;
use App\Repository\UserSeriesRepository;
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
        private UserSeriesRepository $userSeriesRepository
    )
    {
    }

    #[Route('/check', name: 'check', methods: ['POST'])]
    public function check(#[CurrentUser] User $user, Request $request): JsonResponse
    {
        $inputBag = $request->getPayload();
        $lastId = $inputBag->get('lastId');
        $lastWatchedSeriesId = $this->userSeriesRepository->getLastWatchedSeries($user);

        if ($lastId == $lastWatchedSeriesId) {
            return new JsonResponse(['new_episode' => false]);
        }
        $locale = $user->getPreferredLanguage() ?? $request->getLocale();
        $userId = $user->getId();
        $seriesAvailable = $this->userSeriesRepository->findAvailableSeries($userId, $locale);
        $seriesUpToDate = $this->userSeriesRepository->findUpToDateSeries($userId, $locale);

        $view = ($this->renderView)('_blocks/series/_card_tv_time_wrapper.html.twig', ['seriesAvailable' => $seriesAvailable, 'seriesUpToDate' => $seriesUpToDate]);

        return new JsonResponse([
            'new_episode' => true,
            'view' => $view,
            'lastWatchedEpisodeId' => $lastWatchedSeriesId
        ]);
    }
}