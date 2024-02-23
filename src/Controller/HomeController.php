<?php

namespace App\Controller;

use App\Entity\Series;
use App\Entity\User;
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
        /* @var User $user */
        $user = $this->getUser();

        // Dernières séries ajoutées
        /** @var Series[] $series */
        $series = $this->seriesRepository->getLastAddedSeries();
        $config = json_decode($this->tmdbService->imageConfiguration(), true);

        /*
         * Dernières séries ajoutées
         */
        $s = array_map(function ($serie) use ($config) {
            $s['poster_path'] = $this->imageConfiguration->getCompleteUrl($serie->getPosterPath(), 'poster_sizes', 5); // w780
            return $s;
        }, $series);

        /*
         * Watch providers
         */
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
        $filterString = "&page=1&sort_by=first_air_date.desc&with_watch_providers=".$provider."&with_watch_monetization_types=flatrate&language=fr&timezone=Europe/Paris&watch_region=FR&include_adult=false";
        $filterName = "Netflix";
        $filteredSeries = $this->getSelection($filterString, $slugger);
//        dump(['filteredSeries' => $filteredSeries]);

        /*
         * Sélection de séries sorties il y a moins d'un an en fonction des préférences de l'utilisateur, ou pas
         */
        $timezone = $user?->getTimezone() ?? "Europe/Paris";
        $watchRegion = $user?->getCountry() ?? "FR";
        $preferredLanguage = $user?->getPreferredLanguage() ?? "fr";

        $startDate = date('Y-m-d', strtotime('-1 year'));
        $endDate = date('Y-m-d', strtotime('+6 month'));

        $filterString = "&sort_by=popularity.desc&page=1&language=".$preferredLanguage."&timezone=".$timezone."&watch_region=".$watchRegion."&include_adult=false&first_air_date.gte=".$startDate."&first_air_date.lte=".$endDate."&with_watch_monetization_types=flatrate";
        $seriesSelection = $this->getSelection($filterString, $slugger, $watchRegion);

//        dump(['filterString'=>$filterString, 'seriesSelection' => $seriesSelection]);

        return $this->render('home/index.html.twig', [
            'highlightedSeries' => $seriesSelection,
            'series' => $series,
            'watchProviders' => $watchProviders,
            'provider' => $provider,
            'filteredSeries' => $filteredSeries,
            'filterName' => $filterName,
            'config' => $config['images'],
        ]);
    }

    public function getSelection(string $filterString, AsciiSlugger $slugger, ?string $country = null): array
    {
        $seriesSelection = json_decode($this->tmdbService->getFilterTv($filterString), true)['results'];
        return array_map(function ($tv) use ($slugger, $country) {
            $tv['tmdb'] = true;
            $tv['poster_path'] = $tv['poster_path'] ? $this->imageConfiguration->getCompleteUrl($tv['poster_path'], 'poster_sizes', 5) : null; // w780
            $tv['slug'] = strtolower($slugger->slug($tv['name']));

            if ($country) {
                $wpArr = json_decode($this->tmdbService->getTvwatchProviders($tv['id']), true);
                $tv['watch_providers'] = $wpArr['results'][$country] ?? [];
                $tv['watch_providers'] = $tv['watch_providers']['flatrate'] ?? [];
                $tv['watch_providers'] = array_map(function ($wp) {
                    $wp['logo_path'] = $wp['logo_path'] ? $this->imageConfiguration->getCompleteUrl($wp['logo_path'], 'logo_sizes', 2) : null;
                    return $wp;
                }, $tv['watch_providers']);
            }
            else
                $tv['watch_providers'] =[];
            return [
                'id' => $tv['id'],
                'name' => $tv['name'],
                'poster_path' => $tv['poster_path'],
                'slug' => $tv['slug'],
                'watch_providers' => $tv['watch_providers'],
                'overview' => $tv['overview'],
                'tmdb' => true,
            ];
        }, $seriesSelection);
    }
}
