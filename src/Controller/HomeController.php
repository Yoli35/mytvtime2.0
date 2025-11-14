<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\UserSeries;
use App\Repository\UserEpisodeRepository;
use App\Repository\UserMovieRepository;
use App\Repository\UserSeriesRepository;
use App\Repository\WatchProviderRepository;
use App\Service\DateService;
use App\Service\ImageConfiguration;
use App\Service\ImageService;
use App\Service\TMDBService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Contracts\Translation\TranslatorInterface;

/** @method User|null getUser() */
#[Route('/', name: 'app_home_')]
class HomeController extends AbstractController
{
    public function __construct(
        private readonly DateService             $dateService,
        private readonly ImageConfiguration      $imageConfiguration,
        private readonly ImageService            $imageService,
        private readonly SeriesController        $seriesController,
        private readonly UserEpisodeRepository   $userEpisodeRepository,
        private readonly UserMovieRepository     $userMovieRepository,
        private readonly UserSeriesRepository    $userSeriesRepository,
        private readonly TMDBService             $tmdbService,
        private readonly TranslatorInterface     $translator,
        private readonly WatchProviderRepository $watchProviderRepository,
    )
    {
    }

    #[Route('/', name: 'without_locale')]
    public function indexWithoutLocale(Request $request): Response
    {
        return $this->redirectToRoute('app_home_index', ['_locale' => $request->getLocale()]);
    }

    #[Route('/home/{_locale}/', name: 'index', requirements: ['_locale' => 'fr|en|ko'])]
    public function index(Request $request): Response
    {
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
                $series['poster_path'] = $series['poster_path'] ? $this->imageConfiguration->getCompleteUrl($series['poster_path'], 'poster_sizes', 5) : null;
                return $series;
            }, $userSeries);

            // Episodes du jour
            $arr = $this->userEpisodeRepository->episodesOfTheDay($user, $language, false);
            $uniqueEpisodes = [];
            foreach ($arr as $ue) {
                $uniqueEpisodes[$ue['episode_id']] = $ue;
            }
            $logoUrl = $this->imageConfiguration->getUrl('logo_sizes', 2);
            $episodesOfTheDay = array_map(function ($series) use ($logoUrl) {
                $series['poster_path'] = $series['poster_path'] ? '/series/posters' . $series['poster_path'] : null;
                $series['provider_logo_path'] = $this->getProviderLogoFullPath($series['provider_logo_path'], $logoUrl);
                if ($series['provider_logo_path']) {
                    $series['watch_providers'][] = ['provider_name' => $series['provider_name'], 'logo_path' => $series['provider_logo_path']];
                }
                $series['upToDate'] = $series['watched_aired_episode_count'] == $series['aired_episode_count'];
                $series['remainingEpisodes'] = $series['aired_episode_count'] - $series['watched_aired_episode_count'];
                $series['released'] = true;
                return $series;
            }, $uniqueEpisodes);
            // Épisodes à voir parmi les séries commencées
            $episodesToWatch = array_map(function ($series) {
                $series['posterPath'] = $series['posterPath'] ? '/series/posters' . $series['posterPath'] : null;
//                $series['posterPath'] = $series['posterPath'] ? $this->imageConfiguration->getCompleteUrl($series['posterPath'], 'poster_sizes', 5) : null;
                $series['released'] = true;
                return $series;
            }, $this->userEpisodeRepository->episodesToWatch($user, $language));
            // Dernières séries ajoutées
            $lastAddedSeries = array_map(function ($series) {
//                $s = $serie->homeArray();
                $series['poster_path'] = $series['posterPath'] ? '/series/posters' . $series['posterPath'] : null;
//                $series['poster_path'] = $series['posterPath'] ? $this->imageConfiguration->getCompleteUrl($series['posterPath'], 'poster_sizes', 5) : null;
                $series['localized_name'] = $series['localizedName'];
                $series['localized_slug'] = $series['localizedSlug'];
                return $series;
            }, $this->userEpisodeRepository->lastAddedSeries($user, $language, 1, 50));
            // Historique des séries vues
            $historySeries = array_map(function ($series) {
                $series['posterPath'] = $series['posterPath'] ? '/series/posters' . $series['posterPath'] : null;
//                $series['posterPath'] = $series['posterPath'] ? $this->imageConfiguration->getCompleteUrl($series['posterPath'], 'poster_sizes', 5) : null;
                $series['upToDate'] = $series['watched_aired_episode_count'] == $series['aired_episode_count'];
                $series['remainingEpisodes'] = $series['aired_episode_count'] - $series['watched_aired_episode_count'];
                $series['released'] = true;
                return $series;
            }, $this->userEpisodeRepository->historySeries($user, $language, 1, 20));
            // Historique des épisodes vus pendant les 2 semaines passées
            $cookieDayCount = $_COOKIE['mytvtime_2_day_count'] ?? 7;
            $dayCount = $request->query->get('daycount', $cookieDayCount);
            $historyEpisode = $this->seriesController->getEpisodeHistory($user, $dayCount, $language);

            $statusArray = $this->userSeriesRepository->getUserSeriesStatus($user);

            $lastViewedMovies = $this->userMovieRepository->lastViewedMovies($user->getId());
        } else {
            $userSeries = [];
            $episodesOfTheDay = [];
            $episodesToWatch = [];
            $lastAddedSeries = [];
            $historyEpisode = [];
            $lastViewedMovies = [];
            $dayCount = 0;
            $historySeries = [];
            $statusArray = [];
        }

        /*
         * Watch providers
         */
        // Get the value of the cookie "mytvtime.2.provider"
        $cookieProvider = $_COOKIE['mytvtime_2_provider'] ?? 8;
        $provider = $request->query->get('provider', $cookieProvider);

        $watchProviders = json_decode($this->tmdbService->getTvWatchProviderList($language, $country), true);
        $watchProviders = $watchProviders['results'];
        if (count($watchProviders) === 0) {
            $watchProviders = $this->watchProviderRepository->getWatchProviderList($country);
        }
        $logoUrl = $this->imageConfiguration->getUrl('logo_sizes', 2);
        $watchProviders = array_map(function ($watchProvider) use ($logoUrl) {
            $watchProvider['logoPath'] = $this->getProviderLogoFullPath($watchProvider['logo_path'], $logoUrl);
            $watchProvider['id'] = $watchProvider['provider_id'];
            $watchProvider['name'] = $watchProvider['provider_name'];
            return $watchProvider;
        }, $watchProviders);

        $slugger = new AsciiSlugger();

        $filteredSeries = $this->getProviderSeries($provider, $slugger, $country, $timezone, $language);
        $seriesSelection = $this->getSeriesSelection($slugger, $country, $timezone, $language, true);
        $movieSelection = $this->getMovieSelection($slugger, $country, $timezone, $language, true);

        $movieId = $movieSelection[rand(0, count($movieSelection) - 1)]['id'];
        $movie = json_decode($this->tmdbService->getMovie($movieId, $language, ['watch/providers', 'videos']), true);
        $videoList = [];
        $movieVideos = ['title' => '', 'firstVideo' => '', 'videoList' => ''];
        if (count($movie['videos']['results'])) {
            $movieVideos['title'] = $movie['title'];
            $movieVideos['firstVideo'] = $movie['videos']['results'][0];
            $videos = array_filter($movie['videos']['results'], function ($video) {
                return $video['site'] === 'YouTube';
            });
            if (count($videos)) {
                foreach ($videos as $video) {
                    $videoList[] = $video['key'];
                }
            }
            $videoList = implode(',', $videoList);
            $movieVideos['videoList'] = $videoList;
        }

        return $this->render('home/index.html.twig', [
            'highlightedSeries' => $seriesSelection,
            'highlightedMovies' => $movieSelection,
            'statusArray' => $statusArray,
            'userSeries' => $userSeries,
            'episodesOfTheDay' => $episodesOfTheDay,
            'episodesToWatch' => $episodesToWatch,
            'lastAddedSeries' => $lastAddedSeries,
            'historyEpisode' => $historyEpisode,
            'lastViewedMovies' => $lastViewedMovies,
            'dayCount' => $dayCount,
            'historySeries' => $historySeries,
            'userSeriesCount' => $userSeriesCount ?? 0,
            'watchProviders' => $watchProviders,
            'provider' => $provider,
            'filteredSeries' => $filteredSeries,
            'movieVideos' => $movieVideos,
        ]);
    }

    #[Route('/load-new-series', name: 'load_new_series')]
    public function loadNewSeries(): Response
    {
        $user = $this->getUser();
        $country = $user?->getCountry() ?? "FR";
        $timezone = $user?->getTimezone() ?? "Europe/Paris";
        $language = $user?->getPreferredLanguage() ?? "fr";
        $forceProvider = false;
        $tryCount = 0;
        do {
            $seriesSelection = $this->getSeriesSelection(new AsciiSlugger(), $country, $timezone, $language, $forceProvider);
            $tryCount++;
            if ($tryCount > 10) {
                $forceProvider = true;
            }
        } while (count($seriesSelection) < 2);

        return $this->json([
            'status' => 'success',
            'series' => $seriesSelection,
        ]);
    }

    #[Route('/load-provider-series', name: 'load_provider_series', methods: ['POST'])]
    public function loadProviderSeries(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);
        $user = $this->getUser();
        $country = $user?->getCountry() ?? "FR";
        $timezone = $user?->getTimezone() ?? "Europe/Paris";
        $language = $user?->getPreferredLanguage() ?? "fr";
        $provider = $data['provider'] ?? 8;

        if (!is_numeric($provider)) {
            return $this->json([
                'status' => 'error',
                'message' => 'Invalid provider',
            ]);
        }
        $seriesSelection = $this->getProviderSeries($provider, new AsciiSlugger(), $country, $timezone, $language);

        return $this->json([
            'status' => 'success',
            'wrapperContent' => $this->render('_blocks/home/_provider_series.html.twig', [
                'filteredSeries' => $seriesSelection,
            ])->getContent(),
        ]);
    }

    #[Route('/load-episode-history', name: 'load_episode_history', methods: ['POST'])]
    public function loadEpisodeHistory(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);
        $user = $this->getUser();
        $language = $user?->getPreferredLanguage() ?? "fr";
        $dayCount = $data['count'] ?? 8;

        if (!is_numeric($dayCount)) {
            return $this->json([
                'status' => 'error',
                'message' => 'Invalid provider',
            ]);
        }
        $historyEpisode = $this->seriesController->getEpisodeHistory($user, $dayCount, $language);
        $count = count($historyEpisode);

        if ($dayCount == 1) {
            $h2 = $this->translator->trans('Episodes watched in the last %dayCount% hours (%count%)', ['%dayCount%' => $dayCount * 24, '%count%' => $count]);
        } else {
            $h2 = $this->translator->trans('Episodes watched in the last %dayCount% days (%count%)', ['%dayCount%' => $dayCount, '%count%' => $count]);
        }
        return $this->json([
            'status' => 'success',
            'h2Text' => $h2,
            'wrapperContent' => $this->render('_blocks/home/_episode_history.html.twig', [
                'historyEpisode' => $historyEpisode,
            ])->getContent(),
        ]);
    }

    public function getSeriesSelection(AsciiSlugger $slugger, ?string $country = null, ?string $timezone = 'Europe/Paris', ?string $language = 'fr', $forceProvider = null, $forceNoPoster = false): array
    {
        $page = rand(1, 5);

        $startDate = date('Y-m-d', strtotime('-1 year'));
        $endDate = date('Y-m-d', strtotime('+6 month'));

        if ($forceProvider) {
            $selectedProviders = "8|337|119|344|350";
        } else {
            // providers: 8|35|43|119|234|236|337|344|345|350|381
            // 8: Netflix           // 35: Rakuten TV        // 43: Starz            // 119: Amazon Prime Video
            // 234: Arte            // 236: France TV        // 337: Disney Plus     // 344: Rakuten Viki
            // 345: Canal+ Séries   // 350: Apple TV Plus    // 381: Canal Plus
            $providers = [8, 35, 43, 119, 234, 236, 337, 344, 345, 350, 381];
            $count = count($providers);
            $providerCountToAdd = rand(2, $count - 1);
            $selectedProviders = [];
            for ($i = 0; $i < $providerCountToAdd; $i++) {
                do {
                    $index = rand(0, $count - 1);
                    $providerToAdd = $providers[$index];
                } while (in_array($providerToAdd, $selectedProviders));
                $selectedProviders[] = $providerToAdd;
                $providers = array_values(array_diff($providers, $selectedProviders));
                $count = count($providers);
            }
            $selectedProviders = implode('|', $selectedProviders);
        }
        // type: possible values are: [0 Documentary, 1 News, 2 Miniseries, 3 Reality, 4 Scripted, 5 Talk Show, 6 Video],
        // can be a comma (AND) or pipe (OR) separated query
        $filterString = "&sort_by=first_air_date.desc&page=$page&with_type=0|2|4&language=$language"
            . "&timezone=$timezone&watch_region=$country&include_adult=false"
            . "&first_air_date.gte=$startDate&first_air_date.lte=$endDate"
            . "&with_watch_monetization_types=flatrate&with_watch_providers=$selectedProviders";

        if ($forceNoPoster) {
            return $this->getAPISelection($filterString, $slugger, $timezone, $language);
        }
        $seriesSelection = $this->getSelection('tv', $filterString, $slugger, $country, $timezone, $language);
        // array_filter pour retirer les séries sans poster & array_values() pour ré-indexer le tableau
        return array_values(array_filter($seriesSelection, function ($tv) {
            return $tv['poster_path'];
        }));
    }

    public function getMovieSelection(AsciiSlugger $slugger, ?string $country = null, ?string $timezone = 'Europe/Paris', ?string $language = 'fr', $forceProvider = null): array
    {
        $page = rand(1, 5);

        $startDate = date('Y-m-d', strtotime('-1 year'));
        $endDate = date('Y-m-d', strtotime('+6 month'));

        if ($forceProvider) {
            $selectedProviders = "8|119|337|344|350";
        } else {
            // providers: 8|35|43|119|234|236|337|344|345|350|381
            // 8: Netflix           // 35: Rakuten TV        // 43: Starz            // 119: Amazon Prime Video
            // 234: Arte            // 236: France TV        // 337: Disney Plus     // 344: Rakuten Viki
            // 345: Canal+ Séries   // 350: Apple TV Plus    // 381: Canal Plus
            $providers = [8, 35, 43, 119, 234, 236, 337, 344, 345, 350, 381];
            $count = count($providers);
            $providerCountToAdd = rand(2, $count - 1);
            $selectedProviders = [];
            for ($i = 0; $i < $providerCountToAdd; $i++) {
                do {
                    $index = rand(0, $count - 1);
                    $providerToAdd = $providers[$index];
                } while (in_array($providerToAdd, $selectedProviders));
                $selectedProviders[] = $providerToAdd;
                $providers = array_values(array_diff($providers, $selectedProviders));
                $count = count($providers);
            }
            $selectedProviders = implode('|', $selectedProviders);
        }
        $filterString = "&sort_by=primary_release_date.desc&page=$page&language=$language"
            . "&watch_region=$country&include_adult=false"
            . "&release_date.gte=$startDate&release_date.lte=$endDate"
            . "&with_watch_monetization_types=flatrate&with_watch_providers=$selectedProviders";
        $movieSelection = $this->getSelection('movie', $filterString, $slugger, $country, $timezone, $language);
        // array_filter pour retirer les séries sans poster & array_values() pour ré-indexer le tableau
        return array_values(array_filter($movieSelection, function ($tv) {
            return $tv['poster_path'];
        }));
    }

    public function getProviderSeries(int $providerId, AsciiSlugger $slugger, ?string $country = null, ?string $timezone = 'Europe/Paris', ?string $preferredLanguage = 'fr'): array
    {
        $filterString = "&page=1&sort_by=first_air_date.desc&with_watch_providers=" . $providerId . "&with_watch_monetization_types=flatrate&language=fr&timezone=Europe/Paris&watch_region=FR&include_adult=false";
        return $this->getSelection('tv', $filterString, $slugger, $country, $timezone, $preferredLanguage);
    }

    public function getSelection(string $media, string $filterString, AsciiSlugger $slugger, ?string $country = null, ?string $timezone = 'Europe/Paris', ?string $preferredLanguage = 'fr'): array
    {
        $root = $this->getParameter('kernel.project_dir') . '/public';
        if ($media === 'movie') {
            $mediaSelection = json_decode($this->tmdbService->getFilterMovie($filterString), true)['results'];
            $name = 'title';
            $date = 'release_date';
        } else {
            $mediaSelection = json_decode($this->tmdbService->getFilterTv($filterString), true)['results'];
            $name = 'name';
            $date = 'first_air_date';
        }

        return array_map(function ($tv) use ($slugger, $root, $media, $name, $date, $country, $timezone, $preferredLanguage) {

            $tv['tmdb'] = true;
            $this->imageService->saveImage("posters", $tv['poster_path'], $this->imageConfiguration->getUrl('poster_sizes', 5), $media === 'movie' ? '/movies/' : '/series/');
            if ($tv['poster_path']) {
                $localPath = ($media === 'movie' ? '/movies/' : '/series/') . 'posters' . $tv['poster_path'];
                if (file_exists($root . $localPath)) {
                    $tv['poster_path'] = $localPath;
                } else {
                    $tv['poster_path'] = $this->imageConfiguration->getUrl('poster_sizes', 5) . $tv['poster_path'];
                }
            } else {
                $tv['poster_path'] = null;
            }
            $tv['slug'] = strtolower($slugger->slug($tv[$media === 'tv' ? 'name' : 'title']));

            return [
                'date' => $this->dateService->newDateImmutable($tv[$date], $timezone)->format('d/m/Y'),
                'id' => $tv['id'],
                $name => $tv[$name],
                'overview' => $tv['overview'],
                'poster_path' => $tv['poster_path'],
                'slug' => $tv['slug'],
                'status' => $tv['status'] ?? 'no status',
                'tmdb' => true,
                'watch_providers' => [],
            ];
        }, $mediaSelection);
    }

    public function getAPISelection(string $filterString, AsciiSlugger $slugger, ?string $timezone = 'Europe/Paris', ?string $preferredLanguage = 'fr'): array
    {
        $list = json_decode($this->tmdbService->getFilterTv($filterString), true)['results'] ?? [];

        $tvs = [];
        foreach ($list as $item) {
            $tv = $item;//json_decode($this->tmdbService->getTv($item['id'], $preferredLanguage, ['watch/providers']), true);

//            $this->imageService->saveImage("posters", $tv['poster_path'], $this->imageConfiguration->getUrl('poster_sizes', 5));

            $tvs[] = [
                'date' => $this->dateService->newDateImmutable($tv['first_air_date'], $timezone)->format('d/m/Y'),
                'id' => $tv['id'],
                'name' => $tv['name'],
                'overview' => $tv['overview'],
                'poster_path' => $tv['poster_path'],
                'slug' => strtolower($slugger->slug($tv['name'])),
                'status' => $tv['status'] ?? 'no status',
//                'tmdb' => true,
                'year' => $tv['first_air_date'] ? substr($tv['first_air_date'], 0, 4) : '',
                'videos' => $tv['videos']['results'] ?? '',

                'backdrop_path' => $tv['backdrop_path'] ?? null,
                'created_by' => $tv['created_by'] ?? null,
                'genres' => $tv['genres'] ?? null,
                'homepage' => $tv['homepage'] ?? null,
                'in_production' => $tv['in_production'] ?? null,
                'languages' => $tv['languages'] ?? null,
                'last_air_date' => $tv['last_air_date'] ?? null,
                'last_episode_to_air' => $tv['last_episode_to_air'] ?? null,
                'next_episode_to_air' => $tv['next_episode_to_air'] ?? null,
                'networks' => $tv['networks'] ?? null,
                'number_of_episodes' => $tv['number_of_episodes'] ?? null,
                'number_of_seasons' => $tv['number_of_seasons'] ?? null,
                'origin_country' => $tv['origin_country'] ?? null,
                'original_language' => $tv['original_language'] ?? null,
                'original_name' => $tv['original_name'] ?? null,
                'popularity' => $tv['popularity'] ?? null,
                'production_companies' => $tv['production_companies'] ?? null,
                'production_countries' => $tv['production_countries'] ?? null,
                'seasons' => $tv['seasons'] ?? null,
                'spoken_languages' => $tv['spoken_languages'] ?? null,
                'tagline' => $tv['tagline'] ?? null,
                'type' => $tv['type'] ?? null,
                'vote_average' => $tv['vote_average'] ?? null,
                'vote_count' => $tv['vote_count'] ?? null,
                'watch_providers' => $tv['watch/providers'] ?? [],
            ];
        }
        return $tvs;
    }

    public function getProviderLogoFullPath(?string $path, string $tmdbUrl): ?string
    {
        if (!$path) return null;
        if (str_starts_with($path, '/')) {
            return $tmdbUrl . $path;
        }
        return '/images/providers' . substr($path, 1);
    }

//    public function getAdditionalInfos
}
