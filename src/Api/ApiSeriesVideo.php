<?php

namespace App\Api;

use App\Entity\SeriesVideo;
use App\Entity\User;
use App\Repository\SeriesRepository;
use App\Repository\SeriesVideoRepository;
use App\Service\SeriesService;
use App\Service\VideoService;
use Closure;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\DependencyInjection\Attribute\AutowireMethodOf;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/series/video', name: 'api_series_video_')]
readonly class ApiSeriesVideo
{
    public function __construct(
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure               $renderView,
        private SeriesRepository      $seriesRepository,
        private SeriesService         $seriesService,
        private SeriesVideoRepository $seriesVideoRepository,
        private VideoService          $videoService,
    )
    {
    }

    #[Route('/add', name: 'add', methods: ['POST'])]
    public function add(#[CurrentUser] User $user, Request $request): JsonResponse
    {
        $inputBag = $request->getPayload();
        $seriesId = $inputBag->getInt('seriesId');
        $title = $inputBag->get('title');
        $newLink = $inputBag->get('link');
        $link = $this->videoService->parseLink($newLink);

        $series = $this->seriesRepository->find($seriesId);
        if (!$series) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Series not found'
            ], 404);
        }
        $seriesVideo = new SeriesVideo($series, $title, $link);
        $this->seriesVideoRepository->save($seriesVideo, true);

        $seriesArr['videos'] = $this->seriesService->getSeriesVideoList($series);
        $seriesArr['videoListFolded'] = $this->seriesService->isVideoListFolded(count($seriesArr['videos']), $user);
        $formView = $this->seriesService->handleSerieVideoForm($request, $series);

        $videoSection = ($this->renderView)('_blocks/series/_videos.html.twig', [
            'series' => $seriesArr,
            'form' => $formView
        ]);

        return new JsonResponse([
            'success' => true,
            'message' => 'Video added',
            'block' => $videoSection,
        ]);
    }

}