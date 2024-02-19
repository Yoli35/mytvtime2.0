<?php

namespace App\Controller;

use App\Entity\Series;
use App\Repository\SeriesRepository;
use App\Service\ImageConfiguration;
use App\Service\TMDBService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    public function __construct(
        private readonly ImageConfiguration $imageConfiguration,
        private readonly SeriesRepository $seriesRepository,
        private readonly TMDBService $tmdbService,
    )
    {
    }

    #[Route('/', name: 'app_home')]
    public function index(Request $request): Response
    {
        /** @var Series[] $series */
        $series = $this->seriesRepository->getLastAddedSeries();
        $config = json_decode($this->tmdbService->imageConfiguration(), true);

        $s = array_map(function ($serie) use ($config) {
            $s['poster_path'] = $this->imageConfiguration->getCompleteUrl($serie->getPosterPath(), 'poster_sizes', 5); // w780
            $s['backdrop_path'] = $this->imageConfiguration->getCompleteUrl($serie->getBackdropPath(), 'backdrop_sizes', 3); // original
            return $s;
        }, $series);
        dump($series, $s, $config['images']);

        return $this->render('home/index.html.twig', [
            'series' => $series,
            'config' => $config['images'],
        ]);
    }
}
