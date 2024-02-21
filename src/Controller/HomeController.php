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
            return $s;
        }, $series);

        // Get the value of the cookie "mytvtime.2.provider"
        if (isset($_COOKIE['mytvtime_2_provider']))
            $cookieProvider = $_COOKIE['mytvtime_2_provider'];
        else
            $cookieProvider = null;

        $provider = $request->query->get('provider', $cookieProvider ?? 8);
        $watchProviders = json_decode($this->tmdbService->getTvWatchProviderList('fr-FR', 'FR'), true);
        $watchProviders = $watchProviders['results'];
        $watchProviders = array_map(function ($watchProvider) {
            $watchProvider['id'] = $watchProvider['provider_id'];
            $watchProvider['name'] = $watchProvider['provider_name'];
            $watchProvider['logoPath'] = $watchProvider['logo_path'] ? $this->imageConfiguration->getCompleteUrl($watchProvider['logo_path'], 'logo_sizes', 2) : null;
            return $watchProvider;
        }, $watchProviders);

        $slugger = new AsciiSlugger();
        // Séries Netflix
        $filterString = "&page=1&sort_by=first_air_date.desc&with_watch_providers=".$provider."&with_watch_monetization_types=flatrate&language=fr&timezone=Europe/Paris&watch_region=FR&include_adult=false";
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
            'watchProviders' => $watchProviders,
            'provider' => $provider,
            'filteredSeries' => $filteredSeries,
            'filterName' => $filterName,
            'config' => $config['images'],
        ]);
    }
}
