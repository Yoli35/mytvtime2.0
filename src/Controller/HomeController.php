<?php

namespace App\Controller;

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
        private readonly SeriesRepository $seriesRepository,
        private readonly TMDBService $tmdbService,
    )
    {
    }

    #[Route('/', name: 'app_home')]
    public function index(Request $request): Response
    {
        $series = $this->seriesRepository->getLastAddedSeries();

        $config = json_decode($this->tmdbService->imageConfiguration(), true);
        dump($series);

        return $this->render('home/index.html.twig', [
            'series' => $series,
            'config' => $config['images'],
        ]);
    }
}
