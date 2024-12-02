<?php

namespace App\Controller;

use App\DTO\SeriesAdvancedSearchDTO;
use App\DTO\SeriesSearchDTO;
use App\Entity\EpisodeLocalizedOverview;
use App\Entity\EpisodeSubstituteName;
use App\Entity\FilmingLocation;
use App\Entity\FilmingLocationImage;
use App\Entity\SeasonLocalizedOverview;
use App\Entity\Series;
use App\Entity\SeriesAdditionalOverview;
use App\Entity\SeriesBroadcastSchedule;
use App\Entity\SeriesDayOffset;
use App\Entity\SeriesExternal;
use App\Entity\SeriesImage;
use App\Entity\SeriesLocalizedName;
use App\Entity\SeriesLocalizedOverview;
use App\Entity\Settings;
use App\Entity\User;
use App\Entity\UserEpisode;
use App\Entity\UserPinnedSeries;
use App\Entity\UserSeries;
use App\Form\AddBackdropForm;
use App\Form\SeriesAdvancedSearchType;
use App\Form\SeriesSearchType;
use App\Repository\DeviceRepository;
use App\Repository\EpisodeLocalizedOverviewRepository;
use App\Repository\EpisodeSubstituteNameRepository;
use App\Repository\FilmingLocationImageRepository;
use App\Repository\FilmingLocationRepository;
use App\Repository\KeywordRepository;
use App\Repository\NetworkRepository;
use App\Repository\ProviderRepository;
use App\Repository\SeasonLocalizedOverviewRepository;
use App\Repository\SeriesAdditionalOverviewRepository;
use App\Repository\SeriesBroadcastScheduleRepository;
use App\Repository\SeriesDayOffsetRepository;
use App\Repository\SeriesExternalRepository;
use App\Repository\SeriesImageRepository;
use App\Repository\SeriesLocalizedNameRepository;
use App\Repository\SeriesLocalizedOverviewRepository;
use App\Repository\SeriesRepository;
use App\Repository\SettingsRepository;
use App\Repository\SourceRepository;
use App\Repository\UserEpisodeRepository;
use App\Repository\UserPinnedSeriesRepository;
use App\Repository\UserSeriesRepository;
use App\Repository\WatchProviderRepository;
use App\Service\DateService;
use App\Service\DeeplTranslator;
use App\Service\ImageConfiguration;
use App\Service\KeywordService;
use App\Service\TMDBService;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use DeepL\DeepLException;
use Deepl\TextResult;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\DatePoint;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\Map\InfoWindow;
use Symfony\UX\Map\Map;
use Symfony\UX\Map\Marker;
use Symfony\UX\Map\Point;

#[Route('/{_locale}/series', name: 'app_series_', requirements: ['_locale' => 'fr|en|kr'])]
class SeriesController extends AbstractController
{
    public function __construct(
        private readonly ClockInterface                     $clock,
        private readonly DateService                        $dateService,
        private readonly DeviceRepository                   $deviceRepository,
        private readonly DeeplTranslator                    $deeplTranslator,
        private readonly EpisodeLocalizedOverviewRepository $episodeLocalizedOverviewRepository,
        private readonly EpisodeSubstituteNameRepository    $episodeSubstituteNameRepository,
        private readonly FilmingLocationImageRepository     $filmingLocationImageRepository,
        private readonly FilmingLocationRepository          $filmingLocationRepository,
        private readonly ImageConfiguration                 $imageConfiguration,
        private readonly KeywordRepository                  $keywordRepository,
        private readonly KeywordService                     $keywordService,
        private readonly NetworkRepository                  $networkRepository,
        private readonly ProviderRepository                 $providerRepository,
        private readonly SeasonLocalizedOverviewRepository  $seasonLocalizedOverviewRepository,
        private readonly SeriesAdditionalOverviewRepository $seriesAdditionalOverviewRepository,
        private readonly SeriesBroadcastScheduleRepository  $seriesBroadcastScheduleRepository,
        private readonly SeriesDayOffsetRepository          $seriesDayOffsetRepository,
        private readonly SeriesExternalRepository           $seriesExternalRepository,
        private readonly SeriesImageRepository              $seriesImageRepository,
        private readonly SeriesRepository                   $seriesRepository,
        private readonly SeriesLocalizedNameRepository      $seriesLocalizedNameRepository,
        private readonly SeriesLocalizedOverviewRepository  $seriesLocalizedOverviewRepository,
        private readonly SettingsRepository                 $settingsRepository,
        private readonly SourceRepository                   $sourceRepository,
        private readonly TMDBService                        $tmdbService,
        private readonly TranslatorInterface                $translator,
        private readonly UserEpisodeRepository              $userEpisodeRepository,
        private readonly UserPinnedSeriesRepository         $userPinnedSeriesRepository,
        private readonly UserSeriesRepository               $userSeriesRepository,
        private readonly WatchProviderRepository            $watchProviderRepository,
    )
    {
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/', name: 'index')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $country = $user->getCountry() ?? 'FR';
        $locale = $user->getPreferredLanguage() ?? 'fr';
        $language = $locale . '-' . $country;
        $timezone = $user->getTimezone() ?? 'Europe/Paris';

        $userProviderIds = array_map(function ($up) {
            return $up->getProviderId();
        }, $user->getProviders()->toArray());

        $now = $this->now();
        // Day of week with monday = 1 to sunday = 7
        $dayOfWeek = $now->format('N') ? $now->format('N') : 7;
        // Monday of the current week
        $monday = $now->modify('-' . ($dayOfWeek - 1) . ' days')->format('Y-m-d');
        // Sunday of the current week
        $sunday = $now->modify('+' . (7 - $dayOfWeek) . ' days')->format('Y-m-d');

        $searchString = "&air_date.gte=$monday&air_date.lte=$sunday&include_adult=false&include_null_first_air_dates=false&language=$language&sort_by=first_air_date.desc&timezone=$timezone&watch_region=$country&with_watch_providers=" . implode('|', $userProviderIds);

        $searchResult = json_decode($this->tmdbService->getFilterTv($searchString . "&page=1"), true);
        for ($i = 2; $i <= $searchResult['total_pages']; $i++) {
            $searchResult['results'] = array_merge($searchResult['results'], json_decode($this->tmdbService->getFilterTv($searchString . "&page=$i"), true)['results']);
        }
        $series = $this->getSearchResult($searchResult, new AsciiSlugger());
        $userSeriesTMDBIds = array_column($this->userSeriesRepository->userSeriesTMDBIds($user), 'id');
//        dump(['series' => $series, 'userSeriesTMDBIds' => $userSeriesTMDBIds]);

        // Historique des épisodes vus pendant les 2 semaines passées
        $episodeHistory = $this->getEpisodeHistory($user, 14, $country, $locale);

        $providerUrl = $this->imageConfiguration->getUrl('logo_sizes', 3);

        $AllEpisodesOfTheDay = array_map(function ($ue) use ($providerUrl) {
            $this->saveImage("posters", $ue['posterPath'], $this->imageConfiguration->getUrl('poster_sizes', 5));
            if ($ue['airAt']) {
                $time = explode(':', $ue['airAt']);
                $now = $this->now()->setTime($time[0], $time[1], $time[2]);
                $ue['airAt'] = $now->format('Y-m-d H:i:s');
            }
            if (!$ue['posterPath']) {
                $ue['poster_path'] = $this->getAlternatePosterPath($ue['id']);
            }
            return [
                'episode_of_the_day' => true,
                'id' => $ue['id'],
                'tmdbId' => $ue['tmdbId'],
                'date' => $ue['date'],
                'name' => $ue['name'],
                'slug' => $ue['slug'],
                'status' => $ue['status'],
                'released' => $ue['released'],
                'localized_name' => $ue['localizedName'],
                'localized_slug' => $ue['localizedSlug'],
                'poster_path' => $ue['posterPath'] ? '/series/posters' . $ue['posterPath'] : null,
                'progress' => $ue['progress'],
                'favorite' => $ue['favorite'],
                'episode_number' => $ue['episodeNumber'],
                'season_number' => $ue['seasonNumber'],
                'upToDate' => $ue['watched_aired_episode_count'] == $ue['aired_episode_count'],
                'remainingEpisodes' => $ue['aired_episode_count'] - $ue['watched_aired_episode_count'],
                'released_episode_count' => $ue['released_episode_count'],
                'watch_at' => $ue['watchAt'],
                'air_at' => $ue['airAt'],
//                'provider_logo_path' => $ue['providerLogoPath'],
//                'provider_name' => $ue['providerName'],
                'watch_providers' => $ue['providerId'] ? [['logo_path' => $providerUrl . $ue['providerLogoPath'], 'provider_name' => $ue['providerName']]] : [],
            ];
        }, $this->userEpisodeRepository->episodesOfTheDay($user, $country, $locale));
        $tmdbIds = array_column($AllEpisodesOfTheDay, 'tmdbId');
        $episodesOfTheDay = [];
        foreach ($AllEpisodesOfTheDay as $us) {
            if ($us['released_episode_count'] > 1) {
                $episodesOfTheDay[$us['date'] . '-' . $us['id']][] = $us;
            } else {
                $episodesOfTheDay[$us['date'] . '-' . $us['id']][0] = $us;
            }
        }
//        dump(['episodesOfTheDay' => $episodesOfTheDay]);

        $allEpisodesOfTheWeek = array_map(function ($us) use ($providerUrl) {
            $this->saveImage("posters", $us['poster_path'], $this->imageConfiguration->getUrl('poster_sizes', 5));
            if (!$us['poster_path']) {
                $us['poster_path'] = $this->getAlternatePosterPath($us['id']);
            }
            return [
                'series_of_the_week' => true,
                'episode_of_the_day' => true,
                'id' => $us['id'],
                'tmdb_id' => $us['tmdb_id'],
                'date' => $us['air_date'],
                'original_air_date' => $us['original_air_date'],
                'name' => $us['name'],
                'slug' => $us['slug'],
                'status' => $us['status'],
                'released' => $us['released'],
                'localized_name' => $us['localized_name'],
                'localized_slug' => $us['localized_slug'],
                'poster_path' => $us['poster_path'] ? '/series/posters' . $us['poster_path'] : null,
                'progress' => $us['progress'],
                'watch_at' => $us['watch_at'],
                'season_number' => $us['season_number'],
                'episode_number' => $us['episode_number'],
                'released_episode_count' => $us['released_episode_count'],
                'air_at' => $us['air_at'],
                'provider_logo_path' => $us['provider_logo_path'],
                'provider_name' => $us['provider_name'],
                'watch_providers' => $us['provider_id'] ? [['logo_path' => $providerUrl . $us['provider_logo_path'], 'provider_name' => $us['provider_name']]] : [],
            ];
        }, $this->userSeriesRepository->getUserSeriesOfTheNext7Days($user, $country, $locale));
        $tmdbIds = array_values(array_unique(array_merge($tmdbIds, array_column($allEpisodesOfTheWeek, 'tmdb_id'))));
        $seriesOfTheWeek = [];
        foreach ($allEpisodesOfTheWeek as $us) {
            if ($us['released_episode_count'] > 1) {
                $seriesOfTheWeek[$us['date'] . '-' . $us['id']][] = $us;
            } else {
                $seriesOfTheWeek[$us['date'] . '-' . $us['id']][0] = $us;
            }
        }

        $order = 'firstAirDate'; // TODO: ajouter un menu pour choisir l'ordre (firstAirDate, lastWatched, addedAt, ...)
        $seriesToStart = array_map(function ($s) {
            $this->saveImage("posters", $s['poster_path'], $this->imageConfiguration->getUrl('poster_sizes', 5));
            return $s;
        }, $this->userEpisodeRepository->seriesToStart($user, $locale, $order, 1, 20));
        $seriesToStartCount = $this->userEpisodeRepository->seriesToStartCount($user, $locale);

//        dump([
//            'episodesOfTheDay' => $episodesOfTheDay,
//            'seriesOfTheWeek' => $seriesOfTheWeek,
//            'episodeHistory' => $episodeHistory,
//            'seriesToStart' => $seriesToStart,
//            'seriesToStartCount' => $seriesToStartCount,
//            'seriesList' => $series,
//            'total_results' => $searchResult['total_results'] ?? -1,
//            'hier' => $this->now()->modify('-1 day')->format('Y-m-d'),
//        ]);

        return $this->render('series/index.html.twig', [
            'episodesOfTheDay' => $episodesOfTheDay,
            'seriesOfTheWeek' => $seriesOfTheWeek,
            'episodeHistory' => $episodeHistory,
            'seriesToStart' => $seriesToStart,
            'seriesToStartCount' => $seriesToStartCount,
            'seriesList' => $series,
            'userSeriesTMDBIds' => $userSeriesTMDBIds,
            'total_results' => $searchResult['total_results'] ?? -1,
            'tmdbIds' => $tmdbIds,
        ]);
    }

    #[Route('/to/start', name: 'to_start')]
    public function serieToStart(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $locale = $user->getPreferredLanguage() ?? $request->getLocale();

        $seriesToStart = array_map(function ($s) {
            $this->saveImage("posters", $s['poster_path'], $this->imageConfiguration->getUrl('poster_sizes', 5));
            return $s;
        }, $this->userEpisodeRepository->seriesToStart($user, $locale, 'addedAt', 1, -1));
        $tmdbIds = array_column($seriesToStart, 'tmdb_id');

        return $this->render('series/series-to-start.html.twig', [
            'seriesToStart' => $seriesToStart,
            'tmdbIds' => $tmdbIds,
        ]);
    }

    #[Route('/by/country/{country}', name: 'by_country', requirements: ['country' => '[A-Z]{2}'])]
    public function seriesByCountry(Request $request, string $country): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $locale = $user->getPreferredLanguage() ?? $request->getLocale();

        $settings = $this->settingsRepository->findOneBy(['user' => $user, 'name' => 'by country']);
        if (!$settings) {
            $settings = new Settings($user, 'by country', ['country' => $country]);
            $this->settingsRepository->save($settings, true);
        } else {
            $data = $settings->getData();
            $data['country'] = $country;
            $settings->setData($data);
            $this->settingsRepository->save($settings, true);
        }

        $usc = $this->userSeriesRepository->getUserSeriesCountries($user);
        $userSeriesCountries = [];
        foreach ($usc as $arr) {
            $arr = json_decode($arr['origin_country'], true);
            foreach ($arr as $originCountry) {
                $userSeriesCountries[] = $originCountry;
            }
        }
        $userSeriesCountries = array_unique($userSeriesCountries);
        sort($userSeriesCountries);

        $series = array_map(function ($s) {
            $this->saveImage("posters", $s['poster_path'], $this->imageConfiguration->getUrl('poster_sizes', 5));
            $s['upToDate'] = $s['watched_aired_episode_count'] == $s['aired_episode_count'];
            return $s;
        }, $this->userSeriesRepository->seriesByCountry($user, $country, $locale, 1, -1));
        // le tableau $series est trié par date de première diffusion décroissante, mais certaines séries n'ont pas de date de première diffusion.
        // Ces séries sans date de première diffusion doivent être placées en début de tableau.
        usort($series, function ($a, $b) {
            if ($a['final_air_date'] == $b['final_air_date']) return 0;
            if ($a['final_air_date'] == null) return -1;
            if ($b['final_air_date'] == null) return 1;
            return $a['final_air_date'] < $b['final_air_date'] ? 1 : -1;
        });


        $tmdbIds = array_column($series, 'tmdb_id');

        dump($series);

        return $this->render('series/series-by-country.html.twig', [
            'seriesByCountry' => $series,
            'userSeriesCountries' => $userSeriesCountries,
            'country' => $country,
            'tmdbIds' => $tmdbIds,
        ]);
    }

    #[Route('/search', name: 'search')]
    public function search(Request $request): Response
    {
        $series = [];
        $slugger = new AsciiSlugger();
        $simpleSeriesSearch = new SeriesSearchDTO($request->getLocale(), 1);
        $simpleForm = $this->createForm(SeriesSearchType::class, $simpleSeriesSearch);

        $simpleForm->handleRequest($request);
        if ($simpleForm->isSubmitted() && $simpleForm->isValid()) {
            $searchResult = $this->handleSearch($simpleSeriesSearch);
            if ($searchResult['total_results'] == 1) {
                return $this->getOneResult($searchResult['results'][0], $slugger);
            }
            $series = $this->getSearchResult($searchResult, $slugger);
        }

        return $this->render('series/search.html.twig', [
            'form' => $simpleForm->createView(),
            'title' => 'Search a series',
            'seriesList' => $series,
            'results' => [
                'total_results' => $searchResult['total_results'] ?? -1,
                'total_pages' => $searchResult['total_pages'] ?? 0,
                'page' => $searchResult['page'] ?? 0,
            ],
        ]);
    }

    #[Route('/search/all', name: 'search_all')]
    public function searchAll(Request $request): Response
    {
        $slugger = new AsciiSlugger();
        $simpleSeriesSearch = new SeriesSearchDTO($request->getLocale(), 1);
        $simpleSeriesSearch->setQuery($request->get('q'));
        $simpleForm = $this->createForm(SeriesSearchType::class, $simpleSeriesSearch);
        $searchResult = $this->handleSearch($simpleSeriesSearch);
        if ($searchResult['total_results'] == 1) {
            return $this->getOneResult($searchResult['results'][0], $slugger);
        }
        $series = $this->getSearchResult($searchResult, $slugger);

        return $this->render('series/search.html.twig', [
            'form' => $simpleForm->createView(),
            'title' => 'Search a series',
            'seriesList' => $series,
            'results' => [
                'total_results' => $searchResult['total_results'] ?? -1,
                'total_pages' => $searchResult['total_pages'] ?? 0,
                'page' => $searchResult['page'] ?? 0,
            ],
        ]);
    }

    public function handleSearch($simpleSeriesSearch): mixed
    {
        $query = $simpleSeriesSearch->getQuery();
        $language = $simpleSeriesSearch->getLanguage();
        $page = $simpleSeriesSearch->getPage();
        $firstAirDateYear = $simpleSeriesSearch->getFirstAirDateYear();

        $searchString = "&query=$query&include_adult=false&page=$page";
        if (strlen($firstAirDateYear)) $searchString .= "&first_air_date_year=$firstAirDateYear";
        if (strlen($language)) $searchString .= "&language=$language";

        return json_decode($this->tmdbService->searchTv($searchString), true);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/all', name: 'all', methods: ['GET', 'POST'])]
    public function all(Request $request): Response
    {
        /* @var User $user */
        $user = $this->getUser();
        $localisation = [
            'locale' => $user?->getPreferredLanguage() ?? $request->getLocale(),
            'country' => $user?->getCountry() ?? "FR",
            'language' => $user?->getPreferredLanguage() ?? $request->getLocale(),
            'timezone' => $user?->getTimezone() ?? "Europe/Paris"
        ];
        $filtersBoxSettings = $this->settingsRepository->findOneBy(['user' => $user, 'name' => 'series to end: filter box']);
        if (!$filtersBoxSettings) {
            $filtersBoxSettings = new Settings($user, 'series to end: filter box', ['open' => true]);
            $this->settingsRepository->save($filtersBoxSettings, true);
            $filterBoxOpen = true;
        } else {
            $filterBoxOpen = $filtersBoxSettings->getData()['open'];
        }
        $page = 1;
        $settings = $this->settingsRepository->findOneBy(['user' => $user, 'name' => 'series to end']);
        // Parameters count
        if (!count($request->query->all())) {
            if (!$settings) {
                $settings = new Settings($user, 'series to end', ['perPage' => 10, 'sort' => 'lastWatched', 'order' => 'DESC', 'startStatus' => 'series-started', 'endStatus' => 'series-not-watched', 'network' => 'all']);
                $this->settingsRepository->save($settings, true);
            }
        } else {
            // /fr/series/all?sort=episodeAirDate&order=DESC&startStatus=series-not-started&endStatus=series-not-watched&perPage=10
            $paramSort = $request->get('sort');
            $paramOrder = $request->get('order');
            $paramNetwork = $request->get('network');
            $paramStartStatus = $request->get('startStatus');
            $paramEndStatus = $request->get('endStatus');
            $paramPerPage = $request->get('perPage');
            $settings->setData([
                'perPage' => $paramPerPage,
                'sort' => $paramSort,
                'order' => $paramOrder,
                'network' => $paramNetwork,
                'startStatus' => $paramStartStatus,
                'endStatus' => $paramEndStatus,
            ]);
            $this->settingsRepository->save($settings, true);
            $page = $request->get('page') ?? 1;
        }
        $data = $settings->getData();
        $filters = [
            'page' => $page,
            'perPage' => $data['perPage'],
            'network' => $data['network'],
            'sort' => $data['sort'],
            'order' => $data['order'],
            'startStatus' => $data['startStatus'],
            'endStatus' => $data['endStatus'],
        ];
        $startStatus = $data['startStatus'];
        $endStatus = $data['endStatus'];

        $filterMeanings = [
            'name' => 'Name',
            'addedAt' => 'Date added',
            'firstAirDate' => 'First air date',
            'lastWatched' => 'Last series watched',
            'episodeAirDate' => 'Episode air date',
            'DESC' => 'Descending',
            'ASC' => 'Ascending',
        ];

        $progress = [];
        /*if ($startStatus === 'series-started') {
            $progress[] = 'us.progress > 0';
        } elseif ($startStatus === 'series-not-started') {
            $progress[] = 'us.progress = 0';
        }
        if ($endStatus === 'series-ended') {
            $progress[]= 'us.progress = 100';
        } elseif ($endStatus === 'series-not-ended') {
            $progress[] = 'us.progress < 100';
        }*/

        /** @var UserSeries[] $userSeries */
        $userSeries = $this->userSeriesRepository->getAllSeries(
            $user,
            $localisation,
            $filters,
            $progress);
        $userSeriesCount = $this->userSeriesRepository->countAllSeries(
            $user,
            $localisation,
            $filters,
            $progress);

        $userSeries = array_map(function ($series) {
            $series['poster_path'] = $series['poster_path'] ? $this->imageConfiguration->getCompleteUrl($series['poster_path'], 'poster_sizes', 5) : null;
            return $series;
        }, $userSeries);

        $userNetworks = $user->getNetworks();
        $networks = $this->networkRepository->findBy([], ['name' => 'ASC']);
        $nlpArr = $this->networkRepository->networkLogoPaths();
        $networkLogoPaths = ['all' => null];
        foreach ($nlpArr as $nlp) {
            if ($nlp['logo_path'])
                $networkLogoPaths[$nlp['id']] = $this->imageConfiguration->getCompleteUrl($nlp['logo_path'], 'logo_sizes', 3);
            else
                $networkLogoPaths[$nlp['id']] = null;
        }

//        dump([
//            'userSeries' => $userSeries,
//            'userSeriesCount' => $userSeriesCount,
//            'filters' => $filters,
//            'filterBoxOpen' => $filterBoxOpen,
//            'userNetworks' => $userNetworks,
//            'networks' => $networks,
//            'networkLogoPaths' => $networkLogoPaths,
//        ]);

        return $this->render('series/all.html.twig', [
            'userSeries' => $userSeries,
            'userSeriesCount' => $userSeriesCount,
            'pages' => ceil($userSeriesCount / $filters['perPage']),
            'filters' => $filters,
            'filterBoxOpen' => $filterBoxOpen,
            'filterMeanings' => $filterMeanings,
            'userNetworks' => $userNetworks,
            'networks' => $networks,
            'networkLogoPaths' => $networkLogoPaths,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/search/db', name: 'search_db')]
    public function searchDB(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $series = [];
        $simpleSeriesSearch = new SeriesSearchDTO($request->getLocale(), 1);
        $simpleForm = $this->createForm(SeriesSearchType::class, $simpleSeriesSearch);

        $simpleForm->handleRequest($request);
        if ($simpleForm->isSubmitted() && $simpleForm->isValid()) {
            $query = $simpleSeriesSearch->getQuery();
            $page = $simpleSeriesSearch->getPage();
            $firstAirDateYear = $simpleSeriesSearch->getFirstAirDateYear();

            $series = array_map(function ($s) {
                $s['poster_path'] = $s['poster_path'] ? $this->imageConfiguration->getUrl('poster_sizes', 5) . $s['poster_path'] : null;
                return $s;
            }, $this->seriesRepository->search($user, $query, $firstAirDateYear, $page));

            if (count($series) == 1) {
                return $this->redirectToRoute('app_series_show', [
                    'id' => $series[0]['id'],
                    'slug' => $series[0]['slug'],
                ]);
            }
        }

//        dump($series);

        return $this->render('series/search.html.twig', [
            'form' => $simpleForm->createView(),
            'title' => 'Search among your series',
            'seriesList' => $series,
            'results' => [
                'total_results' => $searchResult['total_results'] ?? -1,
                'total_pages' => $searchResult['total_pages'] ?? 0,
                'page' => $searchResult['page'] ?? 0,
            ],
        ]);
    }

    #[Route('/advanced/search', name: 'advanced_search')]
    public function advancedSearch(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $series = [];
        $slugger = new AsciiSlugger();
        $watchProviders = $this->getWatchProviders($user?->getCountry() ?? 'FR');
        $keywords = $this->getKeywords();

        $seriesSearch = new SeriesAdvancedSearchDTO($user?->getPreferredLanguage() ?? $request->getLocale(), $user?->getCountry() ?? 'FR', $user?->getTimezone() ?? 'Europe/Paris', 1);
        $seriesSearch->setWatchProviders($watchProviders['select']);
        $seriesSearch->setKeywords($keywords);
        $form = $this->createForm(SeriesAdvancedSearchType::class, $seriesSearch);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $searchString = $this->getSearchString($form->getData());
            $searchResult = json_decode($this->tmdbService->getFilterTv($searchString), true);

            if ($searchResult['total_results'] == 1) {
                return $this->getOneResult($searchResult['results'][0], $slugger);
            }
            $series = $this->getSearchResult($searchResult, $slugger);
        }
//        dump($series);

        return $this->render('series/search-advanced.html.twig', [
            'form' => $form->createView(),
            'seriesList' => $series,
            'results' => [
                'total_results' => $searchResult['total_results'] ?? -1,
                'total_pages' => $searchResult['total_pages'] ?? 0,
                'page' => $searchResult['page'] ?? 0,
            ],
        ]);
    }

    #[Route('/tmdb/{id}-{slug}', name: 'tmdb', requirements: ['id' => Requirement::DIGITS])]
    public function tmdb(Request $request, $id, $slug): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $series = $this->seriesRepository->findOneBy(['tmdbId' => $id]);
        $userSeries = ($user && $series) ? $this->userSeriesRepository->findOneBy(['user' => $user, 'series' => $series]) : null;
        $locale = $user ? $user->getPreferredLanguage() : $request->getLocale();

        if ($userSeries) {
            return $this->redirectToRoute('app_series_show', [
                'id' => $series->getId(),
                'slug' => $series->getSlug(),
            ], 301);
        }

        if ($series) {
            $series->setVisitNumber($series->getVisitNumber() + 1);
            $this->seriesRepository->save($series, true);
            $localizedName = $series->getLocalizedName($locale);
            $localizedOverview = $series->getLocalizedOverview($locale);
        } else {
            $localizedName = null;
            $localizedOverview = null;
        }
        $tv = json_decode($this->tmdbService->getTv($id, $request->getLocale(), ["images", "videos", "credits", "watch/providers", "content/ratings", "keywords", "similar"]), true);

//        dump($localizedName);
        $this->checkTmdbSlug($tv, $slug, $localizedName?->getSlug());

//        dump($tv['seasons']);
        if (!$localizedOverview && $tv['overview'] == "" && $locale != 'en') {
            $tvUS = json_decode($this->tmdbService->getTv($id, 'en-US', []), true);
            $tv['overview'] = $tvUS['overview'];
            foreach ($tv['seasons'] as $key => $season) {
                $seasonUs = $this->getSeason($tvUS['seasons'], $season['season_number']);
//                dump(['season' => $season, 'season us' => $seasonUs]);
                if ($season['overview'] == "" && $seasonUs)
                    $tv['seasons'][$key]['overview'] = $seasonUs['overview'];
                if (!key_exists('name', $season)) $season['name'] = "";
                if (!key_exists('name', $seasonUs)) $seasonUs['name'] = "";
                if ($season['name'] == "" || ($season['name'] == "Saison " . $season['season_number'] && $seasonUs['name'] != "Season " . $season['season_number']))
                    $tv['seasons'][$key]['name'] .= ' - ' . $seasonUs['name'];
            }
        }
//        dump($tv['seasons']);
        $this->saveImage("posters", $tv['poster_path'], $this->imageConfiguration->getUrl('poster_sizes', 5));
        $this->saveImage("backdrops", $tv['backdrop_path'], $this->imageConfiguration->getUrl('backdrop_sizes', 3));

        $tv['credits'] = $this->castAndCrew($tv);
        $tv['networks'] = $this->networks($tv);
        $tv['seasons'] = $this->seasonsPosterPath($tv['seasons']);
        $tv['watch/providers'] = $this->watchProviders($tv, 'FR');

//        dump($tv);
        return $this->render('series/tmdb.html.twig', [
            'tv' => $tv,
            'localizedName' => $localizedName,
            'localizedOverview' => $localizedOverview,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/list/{id}/{seriesId}', name: 'list', requirements: ['id' => Requirement::DIGITS, 'showId' => Requirement::DIGITS])]
    public function list(Request $request, int $id, int $seriesId): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $page = $request->get('page') ?? 1;
        $series = $this->seriesRepository->findOneBy(['id' => $seriesId]);
        $userSeriesTMDBIds = array_column($this->userSeriesRepository->userSeriesTMDBIds($user), 'id');
        $list = json_decode($this->tmdbService->getList($id, $page), true);

        $this->saveImage("backdrops", $list['backdrop_path'], $this->imageConfiguration->getUrl('backdrop_sizes', 3));
        $this->saveImage("posters", $list['poster_path'], $this->imageConfiguration->getUrl('poster_sizes', 5));

        $list['results'] = array_map(function ($item) {
            $slugger = new AsciiSlugger();
            $item['poster_path'] = $item['poster_path'] ? $this->imageConfiguration->getCompleteUrl($item['poster_path'], 'poster_sizes', 5) : null;
            $item['slug'] = $item['media_type'] == 'tv' ? $slugger->slug($item['name']) : $slugger->slug($item['title']);
            $item['tmdb'] = true;
            return $item;
        }, $list['results']);

        return $this->render('series/list.html.twig', [
            'list' => $list,
            'series' => $series,
            'ids' => $userSeriesTMDBIds,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/show/{id}-{slug}', name: 'show', requirements: ['id' => Requirement::DIGITS])]
    public function show(Request $request, Series $series, string $slug): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $dayOffset = $this->seriesDayOffsetRepository->findOneBy(['series' => $series, 'country' => $user->getCountry() ?? 'FR']);
        $dayOffset = $dayOffset ? $dayOffset->getOffset() : 0;

        $series->setVisitNumber($series->getVisitNumber() + 1);
        $this->seriesRepository->save($series, true);

        $addBackdropForm = $this->createForm(AddBackdropForm::class);
        $addBackdropForm->handleRequest($request);
        if ($addBackdropForm->isSubmitted() && $addBackdropForm->isValid()) {
            $data = $addBackdropForm->getData();
            $this->addBackdrop($series, $data['file']);
        }

        $this->checkSlug($series, $slug, $user->getPreferredLanguage() ?? $request->getLocale());
        // Get with fr-FR language to get the localized name
        $tv = json_decode($this->tmdbService->getTv($series->getTmdbId(), $request->getLocale(), ["images", "videos", "credits", "watch/providers", "keywords", "lists", "similar"]), true);
//        dump($tv);
        if ($tv) {
            if (!$tv['lists']['total_results']) {
                // Get with en-US language to get the lists
                $tvLists = json_decode($this->tmdbService->getTvLists($series->getTmdbId()), true);
                $tv['lists'] = $tvLists;
            }
            if ($tv['similar']['total_results'] == 0) {
                // Get with en-US language to get the similar series
                $similar = json_decode($this->tmdbService->getTvSimilar($series->getTmdbId()), true);
                $tv['similar'] = $similar;
            }
            $tv['similar']['results'] = array_map(function ($s) {
                $s['poster_path'] = $s['poster_path'] ? $this->imageConfiguration->getUrl('poster_sizes', 5) . $s['poster_path'] : null;
                $s['tmdb'] = true;
                $s['slug'] = (new AsciiSlugger())->slug($s['name']);
                return $s;
            }, $tv['similar']['results']);
//        dump($tv, $tvLists, $similar);

            $this->saveImage("posters", $tv['poster_path'], $this->imageConfiguration->getUrl('poster_sizes', 5));
            $this->saveImage("backdrops", $tv['backdrop_path'], $this->imageConfiguration->getUrl('backdrop_sizes', 3));
            list($series, $seriesBackdrops, $seriesLogos, $seriesPosters) = $this->updateSeries($series, $tv);
//            dump(['series posters' => $seriesPosters]);

            $tv['credits'] = $this->castAndCrew($tv);
            $tv['localized_name'] = $series->getLocalizedName($request->getLocale());
            $tv['localized_overviews'] = $series->getLocalizedOverviews($request->getLocale());
            $tv['additional_overviews'] = $series->getSeriesAdditionalLocaleOverviews($request->getLocale());
            $tv['sources'] = $this->sourceRepository->findBy([], ['name' => 'ASC']);
            $tv['networks'] = $this->networks($tv);
            $tv['overview'] = $this->localizedOverview($tv, $series, $request);
            $tv['seasons'] = $this->seasonsPosterPath($tv['seasons'], $dayOffset);
            $tv['watch/providers'] = $this->watchProviders($tv, $user->getCountry() ?? 'FR');
            $tv['missing_translations'] = $this->keywordService->keywordsTranslation($tv['keywords']['results'], $request->getLocale());
        } else {
            $series->setUpdates(['Series not found']);
            $seriesBackdrops = $seriesLogos = $seriesPosters = [];
        }
        $userSeries = $this->userSeriesRepository->findOneBy(['user' => $user, 'series' => $series]);
        if ($tv) {
            $userSeries = $this->updateUserSeries($userSeries, $tv);
            $tv['status_css'] = $this->statusCss($userSeries, $tv);
        }

        $providers = $this->getWatchProviders($user->getCountry() ?? 'FR');

        $schedules = $this->seriesSchedulesV2($user, $series, $tv, $dayOffset);
        $emptySchedule = $this->emptySchedule();
//        dump($series);
        $seriesArr = $series->toArray();
        $nead = $seriesArr['nextEpisodeAirDate'];
        $seriesArr['nextEpisodeAirDate'] = $nead ? $nead->modify($dayOffset . ' days') : null;
        $seriesArr['schedules'] = $schedules;
        $seriesArr['emptySchedule'] = $emptySchedule;
        $seriesArr['seriesInProgress'] = $this->userEpisodeRepository->isFullyReleased($userSeries);
        $seriesArr['images'] = [
            'backdrops' => $seriesBackdrops,
            'logos' => $seriesLogos,
            'posters' => $seriesPosters,
        ];

        $translations = [
            'Add to favorites' => $this->translator->trans('Add to favorites'),
            'Add' => $this->translator->trans('Add'),
            'Additional overviews' => $this->translator->trans('Additional overviews'),
            'After tomorrow' => $this->translator->trans('After tomorrow'),
            'Available' => $this->translator->trans('Available'),
            'Delete' => $this->translator->trans('Delete'),
            'Edit' => $this->translator->trans('Edit'),
            'Ended' => $this->translator->trans('Ended'),
            'Localized overviews' => $this->translator->trans('Localized overviews'),
            'Now' => $this->translator->trans('Now'),
            'Remove from favorites' => $this->translator->trans('Remove from favorites'),
            'Since' => $this->translator->trans('Since'),
            'That\'s all!' => $this->translator->trans('That\'s all!'),
            'This field is required' => $this->translator->trans('This field is required'),
            'To be continued' => $this->translator->trans('To be continued'),
            'Today' => $this->translator->trans('Today'),
            'Tomorrow' => $this->translator->trans('Tomorrow'),
            'Update' => $this->translator->trans('Update'),
            'Watch on' => $this->translator->trans('Watch on'),
            'available' => $this->translator->trans('available'),
            'day' => $this->translator->trans('day'),
            'days' => $this->translator->trans('days'),
            'since' => $this->translator->trans('since'),
        ];

        $locations = $this->getSeriesLocations($series, $user->getPreferredLanguage() ?? $request->getLocale());

//        $this->fixFilmingLocations();
//        dump([
//            'series' => $seriesArr,
//            'locations' => $locations['filmingLocations'],
//            'tv' => $tv,
//            'dayOffset' => $dayOffset,
//            'userSeries' => $userSeries,
//            'providers' => $providers,
//            'schedules' => $schedules,
//        ]);
        if ($tv) {
            $twig = "series/show.html.twig";
        } else {
            $twig = "series/show-not-found.html.twig";
        }
        return $this->render($twig, [
            'series' => $seriesArr,
            'tv' => $tv,
            'userSeries' => $userSeries,
            'providers' => $providers,
            'seriesLocations' => $locations,
            'externals' => $this->getExternals($series, $request->getLocale()),
            'translations' => $translations,
            'addBackdropForm' => $addBackdropForm->createView(),
        ]);
    }

    public function addBackdrop(Series $series, UploadedFile $backdropFile): bool
    {
        $source = $backdropFile->getPathname();
        $serverPath = '/public/series/backdrops/';
        $destination = $this->getParameter('kernel.project_dir') . $serverPath . $backdropFile->getClientOriginalName();
        if (copy($source, $destination)) {
            $seriesImage = new SeriesImage($series, "backdrop", '/' . $backdropFile->getClientOriginalName());
            $this->seriesImageRepository->save($seriesImage, true);
            $this->addFlash('success', 'The backdrop has been added.');
            return true;
        }
        return false;
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/add/{id}', name: 'add', requirements: ['id' => Requirement::DIGITS])]
    public function addUserSeries(int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $date = $this->now();

        $result = $this->addSeries($id, $date);
        $tv = $result['tv'];
        $series = $result['series'];
        $this->addSeriesToUser($user, $series, $tv, $date);

        return $this->redirectToRoute('app_series_show', [
            'id' => $series->getId(),
            'slug' => $series->getSlug(),
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/remove/{id}', name: 'remove', requirements: ['id' => Requirement::DIGITS])]
    public function removeUserSeries(UserSeries $userSeries): Response
    {
        $this->userSeriesRepository->remove($userSeries);

        return $this->json([
            'ok' => true,
        ]);
    }

    #[Route('/broadcast/delay/{id}', name: 'broadcast_delay', requirements: ['id' => Requirement::DIGITS])]
    public function broadcastDelay(Request $request, Series $series): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);
        $delay = $data['delay'];

        $seriesDayOffset = $this->seriesDayOffsetRepository->findOneBy(['series' => $series, 'country' => $user->getCountry() ?? 'FR']);

        if ($seriesDayOffset) {
            $seriesDayOffset->setOffset($delay);
        } else {
            $seriesDayOffset = new SeriesDayOffset($series, $delay, $user->getCountry() ?? 'FR');
        }
        $this->seriesDayOffsetRepository->save($seriesDayOffset);

        return $this->json([
            'ok' => true,
        ]);
    }

    #[Route('/schedules/save', name: 'schedule_save', methods: ['POST'])]
    public function schedulesSave(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);
        $id = $data['id'];
        $country = $data['country'];
        $date = $data['date'];
        $time = $data['time'];
        $override = $data['override'];
        $frequency = $data['frequency'];
        $provider = $data['provider'];
        $seriesId = $data['seriesId'];
        $dayArr = array_map(function ($d) {
            return intval($d);
        }, $data['days']);

        dump($data);

        $hour = (int)substr($time, 0, 2);
        $minute = (int)substr($time, 3, 2);

        if ($id === 0) {
            $seriesBroadcastSchedule = new SeriesBroadcastSchedule();
            $seriesBroadcastSchedule->setSeries($this->seriesRepository->findOneBy(['id' => $seriesId]));
        } else {
            $seriesBroadcastSchedule = $this->seriesBroadcastScheduleRepository->findOneBy(['id' => $id]);
        }

        $seriesBroadcastSchedule->setFirstAirDate($this->dateService->newDateImmutable($date, "Europe/Paris", true));
        $seriesBroadcastSchedule->setAirAt((new DateTimeImmutable())->setTime($hour, $minute));
        $seriesBroadcastSchedule->setFrequency($frequency);
        $seriesBroadcastSchedule->setOverride($override);
        $seriesBroadcastSchedule->setCountry($country);
        $seriesBroadcastSchedule->setDaysOfWeek($dayArr);
        $seriesBroadcastSchedule->setProviderId($provider);
        $this->seriesBroadcastScheduleRepository->save($seriesBroadcastSchedule);

        return $this->json([
            'ok' => true,
            'success' => true,
        ]);
    }

    #[Route('/pinned/{id}', name: 'pinned', requirements: ['id' => Requirement::DIGITS])]
    public function pinnedSeries(Request $request, UserSeries $userSeries): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);
        $newPinnedValue = $data['newStatus'];

        if ($newPinnedValue) {
            $userPinnedSeries = new UserPinnedSeries($user, $userSeries);
            $this->userPinnedSeriesRepository->add($userPinnedSeries, true);
        } else {
            $userPinnedSeries = $this->userPinnedSeriesRepository->findOneBy(['user' => $user, 'userSeries' => $userSeries]);
            $this->userPinnedSeriesRepository->remove($userPinnedSeries, true);
        }

        return $this->json([
            'ok' => true,
        ]);
    }

    #[Route('/favorite/{id}', name: 'favorite', requirements: ['id' => Requirement::DIGITS])]
    public function favoriteSeries(Request $request, UserSeries $userSeries): Response
    {
        $data = json_decode($request->getContent(), true);
        $newFavoriteValue = $data['favorite'];
        $userSeries->setFavorite($newFavoriteValue);
        $this->userSeriesRepository->save($userSeries, true);

        return $this->json([
            'ok' => true,
        ]);
    }

    #[Route('/rating/{id}', name: 'rating', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function ratingSeries(Request $request, UserSeries $userSeries): Response
    {
        $data = json_decode($request->getContent(), true);
        $rating = $data['rating'];
        $userSeries->setRating($rating);
        $this->userSeriesRepository->save($userSeries, true);

        return $this->json([
            'ok' => true,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/show/season/{id}-{slug}/{seasonNumber}', name: 'season', requirements: ['id' => Requirement::DIGITS, 'seasonNumber' => Requirement::DIGITS])]
    public function showSeason(Request $request, Series $series, int $seasonNumber, string $slug): Response
    {
        /** @var User $user */
        $user = $this->getUser();
//        $series = $this->seriesRepository->findOneBy(['id' => $id]);
        $seriesDayOffset = $this->seriesDayOffsetRepository->findOneBy(['series' => $series, 'country' => $user->getCountry() ?? 'FR']);
        $dayOffset = $seriesDayOffset ? $seriesDayOffset->getOffset() : 0;

        $userSeries = $this->userSeriesRepository->findOneBy(['user' => $user, 'series' => $series]);
        $this->checkSlug($series, $slug, $user->getPreferredLanguage() ?? $request->getLocale());

        $seriesImages = $series->getSeriesImages()->toArray();

        $season = json_decode($this->tmdbService->getTvSeason($series->getTmdbId(), $seasonNumber, $request->getLocale(), ['credits', 'watch/providers']), true);
        if ($season['poster_path']) {
            if (!$this->inImages($season['poster_path'], $seriesImages)) {
                $seriesImage = new SeriesImage($series, "poster", $season['poster_path']);
                $this->seriesImageRepository->save($seriesImage, true);
                $this->saveImage("posters", $season['poster_path'], $this->imageConfiguration->getUrl('poster_sizes', 5));
            }
        } else {
            $season['poster_path'] = $series->getPosterPath();
        }

        $season['deepl'] = null;//$this->seasonLocalizedOverview($series, $season, $seasonNumber, $request);
        $season['episodes'] = $this->seasonEpisodes($season, $userSeries, $dayOffset);
        $season['credits'] = $this->castAndCrew($season);
        $season['watch/providers'] = $this->watchProviders($season, $user->getCountry() ?? 'FR');
        if ($season['overview'] == "") {
            $season['overview'] = $series->getOverview();
            $season['series_overview'] = true;
        } else {
            $season['series_overview'] = false;
        }
        $season['localized_name'] = $series->getLocalizedName($request->getLocale());
        $season['localized_overviews'] = $series->getLocalizedOverviews($request->getLocale());
        $season['additional_overviews'] = $series->getSeriesAdditionalLocaleOverviews($request->getLocale());

        $providers = $this->getWatchProviders($user->getCountry() ?? 'FR');
        $devices = $this->deviceRepository->deviceArray();

        dump([
            'series' => $series,
//            'season' => $season,
//            'userSeries' => $userSeries,
//            'providers' => $providers,
//            'devices' => $devices,
        ]);
        return $this->render('series/season.html.twig', [
            'series' => $series,
            'season' => $season,
            'providers' => $providers,
            'devices' => $devices,
            'externals' => $this->getExternals($series, $request->getLocale()),
        ]);
    }

    #[Route('/add/localized/name/{id}', name: 'add_localized_name', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function addLocalizedName(Request $request, int $id): Response
    {
        $data = json_decode($request->getContent(), true);
        $name = $data['name'];
        $series = $this->seriesRepository->findOneBy(['id' => $id]);
        $slugger = new AsciiSlugger();

        $localizedName = $series->getLocalizedName($request->getLocale());
        if ($localizedName) {
            $localizedName->setName($name);
            $localizedName->setSlug($slugger->slug($name));
        } else {
            $slug = $slugger->slug($name)->lower()->toString();
            $localizedName = new SeriesLocalizedName($series, $name, $slug, $request->getLocale());
        }
        $this->seriesLocalizedNameRepository->save($localizedName, true);

        return $this->json([
            'ok' => true,
        ]);
    }

    #[Route('/delete/localized/name/{id}', name: 'delete_localized_name', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function deleteLocalizedName(Request $request, int $id): Response
    {
        $data = json_decode($request->getContent(), true);
        $locale = $data['locale'];
        $series = $this->seriesRepository->findOneBy(['id' => $id]);
        $slugger = new AsciiSlugger();

        $localizedName = $series->getLocalizedName($locale);
        if ($localizedName) {
            $series->removeSeriesLocalizedName($localizedName);
            $series->setSlug($slugger->slug($series->getName())->lower()->toString());
            $this->seriesRepository->save($series, true);
            $this->seriesLocalizedNameRepository->remove($localizedName);
        }

        return $this->json([
            'ok' => true,
        ]);
    }

//    #[IsGranted('ROLE_USER')]
    #[Route('/add/edit/overview/{id}', name: 'add_overview', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function addOverview(Request $request, int $id): Response
    {
        $serie = $this->seriesRepository->findOneBy(['id' => $id]);
        $data = json_decode($request->getContent(), true);
//        dump($data);
        $overviewId = $data['overviewId'];
        $overviewId = $overviewId == "" ? null : intval($overviewId);
        $overviewType = $data['type'];
        $overview = $data['overview'];
        $locale = $data['locale'];
        $source = null;

        if ($overviewType == "additional") {
            $sourceId = $data['source'];
            $source = $this->sourceRepository->findOneBy(['id' => $sourceId]);
            if ($overviewId) {
                $seriesAdditionalOverview = $this->seriesAdditionalOverviewRepository->findOneBy(['id' => $overviewId]);
                $seriesAdditionalOverview->setOverview($overview);
                $seriesAdditionalOverview->setSource($source);
                $this->seriesAdditionalOverviewRepository->save($seriesAdditionalOverview, true);
            } else {
                $seriesAdditionalOverview = new SeriesAdditionalOverview($serie, $overview, $locale, $source);
                $this->seriesAdditionalOverviewRepository->save($seriesAdditionalOverview, true);
                $overviewId = $seriesAdditionalOverview->getId();
            }
        }
        if ($overviewType == "localized") {
            if ($overviewId) {
                $seriesLocalizedOverview = $this->seriesLocalizedOverviewRepository->findOneBy(['id' => $overviewId]);
                $seriesLocalizedOverview->setOverview($overview);
                $this->seriesLocalizedOverviewRepository->save($seriesLocalizedOverview, true);
            } else {
                $seriesLocalizedOverview = new SeriesLocalizedOverview($serie, $overview, $locale);
                $this->seriesLocalizedOverviewRepository->save($seriesLocalizedOverview, true);
                $overviewId = $seriesLocalizedOverview->getId();
            }
        }

        return $this->json([
            'ok' => true,
            'body' => [
                'id' => $overviewId,
                'source' => $source ? ['id' => $source->getId(), 'name' => $source->getName(), 'path' => $source->getPath(), 'logoPath' => $source->getLogoPath()] : null,
            ]
        ]);
    }

    #[Route('/delete/overview/{id}', name: 'delete_overview', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function deleteOverview(Request $request, int $id): Response
    {
        $data = json_decode($request->getContent(), true);
        $overviewType = $data['overviewType'];
        if ($overviewType == "additional") {
            $overview = $this->seriesAdditionalOverviewRepository->findOneBy(['id' => $id]);
        } else {
            $overview = $this->seriesLocalizedOverviewRepository->findOneBy(['id' => $id]);
        }
        if ($overview) {
            $series = $overview->getSeries();
            if ($overviewType == "additional") {
                $series->removeSeriesAdditionalOverview($overview);
                $this->seriesAdditionalOverviewRepository->remove($overview);
            } else {
                $series->removeSeriesLocalizedOverview($overview);
                $this->seriesLocalizedOverviewRepository->remove($overview);
            }
            $this->seriesRepository->save($series, true);
        }

        return $this->json([
            'ok' => true,
        ]);
    }

    #[Route('/add/episode/{id}', name: 'add_episode', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function addUserEpisode(Request $request, int $id): Response
    {
        $data = json_decode($request->getContent(), true);
        $showId = $data['showId'];
        $lastEpisode = $data['lastEpisode'] == "1";
        $seasonNumber = $data['seasonNumber'];
        $episodeNumber = $data['episodeNumber'];

        /** @var User $user */
        $user = $this->getUser();
        $locale = $user->getPreferredLanguage() ?? $request->getLocale();
        $country = $user->getCountry() ?? 'FR';
        $series = $this->seriesRepository->findOneBy(['tmdbId' => $showId]);
        $dayOffset = $series->getSeriesDayOffset($country);
        $userSeries = $this->userSeriesRepository->findOneBy(['user' => $user, 'series' => $series]);
        $userEpisode = $this->userEpisodeRepository->findOneBy(['user' => $user, 'episodeId' => $id]);
//        if ($userEpisode) {
//            $userEpisode->setWatchAt($this->now());
//            $userEpisode->setNumberOfView($userEpisode->getNumberOfView() + 1);
//            $this->userEpisodeRepository->save($userEpisode, true);
//            return $this->json([
//                'ok' => true,
//            ]);
//        }

        $now = $this->now();
        if (!$userEpisode) {
            $userEpisode = new UserEpisode($userSeries, $id, $seasonNumber, $episodeNumber, $now);
        } else {
            $userEpisode->setWatchAt($now);
        }

        $airDate = $userEpisode->getAirDate();
        if (!$airDate) {
            $episode = json_decode($this->tmdbService->getTvEpisode($showId, $seasonNumber, $episodeNumber, $locale), true);
            $airDate = $this->date($episode['air_date'] . " 09:00:00");
            $userEpisode->setAirDate($airDate);
        }
        if ($dayOffset > 0) {
            $airDate = $airDate->modify("+$dayOffset days");
        } elseif ($dayOffset < 0) {
            $airDate = $airDate->modify("$dayOffset days");
        }

        $tv = json_decode($this->tmdbService->getTv($showId, $locale), true);

        $diff = $now->diff($airDate);
//        dump([
//            'day offset' => $dayOffset,
//            'airDate' => $airDate,
//            'now' => $now,
//            'days' => $diff->days,
//            'hours' => $diff->h,
//            'minutes' => $diff->i,
//            'secondes' => $diff->s
//        ]);
        $userEpisode->setQuickWatchDay($diff->days < 1);
        $userEpisode->setQuickWatchWeek($diff->days < 7);

        // Si le provider et l'appareil du dernier épisode ajouté ne sont renseignés, on les récupère du précédent épisode
        $episodeProviderId = $userEpisode->getProviderId();
        $episodeDeviceId = $userEpisode->getDeviceId();
        if (!$episodeProviderId || !$episodeDeviceId) {
            if ($userEpisode->getEpisodeNumber() > 1) {
                $previousEpisode = $this->userEpisodeRepository->findOneBy(['user' => $user, 'userSeries' => $userSeries, 'seasonNumber' => $seasonNumber, 'episodeNumber' => $episodeNumber - 1]);
                if ($previousEpisode) {
                    if (!$episodeProviderId) $userEpisode->setProviderId($previousEpisode->getProviderId());
                    if (!$episodeDeviceId) $userEpisode->setDeviceId($previousEpisode->getDeviceId());
                }
            }
            // Si on regarde le premier épisode d'une saison, on récupère le provider et l'appareil du dernier épisode
            // de la saison précédente (hors épisodes spéciaux, donc à partir de la saison 2)
            if ($userEpisode->getEpisodeNumber() == 1 && $seasonNumber > 1) {
                $lastEpisodeNumberOfPreviousSeason = $tv['seasons'][$seasonNumber - 2]['episode_count'] ?? 0;
                $previousEpisode = $lastEpisodeNumberOfPreviousSeason > 0 ? $this->userEpisodeRepository->findOneBy(['user' => $user, 'userSeries' => $userSeries, 'seasonNumber' => $seasonNumber - 1, 'episodeNumber' => $lastEpisodeNumberOfPreviousSeason]) : null;
                if ($previousEpisode) {
                    if (!$episodeProviderId) $userEpisode->setProviderId($previousEpisode->getProviderId());
                    if (!$episodeDeviceId) $userEpisode->setDeviceId($previousEpisode->getDeviceId());
                }
            }
        }
        $userEpisode->setNumberOfView($userEpisode->getNumberOfView() + 1);

        $this->userEpisodeRepository->save($userEpisode, true);

        // Si on regarde le dernier épisode de la saison (hors épisodes spéciaux : $seasonNumber > 0)
        // et que l'on n'a pas regardé aure chose entre temps, on considère que c'est un binge
        if ($lastEpisode && $seasonNumber) {
            $userSeries->setBinge($this->isBinge($userSeries, $seasonNumber, $episodeNumber));
        }

        // Si on regarde 3 épisodes en moins d'un jour, on considère que c'est un marathon
        if (!$userSeries->getMarathoner() && $episodeNumber >= 3) {
            $episodes = $this->userEpisodeRepository->findBy(['user' => $user, 'userSeries' => $userSeries, 'seasonNumber' => $seasonNumber], ['watchAt' => 'DESC'], 3);
//            dump($episodes);
            if ($episodes[0]->getEpisodeNumber() - $episodes[1]->getEpisodeNumber() == 1 && $episodes[1]->getEpisodeNumber() - $episodes[2]->getEpisodeNumber() == 1) {
                $firstViewAt = $episodes[0]->getWatchAt();
                $lastViewAt = $episodes[2]->getWatchAt();
                $diff = $lastViewAt->diff($firstViewAt);
                if ($diff->days < 1) {
                    $userSeries->setMarathoner(true);
                }
            }
        }

        if ($seasonNumber) {
            $userSeries->setLastWatchAt($now);
            $userSeries->setLastEpisode($episodeNumber);
            $userSeries->setLastSeason($seasonNumber);
            $userSeries->setViewedEpisodes($userSeries->getViewedEpisodes() + 1);
            $userSeries->setProgress($userSeries->getViewedEpisodes() / $tv['number_of_episodes'] * 100);
            $this->userSeriesRepository->save($userSeries, true);
        }
        return $this->json([
            'ok' => true,
        ]);
    }

    #[Route('/remove/episode/{id}', name: 'remove_episode', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function removeUserEpisode(Request $request, int $id): Response
    {
        $data = json_decode($request->getContent(), true);
        $showId = $data['showId'];
        $seasonNumber = $data['seasonNumber'];
        $episodeNumber = $data['episodeNumber'];
        $locale = $request->getLocale();
        /** @var User $user */
        $user = $this->getUser();
        $series = $this->seriesRepository->findOneBy(['tmdbId' => $showId]);
        $userSeries = $this->userSeriesRepository->findOneBy(['user' => $user, 'series' => $series]);
        $userEpisode = $this->userEpisodeRepository->findOneBy(['user' => $user, 'episodeId' => $id]);
        if ($userEpisode) {
            if ($userEpisode->getNumberOfView() > 1) {
                $userEpisode->setNumberOfView($userEpisode->getNumberOfView() - 1);
                $this->userEpisodeRepository->save($userEpisode, true);
                return $this->json([
                    'ok' => true,
                ]);
            }

            $userEpisode->setWatchAt(null);
            $userEpisode->setNumberOfView(0);
            $userEpisode->setProviderId(null);
            $userEpisode->setDeviceId(null);
            $userEpisode->setVote(null);
            $userEpisode->setQuickWatchDay(false);
            $userEpisode->setQuickWatchWeek(false);
            $this->userEpisodeRepository->save($userEpisode, true);
        }

        if ($episodeNumber > 1 && $seasonNumber > 0) {
            for ($j = $seasonNumber; $j > 0; $j--) {
                for ($i = $episodeNumber - 1; $i > 0; $i--) {
                    $episode = $this->userEpisodeRepository->findOneBy(['user' => $user, 'userSeries' => $userSeries, 'seasonNumber' => $j, 'episodeNumber' => $i]);
                    if ($episode && $episode->getWatchAt()) {
                        $userSeries->setLastEpisode($episode->getEpisodeNumber());
                        $userSeries->setLastSeason($episode->getSeasonNumber());
                        $userSeries->setLastWatchAt($episode->getWatchAt());
                        $viewedEpisodes = $userSeries->getViewedEpisodes();
                        $tv = json_decode($this->tmdbService->getTv($showId, $locale), true);
                        $numberOfEpisode = $tv['number_of_episodes'];
                        $userSeries->setViewedEpisodes($viewedEpisodes - 1);
                        $userSeries->setProgress(($viewedEpisodes - 1) / $numberOfEpisode * 100);
                        $userSeries->setBinge(false);
                        $this->userSeriesRepository->save($userSeries, true);
                        return $this->json([
                            'ok' => true,
                        ]);
                    }
                }
            }
        }
        // on a supprimé le premier épisode de la première saison ou on n'a pas trouvé d'épisode précédemment vu
        if ($seasonNumber == 1 && $episodeNumber == 1) {
            $userSeries->setLastEpisode(null);
            $userSeries->setLastSeason(null);
            $userSeries->setLastWatchAt(null);
            $userSeries->setViewedEpisodes(0);
            $userSeries->setProgress(0);
        }
        $this->userSeriesRepository->save($userSeries, true);
        return $this->json([
            'ok' => true,
        ]);
    }

    #[Route('/episode/provider/{episodeId}', name: 'episode_provider', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function userEpisodeProvider(Request $request, int $episodeId): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $userEpisode = $this->userEpisodeRepository->findOneBy(['user' => $user, 'episodeId' => $episodeId]);
        $data = json_decode($request->getContent(), true);
        $providerId = $data['providerId'];

        $userEpisode->setProviderId($providerId);
        $this->userEpisodeRepository->save($userEpisode, true);

        return $this->json([
            'ok' => true,
        ]);
    }

    #[Route('/episode/device/{episodeId}', name: 'episode_device', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function userEpisodeDevice(Request $request, int $episodeId): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $userEpisode = $this->userEpisodeRepository->findOneBy(['user' => $user, 'episodeId' => $episodeId]);
        $data = json_decode($request->getContent(), true);
        $deviceId = $data['deviceId'];

        $userEpisode->setDeviceId($deviceId);
        $this->userEpisodeRepository->save($userEpisode, true);

        return $this->json([
            'ok' => true,
        ]);
    }

    #[Route('/episode/vote/{episodeId}', name: 'episode_vote', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function userEpisodeVote(Request $request, int $episodeId): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $userEpisode = $this->userEpisodeRepository->findOneBy(['user' => $user, 'episodeId' => $episodeId]);
        $data = json_decode($request->getContent(), true);
        $vote = $data['vote'];

        $userEpisode->setVote($vote);
        $this->userEpisodeRepository->save($userEpisode, true);

        return $this->json([
            'ok' => true,
        ]);
    }

    #[Route('/episode/update/name/{id}', name: 'update_name', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function episodeTitle(Request $request, int $id): Response
    {
        $data = json_decode($request->getContent(), true);
        $name = $data['name'];
        $substitute = $this->episodeSubstituteNameRepository->findOneBy(['episodeId' => $id]);
        if ($substitute) {
            $substitute->setName($name);
        } else {
            $substitute = new EpisodeSubstituteName($id, $name);
        }
        $this->episodeSubstituteNameRepository->save($substitute, true);

        return $this->json([
            'ok' => true,
        ]);
    }

    #[Route('/episode/localize/overview/{id}', name: 'localize_overview', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function episodeLocalizeOverview(Request $request, int $id): Response
    {
        $data = json_decode($request->getContent(), true);
        $overview = $data['overview'];
        $elo = $this->episodeLocalizedOverviewRepository->findOneBy(['episodeId' => $id]);
        if ($elo) {
            $elo->setOverview($overview);
        } else {
            $elo = new EpisodeLocalizedOverview($id, $overview, $request->getLocale());
        }
        $this->episodeLocalizedOverviewRepository->save($elo, true);

        return $this->json([
            'ok' => true,
            'overview' => $overview,
        ]);
    }

    #[Route('/episode/still/{id}', name: 'still', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function episodeStill(Request $request, int $id): Response
    {
        /** @var UploadedFile $uploadedFile */
        $uploadedFile = $request->files->get('file');
        $filename = '/' . $uploadedFile->getClientOriginalName();
        $stillPath = $this->getParameter('kernel.project_dir') . '/public/series/stills' . $filename;
        $copy = $this->copyImage($uploadedFile->getPathname(), $stillPath);

        if ($copy) {
            $episode = $this->userEpisodeRepository->findOneBy(['episodeId' => $id]);
            $episode->setStill($filename);
            $this->userEpisodeRepository->save($episode, true);
        }

        return $this->json([
            'ok' => $copy,
        ]);
    }

    #[Route('/overview/{id}', name: 'get_overview', methods: 'GET')]
    public function getOverview(Request $request, $id): Response
    {
        $type = $request->query->get("type");
        $content = null;

        $standing = match ($type) {
            "tv" => $this->tmdbService->getTv($id, $request->getLocale()),
            "movie" => $this->tmdbService->getMovie($id, $request->getLocale()),
            default => null,
        };

        if ($standing) {
            $content = json_decode($standing, true);
        }
        return $this->json([
            'overview' => $content ? $content['overview'] : "",
            'media_type' => $this->translator->trans($type),
        ]);
    }

    #[Route('/settings/save', name: 'settings_save', methods: 'POST')]
    public function saveSettings(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);
        $name = $data['name'];
        if ($name == 'my movies boxes')
            $value = $data['box'];
        else
            $value = $data['value'];
//        dump([
//            'name' => $name,
//            'value' => $value,
//        ]);
        $settings = $this->settingsRepository->findOneBy(['user' => $user, 'name' => $name]);
        if ($settings) {
            if ($name == 'my movies boxes') {
                $box = $value['key'];
                $open = $value['value'];
                $value = $settings->getData();
                $value[$box] = $open;
            }
            $settings->setData($value);
        } else {
            if ($name == 'my movies boxes') {
                $box = $value['key'];
                $open = $value['value'];
                $value = ['filters' => true, 'pages' => true];
                $value[$box] = $open;
            }
            $settings = new Settings($user, $name, $value);
        }
        $this->settingsRepository->save($settings, true);

        return $this->json([
            'ok' => true,
        ]);
    }

    #[Route('/keywords/save', name: 'keywords_save', methods: ['POST'])]
    public function translationSave(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);

        $tmdbId = $data['id'];
        $keywords = $data['keywords'];
        $language = $data['language'];

        $keywordYaml = $this->keywordService->getTranslationLines($language);

        $n = count($keywords);
        for ($i = 0; $i < $n; $i++) {
            $line = $keywords[$i]['original'] . ': ' . str_replace(':', '→', $keywords[$i]['translated']) . "\n";
            $keywordYaml[] = $line;
        }
        usort($keywordYaml, fn($a, $b) => $a <=> $b);

        $filename = '../translations/keywords.' . $language . '.yaml';
        $res = fopen($filename, 'w');

        foreach ($keywordYaml as $line) {
            fputs($res, $line);
        }
        fclose($res);

        $tvKeywords = json_decode($this->tmdbService->getTvKeywords($tmdbId), true);

        $missingKeywords = $this->keywordService->keywordsTranslation($tvKeywords['results'], $language);
        $keywordBlock = $this->renderView('_blocks/series/_keywords.html.twig', [
            'id' => $tmdbId,
            'keywords' => $tvKeywords['results'],
            'missing' => $missingKeywords,
        ]);

        // fetch response
        return $this->json([
            'ok' => true,
            'keywords' => $keywordBlock,
        ]);
    }

    #[Route('/get/backdrops/{id}', name: 'get_backdrops', requirements: ['id' => Requirement::DIGITS], methods: 'POST')]
    public function getAllBackdrops(int $id): Response
    {
        $images = json_decode($this->tmdbService->getAllTvImages($id), true);
        $backdrops = $images['backdrops'];
        $posters = $images['posters'];

        return $this->json([
            'ok' => true,
            'success' => true,
            'backdrops' => $backdrops,
            'backdropUrl' => $this->imageConfiguration->getUrl('backdrop_sizes', 2),
            'posters' => $posters,
            'posterUrl' => $this->imageConfiguration->getUrl('poster_sizes', 2),
        ]);
    }

    #[Route('/add/backdrops', name: 'add_backdrops', methods: 'POST')]
    public function addAllBackdrops(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);

        $tmdbId = $data['seriesId'];
        $series = $this->seriesRepository->findOneBy(['tmdbId' => $tmdbId]);
        $images = $series->getSeriesImages()->toArray();

        $backdrops = $data['backdrops'];
        $posters = $data['posters'];

        $backdropUrl = $this->imageConfiguration->getUrl('backdrop_sizes', 3);
        $posterUrl = $this->imageConfiguration->getUrl('poster_sizes', 5);

        $addedBackdropCount = 0;
        $addedPosterCount = 0;

        foreach ($backdrops as $backdrop) {
            if (!$this->inImages($backdrop['file_path'], $images)) {
                $seriesImage = new SeriesImage($series, "backdrop", $backdrop['file_path']);
                $this->seriesImageRepository->save($seriesImage);
                $this->saveImage("backdrops", $backdrop['file_path'], $backdropUrl);
                $addedBackdropCount++;
            }
        }
        foreach ($posters as $poster) {
            if (!$this->inImages($poster['file_path'], $images)) {
                $seriesImage = new SeriesImage($series, "poster", $poster['file_path']);
                $this->seriesImageRepository->save($seriesImage);
                $this->saveImage("posters", $poster['file_path'], $posterUrl);
                $addedPosterCount++;
            }
        }

        if ($addedBackdropCount + $addedPosterCount > 0) {
            $this->seriesImageRepository->flush();
        }

        return $this->json([
            'ok' => true,
            'success' => true,
            'addedBackdrops' => $addedBackdropCount,
            'addedPosters' => $addedPosterCount,
        ]);
    }

    #[Route('/add/location/{id}', name: 'add_location', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function addLocation(Request $request, Series $series): Response
    {
        $locations = $series->getLocations();
        $data = json_decode($request->getContent(), true);
//        dump(['locations' => $locations, 'data' => $data]);
        $data = array_filter($data, fn($key) => $key != "google-map-url", ARRAY_FILTER_USE_KEY);
//        dump(['data' => $data]);
        // Javascript code:
        // fetch('/' + lang + '/series/add/location/' + seriesId,
        //     {
        //         method: 'POST',
        //         body: formDatas,
        //         headers: {
        //             "Content-Type": "multipart/form-data"
        //         }
        //     }
        // )
        // Php code:
//        $rawContent = $request->getContent();
        //extract boundary for multipart form data: ------WebKitFormBoundarywRsOZ331E9nhgGan\n
//        preg_match('/^(.*oundary.*\r\n)/', $rawContent, $matches);
//        $boundary = $matches[1];
//        dump($boundary);
        //fetch the content and determine the boundary
//        $blocks = preg_split("/$boundary/", $rawContent);
        //parse each block
//        foreach ($blocks as $block) {
//            if (empty($block)) {
//                continue;
//            }
//            dump($block);
//            // if header contains filename, it is a file
//            if (str_contains($block, 'filename')) {
//                preg_match('/Content-Disposition: form-data; name="(.*)"; filename="(.*)"\r\nContent-Type: (.*)\r\n\r\n(...)\r\n/', $block, $matches);
//                dump($matches);
//            } else {
//                preg_match('/Content-Disposition: form-data; name="([^"]*)"\r\n\r\n(.*)\r\n/', $block, $matches);
//                dump($matches);
//            }
//        }

        $uuid = $data['uuid'] = Uuid::v4()->toString();
        $title = $data['title'];
        $location = $data['location'];
        $description = $data['description'];
        $data['latitude'] = str_replace(',', '.', $data['latitude']);
        $data['longitude'] = str_replace(',', '.', $data['longitude']);
        $latitude = $data['latitude'] = floatval($data['latitude']);
        $longitude = $data['longitude'] = floatval($data['longitude']);
        $tmdbId = $series->getTmdbId();

        $locations['locations'][] = $data;
        $series->setLocations($locations);
        $this->seriesRepository->save($series, true);

        $filmingLocation = new FilmingLocation($uuid, $tmdbId, $location, $description, $latitude, $longitude, true);
        $this->filmingLocationRepository->save($filmingLocation, true);

        $images = [];
        $images[0] = $data['image'];
        $rootDir = $this->getParameter('kernel.project_dir') . '/public';
        $messages = [];
        $n = 0;
        foreach ($images as $image) {
            if (str_contains($image, '/images/map')) {
                $image = str_replace('/images/map', '', $image);
            } else {
                $basename = basename($image);
                $destination = $rootDir . '/images/map/' . $basename;
                $copied = $this->saveImageFromUrl($image, $destination);
                if ($copied) {
                    $messages[] = 'Image [ ' . $image . ' ] copied to ' . $destination;
                } else {
                    $messages[] = 'Image [ ' . $image . ' ] not copied';
                }
                $image = '/' . $basename;
            }
            $filmingLocationImage = new FilmingLocationImage($filmingLocation, $image);
            $this->filmingLocationImageRepository->save($filmingLocationImage, true);

            if ($n == 0) {
                $filmingLocation->setStill($filmingLocationImage);
                $this->filmingLocationRepository->save($filmingLocation, true);
            }
            $n++;
        }

        return $this->json([
            'ok' => true,
//            'filmingLocation' => $filmingLocation,
//            'filmingLocationImage' => $filmingLocationImage,
            'messages' => $messages,
        ]);
    }

    #[Route('/edit/location/{id}', name: 'edit_location', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function editLocation(Request $request, Series $series): Response
    {
        $locations = $series->getLocations();
        $data = json_decode($request->getContent(), true);
//        dump(['locations' => $locations, 'data' => $data]);
        $data = array_filter($data, fn($key) => $key != "google-map-url", ARRAY_FILTER_USE_KEY);
//        dump(['data' => $data]);
        // Javascript code:
        // fetch('/' + lang + '/series/add/location/' + seriesId,
        //     {
        //         method: 'POST',
        //         body: formDatas,
        //         headers: {
        //             "Content-Type": "multipart/form-data"
        //         }
        //     }
        // )
        // Php code:
//        $rawContent = $request->getContent();
        //extract boundary for multipart form data: ------WebKitFormBoundarywRsOZ331E9nhgGan\n
//        preg_match('/^(.*oundary.*\r\n)/', $rawContent, $matches);
//        $boundary = $matches[1];
//        dump($boundary);
        //fetch the content and determine the boundary
//        $blocks = preg_split("/$boundary/", $rawContent);
        //parse each block
//        foreach ($blocks as $block) {
//            if (empty($block)) {
//                continue;
//            }
//            dump($block);
//            // if header contains filename, it is a file
//            if (str_contains($block, 'filename')) {
//                preg_match('/Content-Disposition: form-data; name="(.*)"; filename="(.*)"\r\nContent-Type: (.*)\r\n\r\n(...)\r\n/', $block, $matches);
//                dump($matches);
//            } else {
//                preg_match('/Content-Disposition: form-data; name="([^"]*)"\r\n\r\n(.*)\r\n/', $block, $matches);
//                dump($matches);
//            }
//        }

//        $uuid = $data['uuid'] = Uuid::v4()->toString();
        $id = $data['crud-id'];
        $title = $data['title'];
        $location = $data['location'];
        $description = $data['description'];
        $data['latitude'] = str_replace(',', '.', $data['latitude']);
        $data['longitude'] = str_replace(',', '.', $data['longitude']);
        $latitude = $data['latitude'] = floatval($data['latitude']);
        $longitude = $data['longitude'] = floatval($data['longitude']);


        $locations['locations'][] = $data;
        $series->setLocations($locations);
        $this->seriesRepository->save($series, true);

//        $filmingLocation = new FilmingLocation($uuid, $title, $location, $description, $latitude, $longitude, true);
        $filmingLocation = $this->filmingLocationRepository->findOneBy(['id' => $id]);
        $filmingLocation->setTitle($title);
        $filmingLocation->setLocation($location);
        $filmingLocation->setDescription($description);
        $filmingLocation->setLatitude($latitude);
        $filmingLocation->setLongitude($longitude);
        $this->filmingLocationRepository->save($filmingLocation, true);

        $image = $data['image'];
        $rootDir = $this->getParameter('kernel.project_dir') . '/public';
        $messages = [];
        if (str_contains($image, '/images/map')) {
            $image = str_replace('/images/map', '', $image);
        } else {
            // copy image to /images/map
            // https://someurl.com/image.jpg -> /images/map/image.jpg
            $basename = basename($image);
            $destination = $rootDir . '/images/map/' . $basename;
            $copied = $this->saveImageFromUrl($image, $destination);
            if ($copied) {
                $messages[] = 'Image [ ' . $image . ' ] copied to ' . $destination;
            } else {
                $messages[] = 'Image [ ' . $image . ' ] not copied';
            }
            $image = '/' . $basename;
        }
        $filmingLocationImage = new FilmingLocationImage($filmingLocation, $image);
        $this->filmingLocationImageRepository->save($filmingLocationImage, true);

        $filmingLocation->setStill($filmingLocationImage);
        $this->filmingLocationRepository->save($filmingLocation, true);

        return $this->json([
            'ok' => true,
            'filmingLocation' => $filmingLocation,
            'filmingLocationImage' => $filmingLocationImage,
            'messages' => $messages,
        ]);
    }

    #[Route('/fetch/search/db/tv', name: 'fetch_search_db_tv', methods: ['POST'])]
    public function fetchSearchDbTv(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $locale = $user->getPreferredLanguage() ?? $request->getLocale();
        $data = json_decode($request->getContent(), true);
        $query = $data['query'];
        $series = $this->userSeriesRepository->searchSeries($user, $query, $locale);

        return $this->json([
            'ok' => true,
            'results' => $series,
        ]);
    }

    #[Route('/tmdb/check', name: 'tmdb_check', methods: ['POST'])]
    public function tmdbCheck(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $locale = $user->getPreferredLanguage() ?? $request->getLocale();
        $data = json_decode($request->getContent(), true);
        $tmdbIds = $data['tmdbIds'];

        $dbSeries = $this->seriesRepository->findBy(['tmdbId' => $tmdbIds]);
        $dbSeriesCount = count($dbSeries);
        $seriesIds = array_map(fn($series) => $series->getId(), $dbSeries);
        $localizedNameArr = $this->seriesRepository->getLocalizedNames($seriesIds, $locale);
        $localizedNames = [];
        foreach ($localizedNameArr as $ln) {
            $localizedNames[$ln['series_id']] = $ln['name'];
        }

        $now = $this->now();
        $tmdbCalls = 0;
        $updates = [];

        foreach ($dbSeries as $series) {
            $lastUpdate = $series->getUpdatedAt();
            $interval = $now->diff($lastUpdate);

//            if ($interval->days < 1) {
//                $updates[] = [
//                    'id' => $series->getId(),
//                    'name' => $series->getName(),
//                    'localized_name' => $localizedNames[$series->getId()] ?? null,
//                    'poster_path' => $series->getPosterPath(),
//                    'updates' => [], // '*** Updated less than 24 hours ago ***'
//                ];
//                continue;
//            }
            $tv = json_decode($this->tmdbService->getTv($series->getTmdbId(), $locale, ['images']), true);
            $tmdbCalls++;
            if ($tv == null) {
                $updates[] = [
                    'id' => $series->getId(),
                    'name' => $series->getName(),
                    'localized_name' => $localizedNames[$series->getId()] ?? null,
                    'poster_path' => $series->getPosterPath(),
                    'updates' => ['*** Series not found ***'],
                ];
                $series->setUpdatedAt($now);
                $this->seriesRepository->save($series);
                continue;
            }
            $updateSeries = $this->updateSeries($series, $tv);
            $update = $updateSeries[0]->getUpdates();
            $updates[] = [
                'id' => $series->getId(),
                'name' => $series->getName(),
                'localized_name' => $localizedNames[$series->getId()] ?? null,
                'poster_path' => $series->getPosterPath(),
                'updates' => $update];
            $series->setUpdatedAt($now);
            $this->seriesRepository->save($series);
        }
        $this->seriesRepository->flush();

        dump([
            'tmdbIds' => $tmdbIds,
            'dbSeries' => $dbSeries,
            'updates' => $updates,
        ]);

        return $this->json([
            'ok' => true,
            'updates' => $updates,
            'dbSeriesCount' => $dbSeriesCount,
            'tmdbCalls' => $tmdbCalls,
        ]);
    }

    public function isBinge(UserSeries $userSeries, int $seasonNumber, int $numberOfEpisode): bool
    {
        $isBinge = false;

        $lastEpisodeDb = $this->userEpisodeRepository->findOneBy(['userSeries' => $userSeries, 'seasonNumber' => $seasonNumber], ['episodeNumber' => 'DESC']);
        $firstEpisodeDb = $this->userEpisodeRepository->findOneBy(['userSeries' => $userSeries, 'seasonNumber' => $seasonNumber, 'episodeNumber' => 1]);
        $lastId = $lastEpisodeDb->getId();
        $firstId = $firstEpisodeDb->getId();
        $userSeriesId = $userSeries->getId();
        $userId = $userSeries->getUser()->getId();
        $userEpisodes = $this->userEpisodeRepository->getEpisodeListBetweenIds($userId, $firstId, $lastId);
        $episodeCount = count($userEpisodes);
        if ($episodeCount == $numberOfEpisode) {
            return true;
        }
        $previousUserEpisode = null;
        $episodeCount = 0;
        $interruptions = 0;
        $otherSeriesEpisodes = 0;
        // userEpisode
        //  "episode_number" => 3
        //  "season_number" => 1
        //  "user_series_id" => 826
        foreach ($userEpisodes as $userEpisode) {
            $currentUserSeriesId = $userEpisode['user_series_id'];
            if (!$previousUserEpisode) {
                if ($currentUserSeriesId == $userSeriesId) {
                    $previousUserEpisode = $userEpisode;
                    $episodeCount++;
                }
                continue;
            }

            if ($currentUserSeriesId != $userSeriesId
                || $previousUserEpisode['season_number'] != $userEpisode['season_number']) {
                $interruptions++;
                $otherSeriesEpisodes++;
                // We leave a margin of two episodes of another series
                if ($otherSeriesEpisodes > 2) {
                    break;
                }
                continue;
            } else {
                if ($interruptions > 0) {
                    $interruptions = 0;
                    $otherSeriesEpisodes = 0;
                }
            }
            $previousUserEpisode = $userEpisode;
            $episodeCount++;
        }
//        dump($userEpisodes, $episodeCount, $numberOfEpisode);
        if ($episodeCount == $numberOfEpisode) {
            $isBinge = true;
        }
        return $isBinge;
    }

    public function updateSeries(Series $series, array $tv): array
    {
        $slugger = new AsciiSlugger();
        $series->setUpdates([]);
        if ($tv['name'] != $series->getName()) {
            $series->setName($tv['name']);
            $series->setSlug($slugger->slug($tv['name']));
            $series->addUpdate($this->translator->trans('Name updated'));
        }
        $slug = $slugger->slug($tv['name'])->lower()->toString();
        if ($series->getSlug() != $slug) {
            $series->setSlug($slugger->slug($slug));
            $series->addUpdate($this->translator->trans('Slug updated'));
        }

        if ($tv['original_name'] != $series->getOriginalName()) {
            $series->setOriginalName($tv['original_name']);
            $series->addUpdate($this->translator->trans('Original name updated'));
        }
        if ($tv['first_air_date'] && !$series->getFirstAirDate()) {
            $series->setFirstAirDate($this->dateService->newDateImmutable($tv['first_air_date'], "Europe/Paris", true));
//            $series->setFirstAirDate(new DatePoint($tv['first_air_date']));
            $series->addUpdate($this->translator->trans('First air date updated'));
        }

        if (strlen($tv['overview']) && strcmp($tv['overview'], $series->getOverview())) {
            $series->setOverview($tv['overview']);
            $series->addUpdate($this->translator->trans('Overview updated'));
        }

        if ($tv['status'] != $series->getStatus()) {
            $series->setStatus($tv['status']);
            $series->addUpdate($this->translator->trans('New status') . ' → ' . $this->translator->trans($tv['status']));
        }

        $dbNextEpisodeAirDate = $series->getNextEpisodeAirDate()?->format('Y-m-d');
        if ($tv['next_episode_to_air'] && $tv['next_episode_to_air']['air_date']) {
            $tvNextEpisodeAirDate = $tv['next_episode_to_air']['air_date'];
        } else {
            $tvNextEpisodeAirDate = null;
        }
        if ($tvNextEpisodeAirDate != $dbNextEpisodeAirDate) {
            $nextEpisodeAirDate = $tvNextEpisodeAirDate ? $this->date($tvNextEpisodeAirDate . " 00:00:00") : null;
            $series->setNextEpisodeAirDate($nextEpisodeAirDate);
            $series->addUpdate($this->translator->trans('Next episode air date updated') . ' → ' . $nextEpisodeAirDate?->format('d-m-Y'));
        }

        $sizes = ['backdrops' => 3, 'logos' => 5, 'posters' => 5];
        $seriesImages = $series->getSeriesImages()->toArray();
        foreach ($seriesImages as $seriesImage) {
            $type = $seriesImage->getType();
            $imageConfigType = $type . '_sizes';
            $type .= 's';
            $url = $this->imageConfiguration->getUrl($imageConfigType, $sizes[$type]);
//            dump(['type' => $type, 'series Image' => $seriesImage, 'url' => $url]);
            $this->saveImage($type, $seriesImage->getImagePath(), $url);
        }

        if (!$this->inImages($tv['poster_path'], $seriesImages)) {
            $seriesImage = new SeriesImage($series, "poster", $tv['poster_path']);
            $this->seriesImageRepository->save($seriesImage, true);
            $series->addUpdate($this->translator->trans('Poster added'));
        }
        if (!$this->inImages($tv['backdrop_path'], $seriesImages)) {
            $seriesImage = new SeriesImage($series, "backdrop", $tv['backdrop_path']);
            $this->seriesImageRepository->save($seriesImage, true);
            $series->addUpdate($this->translator->trans('Backdrop added'));
        }

        foreach (['backdrops', 'logos', 'posters'] as $type) {
            $dbType = substr($type, 0, -1);
            $imageConfigType = $dbType . '_sizes';
            $url = $this->imageConfiguration->getUrl($imageConfigType, $sizes[$type]);
            foreach ($tv['images'][$type] as $img) {
                if (!$this->inImages($img['file_path'], $seriesImages)) {
                    $seriesImage = new SeriesImage($series, $dbType, $img['file_path']);
                    $this->seriesImageRepository->save($seriesImage, true);
                    $series->addUpdate($this->translator->trans(ucfirst($dbType) . ' added'));
                }
            }
            $tv['images'][$type] = array_map(function ($image) use ($type, $sizes, $imageConfigType) {
                $this->saveImage($type, $image['file_path'], $this->imageConfiguration->getUrl($imageConfigType, $sizes[$type]));
                return '/series/' . $type . $image['file_path'];
            }, $tv['images'][$type]);
        }

        if ($tv['poster_path'] != $series->getPosterPath()) {
            $series->setPosterPath($tv['poster_path']);
            $this->saveImage("posters", $series->getPosterPath(), $this->imageConfiguration->getUrl('poster_sizes', 5));
            $series->addUpdate($this->translator->trans('Poster updated'));
        }
        if ($tv['backdrop_path'] != $series->getBackdropPath()) {
            $series->setBackdropPath($tv['backdrop_path']);
            $this->saveImage("backdrops", $series->getBackdropPath(), $this->imageConfiguration->getUrl('backdrop_sizes', 3));
            $series->addUpdate($this->translator->trans('Backdrop updated'));
        }
        $this->seriesRepository->save($series, true);

        $seriesImages = $this->seriesRepository->seriesImages($series);
        $seriesBackdrops = array_filter($seriesImages, fn($image) => $image['type'] == "backdrop");

        $seriesLogos = array_filter($seriesImages, fn($image) => $image['type'] == "logo");
        $seriesPosters = array_filter($seriesImages, fn($image) => $image['type'] == "poster");

        $seriesBackdrops = array_values(array_map(fn($image) => "/series/backdrops" . $image['image_path'], $seriesBackdrops));
        $seriesLogos = array_values(array_map(fn($image) => "/series/logos" . $image['image_path'], $seriesLogos));
        $seriesPosters = array_values(array_map(fn($image) => "/series/posters" . $image['image_path'], $seriesPosters));

        return [$series, $seriesBackdrops, $seriesLogos, $seriesPosters];
    }

    public function getExternals(Series $series, string $locale): array
    {
        // https://mydramalist.com/search?q=between+us
        // https://www.nautiljon.com/search.php?q=love+sick
        // https://www.senscritique.com/search?query=Bad%20Guy%20My%20Boss
        // https://world-of-bl.com/index.php?n=Main.HomePage&action=search&q=love+sick
        $seriesCountries = $series->getOriginCountry();
        $dbExternals = $this->seriesExternalRepository->findAll();
        $externals = [];
        $displayName = $series->getLocalizedName($locale)?->getName() ?? $series->getName();

        /** @var SeriesExternal $dbExternal */
        foreach ($dbExternals as $dbExternal) {
            $countries = $dbExternal->getCountries();
            $searchQuery = $dbExternal->getSearchQuery();
            $searchSeparator = $dbExternal->getSearchSeparator();
            $searchName = strtolower($searchSeparator ? str_replace(' ', $searchSeparator, $displayName) : $displayName);
            if (!count($countries) || array_intersect($seriesCountries, $countries)) {
                $dbExternal->setFullUrl($searchQuery ? $searchName : null);
                $externals[] = $dbExternal;
            }
        }
        return $externals;
    }

    public function statusCss(UserSeries $userSeries, array $tv): string
    {
        $status = $tv['status'];
        $progress = $userSeries->getProgress();
        $statusCss = 'status-';
        if ($status == 'Returning Series') {
            $statusCss .= 'returning';
        } elseif ($status == 'Ended') {
            $statusCss .= 'ended';
        } elseif ($status == 'Canceled') {
            $statusCss .= 'canceled';
        } elseif ($status == 'In Production') {
            $statusCss .= 'in-production';
        } elseif ($status == 'Planned') {
            $statusCss .= 'planned';
        } elseif ($status == 'Pilot') {
            $statusCss .= 'pilot';
        } elseif ($status == 'Rumored') {
            $statusCss .= 'rumored';
        } else {
            $statusCss .= 'unknown';
        }
        if ($progress == 100) {
            $statusCss .= ' watched';
        }
        return $statusCss;
    }

    public function updateUserSeries(UserSeries $userSeries, array $tv): UserSeries
    {
        $change = false;
        $episodeCount = $this->checkNumberOfEpisodes($tv);
        dump($episodeCount);
        if ($episodeCount != $tv['number_of_episodes']) {
            $this->addFlash('warning', $this->translator->trans('Number of episodes has changed') . '<br>' . $tv['number_of_episodes'] . ' → ' . $episodeCount);
        }
        if ($episodeCount == 0 && $userSeries->getProgress() != 0) {
            $this->addFlash('warning', 'Number of episodes is zero');
            $userSeries->setProgress(0);
            $change = true;
        } else {
            if (/*$userSeries->getProgress() == 100 && */ $userSeries->getViewedEpisodes() < $episodeCount) {
                $newProgress = $userSeries->getViewedEpisodes() / $episodeCount * 100;
                if ($newProgress != $userSeries->getProgress()) {
                    $userSeries->setProgress($newProgress);
                    $this->addFlash('success', 'Progress updated to ' . $newProgress . '%');
                    $change = true;
                }
            }
            if ($userSeries->getProgress() != 100 && $userSeries->getViewedEpisodes() === $episodeCount) {
                $userSeries->setProgress(100);
                $this->addFlash('success', 'Progress fixed to 100%');
                $change = true;
            }
        }
        if ($change) {
            $this->userSeriesRepository->save($userSeries, true);
        }
        return $userSeries;
    }

    public function checkNumberOfEpisodes(array $tv): int
    {
        $seasonEpisodeCount = 0;
        foreach ($tv['seasons'] as $season) {
            if ($season['season_number'] > 0) {
                // Si la série n'a plus d'épisode à venir, on compte les épisodes
                // (nombre d'épisodes égal à un, signifie que la saison est à venir, juste annoncée)
                // de la saison qui ont une date de diffusion.
                // Sinon, on se fie au nombre d'épisodes de la saison fourni par l'API
                if (!$tv['next_episode_to_air']) {
                    $s = json_decode($this->tmdbService->getTvSeason($tv['id'], $season['season_number'], 'fr-FR'), true);
                    $episodeCount = 0;
                    foreach ($s['episodes'] as $episode) {
                        if ($episode['air_date']) $episodeCount++;
                    }
                    $seasonEpisodeCount += $episodeCount;
                } else {
                    $seasonEpisodeCount += $season['episode_count'];
                }
            }
        }
        return $seasonEpisodeCount;
    }

    public function seriesSchedulesV2(User $user, Series $series, ?array $tv, int $dayOffset): array
    {
        $schedules = [];
        $locale = $user->getPreferredLanguage() ?? 'fr';
        foreach ($series->getSeriesBroadcastSchedules() as $schedule) {
            $airAt = $schedule->getAirAt();
            $firstAirDate = $schedule->getFirstAirDate();
            $frequency = $schedule->getFrequency();
            $override = $schedule->isOverride();
            $dayOfWeekArr = [
                'en' => ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
                'fr' => ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'],
            ];
            $daysOfWeek = $schedule->getDaysOfWeek();
            $scheduleDayOfWeek = array_map(fn($day) => $dayOfWeekArr[$locale][$day], $daysOfWeek);
            $scheduleDayOfWeek = ucfirst(implode(', ', $scheduleDayOfWeek));
            $dayArr = array_fill(0, 7, false);
            foreach ($daysOfWeek as $day) {
                $dayArr[$day] = true;
            }

            if ($tv) {
                $tvLastEpisode = $this->offsetEpisodeDate($tv['last_episode_to_air'], $dayOffset, $airAt, $user->getTimezone() ?? 'Europe/Paris');
                $tvNextEpisode = $this->offsetEpisodeDate($tv['next_episode_to_air'], $dayOffset, $airAt, $user->getTimezone() ?? 'Europe/Paris');
            } else {
                $tvLastEpisode = null;
                $tvNextEpisode = null;
            }

            $now = $this->dateService->newDateImmutable('now', 'Europe/Paris');
//            $tomorrow = $now->modify('+1 day')->setTime(0, 0);
//            $remainingTodayTS = $tomorrow->getTimestamp() - $now->getTimestamp();

            $nextEpisodeAiDate = $tvNextEpisode ? $this->dateService->newDateImmutable($tvNextEpisode['air_date'], 'Europe/Paris') : null;
            $lastEpisodeAiDate = $tvLastEpisode ? $this->dateService->newDateImmutable($tvLastEpisode['air_date'], 'Europe/Paris') : null;
            if ($nextEpisodeAiDate) {
                $target = $nextEpisodeAiDate;
            } elseif ($lastEpisodeAiDate) {
                $target = $lastEpisodeAiDate;
            } else {
                $target = null;
            }
            $targetTS = $target?->getTimestamp();

            $userLastEpisode = $this->userEpisodeRepository->getScheduleLastEpisode($user, $series);
            $userNextEpisode = $this->userEpisodeRepository->getScheduleNextEpisode($user, $series);
            $userLastEpisode = $userLastEpisode[0] ?? null;
            $userNextEpisode = $userNextEpisode[0] ?? null;
            if ($userNextEpisode) {
                $userNextEpisodes = $this->userEpisodeRepository->getScheduleNextEpisodes($user, $series, $userNextEpisode['air_date']);
                $count = count($userNextEpisodes);
                $multiple = $count > 1;
                if ($multiple) {
                    $userLastNextEpisode = $userNextEpisodes[$count - 1];
                } else {
                    $multiple = false;
                    $userLastNextEpisode = null;
                }
            } else {
                $multiple = false;
                $userLastNextEpisode = null;
            }

            $providerId = $schedule->getProviderId();
            if ($providerId) {
                $provider = $this->providerRepository->findOneBy(['providerId' => $providerId]);
                $providerName = $provider->getName();
                $providerLogo = $provider->getLogoPath() ? $this->imageConfiguration->getUrl('logo_sizes', 2) . $provider->getLogoPath() : null;
            }

            $schedules[] = [
                'id' => $schedule->getId(),
                'airAt' => $airAt->format('H:i'),
                'firstAirDate' => $firstAirDate,
                'frequency' => $frequency ?? 0,
                'override' => $override ?? false,
                'providerId' => $providerId,
                'providerName' => $providerName ?? null,
                'providerLogo' => $providerLogo ?? null,
                'targetTS' => $targetTS,
                'before' => $target ? $now->diff($target) : null,
                'dayList' => $scheduleDayOfWeek,
                'dayArr' => $dayArr,
                'userLastEpisode' => $userLastEpisode,
                'userNextEpisode' => $userNextEpisode,
                'multiple' => $multiple,
                'userLastNextEpisode' => $userLastNextEpisode,
                'tvLastEpisode' => $tvLastEpisode,
                'tvNextEpisode' => $tvNextEpisode,
                'toBeContinued' => $tv && $this->isToBeContinued($tv, $userLastEpisode),
                'tmdbStatus' => $tv['status'] ?? 'series not found',
            ];
        }
        return $schedules;
    }

    public function emptySchedule(): array
    {
        $dayArrEmpty = array_fill(0, 7, false);
        return [
            'id' => 0,
            'airAt' => "12:00",
            'firstAirDate' => null,
            'frequency' => 0,
            'override' => false,
            'providerId' => null,
            'providerName' => null,
            'providerLogo' => null,
            'targetTS' => null,
            'before' => null,
            'dayList' => [],
            'dayArr' => $dayArrEmpty,
            'userLastEpisode' => null,
            'userNextEpisode' => null,
            'multiple' => null,
            'userLastNextEpisode' => null,
            'tvLastEpisode' => null,
            'tvNextEpisode' => null,
            'toBeContinued' => null,
            'tmdbStatus' => null,
        ];
    }

    public function offsetEpisodeDate(?array $episode, int $offset, DateTimeInterface $time, string $timezone): ?array
    {
        if (!$episode) return null;
        $date = $episode['air_date'];
        $date = $this->dateService->newDateImmutable($date, $timezone, true);
        $date = $date->modify('+' . $offset . ' days');
        $date = $date->setTime($time->format('H'), $time->format('i'));
        $date = $date->format('Y-m-d H:i');
        $episode['air_date'] = str_replace(' ', 'T', $date);
        return $episode;
    }

    public function isToBeContinued(?array $tv, ?array $userLastEpisode): bool
    {
        if (($tv['next_episode_to_air'] && $tv['next_episode_to_air']['episode_type'] == 'standard')) {
            return true;
        }

        if (!$tv['next_episode_to_air'] && $userLastEpisode) {
            $episodeSeason = $this->getSeason($tv['seasons'], $userLastEpisode['season_number']);
            if ($episodeSeason && $episodeSeason['episode_count'] < $userLastEpisode['episode_number']) {
                return true;
            }
            if ($tv['status'] == 'Returning Series') {
                return true;
            }
        }

        if (in_array($tv['status'], ['Planned', 'In Production', 'Pilot', 'Returning Series'])) {
            return true;
        }
        return false;
    }

    public function inImages(?string $image, array $images): bool
    {
        if (!$image) return true;
        foreach ($images as $img) {
            if ($img->getimagePath() == $image) return true;
        }
        return false;
    }

    public function addSeries($id, $date): array
    {
        $series = $this->seriesRepository->findOneBy(['tmdbId' => $id]);

        $slugger = new AsciiSlugger();
        $tv = json_decode($this->tmdbService->getTv($id, 'en-US'), true);
        if (!$series) $series = new Series();
        $series->setBackdropPath($tv['backdrop_path']);
        $series->setCreatedAt($date);
        $series->setFirstAirDate($tv['first_air_date'] ? $this->dateService->newDateImmutable($tv['first_air_date'], "Europe/Paris", true) : null);
//        $series->setFirstAirDate($tv['first_air_date'] ? new DatePoint($tv['first_air_date']) : null);
        $series->setName($tv['name']);
        $series->setOriginalName($tv['original_name']);
        $series->setOriginCountry($tv['origin_country']);
        $series->setOverview($tv['overview']);
        $series->setPosterPath($tv['poster_path']);
        $series->setSlug($slugger->slug($tv['name']));
        $series->setStatus($tv['status']);
        $series->setTmdbId($id);
        $series->setUpdatedAt($date);
        $series->setVisitNumber(0);
        $this->seriesRepository->save($series, true);

        return [
            'series' => $this->seriesRepository->findOneBy(['tmdbId' => $id]),
            'tv' => $tv,
        ];
    }

    public function addSeriesToUser(User $user, Series $series, array $tv, DateTimeImmutable $date): UserSeries
    {
        $userSeries = $this->userSeriesRepository->findOneBy(['user' => $user, 'series' => $series]);
        if ($userSeries) {
            $this->addFlash('warning', $this->translator->trans('Series already added to your watchlist'));
        }

        if (!$userSeries) {
            $userSeries = new UserSeries($user, $series, $date);
            $this->userSeriesRepository->save($userSeries, true);
            $this->addFlash('success', $this->translator->trans('Series added to your watchlist'));
            $userSeries = $this->userSeriesRepository->findOneBy(['user' => $user, 'series' => $series]);
        }

        foreach ($tv['seasons'] as $season) {
            $this->addSeasonToUser($user, $userSeries, $season);
        }
        return $userSeries;
    }

    public function addSeasonToUser(User $user, UserSeries $userSeries, array $season): void
    {
        $series = $userSeries->getSeries();
        $language = $user->getPreferredLanguage() ?? "fr" . "-" . $user->getCountry() ?? "FR";
        $tvSeason = json_decode($this->tmdbService->getTvSeason($series->getTmdbId(), $season['season_number'], $language), true);
        if ($tvSeason) {
            $episodeCount = count($tvSeason['episodes']);
            $seasonNumber = $tvSeason['season_number'];
            foreach ($tvSeason['episodes'] as $episode) {
                $this->addEpisodeToUser($user, $userSeries, $episode, $seasonNumber, $episodeCount);
            }
        }
    }

    public function addEpisodeToUser(User $user, UserSeries $userSeries, array $episode, int $seasonNumber, int $episodeCount): void
    {
        $userEpisode = $this->userEpisodeRepository->findOneBy(['userSeries' => $userSeries, 'episodeId' => $episode['id']]);
        if ($userEpisode) {
            return;
        }
        $userEpisode = new UserEpisode($userSeries, $episode['id'], $seasonNumber, $episode['episode_number'], null);
        $airDate = $episode['air_date'] ? $this->dateService->newDateImmutable($episode['air_date'], $user->getTimezone() ?? 'Europe/Paris', true) : null;
        $userEpisode->setAirDate($airDate);
        if ($episode['episode_number'] == $episodeCount) {
            $this->userEpisodeRepository->save($userEpisode, true);
        } else {
            $this->userEpisodeRepository->save($userEpisode);
        }
    }

    public function getEpisodeHistory(User $user, int $dayCount, string $country, string $locale): array
    {
        $arr = $this->userEpisodeRepository->historyEpisode($user, $dayCount, $country, $locale);

        return array_map(function ($series) {
            if (!$series['posterPath']) {
                $series['posterPath'] = $this->getAlternatePosterPath($series['id']);
            }
            $series['posterPath'] = $series['posterPath'] ? '/series/posters' . $series['posterPath'] : null;
            $series['providerLogoPath'] = $series['providerLogoPath'] ? ($series['providerId'] > 0 ? $this->imageConfiguration->getCompleteUrl($series['providerLogoPath'], 'logo_sizes', 2) : '/images/providers' . $series['providerLogoPath']) : null;
            $series['upToDate'] = $series['watched_aired_episode_count'] == $series['aired_episode_count'];
            $series['remainingEpisodes'] = $series['aired_episode_count'] - $series['watched_aired_episode_count'];
            return $series;
        }, $arr);
    }

    public function getAlternatePosterPath(int $id): ?string
    {
        $posters = $this->seriesRepository->seriesPosters($id);
        if (count($posters)) {
            return $posters[rand(0, count($posters) - 1)]['image_path'];
        }
        return null;
    }

    public function getSeriesLocations(Series $series, string $locale): array
    {
        $tmdbId = $series->getTmdbId();
//        $filmingLocations = $this->filmingLocationRepository->findBy(['tmdbId' => $tmdbId]);
        $filmingLocations = $this->getFilmingLocations($tmdbId);

        $seriesLocations = $series->getLocations()['locations'] ?? [];
//        dump($seriesLocations);
        if (empty($seriesLocations)) {
            return ['map' => null, 'locations' => null, 'filmingLocations' => $filmingLocations];
        }
        $map = new Map();
        $count = count($seriesLocations);
        if ($count > 1) {
            $map->fitBoundsToMarkers();
        } else {
            $map->zoom(10)
                ->center(new Point($seriesLocations[0]['latitude'], $seriesLocations[0]['longitude']));
        }

        $seriesLocations = array_map(function ($location) use ($series, $locale, $map) {
            $localizedName = $series->getLocalizedName($locale)?->getName();
            $name = $series->getName();
            $uuid = Uuid::v7()->toString();
            if ($localizedName) {
                $name = $localizedName . ' - ' . $name;
            }
            $map->addMarker(new Marker(
                new Point($location['latitude'], $location['longitude']),
                $name,
                new InfoWindow('<strong>' . $name . '</strong> - ' . $location['description'], '<img src="' . $location['image'] . '" alt="' . $location['description'] . '" style="height: auto; width: 100%">'),
                ['draggable' => false, 'data-uuid' => $uuid]
            ));
            $location['uuid'] = $uuid;
            return $location;
        }, $seriesLocations);

        //        dump($seriesLocations);
        return ['map' => $map, 'locations' => $seriesLocations, 'filmingLocations' => $filmingLocations];
    }

    public function getFilmingLocations(int $tmdbId): array
    {
        $filmingLocations = $this->filmingLocationRepository->locations($tmdbId);
        $filmingLocationIds = array_map(fn($location) => $location['id'], $filmingLocations);
        $filmingLocationImages = $this->filmingLocationRepository->locationImages($filmingLocationIds);
        $flImages = [];
        foreach ($filmingLocationImages as $image) {
            $flImages[$image['filming_location_id']][] = $image;
        }
        foreach ($filmingLocations as &$location) {
            $location['filmingLocationImages'] = $flImages[$location['id']] ?? [];
        }
        dump([
            'findBy' => $this->filmingLocationRepository->findBy(['tmdbId' => $tmdbId]),
            'filmingLocations' => $filmingLocations,
            'filmingLocationIds' => $filmingLocationIds,
            'filmingLocationImages' => $filmingLocationImages
        ]);
        return $filmingLocations;
    }

    public function now(): DateTimeImmutable
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($user->getTimezone()) {
            $timezone = $user->getTimezone();
        } else {
            $timezone = 'Europe/Paris';
        }
        $now = $this->clock->now();
        try {
            $now = $now->setTimezone(new DateTimeZone($timezone));
        } catch (Exception) {
        }
        return $now;
    }

    public function date(string $dateString): DateTimeImmutable
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($user->getTimezone()) {
            $timezone = $user->getTimezone();
        } else {
            $timezone = 'Europe/Paris';
        }
        $date = null;
        try {
            $date = new DatePoint($dateString, new DateTimeZone($timezone));
        } catch (Exception) {
        }
        return $date;
    }

    public function checkSlug($series, $slug, $locale = 'fr'): bool|Response
    {
        $localizedName = $series->getLocalizedName($locale);
        $seriesSlug = $localizedName ? $localizedName->getSlug() : $series->getSlug();
        if ($seriesSlug !== $slug) {
            return $this->redirectToRoute('app_series_show', [
                'id' => $series->getId(),
                'slug' => $series->getSlug(),
            ], 301);
        }
        return true;
    }

    public function checkTmdbSlug($series, $slug, $localizedSlug = null): bool|Response
    {
        if ($localizedSlug) {
            $realSlug = $localizedSlug;
        } else {
            $slugger = new AsciiSlugger();
            $realSlug = $slugger->slug($series['name'])->lower()->toString();
        }
        if ($realSlug != "" && $realSlug !== $slug) {
            return $this->redirectToRoute('app_series_tmdb', [
                'id' => $series['id'],
                'slug' => $realSlug,
            ], 301);
        }
        return true;
    }

    public function localizedOverview($tv, $series, $request): string
    {
        if ($tv['overview']) return $tv['overview'];
        $overview = $series->getOverview();
        if (!strlen($series->getOverview())) {
            $usSeries = json_decode($this->tmdbService->getTv($series->getTmdbId(), 'en-US'), true);
            $overview = $usSeries['overview'];
            if (strlen($overview)) {
                try {
                    $usage = $this->deeplTranslator->translator->getUsage();
                    if ($usage->character->count + strlen($overview) < $usage->character->limit) {
                        /** @var TextResult $DeeplOverview */
                        $DeeplOverview = $this->deeplTranslator->translator->translateText($usSeries['overview'], null, $request->getLocale());
                        $overview = $DeeplOverview->text;
                        $series->setOverview($overview);
                        $this->seriesRepository->save($series, true);
                    }
                } catch (DeepLException) {
                }
            }
        }
        return $overview;
    }

    public function castAndCrew($tv): array
    {
        $slugger = new AsciiSlugger();
        $tv['credits']['cast'] = array_map(function ($cast) use ($slugger) {
            $cast['slug'] = $slugger->slug($cast['name'])->lower()->toString();
            $cast['profile_path'] = $cast['profile_path'] ? $this->imageConfiguration->getCompleteUrl($cast['profile_path'], 'profile_sizes', 2) : null; // w185
            return $cast;
        }, $tv['credits']['cast'] ?? []);
        $tv['credits']['guest_stars'] = array_map(function ($cast) use ($slugger) {
            $cast['slug'] = $slugger->slug($cast['name'])->lower()->toString();
            $cast['profile_path'] = $cast['profile_path'] ? $this->imageConfiguration->getCompleteUrl($cast['profile_path'], 'profile_sizes', 2) : null; // w185
            return $cast;
        }, $tv['credits']['guest_stars'] ?? []);
        $tv['credits']['crew'] = array_map(function ($crew) use ($slugger) {
            $crew['slug'] = $slugger->slug($crew['name'])->lower()->toString();
            $crew['profile_path'] = $crew['profile_path'] ? $this->imageConfiguration->getCompleteUrl($crew['profile_path'], 'profile_sizes', 2) : null; // w185
            return $crew;
        }, $tv['credits']['crew'] ?? []);

        usort($tv['credits']['cast'], function ($a, $b) {
            return !$a['profile_path'] <=> !$b['profile_path'];
        });
        usort($tv['credits']['guest_stars'], function ($a, $b) {
            return !$a['profile_path'] <=> !$b['profile_path'];
        });
        usort($tv['credits']['crew'], function ($a, $b) {
            return !$a['profile_path'] <=> !$b['profile_path'];
        });
        return $tv['credits'];
    }

    public function networks(array $tv): array
    {
        return array_map(function ($network) {
            $network['logo_path'] = $network['logo_path'] ? $this->imageConfiguration->getCompleteUrl($network['logo_path'], 'logo_sizes', 2) : null; // w92
            return $network;
        }, $tv['networks']);
    }

    public function getSeason(array $seasons, int $seasonNumber): array
    {
        foreach ($seasons as $season) {
            if ($season['season_number'] == $seasonNumber) {
                return $season;
            }
        }
        return [];
    }

    public function seasonsPosterPath(array $seasons, int $dayOffset = 0): array
    {
        $slugger = new AsciiSlugger();
        return array_map(function ($season) use ($slugger, $dayOffset) {
            if ($dayOffset && $season['air_date']) {
                $airDate = $season['air_date'];
                $season['air_date'] = $this->offsetDate($airDate, $dayOffset, 'Europe/Paris');
            }
            $season['slug'] = $slugger->slug($season['name'])->lower()->toString();
            $season['poster_path'] = $season['poster_path'] ? $this->imageConfiguration->getCompleteUrl($season['poster_path'], 'poster_sizes', 5) : null; // w500
            return $season;
        }, $seasons);
    }

    public function seasonEpisodes(array $season, UserSeries $userSeries, int $dayOffset): array
    {
        $user = $userSeries->getUser();
        $series = $userSeries->getSeries();
        $next_episode_to_air = $series->getNextEpisodeAirDate();
        $slugger = new AsciiSlugger();
        $seasonEpisodes = [];
        $userEpisodes = $this->userEpisodeRepository->getUserEpisodes($user->getId(), $userSeries->getId(), $season['season_number'], $user->getPreferredLanguage() ?? 'fr');

        foreach ($season['episodes'] as $episode) {
            if (!$next_episode_to_air && !$episode['air_date']) {
                continue;
            }
            $episode['still_path'] = $episode['still_path'] ? $this->imageConfiguration->getCompleteUrl($episode['still_path'], 'still_sizes', 3) : null; // w300
            $episode['air_date'] = ($episode['air_date'] ? $this->offsetDate($episode['air_date'], $dayOffset, $user->getTimezone() ?? 'Europe/Paris') : null);
            $episode['crew'] = array_map(function ($crew) use ($slugger, $user) {
                if (key_exists('person_id', $crew)) return null;
                $crew['profile_path'] = $crew['profile_path'] ? $this->imageConfiguration->getCompleteUrl($crew['profile_path'], 'profile_sizes', 2) : null; // w185
                $crew['slug'] = $slugger->slug($crew['name'])->lower()->toString();
                return $crew;
            }, $episode['crew'] ?? []);
            $episode['crew'] = array_filter($episode['crew'], function ($crew) {
                return $crew;
            });

            $episode['guest_stars'] = array_filter($episode['guest_stars'] ?? [], function ($guest) {
                return key_exists('id', $guest);
            });
            usort($episode['guest_stars'], function ($a, $b) {
                return !$a['profile_path'] <=> !$b['profile_path'];
            });
            $episode['guest_stars'] = array_map(function ($guest) use ($slugger, $series) {
                $guest['profile_path'] = $guest['profile_path'] ? $this->imageConfiguration->getCompleteUrl($guest['profile_path'], 'profile_sizes', 2) : null; // w185
                $guest['slug'] = $slugger->slug($guest['name'])->lower()->toString();
                if (!$guest['profile_path']) {
                    $guest['google'] = 'https://www.google.com/search?q=' . urlencode($guest['name'] . ' ' . $series->getName());
                }
                return $guest;
            }, $episode['guest_stars']);

            $userEpisode = $this->getUserEpisode($userEpisodes, $episode['episode_number']);//$userSeries->getEpisode($episode['id']);
            if (empty($userEpisode)) {
                $ue = new UserEpisode($userSeries, $episode['id'], $season['season_number'], $episode['episode_number'], null);
                $airDate = $episode['air_date'] ? $this->dateService->newDateImmutable($episode['air_date'], $user->getTimezone() ?? 'Europe/Paris') : null;
                $ue->setAirDate($airDate);
                $this->userEpisodeRepository->save($ue);
                $userSeries->addUserEpisode($ue);
                $this->userSeriesRepository->save($userSeries, true);
                $ue = $this->userEpisodeRepository->findOneBy(['userSeries' => $userSeries, 'episodeId' => $episode['id']]);

                $userEpisode['id'] = $ue->getId();
                $userEpisode['episode_id'] = $ue->getEpisodeId();
                $userEpisode['substitute_name'] = null;
                $userEpisode['localized_overview'] = null;
                $userEpisode['episode_number'] = $ue->getEpisodeNumber();
                $userEpisode['watch_at'] = null;
                $userEpisode['air_date'] = $ue->getAirDate();
                $userEpisode['provider_id'] = null;
                $userEpisode['provider_name'] = null;
                $userEpisode['provider_logo_path'] = null;
                $userEpisode['device_id'] = null;
                $userEpisode['device_name'] = null;
                $userEpisode['device_logo_path'] = null;
                $userEpisode['device_svg'] = null;
                $userEpisode['vote'] = 0;
                $userEpisode['number_of_view'] = 0;
            }

            $episode['user_episode'] = $userEpisode;
//            $episode['substitute_name'] = $this->userEpisodeRepository->getSubstituteName($episode['id']);
            $seasonEpisodes[] = $episode;
        }
        return $seasonEpisodes;
    }

    public function getUserEpisode(array $userEpisodes, int $episodeNumber): array
    {
        foreach ($userEpisodes as $userEpisode) {
            if ($userEpisode['episode_number'] == $episodeNumber) {
                if ($userEpisode['provider_id'] > 0)
                    $userEpisode['provider_logo_path'] = $userEpisode['provider_logo_path'] ? $this->imageConfiguration->getCompleteUrl($userEpisode['provider_logo_path'], 'logo_sizes', 2) : null; // w45
                else
                    $userEpisode['provider_logo_path'] = '/images/providers/' . $userEpisode['provider_logo_path'];
                return $userEpisode;
            }
        }
        return [];
    }

    public function offsetDate(string $dateString, int $offset, string $timezone): ?string
    {
        // Nombre de jours entre le 18 juillet 2022 et le 21 juin 2024 : 704
        if ($dateString && $offset) {
            $date = $this->dateService->newDateImmutable($dateString, $timezone);
            if ($offset > 0)
                $date = $date->modify("+$offset days");
            else if ($offset < 0)
                $date = $date->modify("$offset days");
            $dateString = $date->format('Y-m-d');
        }
        return $dateString;
    }

    public function seasonLocalizedOverview($series, $season, $seasonNumber, $request): array|null
    {
        $locale = $request->getLocale();
        $localized = false;
        $localizedResult = null;
        $localizedOverview = $this->seasonLocalizedOverviewRepository->findOneBy(['series' => $series, 'seasonNumber' => $seasonNumber, 'locale' => $locale]);

        if (!$localizedOverview) {
            if (!strlen($season['overview'])) {
                $usSeason = json_decode($this->tmdbService->getTvSeason($series->getTmdbId(), $seasonNumber, 'en-US'), true);
                $season['overview'] = $usSeason['overview'];
                if (strlen($season['overview'])) {
                    try {
                        $usage = $this->deeplTranslator->translator->getUsage();
//                    dump($usage);
                        if ($usage->character->count + strlen($season['overview']) < $usage->character->limit) {
                            $localizedOverview = $this->deeplTranslator->translator->translateText($season['overview'], null, $locale);
                            $localized = true;

                            $seasonLocalizedOverview = new SeasonLocalizedOverview($series, $seasonNumber, $localizedOverview, $locale);
                            $this->seasonLocalizedOverviewRepository->save($seasonLocalizedOverview, true);
                        } else {
                            $localizedResult = 'Limit exceeded';
                        }
                    } catch (DeepLException $e) {
                        $localizedResult = 'Error: code ' . $e->getCode() . ', message: ' . $e->getMessage();
                        $usage = [
                            'character' => [
                                'count' => 0,
                                'limit' => 500000
                            ]
                        ];
                    }
                }
                return [
                    'us_overview' => $usSeason['overview'],
                    'us_episode_overviews' => []/*array_map(function ($ep) use ($locale) {
                    return $this->episodeLocalizedOverview($ep, $locale);
                }, $usSeason['episodes'])*/,
                    'localized' => $localized,
                    'localizedOverview' => $localizedOverview,
                    'localizedResult' => $localizedResult,
                    'usage' => $usage ?? null
                ];
            }
        } else {
            return [
                'us_overview' => null,
                'us_episode_overviews' => [],
                'localized' => true,
                'localizedOverview' => $localizedOverview->getOverview(),
                'localizedResult' => null,
                'usage' => null
            ];
        }
        return null;
    }

//    public function episodeLocalizedOverview($episode, $locale): string
//    {
//        $episodeId = $episode['id'];
//        $localizedOverview = $this->episodeLocalizedOverviewRepository->findOneBy(['episodeId' => $episodeId, 'locale' => $locale]);
//        if ($localizedOverview) {
////            dump('we have it');
//            return $localizedOverview->getOverview();
//        }
//        $overview = $episode['overview'];
//        if (strlen($overview)) {
//            try {
//                $usage = $this->deeplTranslator->translator->getUsage();
////                dump($usage);
//                if ($usage->character->count + strlen($overview) < $usage->character->limit) {
//                    $overview = $this->deeplTranslator->translator->translateText($overview, null, $locale);
//                    $localizedOverview = new EpisodeLocalizedOverview($episodeId, $overview, $locale);
//                    $this->episodeLocalizedOverviewRepository->save($localizedOverview, true);
//                }
//            } catch (DeepLException) {
//            }
//        }
//        return $overview;
//    }

    public function watchProviders($tv, $country): array
    {
        $watchProviders = [];
        if (isset($tv['watch/providers']['results'][$country])) {
            $watchProviders = $tv['watch/providers']['results'][$country];
        }
        $flatrate = $watchProviders['flatrate'] ?? [];
        $rent = $watchProviders['rent'] ?? [];
        $buy = $watchProviders['buy'] ?? [];

        $flatrate = array_map(function ($wp) {
            return [
                'provider_id' => $wp['provider_id'],
                'provider_name' => $wp['provider_name'],
                'logo_path' => $wp['logo_path'] ? $this->imageConfiguration->getCompleteUrl($wp['logo_path'], 'logo_sizes', 2) : null, // w45
            ];
        }, $flatrate);
        $rent = array_map(function ($wp) {
            return [
                'provider_id' => $wp['provider_id'],
                'provider_name' => $wp['provider_name'],
                'logo_path' => $wp['logo_path'] ? $this->imageConfiguration->getCompleteUrl($wp['logo_path'], 'logo_sizes', 2) : null, // w45
            ];
        }, $rent);
        $buy = array_map(function ($wp) {
            return [
                'provider_id' => $wp['provider_id'],
                'provider_name' => $wp['provider_name'],
                'logo_path' => $wp['logo_path'] ? $this->imageConfiguration->getCompleteUrl($wp['logo_path'], 'logo_sizes', 2) : null, // w45
            ];
        }, $buy);
        return [
            'flatrate' => $flatrate,
            'rent' => $rent,
            'buy' => $buy,
        ];
    }

    public function getWatchProviders($watchRegion): array
    {
        // May be unavailable - when Youtube was added for example
        // TODO: make a command to regularly update db
//        $providers = json_decode($this->tmdbService->getTvWatchProviderList($language, $watchRegion), true);
//        dump(['TV providers' => $providers]);
//        $providers = $providers['results'];
//        if (count($providers) == 0) {
        $providers = $this->watchProviderRepository->getWatchProviderList($watchRegion);
//        }
        $watchProviders = [];
        foreach ($providers as $provider) {
            $watchProviders[$provider['provider_name']] = $provider['provider_id'];
        }
        $watchProviderNames = [];
        foreach ($providers as $provider) {
            $watchProviderNames[$provider['provider_id']] = $provider['provider_name'];
        }
        $watchProviderLogos = [];
        foreach ($providers as $provider) {
            if ($provider['provider_id'] > 0)
                $watchProviderLogos[$provider['provider_id']] = $this->imageConfiguration->getCompleteUrl($provider['logo_path'], 'logo_sizes', 2);
            else
                $watchProviderLogos[$provider['provider_id']] = '/images/providers' . $provider['logo_path'];
        }
        uksort($watchProviders, function ($a, $b) {
            return strcasecmp($a, $b);
        });
        $list = [];
        foreach ($watchProviders as $key => $value) {
            $list[] = ['provider_id' => $value, 'provider_name' => $key, 'logo_path' => $watchProviderLogos[$value]];
        }

        return [
            'select' => $watchProviders,
            'logos' => $watchProviderLogos,
            'names' => $watchProviderNames,
            'list' => $list,
        ];
    }

    public function getKeywords(): array
    {
        $keywords = $this->keywordRepository->findby([], ['name' => 'ASC']);

        $keywordArray = [];
        foreach ($keywords as $keyword) {
            $keywordArray[$keyword->getName()] = $keyword->getKeywordId();
        }
        return $keywordArray;
    }

    public function getSearchString($data): string
    {
        // App\DTO\SeriesAdvancedSearchDTO {#811 ▼
        //  *-language: "fr"
        //  *-timezone: "Europe/Paris"
        //  *-watchRegion: "FR"
        //  -firstAirDateYear: 2023                     -> first_air_date_year
        //  -firstAirDateGTE: null                      -> first_air_date.gte
        //  -firstAirDateLTE: null                      -> first_air_date.lte
        //  -withOriginCountry: null                    -> with_origin_country
        //  -withOriginalLanguage: null                 -> with_original_language
        //  -withWatchMonetizationTypes: "flatrate"     -> with_watch_monetization_types
        //  -withWatchProviders: "119"                  -> with_watch_providers
        //  -watchProviders: array:59 [▶]
        //  -withRuntimeGTE: 0                          -> with_runtime.gte
        //  -withRuntimeLTE: 0                          -> with_runtime.lte
        //  -withStatus: null                           -> with_status
        //  -withType: null                             -> with_type
        //  -sortBy: "popularity.desc"                  -> sort_by
        //  *-page: 1
        //}
        $page = $data->getPage();
        $language = $data->getLanguage();
        $timezone = $data->getTimezone();
        $watchRegion = $data->getWatchRegion();
        $firstAirDateYear = $data->getFirstAirDateYear();
        $firstAirDateGTE = $data->getFirstAirDateGTE()?->format('Y-m-d');
        $firstAirDateLTE = $data->getFirstAirDateLTE()?->format('Y-m-d');
        $withOriginCountry = $data->getWithOriginCountry();
        $withOriginalLanguage = $data->getWithOriginalLanguage();
        $withWatchMonetizationTypes = $data->getWithWatchMonetizationTypes();
        $withWatchProviders = $data->getWithWatchProviders();
        $withKeywords = $data->getWithKeywords();
        $withRuntimeGTE = $data->getWithRuntimeGTE();
        $withRuntimeLTE = $data->getWithRuntimeLTE();
        $withStatus = $data->getWithStatus();
        $withType = $data->getWithType();
        $sortBy = $data->getSortBy();

        $searchString = "&include_adult=false&page=$page&language=$language&timezone=$timezone&watch_region=$watchRegion";
        if ($firstAirDateYear) $searchString .= "&first_air_date_year=$firstAirDateYear";
        if ($firstAirDateGTE) $searchString .= "&first_air_date.gte=$firstAirDateGTE";
        if ($firstAirDateLTE) $searchString .= "&first_air_date.lte=$firstAirDateLTE";
        if ($withOriginCountry) $searchString .= "&with_origin_country=$withOriginCountry";
        if ($withOriginalLanguage) $searchString .= "&with_original_language=$withOriginalLanguage";
        if ($withWatchMonetizationTypes) $searchString .= "&with_watch_monetization_types=$withWatchMonetizationTypes";
        if ($withWatchProviders) $searchString .= "&with_watch_providers=$withWatchProviders";
        if ($withKeywords) $searchString .= "&with_keywords=$withKeywords";
        if ($withRuntimeGTE) $searchString .= "&with_runtime.gte=$withRuntimeGTE";
        if ($withRuntimeLTE) $searchString .= "&with_runtime.lte=$withRuntimeLTE";
        if ($withStatus) $searchString .= "&with_status=$withStatus";
        if ($withType) $searchString .= "&with_type=$withType";
        if ($sortBy) $searchString .= "&sort_by=$sortBy";
        return $searchString;
    }

    public function getSearchResult($searchResult, $slugger): array
    {
        return array_map(function ($tv) use ($slugger) {
            $this->saveImage("posters", $tv['poster_path'], $this->imageConfiguration->getUrl('poster_sizes', 5));
            $tv['poster_path'] = $tv['poster_path'] ? '/series/posters' . $tv['poster_path'] : null;

            $name = $tv['name'];
            $slug = $slugger->slug($name)->lower()->toString();
            if ($slug == "") {
                $tvUS = $this->tmdbService->getTv($tv['id'], 'en-US');
                $tvUS = json_decode($tvUS, true);
                $slug = $slugger->slug($tvUS['name'])->lower()->toString();
                if ($slug == "") {
                    $slug = $tv['id'];
                } else {
                    $name = $tvUS['name'];
                }
            }

            return [
                'tmdb' => true,
                'id' => $tv['id'],
                'name' => $name,
                'air_date' => $tv['first_air_date'],
                'slug' => $slug,
                'poster_path' => $tv['poster_path'],
            ];
        }, $searchResult['results'] ?? []);
    }

    public function getOneResult($tv, $slugger): Response
    {
        return $this->redirectToRoute('app_series_tmdb', [
            'id' => $tv['id'],
            'slug' => $slugger->slug($tv['name'])->lower()->toString(),
        ]);
    }

    public function getProjectDir(): string
    {
        return $this->getParameter('kernel.project_dir');
    }

    public function saveImage($type, $imagePath, $imageUrl, $localPath = "/series/"): void
    {
        if (!$imagePath) return;
        $root = $this->getParameter('kernel.project_dir');
        $this->saveImageFromUrl(
            $imageUrl . $imagePath,
            $root . "/public" . $localPath . $type . $imagePath
        );
    }

    public function saveImageFromUrl($imageUrl, $localeFile): bool
    {
        if (!file_exists($localeFile)) {

            // Vérifier si l'URL de l'image est valide
            if (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                // Récupérer le contenu de l'image à partir de l'URL
                try {
                    $imageContent = file_get_contents($imageUrl);

                    // Ouvrir un fichier en mode écriture binaire
                    $file = fopen($localeFile, 'wb');

                    // Écrire le contenu de l'image dans le fichier
                    fwrite($file, $imageContent);

                    // Fermer le fichier
                    fclose($file);

                    return true;
                } catch (Exception) {
                    return false;
                }
            } else {
                return false;
            }
        }
        return true;
    }

    public function copyImage($source, $destination): bool
    {
        if (!file_exists($destination)) {
            if (file_exists($source)) {
                copy($source, $destination);
                return true;
            }
        }
        return false;
    }

    public function getRootDir(): string
    {
        return $this->getParameter('kernel.project_dir');
    }

    public function fixFilmingLocations(): void
    {
        $arr = [
            3 => 'Leaf Lake Kan Resort - Episode 10 @25:43 - 399, Tha Kradan, Si Sawat District, Kanchanaburi 71250, Thaïlande',
            4 => 'Serene WaterPark - Episode 12 @29:00 - เลขที่ 1/11 หมู่ที่ 2 Thanon Pla, Tambon Phla, Ban Chang District, Rayong 21130, Thaïlande',
            5 => 'The Belnord sur la 86e rue',
            14 => 'Université de Mahidol, Salaya, Thaïlande',
            15 => 'Tha Phae Gate - Episode 5@17:33 - Tha Phae Road, Chang Khlan Sub-district, Mueang Chiang Mai District, Chiang Mai 50200, Thaïlande',
            16 => 'Maysa.BKK - 55/9 Sena Niwet Soi 112, Lat Phrao, Bangkok 10230, Thaïlande',
            17 => 'Granny Café Si Wari - MQXP+W5R, Sisa Chorakhe Noi, Bang Sao Thong District, Samut Prakan 10540, Thaïlande',
            18 => 'Greyhound Cafe Emquartier - Room 2C03 - 04 ชั้น 2 35 Sukhumvit Rd, Khlong Tan Nuea, Watthana, Bangkok 10110, Thaïlande',
            19 => 'Parking Toys - No.1, 29 ถนน ประเสริฐมนูกิจ Chorakhe Bua, Lat Phrao, Bangkok 10230, Thaïlande',
            20 => 'Suan Luang Rama IX - Chaloem Phrakiat Ratchakan Thi 9 Rd, เเขวง หนองบอน Prawet, Bangkok 10250, Thaïlande',
            21 => 'Siam Amazing Park - 203 Suan Sayam Rd, Khan Na Yao, Bangkok 10230, Thaïlande',
            22 => 'Wat Arun - 158 Thanon Wang Doem, Wat Arun, Bangkok Yai, Bangkok 10600, Thaïlande',
            23 => 'Wat Phra That Doi Suthep - Suthep, Mueang Chiang Mai, Chiang Mai 50200, Thaïlande',
            24 => 'Chiang Mai Zoo - 100 Huay Kaew Rd, Tambon Su Thep, Mueang Chiang Mai District, Chiang Mai 50200, Thaïlande',
            25 => 'Ao Nam Mao Pier - Ao Nang, Mueang Krabi, Province de Krabi 81000, Thaïlande',
            26 => 'The SeaShell - 999 Moo 6 Laemphopattana 1 Road, Sai Thai, Mueang Krabi District, Krabi 81000, Thaïlande',
            27 => 'Railay Beach - Ao Nang, Province de Krabi, Thaïlande',
            28 => 'Le magasin de reprographie des parents du stagiaire Ryan',
            29 => 'Lieu de tournage, épisode 8 - Royal Thai Air Force and National Aviation Museum',
            30 => 'Wine Connection Big C Ratchadamri',
            31 => 'Sub-Zero Ice Skate Club Sukhumvit',
            32 => 'Bangkok - Joe\'s apartment',
            33 => 'Slōlē Café & Garden - 9 Chok Chai 4 Soi 52/1, Lat Phrao, Bangkok 10230, Thaïlande',
            34 => 'Manor Studio - 103 Ramkhamhaeng 24 Alley, Lane 14, Hua Mak, Bang Kapi District, Bangkok 10240, Thaïlande',
            35 => 'Oliva cafe - 127, Ban Klang, Mueang Pathum Thani District, Pathum Thani 12000, Thaïlande',
            36 => 'Kaohsiung, Taïwan',
            37 => 'Hi-ing Music Hall - Episode 8@18:18 - No. 1號, Zhen\'ai Rd, Yancheng District, Kaohsiung City, Taïwan 803',
            38 => '804, Taïwan, Kaohsiung, District de Gushan - Episode 9 @ 11:25',
            39 => 'Hamasen Railway Cultural Park - Episode 9 @23:45 - No. 32號, Gushan 1st Rd, Gushan District, Kaohsiung City, Taïwan 804',
            40 => 'Concert at Sea\'s guinguette - Episode 11 - Concert - No. 109-1號, Binhai 1st Rd, Gushan District, Kaohsiung City, Taïwan 804',
            41 => 'Kuan Du Bridge -, 121.458866',
            42 => 'Tamsui',
            43 => 'Pescador Café with a view of the bridge where Sheng Wang and classmates took selfies - No. 253號, Zhongzheng Rd, Tamsui District, New Taipei City, Taïwan 251',
            44 => 'Selfies\'s Bridge in episode 3',
            45 => 'Épisode 5, saison 1 ~ @44:00 ',
            46 => 'Near LB Cafe - Episode 10',
            47 => 'Commissariat - 84 Rue de Trévise 59000 Lille',
            48 => 'Wattignies - Habitation de Morgan Alvaro',
            49 => 'Morbecque - Le château',
            50 => 'Le Touquet - Vacances en famille - Episode 4, saison 1',
            51 => 'La Corse - Épisode 8, saison 2',
            52 => 'Islande - Épisode 4, saison 3',
            53 => 'Dunkerque - Épisode 6, saison 1',
            54 => 'Le Quesnoy - Épisode 2, saison 2',
            55 => 'Le Clos Barthélemy - 62156 Éterpigny',
            56 => 'Asiatique Sky - 2194 Charoen Krung Road, Wat Phraya Krai, Bang Kho Laem, Bangkok 10120, Thaïlande',
            57 => 'Suan Luang Rama IX - Chaloem Phrakiat Ratchakan Thi 9 Rd, เเขวง หนองบอน Prawet, Bangkok 10250, Thaïlande',
            58 => 'Jupiter Trevi Resort and Spa - Khanong Phra, Pak Chong District, Nakhon Ratchasima 30130, Thaïlande',
            59 => 'Ao Phrao Beach - HHV9+64X Unnamed Road Ko Kut, Ko Kut District, Trat 23000, Thaïlande',
            60 => 'Chonthicha Seafood - JH5V+9JC 7 Ko Kut District, Trat 23000, Thaïlande',
            61 => 'Saphan Nam Leuk Pier - MG3M+9G7, Yothathikan Trat Wat Rat-tha Ruea Nam Luek Rd, Ko Kut, Ko Kut District, Trat 23000, Thaïlande',
            62 => 'Monfai Cultural Center / Living Museum - RX9R+M3V, Tambon Chang Phueak, Mueang Chiang Mai District, Chiang Mai 50300, Thaïlande',
            63 => 'Khua Lek (Iron Bridge) - Q2M3+HRW, Loi Kroh Rd, Tambon Chang Moi, Mueang Chiang Mai District, Chiang Mai 50100, Thaïlande',
            64 => 'บ้านไวทยภักดิ์ – Baan Vaithayaphak - หมู่ที่ 1 99 Hua Wiang, Sena District, Phra Nakhon Si Ayutthaya 13110, Thaïlande',
            65 => 'บุราณ บางโตนด – Burann Bangtanode - 22 หมู่ 3, Bang Tanot, Photharam District, Ratchaburi 70120, Thaïlande',
            66 => '13 Coins Airport Hotel - 30/55 Chulakasem 19 Alley, Tambon Bang Khen, Mueang Nonthaburi District, Nonthaburi 11000, Thaïlande',
            67 => 'Wat Intharawat (Wat Ton Kwen) - PWFG+45F บ้านต้นเกว๋น ซอย 3 Nong Kwai, Hang Dong District, Chiang Mai 50230, Thaïlande',
            68 => 'Seoul - Dongho Bridge - Saison 2',
            69 => 'Samila Beach - Province de Songkhla, Thaïlande',
            70 => 'Tinsulanonda Bridge - 5GRX+C63 ทะเลสาบสงขลา, Sathing Mo, Amphoe Singhanakhon, Songkhla 90280, Thaïlande',
            71 => 'Hub Ho Hin (Red Rice Mill) - 5HXQ+9C4, 13 Nakhonnok St, Bo Yang, Mueang Songkhla District, Songkhla 90000, Thaïlande',
            72 => 'Homestay Ban Nai Singtho - Unnamed Road Khuha Tai, Rattaphum District, Songkhla 90180, Thaïlande',
            73 => 'Khao Khuha Mountain - Khuha Tai, Rattaphum District, Songkhla 90180, Thaïlande',
            74 => 'Songkhla National Museum - 6H2Q+WCM road Wichian Chom - Rong Mueang Alley Bo Yang, Mueang Songkhla District, Songkhla 90000, Thaïlande',
            75 => 'Chao Phraya Sky Park - PFQX+J95, Phra Pokklao Brg, Wang Burapha Phirom, Phra Nakhon, Bangkok 10200, Thaïlande',
            76 => 'Chatuchak Weekend Market - 587, 10 Kamphaeng Phet 2 Rd, Khwaeng Chatuchak, Chatuchak, Bangkok 10900, Thaïlande',
            77 => 'Pusan - Corée du sud - Episode 1 @46:11',
            78 => 'Pusan - Corée du sud - Épisode 2 @10:05',
            79 => 'Busan - Épisode 2 @46:15',
            80 => 'Samrong Klang, Samut Prakan',
            81 => 'Rosewood Hotel, Bangkok',
            82 => 'Bangkok Yacht Club Condominium',
            83 => 'Tank1969space',
            84 => 'Red Brick Kitchen by Chef Aue, Bangkok',
            85 => 'Soopanava Group co.,Ltd - 107, Song Khanong, Phra Pradaeng District, Samut Prakan 10130, Thailand',
            86 => 'Thonburi District - 196 Bangkok 10160, Thaïlande',
            87 => 'Moldna Club - 168 Soi Charan Sanitwong 92, Bang O, Bang Phlat, Bangkok 10700, Thaïlande',
            88 => 'Si Phraya Pier - Bang Rak, Bangkok 10500, Thaïlande',
            89 => 'Phraeng Phuthon Rd - 109 Phraeng Phuthon Rd, Khwaeng San Chao Pho Sua, Khet Phra Nakhon, Krung Thep Maha Nakhon 10200, Thaïlande',
            90 => 'Connext Sriracha - 168 292 ม.9 Sukhumvit Rd, Bang Phra, Si Racha District, Chon Buri 20110, Thaïlande',
            91 => 'Medici Kitchen & Bar (Hotel Muse) - 55, 55/555 โรงแรมมิวส์ กรุงเทพฯ 555 Lang Suan Rd, Lumphini, Pathum Wan, Bangkok 10330, Thaïlande',
            92 => 'Huachiew Chalermprakiet University, Province de Samut Prakan, Thaïlande',
            93 => 'Institut de technologie de King Mongkut, district de Lat Krabang',
            94 => 'Doi Pha Tang - Chiang Khian, Thoeng, Province de Chiang Rai 57230, Thaïlande',
            95 => 'Tha Phae Gate - Tha Phae Road, Chang Khlan Sub-district, Mueang Chiang Mai District, Chiang Mai 50200, Thaïlande',
            96 => 'Huai Mae Sai Waterfall - 2P38+RP2, Mae Yao, Mueang Chiang Rai District, Chiang Rai 57100, Thaïlande',
            97 => 'The Village was made for show in Chiang Rai',
            98 => 'Jasmine 59 Hotel - 9 Sukhumvit 59(Boonchana Sukhumvit Rd, Klongtan-Nua Watthana, Bangkok 10110, Thaïlande',
            99 => 'Le Khwam Luck Cafe Bar and Restaurant - 98 5 Ekkamai 22 Aly, Khlong Tan Nuea, Watthana, Bangkok 10110, Thaïlande',
            100 => 'White House 36 - 36 Borromratchachonnani 62/6, Sala Thammasop, Tawiwattana, Bangkok 10170, Thaïlande',
            101 => 'St. Thomas Aquinas Church - 6 Soi Ramkhamheang 184, Min Buri, Bangkok 10510, Thaïlande',
            102 => 'Hin Khao Ngu Park - HQ9G+976, Ko Phlappla, Mueang Ratchaburi, Ratchaburi 70000, Thaïlande',
            103 => 'Pattaya - Episode 5',
            104 => 'Pattaya - Episode 5 @04:21',
            105 => 'Pattaya - Episode 5 @04:24',
            106 => 'Bangkok Yacht Club Condominium - 64 Rat Burana Rd, Bang Pakok, Rat Burana, Bangkok 10140, Thaïlande',
            107 => 'Chao Pho Khao Yai Shrine - 5R94+7MM, Tha Thewawong, Ko Sichang District, Chon Buri 20120, Thaïlande',
            108 => 'Azure Hostel- 589 Phra Sumen Rd, Wat Bowon Niwet, Phra Nakhon, Bangkok 10200, Thaïlande',
            109 => 'Sai Kaew Beach - Amphoe Muang, Rayong 21160, Thaïlande',
            110 => 'Blue Cafe Sattahip - ',
            111 => 'Homely Nest Phrae - Episode 9 - 8 Rong Sor 3, Nai Wiang, Mueang Phrae District, Phrae 54000, Thaïlande',
            112 => 'Sano Loi Canal, Bridge and Flats - อาคาร นนทพิมลชัย 117-118​ หมู่​ 2​ ต.โสนลอย​ อ.บางบัวทอง​ 0892054444 ​ Sano Loi, Bang Bua Thong District, Nonthaburi 11110, Thaïlande',
            113 => 'Bebop (Coffee & Music) - Phahonyothin Rd, ลาดยาว จตุจักร Bangkok 10900, Thaïlande',
            114 => 'Somdet Phra Srinakarin Park - Prachachuen-Parkkred Rd, Ban Mai, Pak Kret District, Nonthaburi 11120, Thaïlande',
            115 => 'Skywalk Wat Khao Tabaek - 4369+C8G Unnamed Road Si Racha District, Chon Buri 20110, Thaïlande',
            116 => 'Hua Lamphong train station - Rong Mueang Rd, Rong Muang, Pathum Wan, Bangkok 10330, Thaïlande',
            150 => 'London Eye',
            151 => 'Xi\'an Jiaotong–Liverpool University',
            152 => 'Shanghai Science and Technology Museum',
            153 => 'The F4\'s room was filmed at MixPace Mandela in Huangpu District',
            154 => 'The rooftop scenes among others were filmed at Now Factory in Jiading District',
            155 => 'Various scenes were filmed at Suzhou Poly Grand Theatre in Wuzhong District, Suzhou',
            157 => 'Sunan Shuofang International Airport',
            158 => 'Jiangwan Stadium, Where Si waited for Shan Cai in the rain',
            159 => 'Changbaishan Maplewood Chalet in Jilin',
            160 => 'Where Si and Shan Cai go after escaping his party Hyatt on the Bund Hotel in Hongkou District',
            161 => 'Binjiang Park in Pudong',
            162 => 'Assumption University Suvarnabhumi Campus - 88 หมู่ที่ 8 ถนน บางนา-ตราด Bang Sao Thong, Bang Sao Thong District, Samut Prakan 10540, Thaïlande',
            163 => 'Slōlē Café & Garden - 9 Chok Chai 4 Soi 52/1, Lat Phrao, Bangkok 10230, Thaïlande',
            164 => 'Medici Kitchen & Bar (Hotel Muse) - 55, 55/555 โรงแรมมิวส์ กรุงเทพฯ 555 Lang Suan Rd, Lumphini, Pathum Wan, Bangkok 10330, Thaïlande',
            165 => 'China Town / Yaowarat Rd - PGR5+4W6, Yaowarat Rd, Khwaeng Samphanthawong, Khet Samphanthawong, Bangkok 10100, Thaïlande',
            166 => 'Democracy Monument - QG42+MPQ, Ratchadamnoen Klang Rd, Wat Bowon Niwet, Phra Nakhon, Bangkok 10200, Thaïlande',
            167 => 'Fotoclub BKK - 1158 Charoen Krung 32 Alley, Bang Rak, Bangkok 10500, Thaïlande',
            168 => '“Eiffel Tower” - Av. Gustave Eiffel, 75007 Paris',
            169 => 'Kopitiam by Wilai (โกปี่เตี่ยม) - 18 Thalang Rd, Tambon Talat Yai, Mueang Phuket District, Phuket 83000, Thaïlande',
            170 => 'Promthep Cape (แหลมพรหมเทพ) - แหลมพรหมเทพ, Rawai, Mueang Phuket District, Phuket 83100, Thaïlande',
            171 => 'Bang Neow Shrine - V9GV+JPH, Tambon Talat Yai, Mueang Phuket District, Phuket 83000, Thaïlande',
            172 => 'Ao Po Grand Marina - 113 Pa Klok, Thalang District, Phuket 83110, Thaïlande',
            173 => 'Thanon Talang/Soi Romanee (Various Old Town streets) - V9PQ+5MG, Tambon Talat Yai, Mueang Phuket District, Phuket 83000, Thaïlande',
            174 => 'Cape Panwa Hotel - 27, 27/2, Mu 8 Sakdidej Rd, Wichit, Mueang Phuket District, 83000, Thaïlande',
            175 => 'On On Hotel - 19 Phangnga Rd, Talat Yai, Mueang Phuket District, Phuket 83000, Thaïlande',
            176 => 'Dibuk Restaurant - 69 Dibuk Rd, Tambon Talat Nuea, Mueang Phuket District, Phuket 83000, Thaïlande',
            177 => 'Sangtham Shrine - V9MQ+P4G, Tambon Talat Yai, Mueang Phuket District, Phuket 83000, Thaïlande',
            178 => 'Phuket Thai Hua Museum - 28 Krabi, Tambon Talat Nuea, Mueang Phuket District, Phuket 83000, Thaïlande',
            179 => 'Laem Ka Beach - Q8HP+5RQ Unnamed Road Rawai, Mueang Phuket District, Phuket, Thaïlande',
            180 => 'Iron Balls Parlour & Saloon - 45 Sukhumvit Rd, Khlong Toei, Bangkok 10110, Thaïlande',
            181 => 'Banyan Tree Dinner Cruise Boat - 21/100 S Sathon Rd, Thung Maha Mek, Sathon, Bangkok 10120',
            182 => 'Red Brick Kitchen by Chef Aue - 37 Chokchai 4 Soi 54 Yaek 6, Lat Phrao, Bangkok 10230, Thaïlande',
            183 => 'Rosewood Hotel - Phloen Chit Rd, Khwaeng Lumphini, Pathum Wan, Krung Thep Maha Nakhon 10330, Thaïlande',
            184 => 'Medici Kitchen & Bar (Hotel Muse) - 55, 55/555 โรงแรมมิวส์ กรุงเทพฯ 555 Lang Suan Rd, Lumphini, Pathum Wan, Bangkok 10330, Thaïlande',
            185 => 'Sing Sing Theater - 45 Sukhumvit 45 Alley, Khwaeng Khlong Tan Nuea, Watthana, Bangkok 10110, Thaïlande',
            186 => 'Portobello & Désiré - เกษตรนวมินทร์ ตอหม้อ 168, 40 ซ, 22 Prasert-Manukitch Rd, Lat Phrao, Bangkok 10230, Thaïlande',
            187 => 'Wat Arun - 158 Thanon Wang Doem, Wat Arun, Bangkok Yai, Bangkok 10600, Thaïlande',
            188 => 'Grand Canyon - 20000 Chon Buri By Pass, Huai Kapi, Chon Buri District, Chon Buri 20000, Thaïlande',
            189 => 'Chan Ta Then Waterfall - 62RW+V5H, Bang Phra, Si Racha District, Chon Buri 20110, Thaïlande',
            190 => 'Wat Kanlayanamit Woramahawihan - 371 ซอย อรุณอมรินทร์ 6 เเขวง วัดกัลยาณ์, Thon Buri, Bangkok 10600, Thaïlande',
            191 => 'The House on Sathorn / W Hotel Bangkok - 106 N Sathon Rd, Silom, Bang Rak, Bangkok 10500, Thaïlande',
            192 => 'Hotel Muse (Paranim Penthouse) - 55, 555 Lang Suan Rd, Khwaeng Lumphini, Pathum Wan, Bangkok 10330, Thaïlande',
            193 => 'CAMELOTS - 67, 111 Ekkachai Rd, Khlong Bang Phran, Bang Bon, Bangkok 10150, Thaïlande',
            194 => 'The Manor Studio - 103 Ramkhamhaeng 24 Alley, Lane 14, Hua Mak, Bang Kapi District, Bangkok 10240, Thaïlande',
            195 => 'Wat Arun Ferry Pier - Wat Arun, Bangkok Yai, Bangkok 10600, Thaïlande',
            196 => 'Chana City Residence - 829 Pracha Uthit Rd, Samsen Nok, Huai Khwang, Bangkok 10310, Thaïlande',
            197 => 'Tinidee Hotel Bangkok Golf Club - 99/3 ถนน ติวานนท์ Bang Kadi, Amphoe Mueang Pathum Thani, Pathum Thani 12000, Thaïlande',
            198 => '‘Eco Village’, Hua Hin Fishing Pier - HXG6+892, Hua Hin, Thaïlande',
            199 => 'Rangsit University - 52 347 Phahonyothin Rd, Lak Hok, Mueang Pathum Thani District, Pathum Thani 12000, Thaïlande',
            200 => 'Khao Tao Beach Lodge Old Siam - House Number 15 Khao Tao Beach 77110, Hua Hin District, Prachuap Khiri Khan 77110, Thaïlande',
            201 => 'Sai Noi Beach - FX3J+CMV Sai Noi Beach Road Pak Nam Pran, Hua Hin District, Prachuap Khiri Khan 77220, Thaïlande',
            202 => 'Slōlē Café & Garden - 9 Chok Chai 4 Soi 52/1, Lat Phrao, Bangkok 10230, Thaïlande',
            203 => 'Northgate Ratchayothin - 248 Ratchadaphisek Rd, Khwaeng Lat Yao, Khet Chatuchak, Krung Thep Maha Nakhon 10900, Thaïlande',
            204 => '43 Kamphaeng Phet Rd - Phahon Yothin Rd, Khwaeng Chatuchak, Khet Chatuchak, Krung Thep Maha Nakhon 10900, Thaïlande',
            205 => 'Saphan Han - 4 592 Chakkraphet Rd, Wang Burapha Phirom, Phra Nakhon, Bangkok 10200, Thaïlande',
            206 => 'Wat Arun - 158 Thanon Wang Doem, Wat Arun, Bangkok Yai, Bangkok 10600, Thaïlande',
            207 => 'Ong Ang Walking Street - PGV3+Q4M, Wang Burapha Phirom, Phra Nakhon, Bangkok 10200, Thaïlande',
            208 => 'Erawan Falls (Level 4) - Cette cascade du parc national d\'Erawan comporte 7 niveaux accessibles par des sentiers et des passerelles. - Tha Kradan, Amphoe Si Sawat, Province de Kanchanaburi 71250, Thaïlande',
            209 => 'Sea Life Bangkok - ชั้น บี1-บี2 สยามพารากอน (Étage B1-B2 Siam Paragon) 991 Rama I Rd, Pathum Wan, Bangkok 10330, Thaïlande',
            210 => 'Bang Na Pride Hotel and Residence - 2 Bang Na-Trat Frontage Rd, Bang Kaeo, Bang Phli District, Samut Prakan 10540, Thaïlande',
            211 => 'Dream Park Resort - 17 Mu 2 Rd, Pak Phraek, Mueang Kanchanaburi District, Kanchanaburi 71000, Thaïlande',
            212 => 'River Kwai Bridge - Tha Ma Kham, Mueang Kanchanaburi District, Kanchanaburi 71000, Thaïlande',
            213 => 'Nang Rong Beach - ถ. หาดนางรอง (Chemin de la plage de Nang Rong.), Tambon Samaesarn, Amphoe Sattahip, Chang Wat Chon Buri 20180, Thaïlande',
            214 => 'Marché flottant du Lotus rouge',
            215 => 'Phra Pathom Chedi, imposant temple bouddhiste du IVe siècle avec cour centrale et gigantesque Bouddha allong',
            216 => 'Bang Phae Pathom Pittaya School',
            217 => 'AA Resort Hotel, Nonthaburi',
            218 => 'Northgate Ratchayothin, Bangkok',
            219 => 'Silpakorn University, Sanam Chandra Palace Campus',
            220 => 'Slole Cafe & Garden, one of Zen part-time works',
            221 => 'Charoen Nakhon 65, Bangkok, Thaïlande',
            222 => 'Nang Rong Beach - ถ. หาดนางรอง (Chemin de la plage de Nang Rong.), Tambon Samaesarn, Amphoe Sattahip, Chang Wat Chon Buri 20180, Thaïlande',
            223 => 'Nam Sai Beach - JW3V+8RF, Samaesarn, Sattahip District, Chon Buri 20180, Thaïlande',
            224 => 'Tanya Sea View Resort - 99/46 Moo1, Tambon Samaesarn, Amphoe Sattahip, Chon Buri, 20180, 20180, Thaïlande',
            225 => 'Satit Bilingual School of Rangsit University - 52/347 Muang Ake ถ. พหลโยธิน (Chemin Phahonyothin.) Lak Hok, Mueang Pathum Thani District, Pathum Thani 12000, Thaïlande',
            226 => 'Slōlē Café & Garden - 9 Chok Chai 4 Soi 52/1, Lat Phrao, Bangkok 10230, Thaïlande',
            227 => 'Grand Canyon - 20000 Chon Buri By Pass, Huai Kapi, Chon Buri District, Chon Buri 20000, Thaïlande',
            228 => 'Comté de Gwinnett, Georgie',
            229 => 'Siam Paragon - 991/1 Rama I Rd, Pathum Wan, Bangkok 10330, Thaïlande',
            230 => 'Loong Mala - 99/6-9 อาคาร ศูนย์การค้าโชว์ดีซี AM101 บางกะปิ Huai Khwang, Bangkok 10120, Thaïlande',
            231 => 'Monster Aquarium - 125 1, Muang Pattaya, Bang Lamung District, Chon Buri 20150, Thaïlande',
            232 => 'The Salaya Leisure Park - บางภาษี ต 88/8 หมู่ 5 ถนน Salaya, Phutthamonthon District, Nakhon Pathom 73170, Thaïlande',
            233 => 'Niyai Cafe, 8 Thung Mangkon 8 Alley, Chim Phli, Taling Chan, Bangkok 10170, Thailand',
            234 => 'Pimalai Resort and Spa - 99 Moo 5 Ba Kan Tiang Beach, Ko Lanta District, Krabi 81150, Thaïlande',
            235 => 'Kantiang Bay - 152-152/1 Moo 5 Kantiang Bay, Amphoe Ko Lanta, Chang Wat Krabi 81150, Thaïlande',
            236 => 'Koh Lanta Old Town Pier - G3MX+35H, Ko Lanta Yai, Thaïlande',
            237 => 'Sam Roi Yot Beach',
            238 => 'Bhumibol Bridge - MG6Q+8RG, Industrial Ring Rd, Bang Ya Phraek, Phra Pradaeng District, Samut Prakan 10130, Thaïlande',
            239 => 'Lappis Wine & Restaurant - โครงการเดอะมูน Nuan Chan 46 Alley, Nuanchan, Bueng Kum, Bangkok 10230, Thaïlande',
            240 => 'Liberty Walk - 5 Soi Sangkhom Songkhro 12/1, Khwaeng Lat Phrao, Khet Lat Phrao, Krung Thep Maha Nakhon 10230, Thaïlande',
            241 => 'Bira Circuit - W2C5+P5G, Pong, Bang Lamung District, Chon Buri 20150, Thaïlande',
            242 => 'ZOOM Sky Bar & Restaurant - 36 Naradhiwas Rajanagarindra Rd, Yan Nawa, Sathon, Bangkok 10120, Thaïlande',
            243 => 'Baan Cool Cafe’ &​ Arts Space - PQGF+3HX Unnamed Road Lat Krabang, Bangkok 10520, Thaïlande',
            244 => 'Northgate Ratchayothin - 248 Ratchadaphisek Rd, Khwaeng Lat Yao, Khet Chatuchak, Krung Thep Maha Nakhon 10900, Thaïlande',
            245 => 'Institute of Marine Science, Burapha University - 169 Long Had Bangsaen Rd, Saen Suk, Chon Buri District, Chon Buri 20131, Thaïlande',
            246 => 'Wat Bang Phun - XHRF+76F, Rangsit-Pathum Thani 43 Alley, Bang Phun, Mueang Pathum Thani District, Pathum Thani 12000, Thaïlande',
            247 => 'Khao Kalok Beach - Pak Nam Pran, District de Pran Buri, Province de Prachuap Khiri Khan 77120, Thaïlande',
            248 => 'Queen Sirikit Park - 200/1 Kamphaeng Phet 2 Rd, Chatuchak, Bangkok 10900, Thaïlande',
            249 => 'Wat Khao Sanam Chai - 1 3 Phet Kasem Rd, Nong Kae, Hua Hin District, Prachuap Khiri Khan 77110, Thaïlande',
            250 => 'Université Rangsit, Pathum Thani',
            251 => 'โรงเจเปาเก็งเต็ง (Pao Keng Teng Vegetarian House) - 28 Moo1, Wat Samrong, Nakhon Chai Si District, Nakhon Pathom 73120, Thaïlande',
            252 => 'บ้านไก่คู่ (Poulet Double) - Ban Pong, Ban Pong District, Ratchaburi 70110, Thaïlande',
            253 => 'China Town / Yaowarat Rd - PGR5+4W6, Yaowarat Rd, Khwaeng Samphanthawong, Khet Samphanthawong, Bangkok 10100, Thaïlande',
            254 => 'ATHENA EXECUTIVE LOUNGE - 710 Pradit Manutham Rd, Khlong Chaokhunsing, Wang Thonglang, Bangkok 10310, Thaïlande',
            255 => 'Bangsaen Aquarium - Burapha University, Saen Suk, Chon Buri District, Chon Buri 20130, Thaïlande',
            256 => 'HIDDEN LAB - 31 Rob Khao Sam Muk, Saen Suk, Chon Buri District, Chon Buri 20130, Thaïlande',
            257 => 'Little Town Sriracha - Sukhumvit Rd, Bang Phra, Si Racha District, Chon Buri 20110, Thaïlande',
            258 => 'The Manor Studio - 103 Ramkhamhaeng 24 Alley, Lane 14, Hua Mak, Bang Kapi District, Bangkok 10240, Thaïlande',
            259 => 'อ่าวพลับพลึง (Ao Phlap Phlueng), Wang Kaew Resort & จุดกางเต็นท์ สวนวังแก้ว (Emplacement pour tentes, parc Wang Kaew) - JHH6+X5R, Unnamed Rd, Chakphong, Klaeng District, Rayong 21190, Thaïlande',
            260 => 'Wat Pha Tak Suea - 28P3+HV2, Pha Tang, Sangkhom District, Nong Khai 43160, Thaïlande',
            261 => 'Wat Sing (Sing Temple) - 3G3R+99F หมู่ 2 บ้านสามโคก, ตําบลสามโคก อําเภอ Wat Sing, Sam Khok District, Pathum Thani 12160, Thaïlande',
            262 => 'Wat Tham Si Mongkhon - X862+PVR, Pha Tang, Sangkhom District, Nong Khai 43160, Thaïlande',
            263 => 'Naga fireballs viewing point – Naga Fireball Festival - 23FG+RC4 Unnamed Rd, Chumphon, Phon Phisai District, Nong Khai 43120, Thaïlande',
            264 => 'The Manor Studio - 103 Ramkhamhaeng 24 Alley, Lane 14, Hua Mak, Bang Kapi District, Bangkok 10240, Thaïlande',
            265 => 'Ascott Thonglor Bangkok - 1 Sukhumvit 59, Khlong Tan Nuea, Watthana, Bangkok 10110, Thaïlande',
            266 => 'Nonsan, Corée du sud - Nonsan est une ville située au centre de la Corée du Sud, légèrement à l\'ouest, dans la province du Chungcheong du Sud',
            267 => 'Lumpini Park - Lumphini, Pathum Wan, Bangkok 10330, Thaïlande',
            268 => 'Cafe I Love U - Fermé - 81 ชั้น1 Prasert-Manukitch Rd, Ram Inthra, Khan Na Yao, Bangkok 10230, Thaïlande',
            269 => 'Kwan Riam Floating Market - 45 Ramkhamhaeng 185 Alley, Min Buri, Bangkok 10510, Thaïlande',
            270 => 'S Cobra Camp - H7PP+QM Phra Non, Nakhon Sawan, Province de Nakhon Sawan, Thaïlande',
            271 => 'Tavi Cafe - 103 Soi Sukhumvit 64, Phra Khanong Tai, Phra Khanong, Bangkok 10260, Thaïlande',
            272 => 'Wat Chak Daeng - เลขที่ 16 หมู่ที่ 6 ถนน เพชรหึงษ์ ซอย 10 Song Khanong, Phra Pradaeng District, Samut Prakan 10130, Thaïlande',
            273 => 'Lam Taphen Reservoir - QCGQ+W2C, Unnamed Rd,, Ong Phra, Nong Prue District, Kanchanaburi, Thaïlande',
            274 => 'Sai Fon Villa Apartment - 1039 31-36 ซอย ปรีดี พนมยงค์ 45 Khlong Tan Nuea, Watthana, Bangkok 10110, Thaïlande',
            275 => 'Lalisa Ratchada 17 - 55 5 ซอย รัชดา 17 แขวงรัชดาภิเษก Din Daeng, Bangkok 10400, Thaïlande',
            276 => 'Max Club - 1000 1st FL, Liberty Plaza Building, Thong Lo, Khlong Tan Nuea, Watthana, Bangkok 10110, Thaïlande',
            277 => 'Bhumirak Chaloem Phra Kiat Park - 89/113 Wat Chaloem Phrakiat Alley, Bang Si Muang, Mueang Nonthaburi District, Nonthaburi 11000, Thaïlande',
            278 => 'Dhurakij Pundit University - 110/1-4 Pracha Chuen Rd, Thung Song Hong, Lak Si, Bangkok 10210, Thaïlande',
            279 => 'Khon Kaen, province de Khon Kaen, Thaïlande',
            280 => 'Faculty of Engineering, Khon Kaen University',
            281 => 'Épisode 3 - Mueang Khon Kean, Province de Khon Kaen, 40000, Thaïlande',
            282 => 'Ton Tann Market - ตลาดต้นตาล, Thanon Mittraphap, Tambon Nai Mueang, Mueang Khon Kaen District, Khon Kaen 40110, Thaïlande',
            283 => 'La Isla Beach Resort - 99 9, Sam Roi Yot, Sam Roi Yot District, Prachuap Khiri Khan 77120, Thaïlande',
            284 => 'Taco Lake - ',
            285 => 'ร้านกาแฟนิยาย-ตลิ่งชัน (Novel Coffee Shop-Taling Chan) - ',
            286 => 'Book Circle - 172 2 Soi Pradiphat 10, แขวง พญาไท Phaya Thai, Bangkok 10400, Thaïlande',
            287 => 'ยกพวกยิง เลเซอร์เกมส์ (Yok Pok Ying Laser Games) - 244/7 Ratchadaphisek Rd, Samsen Nok, Huai Khwang, Bangkok 10310, Thaïlande',
            288 => 'ร้านลีโฟน เมืองเอก (Lephone Shop Muang Ake) - 300/57-60 Lam Luk Ka District, Pathum Thani 12130, Thaïlande',
            289 => 'Knowwherestudio - ',
            290 => 'Chinatown Salaya - 111 หมู่ 4 Borommaratchachonnani Rd, Salaya, Phutthamonthon District, Nakhon Pathom 73170, Thaïlande',
            291 => 'Vinyl & Toys - 19 in the area of Bangkok resort, 9 Pradit Manutham Rd, Khwaeng Lat Phrao, Lat Phrao, Bangkok 10230, Thaïlande',
            292 => 'Tsurumaki Onsen - Episode 7 -  Ryota heading back home ',
            293 => 'Sivarom Park - Cake\'s Family House - Episode 3 (3B) - บางปู 987/102 เมือง Samut Prakan 10280, Thaïlande',
            294 => 'Pearl Farm by Amorn Phuket Pearl - Episode 4 - 58 2 Ko Kaeo, เมือง Phuket 83200, Thaïlande',
            295 => 'BAYPHERE HOTEL PATTAYA - RWR4+H34, 159 หมู่ที่ 2 Na Chom Thian 18 Alley, Na Chom Thian, Sattahip District, Chon Buri 20250, Thaïlande',
            296 => 'Ambassador City Jomtien, Ocean Wing - RWQ5+PR7, 10 หมู่ที่ 2 Sukhumvit Rd, Na Chom Thian, Sattahip District, Chon Buri 20250, Thaïlande',
            297 => 'Chae Son National Park - 343, Chae Son, Mueang Pan District, Lampang 52240, Thaïlande',
            298 => 'The Riverside Guest House - 286 Taladkao Rd Suan Dok, Mueang Lampang District, Chang Wat Lampang 52000, Thaïlande',
            299 => 'Wat Phrathat Lampang Luang - 271 Lampang Luang, Ko Kha District, Lampang 52130, Thaïlande',
            300 => 'Wat Prathat Doi Prachan - ดอยพระฌาน Pa Tan, Mae Tha District, Lampang 52150, Thaïlande',
            301 => 'Chong Nonsi Canal Park - 58 Naradhiwas Rajanagarindra Rd, Thung Maha Mek, Sathon, Bangkok 10120, Thaïlande',
            302 => 'Chong Nonsi Skywalk - 98 N Sathon Rd, Silom, Bang Rak, Bangkok 10500, Thaïlande',
            303 => 'Moo Yoo Rose House - Episode 10 - 99, 65, Bang Rakam, Bang Len District, Nakhon Pathom 73130, Thaïlande',
            304 => 'ร้านเจ๊จู Gold อาหารและเครื่องดื่ม (Magasin de nourriture et de boissons Jeju Gold) - 79 3 ห้วยใหญ่ Bang Lamung District, Chon Buri 20150, Thaïlande',
            305 => 'The Lighthouse of Cape Bali Hai - WVJ6+2VQ, Bali Hai, South Banglamung, Pattaya City, Bang Lamung District, Chon Buri, Thaïlande',
            306 => 'Sea Meen Norwegian Church (Sjømannskirken i Pattaya) - Pattaya, Bang Lamung District, Chon Buri 20150, Thaïlande',
            307 => 'ตลาดมารวย-หทัยราษฏร์ 54 (Marché Maruay-Hathairat 54) - 39 4, Bueng Kham Phroi, Lam Luk Ka District, Pathum Thani 12150, Thaïlande',
            308 => 'Baan Suan Ampond Residence Homestay - 155 Khumthong-Lamtoiting 1 Road Khumthong, Lat Krabang, Bangkok 10520, Thailand',
            309 => 'SARASINEE Mansion - 111 ซอย อินทามระ 22 แขวงรัชดาภิเษก Din Daeng, Bangkok 10400, Thaïlande',
            310 => 'Benchakitti Park - Ratchadaphisek Rd, Khlong Toei, Bangkok 10110, Thaïlande',
            311 => 'Secret Space - 164 ตำบล บ้าน สิงห์ ฝั่งขวา (164, sous-district de Ban Sing, côté droit) Ban Sing, Photharam District, Ratchaburi 70120, Thaïlande',
            312 => 'AUA Language Center - 179 Ratchadamri Rd, Lumphini, Pathum Wan, Bangkok 10330, Thaïlande',
            313 => 'ChangChui Creative Park - 460/8 Sirindhorn Rd, Bang Phlat, Bangkok 10700, Thaïlande',
            314 => 'Away Bangkok Riverside Kene - 1 Charoen Nakhon 35 Alley, Khwaeng Bang Lamphu Lang, Khlong San, Bangkok 10600, Thaïlande',
            315 => 'One Old Day - QFHF+RXF, Arun Amarin, Bangkok Noi, Bangkok 10700, Thaïlande',
            316 => 'Ternajachob cafe - 24 6 Chaloem Phrakiat Ratchakan Thi 9 Rd, ประเวศ Prawet, Bangkok 10250, Thaïlande',
            317 => 'Ratchaburi Grand Canyon - HH9M+HPV, Unnamed Road, Rang Bua, Chom Bueng District, Ratchaburi 70150, Thaïlande',
            318 => 'Found Cafe - 8 86 ถ.นวลจันทร์ Khwaeng Nuanchan, Bueng Kum, Bangkok 10230, Thaïlande',
            319 => 'Letana Hotel - 199/62 Kuphara 1 Alley, Bang Phli Yai, Bang Phli District, Samut Prakan 10540, Thaïlande',
            320 => 'Bangkok Creative Playground - 4 18-19 Soi Nuan Chan 56, Nuanchan, Bueng Kum, Bangkok 10230, Thaïlande',
            321 => 'Symmetry BKK - 653, 653/1-3 Pracha Uthit Rd, Wangthonglang, Bangkok 10310, Thaïlande',
            322 => 'King Rama 9 Park - Chaloem Phrakiat Ratchakan Thi 9 Rd, เเขวง หนองบอน (Sous-district de Nong Bon) Prawet, Bangkok 10250, Thaïlande',
            323 => 'Philtration - 2 Kasem San 3 Alley, Wang Mai, Pathum Wan, Bangkok 10330, Thaïlande',
            324 => 'Nong Nam Homestay - VQ5P+H43, Samoeng Tai, Samoeng District, Chiang Mai 50250, Thaïlande',
            325 => 'Differ Inc Chiang Mai - 66/6 Sanamkila Rd, ต.ศรีภูมิ, อ.เมืองเชียงใหม่ Chiang Mai 50200, Thaïlande',
            326 => 'ร้านอาหารชาววัง (restaurant Royal) - เลขที่ 399/4 ถนน เชียงใหม่- ดอยสะเก็ด San Sai District, Chiang Mai 50210, Thaïlande',
            327 => 'Samoeng Tai Sub District Municipal Food Market - 1766 1269, Samoeng Tai, Samoeng District, Chiang Mai 50250, Thaïlande',
            328 => 'Grand Canyon Water Park - 202 ถนนเลียบคลองชลประทาน Nam Phrae, Hang Dong District, Chiang Mai 50230, Thaïlande',
            329 => 'Lupu Bridge - 5FQJ+V89, Huangpu, Chine, 200023',
            330 => 'Wat Dhammakatanyu (Xian Lo Dai Tien Gong) - 5 ซอยมูลนิธิธรรมกตัญญู ถนนสุขุมวิท Bang Pu Mai, Mueang Samut Prakan District, Samut Prakan 10280, Thaïlande',
            331 => 'SO/ Bangkok - 2 N Sathon Rd, Silom, Bang Rak, Bangkok 10500, Thaïlande',
            332 => 'Red Oven – SO/ Bangkok - 2 N Sathon Rd, Khwaeng Silom, Bang Rak, Bangkok 10500, Thaïlande',
            333 => 'Dream World Bangkok - 62 หมู่ที่ 1 ถนน รังสิต - องครักษ์ Bueng Yitho, Thanyaburi District, Pathum Thani 12130, Thaïlande',
            334 => 'Mariee Avenue - 9 เคหะฯวัดไพร่ฟ้า ซอย 1 Bang Luang, เมือง, Pathum Thani 12000, Thaïlande',
            335 => 'Beach scene episode 3 - Corée du Sud, Busan, district de Suyeong',
            336 => 'Hotel Heaven, episode 3 - The Westin Josun Busan - Corée du Sud, Busan, 67 Dongbaek-ro, Haeundae',
            337 => 'Xiaoyou et Ximen - episode 44',
            338 => 'Chōshi Bridge 銚子大橋 - Episode 1 - 地先 Ohashicho, Choshi, Chiba 288-0046, Japon',
            339 => 'Chōshi Bridge 銚子大橋 - Episode 1 @ 8:38 - Michito et Hiroto',
            340 => 'Maison de Michito, Hiroto et Lion - episode 4 @26:20 - 9532 Hasaki, Kamisu, Ibaraki 314-0408, Japon',
            341 => 'Dongho Bridge - Seoul - Épisode 2 - Oksu-dong, Seongdong-gu, Seoul, Corée du Sud',
            342 => 'Pattaya - Episode 6 - 436 Beach Rd, Muang Pattaya, Amphoe Bang Lamung, Chang Wat Chon Buri 20150, Thaïlande',
            343 => 'Itaewon',
            344 => 'Asiatique Sky - Episode 6 - 2194 Charoen Krung Road, Wat Phraya Krai, Bang Kho Laem, Bangkok 10120, Thaïlande',
            345 => 'Wonhyo Bridge - Episode 7 - Yeouido-dong, Yeongdeungpo District, Seoul, Corée du Sud',
            346 => 'View of the port from the roof of the building - Episode 6',
            347 => 'Aston House - Episode 6 @13:05 - 177 Walkerhill-ro, Gwangjin District, Seoul, Corée du Sud',
            348 => 'Altercation between Babe and a Daddy man - Episode 3 @4:22 - เลขที่ 5 หมู่10 Phetchahung Rd, Song Khanong, Phra Pradaeng District, Samut Prakan 10130, Thaïlande',
            349 => 'Kaeng Krachan Circuit - Wangchan, Kaeng Krachan, Phetchaburi 76170, Thaïlande',
            350 => 'Tinidee Hotel Bangkok Golf Club - 99/3 ถนน ติวานนท์ Bang Kadi, Amphoe Mueang Pathum Thani, Pathum Thani 12000, Thaïlande',
            351 => 'CP Tower North Park - VHC7+J3X 99 อาคาร ซี.พี.ทาวเวอร์ นอร์ธปาร์ค Soi Ngam Wong Wan 47 Yaek 42, Thung Song Hong, Lak Si, Bangkok 10210, Thaïlande',
            352 => 'แอ็กซ์ สตูดิโอ Acts studio - 9/9 หมู่ที่ 1 ซอย บางคูวัดบางบัวทอง, Bang Khu Wat, Mueang Pathum Thani District, Pathum Thani 12000, Thaïlande',
            353 => 'Elizabeth Hotel - 169 51 Pradiphat Rd, Phaya Thai, Bangkok 10400, Thaïlande',
            354 => 'Wat Phrathat Bangphuan - Episode 3 - PMVJ+PQM, Phra That Bang Phuan, Mueang Nong Khai District, Nong Khai 43100, Thaïlande',
            355 => 'Pattaya Beach - Episode 10 - Province de Chonburi, Thaïlande',
            356 => '♫ \"I don\'t have an apartment with a Han River view, or a building at Cheongsam-dong\"  ♫ - Le quartier huppé de Cheongdam-dong est connu pour sa scène gastronomique, avec ses chefs de renom qui proposent des interprétations modernes de plats coréens et internationaux. La zone attire également les foules grâce à ses enseignes de luxe installées le long de Cheongdam Fashion Street. La vie nocturne se concentre autour des discothèques, ainsi que des élégants salons à cocktails et bars à whisky. Le SongEun ArtSpace expose des œuvres d\'artistes locaux émergents, tandis que le Figure Museum W abrite de célèbres figurines d\'action.',
            357 => 'Taipei 101 - Episode 5', 25.033, 121.564908, 423, '6b06199e-7586-4d19-b506-6bfbdef6fa28'
        ];
        dump($arr);

        foreach ($arr as $id => $description) {
            if ($id > 20) {
                $filmingLocation = $this->filmingLocationRepository->findOneBy(['id' => $id]);
                if ($filmingLocation) {
                    $filmingLocation->setDescription($description);
                    $filmingLocation->setLocation($filmingLocation->getTitle());
                    $this->filmingLocationRepository->save($filmingLocation);
                }
            }
        }
        $this->filmingLocationRepository->flush();
    }
}
