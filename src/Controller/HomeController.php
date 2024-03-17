<?php

namespace App\Controller;

use App\Entity\Series;
use App\Entity\User;
use App\Entity\UserSeries;
use App\Repository\SeriesRepository;
use App\Repository\UserEpisodeRepository;
use App\Repository\UserSeriesRepository;
use App\Service\DateService;
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
        private readonly DateService           $dateService,
        private readonly ImageConfiguration    $imageConfiguration,
        private readonly SeriesController      $seriesController,
        private readonly UserEpisodeRepository $userEpisodeRepository,
        private readonly UserSeriesRepository  $userSeriesRepository,
        private readonly TMDBService           $tmdbService,
    )
    {
    }

    #[Route('/', name: 'app_home_without_locale')]
    public function indexWithoutLocale(Request $request): Response
    {
        return $this->redirectToRoute('app_home', ['_locale' => $request->getLocale()]);
    }

    #[Route('/{_locale}/', name: 'app_home', requirements: ['_locale' => 'fr|en|de|es'])]
    public function index(Request $request): Response
    {
        /* @var User $user */
        $user = $this->getUser();
        $language = $user?->getPreferredLanguage() ?? "fr" . "-" . $user?->getCountry() ?? "FR";
        $country = $user?->getCountry() ?? "FR";
        $timezone = $user?->getTimezone() ?? "Europe/Paris";

        if ($user) {
            // Dernières séries ajoutées
            /** @var UserSeries[] $series */
//            $series = $this->userSeriesRepository->getLastAddedSeries($user);
            $userSeries = $this->userSeriesRepository->getUserSeries($user, $user->getPreferredLanguage() ?? $request->getLocale());
            $userSeriesCount = $this->userSeriesRepository->count(['user' => $user]);

            $userSeries = array_map(function ($series) {
//                $s = $serie->homeArray();
                $series['poster_path'] = $this->imageConfiguration->getCompleteUrl($series['poster_path'], 'poster_sizes', 5);
                return $series;
            }, $userSeries);

            // Dernières séries ajoutées
            $lastAddedSeries = array_map(function ($series) {
//                $s = $serie->homeArray();
                $series['poster_path'] = $series['posterPath'] ? $this->imageConfiguration->getCompleteUrl($series['posterPath'], 'poster_sizes', 5) : null;
                $series['localized_name'] = $series['localizedName'];
                $series['localized_slug'] = $series['localizedSlug'];
                return $series;
            }, $this->userEpisodeRepository->lastAddedSeries($user, $language, 1, 20));
            // Historique des séries vues
            $historySeries = array_map(function ($series) {
//                $s = $serie->homeArray();
                $series['posterPath'] = $series['posterPath'] ? $this->imageConfiguration->getCompleteUrl($series['posterPath'], 'poster_sizes', 5) : null;
                return $series;
            }, $this->userEpisodeRepository->historySeries($user, $language, 1, 20));
            // Historique des épisodes vus
            $historyEpisode = array_map(function ($series) {
//                $s = $serie->homeArray();
                $series['posterPath'] = $series['posterPath'] ? $this->imageConfiguration->getCompleteUrl($series['posterPath'], 'poster_sizes', 5) : null;
                $series['providerLogoPath'] = $series['providerLogoPath'] ? $this->imageConfiguration->getCompleteUrl($series['providerLogoPath'], 'logo_sizes', 2) : null;
                return $series;
            }, $this->userEpisodeRepository->historyEpisode($user, $language, 1, 20));
            } else {
            $userSeries = [];
            $lastAddedSeries = [];
            $historyEpisode = [];
            $historySeries = [];
        }
        dump(['last added series' => $lastAddedSeries,]);

        /*
         * Watch providers
         */
        // Get the value of the cookie "mytvtime.2.provider"
        $cookieProvider = $_COOKIE['mytvtime_2_provider'] ?? 8;

        $provider = $request->query->get('provider', $cookieProvider);
        $watchProviders = json_decode($this->tmdbService->getTvWatchProviderList($language, $country), true);
        $watchProviders = $watchProviders['results'];
        $watchProviders = array_map(function ($watchProvider) {
            $watchProvider['id'] = $watchProvider['provider_id'];
            $watchProvider['name'] = $watchProvider['provider_name'];
            $watchProvider['logoPath'] = $watchProvider['logo_path'] ? $this->imageConfiguration->getCompleteUrl($watchProvider['logo_path'], 'logo_sizes', 2) : null;
            return $watchProvider;
        }, $watchProviders);

        $slugger = new AsciiSlugger();
        $filterString = "&page=1&sort_by=first_air_date.desc&with_watch_providers=" . $provider . "&with_watch_monetization_types=flatrate&language=fr&timezone=Europe/Paris&watch_region=FR&include_adult=false";
        $filterName = "Netflix";
        $filteredSeries = $this->getSelection($filterString, $slugger);
//        dump(['filteredSeries' => $filteredSeries]);

        $page = rand(1, 3);

        $startDate = date('Y-m-d', strtotime('-1 year'));
        $endDate = date('Y-m-d', strtotime('+6 month'));

        // providers: 8|35|43|119|234|236|337|344|345|350|381
        // 8: Netflix
        // 35: Rakuten TV
        // 43: Starz
        // 119: Amazon Prime Video
        // 234: Arte
        // 236: France TV
        // 337: Disney Plus
        // 344: Rakuten Viki
        // 345: Canal+ Séries
        // 350: Apple TV Plus
        // 381: Canal Plus
        // type: possible values are: [0 Documentary, 1 News, 2 Miniseries, 3 Reality, 4 Scripted, 5 Talk Show, 6 Video], can be a comma (AND) or pipe (OR) separated query
        $filterString = "&sort_by=first_air_date.desc&page=" . $page . "with_type=0|2|4&language=" . $language . "&timezone=" . $timezone . "&watch_region=" . $country . "&include_adult=false&first_air_date.gte=" . $startDate . "&first_air_date.lte=" . $endDate . "&with_watch_monetization_types=flatrate&with_watch_providers=8|35|43|119|234|236|337|344|345|350|381";
        $seriesSelection = $this->getSelection($filterString, $slugger, $country, $timezone, $language);
        // array_filter pour retirer les séries sans poster & array_values() pour ré-indexer le tableau
        $seriesSelection = array_values(array_filter($seriesSelection, function ($tv) {
            return $tv['poster_path'];
        }));

//        dump(['filterString' => $filterString, 'seriesSelection' => $seriesSelection]);

        return $this->render('home/index.html.twig', [
            'highlightedSeries' => $seriesSelection,
            'userSeries' => $userSeries,
            'lastAddedSeries' => $lastAddedSeries,
            'historyEpisode' => $historyEpisode,
            'historySeries' => $historySeries,
            'userSeriesCount' => $userSeriesCount ?? 0,
            'watchProviders' => $watchProviders,
            'provider' => $provider,
            'filteredSeries' => $filteredSeries,
            'filterName' => $filterName,
        ]);
    }

    public function getSelection(string $filterString, AsciiSlugger $slugger, ?string $country = null, ?string $timezone = 'Europe/Paris', ?string $preferredLanguage = 'fr'): array
    {
        $seriesSelection = json_decode($this->tmdbService->getFilterTv($filterString), true)['results'];

        return array_map(function ($tv) use ($slugger, $country, $timezone, $preferredLanguage) {

            $tv = json_decode($this->tmdbService->getTv($tv['id'], $preferredLanguage, ['videos', 'watch/providers']), true);

            $tv['tmdb'] = true;
            $this->seriesController->saveImage("posters", $tv['poster_path'], $this->imageConfiguration->getUrl('poster_sizes', 5));
            $tv['poster_path'] = $tv['poster_path'] ? '/series/posters' . $tv['poster_path'] : null; // w780
            $tv['slug'] = strtolower($slugger->slug($tv['name']));

            if ($country) {
                $wpArr = $tv['watch/providers'];//json_decode($this->tmdbService->getTvwatchProviders($tv['id']), true);
                $tv['watch_providers'] = $wpArr['results'][$country] ?? [];
                $tv['watch_providers'] = $tv['watch_providers']['flatrate'] ?? [];
                $tv['watch_providers'] = array_map(function ($wp) {
                    $wp['logo_path'] = $wp['logo_path'] ? $this->imageConfiguration->getCompleteUrl($wp['logo_path'], 'logo_sizes', 2) : null;
                    return $wp;
                }, $tv['watch_providers']);
            } else
                $tv['watch_providers'] = [];
            return [
                'date' => $this->dateService->newDateImmutable($tv['first_air_date'], $timezone)->format('d/m/Y'),
                'id' => $tv['id'],
                'name' => $tv['name'],
                'overview' => $tv['overview'],
                'poster_path' => $tv['poster_path'],
                'slug' => $tv['slug'],
                'tmdb' => true,
                'watch_providers' => $tv['watch_providers'],
                'year' => $tv['first_air_date'] ? substr($tv['first_air_date'], 0, 4) : '',
                'videos' => $tv['videos']['results'] ?? '',
            ];
        }, $seriesSelection);
    }
}
