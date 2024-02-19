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
use Symfony\Component\String\Slugger\AsciiSlugger;

class HomeController extends AbstractController
{
    public function __construct(
        private readonly ImageConfiguration $imageConfiguration,
//        private readonly SeriesController   $seriesController,
        private readonly SeriesRepository   $seriesRepository,
        private readonly TMDBService        $tmdbService,
    )
    {
    }

    #[Route('/', name: 'app_home')]
    public function index(Request $request): Response
    {
        // Dernières séries ajoutées
        /** @var Series[] $series */
        $series = $this->seriesRepository->getLastAddedSeries();
        $config = json_decode($this->tmdbService->imageConfiguration(), true);

        $s = array_map(function ($serie) use ($config) {
            $s['poster_path'] = $this->imageConfiguration->getCompleteUrl($serie->getPosterPath(), 'poster_sizes', 5); // w780
//            $s['backdrop_path'] = $this->imageConfiguration->getCompleteUrl($serie->getBackdropPath(), 'backdrop_sizes', 3); // original
            return $s;
        }, $series);
//        dump($series, $s, $config['images']);

        $slugger = new AsciiSlugger();
        // Séries Netflix
        $filterString = "&page=1&sort_by=first_air_date.desc&page=1&language=fr&timezone=Europe/Paris&watch_region=FR&include_adult=false&with_watch_providers=8&with_watch_monetization_types=flatrate";
        $filterName = "Netflix";
        $filteredSeries = json_decode($this->tmdbService->getFilterTv($filterString), true)['results'];
        $filteredSeries = array_map(function ($tv) use($slugger) {
            $tv['tmdb'] = true;
            $tv['poster_path'] = $tv['poster_path'] ? $this->imageConfiguration->getCompleteUrl($tv['poster_path'], 'poster_sizes', 5) : null; // w780
            $tv['slug'] = strtolower($slugger->slug($tv['name']));
            return $tv;
        } , $filteredSeries);
//        dump(['filteredSeries' => $filteredSeries]);

        return $this->render('home/index.html.twig', [
            'series' => $series,
            'filteredSeries' => $filteredSeries,
            'filterName' => $filterName,
            'config' => $config['images'],
        ]);
    }
}
