<?php

namespace App\Controller;

use App\DTO\SeriesAdvancedSearchDTO;
use App\DTO\SeriesSearchDTO;
use App\Entity\EpisodeLocalizedOverview;
use App\Entity\EpisodeStill;
use App\Entity\EpisodeSubstituteName;
use App\Entity\FilmingLocation;
use App\Entity\FilmingLocationImage;
use App\Entity\Series;
use App\Entity\SeriesAdditionalOverview;
use App\Entity\SeriesBroadcastDate;
use App\Entity\SeriesBroadcastSchedule;
use App\Entity\SeriesExternal;
use App\Entity\SeriesImage;
use App\Entity\SeriesLocalizedName;
use App\Entity\SeriesLocalizedOverview;
use App\Entity\SeriesVideo;
use App\Entity\Settings;
use App\Entity\User;
use App\Entity\UserEpisode;
use App\Entity\UserPinnedSeries;
use App\Entity\UserSeries;
use App\Form\AddBackdropType;
use App\Form\SeriesAdvancedSearchType;
use App\Form\SeriesSearchType;
use App\Form\SeriesVideoType;
use App\Repository\DeviceRepository;
use App\Repository\EpisodeLocalizedOverviewRepository;
use App\Repository\EpisodeStillRepository;
use App\Repository\EpisodeSubstituteNameRepository;
use App\Repository\FilmingLocationImageRepository;
use App\Repository\FilmingLocationRepository;
use App\Repository\KeywordRepository;
use App\Repository\NetworkRepository;
use App\Repository\PeopleUserPreferredNameRepository;
use App\Repository\ProviderRepository;
use App\Repository\SeriesAdditionalOverviewRepository;
use App\Repository\SeriesBroadcastDateRepository;
use App\Repository\SeriesBroadcastScheduleRepository;
use App\Repository\SeriesExternalRepository;
use App\Repository\SeriesImageRepository;
use App\Repository\SeriesLocalizedNameRepository;
use App\Repository\SeriesLocalizedOverviewRepository;
use App\Repository\SeriesRepository;
use App\Repository\SeriesVideoRepository;
use App\Repository\SettingsRepository;
use App\Repository\SourceRepository;
use App\Repository\UserEpisodeRepository;
use App\Repository\UserPinnedSeriesRepository;
use App\Repository\UserSeriesRepository;
use App\Repository\WatchProviderRepository;
use App\Service\DateService;
use App\Service\DeeplTranslator;
use App\Service\ImageConfiguration;
use App\Service\ImageService;
use App\Service\KeywordService;
use App\Service\TMDBService;
use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeInterface;
use DeepL\DeepLException;
use Deepl\TextResult;
use Psr\Log\LoggerInterface as MonologLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;
use const FILTER_DEFAULT;
use const FILTER_REQUIRE_ARRAY;

/** @method User|null getUser() */
#[Route('/{_locale}/series', name: 'app_series_', requirements: ['_locale' => 'fr|en|ko'])]
class SeriesController extends AbstractController
{
    public function __construct(
        private readonly DateService                        $dateService,
        private readonly DeeplTranslator                    $deeplTranslator,
        private readonly DeviceRepository                   $deviceRepository,
        private readonly EpisodeLocalizedOverviewRepository $episodeLocalizedOverviewRepository,
        private readonly EpisodeStillRepository             $episodeStillRepository,
        private readonly EpisodeSubstituteNameRepository    $episodeSubstituteNameRepository,
        private readonly FilmingLocationImageRepository     $filmingLocationImageRepository,
        private readonly FilmingLocationRepository          $filmingLocationRepository,
        private readonly ImageConfiguration                 $imageConfiguration,
        private readonly ImageService                       $imageService,
        private readonly KeywordRepository                  $keywordRepository,
        private readonly KeywordService                     $keywordService,
        private readonly MonologLogger                      $logger,
        private readonly NetworkRepository                  $networkRepository,
        private readonly PeopleUserPreferredNameRepository  $peopleUserPreferredNameRepository,
        private readonly ProviderRepository                 $providerRepository,
        private readonly SeriesAdditionalOverviewRepository $seriesAdditionalOverviewRepository,
        private readonly SeriesBroadcastDateRepository      $seriesBroadcastDateRepository,
        private readonly SeriesBroadcastScheduleRepository  $seriesBroadcastScheduleRepository,
        private readonly SeriesExternalRepository           $seriesExternalRepository,
        private readonly SeriesImageRepository              $seriesImageRepository,
        private readonly SeriesLocalizedNameRepository      $seriesLocalizedNameRepository,
        private readonly SeriesLocalizedOverviewRepository  $seriesLocalizedOverviewRepository,
        private readonly SeriesVideoRepository              $seriesVideoRepository,
        private readonly SeriesRepository                   $seriesRepository,
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
//        $monday = $now->modify('-' . ($dayOfWeek - 1) . ' days')->format('Y-m-d');
        $monday = $this->dateModify($now, '-' . ($dayOfWeek - 1) . ' days')->format('Y-m-d');
        // Sunday of the current week
//        $sunday = $now->modify('+' . (7 - $dayOfWeek) . ' days')->format('Y-m-d');
        $sunday = $this->dateModify($now, '+' . (7 - $dayOfWeek) . ' days')->format('Y-m-d');

        $searchString = "&air_date.gte=$monday&air_date.lte=$sunday&include_adult=false&include_null_first_air_dates=false&language=$language&sort_by=first_air_date.desc&timezone=$timezone&watch_region=$country&with_watch_providers=" . implode('|', $userProviderIds);

        $searchResult = json_decode($this->tmdbService->getFilterTv($searchString . "&page=1"), true);
        for ($i = 2; $i <= $searchResult['total_pages']; $i++) {
            $searchResult['results'] = array_merge($searchResult['results'], json_decode($this->tmdbService->getFilterTv($searchString . "&page=$i"), true)['results']);
        }
        $seriesList = $this->getSearchResult($searchResult, new AsciiSlugger());
        $userSeriesTMDBIds = array_column($this->userSeriesRepository->userSeriesTMDBIds($user), 'id');
//        dump(['series' => $series, 'userSeriesTMDBIds' => $userSeriesTMDBIds]);

        // Historique des épisodes vus pendant les 2 semaines passées
        $episodeHistory = $this->getEpisodeHistory($user, 14, $locale);

        $logoUrl = $this->imageConfiguration->getUrl('logo_sizes', 3);
        $posterUrl = $this->imageConfiguration->getUrl('poster_sizes', 5);

        $AllEpisodesOfTheDay = array_map(function ($ue) use ($posterUrl, $logoUrl) {
            $this->imageService->saveImage("posters", $ue['posterPath'], $posterUrl);
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
                'watch_providers' => $ue['providerId'] ? [['logo_path' => $this->getProviderLogoFullPath($ue['providerLogoPath'], $logoUrl), 'provider_name' => $ue['providerName']]] : [],
            ];
        }, $this->userEpisodeRepository->episodesOfTheDay($user, $locale));
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

        $allEpisodesOfTheWeek = array_map(function ($us) use ($posterUrl, $logoUrl) {
            $this->imageService->saveImage("posters", $us['poster_path'], $posterUrl);
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
                'watch_providers' => $us['provider_id'] ? [['logo_path' => $this->getProviderLogoFullPath($us['provider_logo_path'], $logoUrl), 'provider_name' => $us['provider_name']]] : [],
            ];
        }, $this->userSeriesRepository->getUserSeriesOfTheNext7Days($user, $locale));
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
        $seriesToStart = array_map(function ($s) use ($posterUrl, $logoUrl) {
            $this->imageService->saveImage("posters", $s['poster_path'], $posterUrl);
            $s['provider_logo_path'] = $this->getProviderLogoFullPath($s['provider_logo_path'], $logoUrl);
            return $s;
        }, $this->userSeriesRepository->seriesToStart($user, $locale, $order, 1, 20));
        $seriesToStartCount = $this->userSeriesRepository->seriesToStartCount($user, $locale);
        $series = [];
        // Plusieurs résultats pour une même série, à cause de différents liens de streaming (link_name, provider_logo_path, "provider_name)
        // On ne garde que le premier résultat pour chaque série et on ajoute les providers dans un tableau "watch_links".
        foreach ($seriesToStart as $s) {
            if (!array_key_exists($s['id'], $series)) {
                $series[$s['id']] = $s;
                $series[$s['id']]['watch_links'] = [];
            }
            if ($s['link_name']) {
                $series[$s['id']]['watch_links'][] = [
                    'link_name' => $s['link_name'],
                    'logo_path' => $s['provider_logo_path'],
                    'provider_name' => $s['provider_name']
                ];
            }
        }
        $seriesToStart = array_values($series);

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
            'seriesList' => $seriesList,
            'userSeriesTMDBIds' => $userSeriesTMDBIds,
            'total_results' => $searchResult['total_results'] ?? -1,
            'tmdbIds' => $tmdbIds,
        ]);
    }

    #[Route('/to/start', name: 'to_start')]
    public function serieToStart(Request $request): Response
    {
        $user = $this->getUser();
        $locale = $user->getPreferredLanguage() ?? $request->getLocale();

        $posterUrl = $this->imageConfiguration->getUrl('poster_sizes', 5);
        $logoUrl = $this->imageConfiguration->getUrl('logo_sizes', 3);
        $seriesToStart = array_map(function ($s) use ($posterUrl, $logoUrl) {
            $this->imageService->saveImage("posters", $s['poster_path'], $posterUrl);
            $s['provider_logo_path'] = $this->getProviderLogoFullPath($s['provider_logo_path'], $logoUrl);
            return $s;
        }, $this->userSeriesRepository->seriesToStart($user, $locale, 'firstAirDate', 1, -1));
        $tmdbIds = array_column($seriesToStart, 'tmdb_id');

        $series = [];
        // Plusieurs résultats pour une même série, à cause de différents liens de streaming (link_name, provider_logo_path, "provider_name)
        // On ne garde que le premier résultat pour chaque série et on ajoute les providers dans un tableau "watch_links".
        foreach ($seriesToStart as $s) {
            if (!array_key_exists($s['id'], $series)) {
                $series[$s['id']] = $s;
                $series[$s['id']]['watch_links'] = [];
            }
            if ($s['link_name']) {
                $series[$s['id']]['watch_links'][] = [
                    'link_name' => $s['link_name'],
                    'logo_path' => $s['provider_logo_path'],
                    'provider_name' => $s['provider_name']
                ];
            }
        }
        $seriesToStart = array_values($series);

//        dump($seriesToStart, $series);
        return $this->render('series/series-to-start.html.twig', [
            'seriesToStart' => $seriesToStart,
            'tmdbIds' => $tmdbIds,
        ]);
    }

    #[Route('/not/seen/in/a/while', name: 'not_seen_in_a_while')]
    public function serieNotSeen(Request $request): Response
    {
        $user = $this->getUser();
        $locale = $user->getPreferredLanguage() ?? $request->getLocale();

        $inAWhileDate = $this->dateModify($this->now(), '-15 days')->format('Y-m-d');
//        dump($inAWhileDate);
        $series = array_map(function ($s) {
            $this->imageService->saveImage("posters", $s['poster_path'], $this->imageConfiguration->getUrl('poster_sizes', 5));
            return $s;
        }, $this->userSeriesRepository->seriesNotSeenInAWhile($user, $locale, $inAWhileDate, 1, -1));
        $tmdbIds = array_column($series, 'tmdb_id');

        return $this->render('series/series-in-a-while.html.twig', [
            'seriesInAWhile' => $series,
            'tmdbIds' => $tmdbIds,
        ]);
    }

    #[Route('/up/coming', name: 'up_coming')]
    public function upComingSeries(Request $request): Response
    {
        $user = $this->getUser();
        $locale = $user->getPreferredLanguage() ?? $request->getLocale();

        $series = array_map(function ($s) {
            $this->imageService->saveImage("posters", $s['poster_path'], $this->imageConfiguration->getUrl('poster_sizes', 5));
            return $s;
        }, $this->userSeriesRepository->upComingSeries($user, $locale, 'firstAirDate', 1, -1));
        $tmdbIds = array_column($series, 'tmdb_id');

        return $this->render('series/up-coming-series.html.twig', [
            'seriesList' => $series,
            'tmdbIds' => $tmdbIds,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/by/country/{country}', name: 'by_country', requirements: ['country' => '[A-Z]{2}'])]
    public function seriesByCountry(Request $request, string $country): Response
    {
        $user = $this->getUser();
        $locale = $user->getPreferredLanguage() ?? $request->getLocale();

        $settings = $this->settingsRepository->findOneBy(['user' => $user, 'name' => 'by country']);
        if (!$settings) {
            $settings = new Settings($user, 'by country', ['country' => $country, 'keywords' => []]);
        } else {
            $data = $settings->getData();
            $data['country'] = $country;
            $settings->setData($data);
        }
        $this->settingsRepository->save($settings, true);

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
            $this->imageService->saveImage("posters", $s['poster_path'], $this->imageConfiguration->getUrl('poster_sizes', 5));
            $s['upToDate'] = $s['watched_aired_episode_count'] == $s['aired_episode_count'];
            return $s;
        }, $this->userSeriesRepository->seriesByCountry($user, $country, $locale, 1, -1));
        // Le tableau $series est trié par date de première diffusion décroissante, mais certaines séries n'ont pas de date de première diffusion.
        // Ces séries sans date de première diffusion doivent être placées en début de tableau.
        usort($series, function ($a, $b) {
            if ($a['final_air_date'] == $b['final_air_date']) return 0;
            if ($a['final_air_date'] == null) return -1;
            if ($b['final_air_date'] == null) return 1;
            return $a['final_air_date'] < $b['final_air_date'] ? 1 : -1;
        });


        $tmdbIds = array_column($series, 'tmdb_id');

//        dump($series);

        return $this->render('series/series-by-country.html.twig', [
            'seriesByCountry' => $series,
            'userSeriesCountries' => $userSeriesCountries,
            'country' => $country,
            'tmdbIds' => $tmdbIds,
        ]);
    }

    #[Route('/tmdb/search', name: 'search')]
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
            'action' => 'app_series_search',
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
//        dump($request->query->all());
        $slugger = new AsciiSlugger();
        $simpleSeriesSearch = new SeriesSearchDTO($request->getLocale(), 1);
        if ($request->get('q')) $simpleSeriesSearch->setQuery($request->get('q'));
        $simpleForm = $this->createForm(SeriesSearchType::class, $simpleSeriesSearch);
        $searchResult = $this->handleSearch($simpleSeriesSearch);
        if ($searchResult['total_results'] == 1) {
            return $this->getOneResult($searchResult['results'][0], $slugger);
        }
        $series = $this->getSearchResult($searchResult, $slugger);

        return $this->render('series/search.html.twig', [
            'form' => $simpleForm->createView(),
            'action' => 'app_series_search_all',
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
                $settings = new Settings($user, 'series to end', ['perPage' => 10, 'sort' => 'lastWatched', 'order' => 'DESC', 'network' => 'all']);
                $this->settingsRepository->save($settings, true);
            }
        } else {
            // /fr/series/all?sort=episodeAirDate&order=DESC&startStatus=series-not-started&endStatus=series-not-watched&perPage=10
            $paramSort = $request->get('sort');
            $paramOrder = $request->get('order');
            $paramNetwork = $request->get('network');
            $paramPerPage = $request->get('perPage');
            $settings->setData([
                'perPage' => $paramPerPage,
                'sort' => $paramSort,
                'order' => $paramOrder,
                'network' => $paramNetwork
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
        ];

        $filterMeanings = [
            'name' => 'Name',
            'addedAt' => 'Date added',
            'firstAirDate' => 'First air date',
            'lastWatched' => 'Last series watched',
            'episodeAirDate' => 'Episode air date',
            'DESC' => 'Descending',
            'ASC' => 'Ascending',
        ];

        /** @var UserSeries[] $userSeries */
//        $t0 = microtime(true);
        $userSeries = $this->userSeriesRepository->getAllSeries(
            $user,
            $localisation,
            $filters);
        $userSeriesCount = $this->userSeriesRepository->countAllSeries(
            $user,
            $localisation,
            $filters);

        $userSeries = array_map(function ($series) {
            $series['poster_path'] = $series['poster_path'] ? '/series/posters' . $series['poster_path'] : null;
            return $series;
        }, $userSeries);

        $userNetworks = $user->getNetworks();
        $networks = $this->networkRepository->findBy([], ['name' => 'ASC']);
        $nlpArr = $this->networkRepository->networkLogoPaths();
        $networkLogoPaths = ['all' => null];
        $imageConfiguration = $this->imageConfiguration->getUrl('logo_sizes', 3);

        foreach ($nlpArr as $nlp) {
            if ($nlp['logo_path'])
//                $networkLogoPaths[$nlp['id']] = $this->imageConfiguration->getCompleteUrl($nlp['logo_path'], 'logo_sizes', 3);
                $networkLogoPaths[$nlp['id']] = $imageConfiguration . $nlp['logo_path'];
            else
                $networkLogoPaths[$nlp['id']] = null;
        }
//        $t1 = microtime(true);

//        dump([
//            't0' => $t0,
//            't1' => $t1,
//            'diff' => $t1 - $t0,
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
    #[Route('/db/search', name: 'search_db')]
    public function searchDB(Request $request): Response
    {
        $user = $this->getUser();
        $searchName = $request->get('name');

        $series = [];
        $simpleSeriesSearch = new SeriesSearchDTO($request->getLocale(), 1);
        if ($searchName) {
            $series = $this->getDbSearchResult($user, $searchName, 1, null);
            $simpleSeriesSearch->setQuery($searchName);
        }
        $simpleForm = $this->createForm(SeriesSearchType::class, $simpleSeriesSearch);

        $simpleForm->handleRequest($request);
        if ($simpleForm->isSubmitted() && $simpleForm->isValid()) {
            $query = $simpleSeriesSearch->getQuery();
            $page = $simpleSeriesSearch->getPage();
            $firstAirDateYear = $simpleSeriesSearch->getFirstAirDateYear();
            $series = $this->getDbSearchResult($user, $query, $page, $firstAirDateYear);
        }

        if (count($series) == 1) {
            return $this->redirectToRoute('app_series_show', [
                'id' => $series[0]['id'],
                'slug' => $series[0]['slug'],
            ]);
        }

        return $this->render('series/search.html.twig', [
            'form' => $simpleForm->createView(),
            'action' => 'app_series_search_db',
            'title' => 'Search among your series',
            'seriesList' => $series,
            'results' => [
                'total_results' => $searchResult['total_results'] ?? -1,
                'total_pages' => $searchResult['total_pages'] ?? 0,
                'page' => $searchResult['page'] ?? 0,
            ],
        ]);
    }

    public function getDbSearchResult($user, $query, $page, $firstAirDateYear): array
    {
        return array_map(function ($s) {
            $s['poster_path'] = $s['poster_path'] ? $this->imageConfiguration->getUrl('poster_sizes', 5) . $s['poster_path'] : null;
            return $s;
        }, $this->seriesRepository->search($user, $query, $firstAirDateYear, $page));
    }

    #[Route('/advanced/search', name: 'advanced_search')]
    public function advancedSearch(Request $request): Response
    {
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
        $user = $this->getUser();
        $series = $this->seriesRepository->findOneBy(['tmdbId' => $id]);
        $userSeries = ($user && $series) ? $this->userSeriesRepository->findOneBy(['user' => $user, 'series' => $series]) : null;
        $locale = $user ? $user->getPreferredLanguage() : $request->getLocale();

        if ($userSeries) {
            return $this->redirectToRoute('app_series_show', [
                'id' => $series->getId(),
                'slug' => $series->getSlug(),
                'oldSeriesAdded' => 'false',
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
        $tv = json_decode($this->tmdbService->getTv($id, $request->getLocale(), ["images", "videos", "credits", "watch/providers", "content/ratings", "keywords", "similar", "translations"]), true);

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
        $this->imageService->saveImage("posters", $tv['poster_path'], $this->imageConfiguration->getUrl('poster_sizes', 5));
        $this->imageService->saveImage("backdrops", $tv['backdrop_path'], $this->imageConfiguration->getUrl('backdrop_sizes', 3));

        $tv['credits'] = $this->castAndCrew($tv);
        $tv['networks'] = $this->networks($tv);
        $tv['seasons'] = $this->seasonsPosterPath($tv['seasons']);
        $tv['watch/providers'] = $this->watchProviders($tv, 'FR');
        $tv['translations'] = $this->getTranslations($tv, $user);
        $c = count($tv['episode_run_time']);
        $tv['average_episode_run_time'] = $c ? array_reduce($tv['episode_run_time'], function ($carry, $item) {
                return $carry + $item;
            }, 0) / $c : 0;

//        dump($tv);
        return $this->render('series/tmdb.html.twig', [
            'tv' => $tv,
            'localizedName' => $localizedName,
            'localizedOverview' => $localizedOverview,
            'externals' => $this->getTMDBExternals($tv['name'], $tv['origin_country']),
        ]);
    }

    public function getTranslations(array $tv, User $user): ?array
    {
        // "translations" => array:10 [▼
        //      0 => array:5 [▼
        //        "iso_3166_1" => "CN"
        //        "iso_639_1" => "zh"
        //        "name" => "普通话"
        //        "english_name" => "Mandarin"
        //        "data" => array:4 [▶]
        //      ]
        //      1 => array:5 [▼
        //        "iso_3166_1" => "US"
        //        "iso_639_1" => "en"
        //        "name" => "English"
        //        "english_name" => "English"
        //        "data" => array:4 [▼
        //          "name" => "Reunion: The Sound of the Providence"
        //          "overview" => "Ten years after their last mission, the famed tomb raiders Wu Xie, Wang Pang Zi, and Zhang Qi Ling have moved on, believing their days of adventure are behind t ▶"
        //          "homepage" => ""
        //          "tagline" => ""
        //        ]
        //      ]
        $country = $user->getCountry() ?? 'FR'; // user iso_3166_1
        $locale = $user->getPreferredLanguage() ?? 'fr'; // user iso_639_1
        $translations = $tv['translations']['translations'];
        $translation = null;

        foreach ($translations as $t) {
            if ($t['iso_3166_1'] == $country && $t['iso_639_1'] == $locale) {
                $translation = $t;
                break;
            }
        }
        if ($translation == null) {
            foreach ($translations as $t) {
                if ($t['iso_3166_1'] == $country) {
                    $translation = $t;
                    break;
                }
            }
        }
        if ($translation == null) {
            foreach ($translations as $t) {
                if ($t['iso_639_1'] == $locale) {
                    $translation = $t;
                    break;
                }
            }
        }
        // get en-Us if null
        if ($translation == null) {
            foreach ($translations as $t) {
                if ($t['iso_3166_1'] == 'US' && $t['iso_639_1'] == 'en') {
                    $translation = $t;
                    break;
                }
            }
        }
        return $translation;
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/list/{id}/{seriesId}', name: 'list', requirements: ['id' => Requirement::DIGITS, 'showId' => Requirement::DIGITS])]
    public function list(Request $request, int $id, int $seriesId): Response
    {
        $user = $this->getUser();
        $page = $request->get('page') ?? 1;
        $series = $this->seriesRepository->findOneBy(['id' => $seriesId]);
        $userSeriesTMDBIds = array_column($this->userSeriesRepository->userSeriesTMDBIds($user), 'id');
        $list = json_decode($this->tmdbService->getList($id, $page), true);

        $this->imageService->saveImage("backdrops", $list['backdrop_path'], $this->imageConfiguration->getUrl('backdrop_sizes', 3));
        $this->imageService->saveImage("posters", $list['poster_path'], $this->imageConfiguration->getUrl('poster_sizes', 5));

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
        $user = $this->getUser();
        $locale = $user->getPreferredLanguage() ?? $request->getLocale();
        $country = $user->getCountry() ?? 'FR';

        $addBackdropForm = $this->createForm(AddBackdropType::class);
        $addBackdropForm->handleRequest($request);
        if ($addBackdropForm->isSubmitted() && $addBackdropForm->isValid()) {
            $data = $addBackdropForm->getData();
            $this->addBackdrop($series, $data['file']);
        }
        $addVideoForm = $this->createForm(SeriesVideoType::class, new SeriesVideo($series, "", ""));
        $addVideoForm->handleRequest($request);
        if ($addVideoForm->isSubmitted() && $addVideoForm->isValid()) {
            $data = $addVideoForm->getData();
            $this->addVideo($data);
        }
        $userSeries = $this->userSeriesRepository->findOneBy(['user' => $user, 'series' => $series]);
        $userEpisodes = $this->userEpisodeRepository->findBy(['userSeries' => $userSeries], ['seasonNumber' => 'ASC', 'episodeNumber' => 'ASC']);

        $this->checkSlug($series, $slug, $locale);
        // Get with fr-FR language to get the localized name
        $tv = json_decode($this->tmdbService->getTv($series->getTmdbId(), $request->getLocale(), [
            "images",
            "videos",
            "credits",
            "watch/providers",
            "keywords",
            "external_ids",
            "lists",
            "similar",
            "translations",
        ]), true);
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
            $posterUrl = $this->imageConfiguration->getUrl('poster_sizes', 5);
            $backdropUrl = $this->imageConfiguration->getUrl('backdrop_sizes', 3);
            $tv['similar']['results'] = array_map(function ($s) use ($posterUrl) {
                $s['poster_path'] = $s['poster_path'] ? $posterUrl . $s['poster_path'] : null;
                $s['tmdb'] = true;
                $s['slug'] = new AsciiSlugger()->slug($s['name']);
                return $s;
            }, $tv['similar']['results']);
//        dump($tv, $tvLists, $similar);

            $this->imageService->saveImage("posters", $tv['poster_path'], $posterUrl);
            $this->imageService->saveImage("backdrops", $tv['backdrop_path'], $backdropUrl);
            $series = $this->updateSeries($series, $tv);
//            dump(['series posters' => $seriesPosters]);

            $tv['additional_overviews'] = $series->getSeriesAdditionalLocaleOverviews($request->getLocale());
            $tv['credits'] = $this->castAndCrew($tv);
            $tv['localized_name'] = $series->getLocalizedName($request->getLocale());
            $tv['localized_overviews'] = $series->getLocalizedOverviews($request->getLocale());
            $tv['keywords']['results'] = $this->keywordService->keywordsCleaning($tv['keywords']['results']);
            $tv['missing_translations'] = $this->keywordService->keywordsTranslation($tv['keywords']['results'], $locale);
            $tv['networks'] = $this->networks($tv);
            $tv['overview'] = $this->localizedOverview($tv, $series, $request);
            $tv['seasons'] = $this->seasonsPosterPath($tv['seasons']);
            $tv['sources'] = $this->sourceRepository->findBy([], ['name' => 'ASC']);
            $tv['watch/providers'] = $this->watchProviders($tv, $country);
            $tv['translations'] = $this->getTranslations($tv, $user);
            if ($tv['localized_name'] == null && $tv['translations'] && $tv['name'] != $tv['translations']['data']['name']) {
                if (strlen($tv['translations']['data']['name'])) {
                    $slugger = new AsciiSlugger();
                    $slug = $slugger->slug($tv['translations']['data']['name'])->lower()->toString();
                    $newLocalizedName = new SeriesLocalizedName($series, $tv['translations']['data']['name'], $slug, $locale);
                    $this->seriesLocalizedNameRepository->save($newLocalizedName, true);
                    $tv['localized_name'] = $newLocalizedName;
                    $this->addFlash('success', 'The series name “' . $newLocalizedName->getName() . '” has been added to the database.');
                }
            }
            $c = count($tv['episode_run_time']);
            $tv['average_episode_run_time'] = $c ? array_reduce($tv['episode_run_time'], function ($carry, $item) {
                    return $carry + $item;
                }, 0) / $c : 0;
            $noTv = [];
        } else {
            $series->setUpdates(['Series not found']);
            $noTv['additional_overviews'] = $series->getSeriesAdditionalLocaleOverviews($locale);
            $noTv['credits'] = $this->castAndCrew($tv);
            $noTv['localized_name'] = $series->getLocalizedName($locale);
            $noTv['localized_overviews'] = $series->getLocalizedOverviews($locale);
            $noTv['seasons'] = $this->getUserSeasons($series, $userEpisodes);
            $noTv['sources'] = $this->sourceRepository->findBy([], ['name' => 'ASC']);
            $noTv['average_episode_run_time'] = 0;
        }

        $series->setVisitNumber($series->getVisitNumber() + 1);
        if (!$series->getNumberOfEpisode()) {
            $series->setNumberOfEpisode($tv['number_of_episodes'] ?? -1);
            $series->setNumberOfSeason($tv['number_of_seasons'] ?? -1);
        }
        $this->seriesRepository->save($series, true);

        list($seriesBackdrops, $seriesLogos, $seriesPosters) = $this->getSeriesImages($series);

        if ($tv) {
            $userSeries = $this->updateUserSeries($userSeries, $tv);
            $tv['status_css'] = $this->statusCss($userSeries, $tv);
        }

        $providers = $this->getWatchProviders($country);

        $schedules = $this->seriesSchedulesV2($user, $series, $tv);
        $emptySchedule = $this->emptySchedule();
        $alternateSchedules = array_map(function ($s) use ($tv, $userEpisodes) {
            // Ajouter la "user" séries pour les épisodes vus
            return $this->getAlternateSchedule($s, $tv, array_filter($userEpisodes, function ($ue) use ($s) {
                return $ue->getSeasonNumber() == $s->getSeasonNumber();
            }));
        }, $series->getSeriesBroadcastSchedules()->toArray());
        foreach ($alternateSchedules as &$s) {
            $s['airDays'] = array_map(function ($day) use ($s, $series) {
                $day['url'] = $this->generateUrl('app_series_season', [
                        'id' => $series->getId(),
                        'slug' => $series->getSlug(),
                        'seasonNumber' => $s['seasonNumber'],
                    ]) . "#episode-" . $day['episodeNumber'];
                return $day;
            }, $s['airDays']);
        }

        $seriesArr = $series->toArray();
        $seriesArr['schedules'] = $schedules;
        $seriesArr['emptySchedule'] = $emptySchedule;
        $seriesArr['alternateSchedules'] = $alternateSchedules;
        $seriesArr['seriesInProgress'] = $this->userEpisodeRepository->isFullyReleased($userSeries);
        $seriesArr['images'] = [
            'backdrops' => $seriesBackdrops,
            'logos' => $seriesLogos,
            'posters' => $seriesPosters,
        ];
        $seriesArr['videos'] = array_map(function ($v) {
            $vArr['title'] = $v->getTitle();
            $link = $v->getLink();
            if (strlen($link) === 11) {
                $vArr['link'] = 'https://www.youtube.com/embed/' . $link;
                $vArr['iframe'] = true;
            } else {
                $vArr['link'] = $link;
                $vArr['iframe'] = false;
            }
            return $vArr;
        }, $series->getSeriesVideos()->toArray());
        if (count($seriesArr['videos'])) {
            $settings = $this->settingsRepository->findOneBy(['user' => $user, 'name' => 'series_video_list_folded']);
            if (!$settings) {
                $settings = new Settings($user, 'series_video_list_folded', ['folded' => true]);
                $this->settingsRepository->save($settings, true);
            }
            $videoListFolded = $settings->getData()['folded'];
        }

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
            'Season completed' => $this->translator->trans('Season completed'),
            'Up to date' => $this->translator->trans('Up to date'),
            'Not a valid file type. Update your selection' => $this->translator->trans('Not a valid file type. Update your selection'),
        ];

        $filmingLocationsWithBounds = $this->getFilmingLocations($series->getTmdbId());

        $tvKeywords = $tv['keywords']['results'] ?? [];
        $tvExternalIds = $tv['external_ids'] ?? [];

        $seriesAround = $this->userSeriesRepository->getSeriesAround($user, $series->getId(), $locale);
        $previousSeries = null;
        $nextSeries = null;
        if (count($seriesAround) == 2) {
            $previousSeries = $seriesAround[0];
            $nextSeries = $seriesAround[1];
        }
        if (count($seriesAround) == 1) {
            $previousSeries = $seriesAround[0]['id'] < $userSeries->getId() ? $seriesAround[0] : null;
            $nextSeries = $seriesAround[0]['id'] > $userSeries->getId() ? $seriesAround[0] : null;
        }

//        dump([
//            'series' => $seriesArr,
//            'previousSeries' => $previousSeries,
//            'nextSeries' => $nextSeries,
//            'locations' => $filmingLocationsWithBounds['filmingLocations'],
//            'locationsBounds' => $filmingLocationsWithBounds['bounds'],
//            'tv' => $tv,
//            'oldSeriesAdded - get' => $request->get('oldSeriesAdded'),
//            'oldSeriesAdded - query' => $request->query->get('oldSeriesAdded'),
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
            'previousSeries' => $previousSeries,
            'nextSeries' => $nextSeries,
            'videoListFolded' => $videoListFolded ?? true,
            'tv' => $tv ?? $noTv,
            'userSeries' => $userSeries,
            'providers' => $providers,
            'locations' => $filmingLocationsWithBounds['filmingLocations'],
            'locationsBounds' => $filmingLocationsWithBounds['bounds'],
            'mapSettings' => $this->settingsRepository->findOneBy(['name' => 'mapbox']),
            'externals' => $this->getExternals($series, $tvKeywords, $tvExternalIds, $request->getLocale()),
            'translations' => $translations,
            'addBackdropForm' => $addBackdropForm->createView(),
            'addVideoForm' => $addVideoForm->createView(),
            'oldSeriesAdded' => $request->get('oldSeriesAdded') === 'true',
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/add/{id}', name: 'add', requirements: ['id' => Requirement::DIGITS])]
    public function addUserSeries(int $id): Response
    {
        $user = $this->getUser();
        $date = $this->now();

        $result = $this->addSeries($id, $date);
        $tv = $result['tv'];
        $series = $result['series'];
        $this->addSeriesToUser($user, $series, $tv, $date);

        $firstAirDate = $tv['first_air_date'] ? $this->dateService->newDateImmutable($tv['first_air_date'], 'Europe/Paris', true) : null;
        $oldSeries = false;
        $nowYear = $this->now()->format('Y');
        $firstAirDateYear = $firstAirDate?->format('Y');

        if ($firstAirDateYear) {
            if ($nowYear - $firstAirDateYear > 2) {
                $oldSeries = true;
            }
        }

        return $this->redirectToRoute('app_series_show', [
            'id' => $series->getId(),
            'slug' => $series->getSlug(),
            'oldSeriesAdded' => $oldSeries,
        ]);
    }

    #[Route('/old/{id}', name: 'old', requirements: ['id' => Requirement::DIGITS])]
    public function markAllEpisodeAsViewed(int $id): Response
    {
        $series = $this->seriesRepository->findOneBy(['id' => $id]);
        $user = $this->getUser();
        $userSeries = $this->userSeriesRepository->findOneBy(['user' => $user, 'series' => $series]);
        $userEpisodes = $this->userEpisodeRepository->findBy(['userSeries' => $userSeries]);

        $episodeCount = count($userEpisodes);
        $watchedEpisodeCount = 0;
        $lastEpisodeNumber = 0;
        $lastSeasonNumber = 0;
        $lastWatchedAt = null;

        foreach ($userEpisodes as $userEpisode) {
            if ($userEpisode->getAirDate()) {
                $userEpisode->setWatchAt($userEpisode->getAirDate());
                $this->userEpisodeRepository->save($userEpisode);
                $lastEpisodeNumber = $userEpisode->getEpisodeNumber();
                $lastSeasonNumber = $userEpisode->getSeasonNumber();
                $lastWatchedAt = $userEpisode->getWatchAt();
                $watchedEpisodeCount++;
            }
        }
        $this->userEpisodeRepository->flush();

        $userSeries->setLastEpisode($lastEpisodeNumber);
        $userSeries->setLastSeason($lastSeasonNumber);
        $userSeries->setLastWatchAt($lastWatchedAt);
        $userSeries->setViewedEpisodes($watchedEpisodeCount);
        $userSeries->setProgress($watchedEpisodeCount / $episodeCount * 100);

        $this->userSeriesRepository->save($userSeries, true);

        return $this->json([
            'ok' => true,
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

    #[IsGranted('ROLE_USER')]
    #[Route('/schedules/save', name: 'schedule_save', methods: ['POST'])]
    public function schedulesSave(Request $request): JsonResponse
    {
        $inputBag = $request->getPayload();
        $id = $inputBag->get('id');
        $country = $inputBag->get('country');
        $seasonNumber = $inputBag->get('seasonNumber');
        $seasonMultiPart = $inputBag->get('multiPart');
        $seasonPart = $inputBag->get('seasonPart');
        $seasonPartFirstEpisode = $inputBag->get('seasonPartFirstEpisode');
        $seasonPartEpisodeCount = $inputBag->get('seasonPartEpisodeCount');
        $date = $inputBag->get('date');
        $time = $inputBag->get('time');
        $override = $inputBag->get('override');
        $frequency = $inputBag->get('frequency');
        $provider = $inputBag->get('provider');
        $seriesId = $inputBag->get('seriesId');
        $dayArr = array_map(function ($d) {
            return intval($d);
        }, (array)$inputBag->filter('days', [], FILTER_DEFAULT, ['flags' => FILTER_REQUIRE_ARRAY]));

        $hour = (int)substr($time, 0, 2);
        $minute = (int)substr($time, 3, 2);

        $series = $this->seriesRepository->findOneBy(['id' => $seriesId]);

        if ($id === 0) {
            $seriesBroadcastSchedule = new SeriesBroadcastSchedule();
            $seriesBroadcastSchedule->setSeries($series);
        } else {
            $seriesBroadcastSchedule = $this->seriesBroadcastScheduleRepository->findOneBy(['id' => $id]);
        }

        $previousOverrideStatus = $seriesBroadcastSchedule->isOverride();

        $seriesBroadcastSchedule->setSeasonNumber($seasonNumber);
        $seriesBroadcastSchedule->setMultiPart($seasonMultiPart);
        $seriesBroadcastSchedule->setSeasonPart($seasonPart);
        $seriesBroadcastSchedule->setSeasonPartFirstEpisode($seasonPartFirstEpisode);
        $seriesBroadcastSchedule->setSeasonPartEpisodeCount($seasonPartEpisodeCount);
        $seriesBroadcastSchedule->setFirstAirDate($this->dateService->newDateImmutable($date, "Europe/Paris", true));
        $seriesBroadcastSchedule->setAirAt(new DateTimeImmutable()->setTime($hour, $minute));
        $seriesBroadcastSchedule->setFrequency($frequency);
        $seriesBroadcastSchedule->setOverride($override);
        $seriesBroadcastSchedule->setCountry($country);
        $seriesBroadcastSchedule->setDaysOfWeek($dayArr);
        $seriesBroadcastSchedule->setProviderId($provider);
        $this->seriesBroadcastScheduleRepository->save($seriesBroadcastSchedule);

        // Override TMDB dates, create SeriesBroadcastDate records with the new dates
        if ($override) {
            $user = $this->getUser();
            $userSeries = $this->userSeriesRepository->findOneBy(['user' => $user, 'series' => $series]);
            $userEpisodes = $this->userEpisodeRepository->findBy(['userSeries' => $userSeries, 'seasonNumber' => $seasonNumber], ['seasonNumber' => 'ASC', 'episodeNumber' => 'ASC']);

            $tv = json_decode($this->tmdbService->getTv($seriesBroadcastSchedule->getSeries()->getTmdbId(), 'fr-FR', ['seasons']), true);
            $as = $this->getAlternateSchedule($seriesBroadcastSchedule, $tv, $userEpisodes);

            $airDays = $as['airDays'];
            foreach ($airDays as $airDay) {
                if (!$previousOverrideStatus) {
                    $seriesBroadcastDate = new SeriesBroadcastDate($seriesBroadcastSchedule, $airDay['episodeId'], $seasonNumber, $seasonPart, $airDay['episodeNumber'], $airDay['date']);
                } else {
                    $seriesBroadcastDate = $this->seriesBroadcastDateRepository->findOneBy(['episodeId' => $airDay['episodeId']]);
                    $seriesBroadcastDate->setDate($airDay['date']);
                }
                $this->seriesBroadcastDateRepository->save($seriesBroadcastDate);
            }
        }
        // Remove SeriesBroadcastDate records if the override is disabled
        if (!$override && $previousOverrideStatus) {
            $seriesBroadcastDates = $this->seriesBroadcastDateRepository->findBy(['seriesBroadcastSchedule' => $seriesBroadcastSchedule, 'seasonNumber' => $seasonNumber, 'seasonPart' => $seasonPart]);
            foreach ($seriesBroadcastDates as $seriesBroadcastDate) {
                $this->seriesBroadcastDateRepository->remove($seriesBroadcastDate);
            }
        }
        $this->seriesBroadcastDateRepository->flush();

        /*return $this->json([
            'ok' => true,
            'success' => true,
        ]);*/
        return new JsonResponse([
            'ok' => true,
            'success' => true,
        ]);
    }

    #[Route('/pinned/{id}', name: 'pinned', requirements: ['id' => Requirement::DIGITS])]
    public function pinnedSeries(Request $request, UserSeries $userSeries): Response
    {
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
    #[Route('/season/{id}-{slug}/{seasonNumber}', name: 'season', requirements: ['id' => Requirement::DIGITS, 'seasonNumber' => Requirement::DIGITS])]
    public function showSeason(Request $request, Series $series, int $seasonNumber, string $slug): Response
    {
        $user = $this->getUser();
        $locale = $user->getPreferredLanguage() ?? $request->getLocale();
        $country = $user->getCountry() ?? 'FR';
        $this->logger->info('showSeason', ['series' => $series->getId(), 'season' => $seasonNumber, 'slug' => $slug]);

        $userSeries = $this->userSeriesRepository->findOneBy(['user' => $user, 'series' => $series]);
        $this->checkSlug($series, $slug, $locale);

        $seriesImages = $series->getSeriesImages()->toArray();

        $episodeSizeSettings = $this->settingsRepository->findOneBy(['user' => $user, 'name' => 'episode_div_size_' . $userSeries->getId()]);
        if ($episodeSizeSettings) {
            $value = $episodeSizeSettings->getData();
            $episodeDivSize = $value['height'];
            $aspectRatio = $value['aspect-ratio'] ?? '16 / 9';
        } else {
            $episodeSizeSettings = new Settings($user, 'episode_div_size_' . $userSeries->getId(), ['height' => '15rem', 'aspect-ratio' => '16 / 9']);
            $this->settingsRepository->save($episodeSizeSettings, true);
            $episodeDivSize = '15rem';
            $aspectRatio = '16 / 9';
        }

        $season = json_decode($this->tmdbService->getTvSeason($series->getTmdbId(), $seasonNumber, $request->getLocale(), ['credits', 'watch/providers']), true);
        if (!$season) {
            return $this->redirectToRoute('app_series_show', [
                'id' => $series->getId(),
                'slug' => $series->getSlug(),
            ]);
        }
        if ($season['poster_path']) {
            if (!$this->inImages($season['poster_path'], $seriesImages)) {
                $seriesImage = new SeriesImage($series, "poster", $season['poster_path']);
                $this->seriesImageRepository->save($seriesImage, true);
                $this->imageService->saveImage("posters", $season['poster_path'], $this->imageConfiguration->getUrl('poster_sizes', 5));
            }
        } else {
            $season['poster_path'] = $series->getPosterPath();
        }

        $season['deepl'] = null;//$this->seasonLocalizedOverview($series, $season, $seasonNumber, $request);
        $season['episodes'] = $this->seasonEpisodes($season, $userSeries);
        $season['credits'] = $this->castAndCrew($season);
        $season['watch/providers'] = $this->watchProviders($season, $country);
        if ($season['overview'] == "") {
            $season['overview'] = $series->getOverview();
            $season['series_overview'] = true;
        } else {
            $season['series_overview'] = false;
        }
        $season['localized_name'] = $series->getLocalizedName($request->getLocale());
        $season['localized_overviews'] = $series->getLocalizedOverviews($request->getLocale());
        $season['additional_overviews'] = $series->getSeriesAdditionalLocaleOverviews($request->getLocale());

        $providers = $this->getWatchProviders($country);
        $devices = $this->deviceRepository->deviceArray();

        // Nouvelle saison, premier épisode non vu
        if ($season['season_number'] > 1 && count($season['episodes']) && $season['episodes'][0]['user_episode']['watch_at'] == null) {
            $firstEpisode = $this->userEpisodeRepository->findOneBy(['userSeries' => $userSeries, 'seasonNumber' => $season['season_number'], 'episodeNumber' => 1]);
            $previousSeasonNumber = $season['season_number'] - 1;
            $lastEpisode = $this->userEpisodeRepository->findOneBy(['userSeries' => $userSeries, 'seasonNumber' => $previousSeasonNumber], ['episodeNumber' => 'DESC']);
            $season['episodes'][0]['user_episode']['provider_id'] = $providerId = $lastEpisode->getProviderId();
            $season['episodes'][0]['user_episode']['provider_logo_path'] = $providerId ? $providers['logos'][$providerId] : null;
//            $season['episodes'][0]['user_episode']['device_id'] = $deviceId = $lastEpisode->getDeviceId();
            if ($firstEpisode->getProviderId() != $providerId) {
                $firstEpisode->setProviderId($providerId);
//                $firstEpisode->setDeviceId($deviceId);
                $this->userEpisodeRepository->save($firstEpisode, true);
            }
        }

        $tvKeywords = json_decode($this->tmdbService->getTvKeywords($series->getTmdbId()), true);
        $tvExternalIds = json_decode($this->tmdbService->getTvExternalIds($series->getTmdbId()), true);

        if (!$series->getNumberOfEpisode()) {
            $tv = json_decode($this->tmdbService->getTv($series->getTmdbId(), 'en-US'), true);
            $series->setNumberOfEpisode($tv['number_of_episodes']);
            $series->setNumberOfSeason($tv['number_of_seasons']);
            $this->seriesRepository->save($series, true);
        }

//        dump([
//            'series' => $series,
//            'season' => $season,
//            'episodeDivSize' => $episodeDivSize,
//            'now' => $this->now()->format('Y-m-d H:i O'),
//            'userSeries' => $userSeries,
//            'providers' => $providers,
//            'devices' => $devices,
//        ]);
        return $this->render('series/season.html.twig', [
            'series' => $series,
            'userSeries' => $userSeries,
            'season' => $season,
            'now' => $this->now()->format('Y-m-d H:i O'),
            'episodeDiv' => [
                'height' => $episodeDivSize,
                'aspectRatio' => $aspectRatio
            ],
            'providers' => $providers,
            'devices' => $devices,
            'externals' => $this->getExternals($series, $tvKeywords['results'] ?? [], $tvExternalIds, $request->getLocale()),
        ]);
    }

    #[Route('/localized/name/add/{id}', name: 'add_localized_name', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
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

    #[Route('/localized/name/delete/{id}', name: 'delete_localized_name', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
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
    #[Route('/overview/add/edit/{id}', name: 'add_overview', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
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

    #[Route('/overview/delete/{id}', name: 'delete_overview', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
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

    #[Route('/episode/add/{id}', name: 'add_episode', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function addUserEpisode(Request $request, int $id): Response
    {
        $inputBag = $request->getPayload();

        $showId = $inputBag->get('showId');
        $lastEpisode = $inputBag->get('lastEpisode') == "1";
        $seasonNumber = $inputBag->get('seasonNumber');
        $episodeNumber = $inputBag->get('episodeNumber');
        $ueId = $inputBag->get('ueId');
        $new = false;

        $messages = [];

        $user = $this->getUser();
//        $country = $user->getCountry() ?? 'FR';
        $series = $this->seriesRepository->findOneBy(['tmdbId' => $showId]);
        $userSeries = $this->userSeriesRepository->findOneBy(['user' => $user, 'series' => $series]);
        $userSeriesEpisodes = $this->userEpisodeRepository->findBy(['userSeries' => $userSeries], ['seasonNumber' => 'ASC', 'episodeNumber' => 'ASC']);
        $userEpisode = $this->userEpisodeRepository->findOneBy(['id' => $ueId]);
        $userEpisodes = $this->userEpisodeRepository->findBy(['userSeries' => $userSeries, 'episodeId' => $id], ['id' => 'ASC']);
        /*dump([
            'user' => $user->getId(),
            'showId' => $showId,
            'seasonNumber' => $seasonNumber,
            'episodeNumber' => $episodeNumber,
            'ueId' => $ueId,
            'userEpisodes' => $userEpisodes,
        ]);*/

        $now = $this->dateService->getNowImmutable($user->getTimezone() ?? 'Europe/Paris');
        if ($userEpisode->getWatchAt()) { // Si l'épisode a déjà été vu
            $userEpisode = new UserEpisode($userSeries, $id, $seasonNumber, $episodeNumber, $now);
            $userEpisode->setPreviousOccurrence($userEpisodes[count($userEpisodes) - 1]);
            $new = true;
        } else {
            $userEpisode->setWatchAt($now);
        }

        $firstViewedUserEpisode = $userEpisodes[0];
        $airDate = $firstViewedUserEpisode->getAirDate();
        if (!$airDate) {
            $tmdbEpisode = json_decode($this->tmdbService->getTvEpisode($showId, $seasonNumber, $episodeNumber, 'en-US'), true);
            $airDate = $tmdbEpisode['air_date'] ? $this->dateService->newDateImmutable($tmdbEpisode['air_date'], 'Europe/Paris', true) : null;
            $firstViewedUserEpisode->setAirDate($airDate);
            $this->userEpisodeRepository->save($firstViewedUserEpisode, true);
        }

        if ($new) {
            $userEpisode->setAirDate($airDate);
            $lastViewedUserEpisode = $userEpisodes[count($userEpisodes) - 1];
            $userEpisode->setProviderId($lastViewedUserEpisode->getProviderId());
            $userEpisode->setDeviceId($lastViewedUserEpisode->getDeviceId());
        } else {
            if ($airDate) {
                $diff = $now->diff($airDate);
                $quickWatchDay = $diff->days < 1;
                $quickWatchWeek = $diff->days < 7;
                $userEpisode->setQuickWatchDay($quickWatchDay);
                $userEpisode->setQuickWatchWeek($quickWatchWeek);
                if ($quickWatchWeek) {
                    if ($quickWatchDay) {
                        $messages[] = $this->translator->trans('Quick day watch badge unlocked');
                    } else {
                        $messages[] = $this->translator->trans('Quick week watch badge unlocked');
                    }
                }
            }

            if ($episodeNumber > 1) {
                $previousUserEpisode = $this->userEpisodeRepository->findOneBy(['userSeries' => $userSeries, 'seasonNumber' => $seasonNumber, 'episodeNumber' => $episodeNumber - 1]);
                $userEpisode->setProviderId($previousUserEpisode->getProviderId());
                $userEpisode->setDeviceId($previousUserEpisode->getDeviceId());
            } else {
                if ($seasonNumber > 1) {
                    $previousUserEpisode = $this->userEpisodeRepository->findOneBy(['userSeries' => $userSeries, 'seasonNumber' => $seasonNumber - 1], ['episodeNumber' => 'DESC']);
                    $userEpisode->setProviderId($previousUserEpisode->getProviderId());
                    $userEpisode->setDeviceId($previousUserEpisode->getDeviceId());
                }
            }

            // Si on regarde 3 épisodes en moins d'un jour, on considère que c'est un marathon
            if (!$userSeries->getMarathoner() && $episodeNumber >= 3) {
                $episodes = $this->userEpisodeRepository->findBy(['userSeries' => $userSeries, 'seasonNumber' => $seasonNumber], ['watchAt' => 'DESC'], 3);
//            dump($episodes);
                if ($episodes[0]->getEpisodeNumber() - $episodes[1]->getEpisodeNumber() == 1 && $episodes[1]->getEpisodeNumber() - $episodes[2]->getEpisodeNumber() == 1) {
                    $firstViewAt = $episodes[0]->getWatchAt();
                    $lastViewAt = $episodes[2]->getWatchAt();
                    $diff = $lastViewAt->diff($firstViewAt);
                    if ($diff->days < 1) {
                        $userSeries->setMarathoner(true);
                        $messages[] = $this->translator->trans('Marathoner badge unlocked');
                    }
                }
            }

            // Si on regarde le dernier épisode de la saison (hors épisodes spéciaux : $seasonNumber > 0)
            // et que l'on n'a pas regardé aure chose entre temps, on considère que c'est un binge
            if ($lastEpisode && $seasonNumber) {
                $isBinge = $this->isBinge($userSeries, $seasonNumber, $episodeNumber);
                $userSeries->setBinge($isBinge);
                if ($isBinge) {
                    $messages[] = $this->translator->trans('Binge watcher badge unlocked');
                }
            }
        }

        $this->userEpisodeRepository->save($userEpisode, true);

        if ($seasonNumber) {
            $userSeries->setLastWatchAt($now);
            $userSeries->setLastEpisode($episodeNumber);
            $userSeries->setLastSeason($seasonNumber);
            $userSeries->setLastUserEpisode($userEpisode);
            $nextUserEpisode = array_find($userSeriesEpisodes, function ($ue) {
                return $ue->getWatchAt() == null;
            });
            $userSeries->setNextUserEpisode($nextUserEpisode);

            if (!$new) {
                $userSeries->setViewedEpisodes($userSeries->getViewedEpisodes() + 1);
                $userSeries->setProgress($userSeries->getViewedEpisodes() / $series->getNumberOfEpisode() * 100);
            }
            $this->userSeriesRepository->save($userSeries, true);
        }

        $sbd = $this->seriesBroadcastDateRepository->findOneBy(['episodeId' => $id]);
        $airDate = $sbd ? $sbd->getDate() : $userEpisode->getAirDate();
        $ue = $this->userEpisodeRepository->getUserEpisodeDB($userEpisode->getId(), $user->getPreferredLanguage() ?? $request->getLocale());
        if ($ue['custom_date']) {
            $cd = $this->dateService->newDateImmutable($ue['custom_date'], 'Europe/Paris');
            $ue['custom_date'] = $cd->format('Y-m-d H:i O');
        }
        if ($ue['air_at']) {
            // 10:00:00 → 10:00
            $ue['air_at'] = $this->dateService->newDateImmutable($ue['air_at'], 'Europe/Paris');
            $ue['air_at'] = $ue['air_at']->format('H:i');
        }
        $ue['watch_at_db'] = $ue['watch_at'];
        if ($ue['watch_at']) {
            $ue['watch_at'] = $this->dateService->newDateImmutable($ue['watch_at'], 'UTC');
        }
        $arr = $this->userEpisodeRepository->getUserEpisodeViews($user->getId(), $id);
        $ues = array_map(function ($ue) {
            $ue['watch_at_db'] = $ue['watch_at'];
            $ue['watch_at'] = $this->dateService->newDateImmutable($ue['watch_at'], 'UTC');
            return $ue;
        }, $arr);
//        dump([
//            'episode' => ['id' => $id, 'air_date' => $airDate->format('Y-m-d')],
//            'ue' => $ue, //['watch_at' => $userEpisode->getWatchAt()->format('Y-m-d')],
//            'ues' => $ues,
//        ]);
        $airDateBlock = $this->renderView('_blocks/series/_episode-air-date.html.twig', [
            'episode' => ['id' => $id, 'air_date' => $airDate->format('Y-m-d')],
            'ue' => $ue, //['watch_at' => $userEpisode->getWatchAt()->format('Y-m-d')],
            'ues' => $ues,
        ]);

        return $this->json([
            'ok' => true,
            'airDateBlock' => $airDateBlock,
            'new' => $new,
            'views' => $this->translator->trans('Watched %time% times', ['%time%' => count($ues)]),
            'progress' => $userSeries->getProgress(),//$this->userEpisodeRepository->seasonProgress($userSeries, $seasonNumber),
            'messages' => $messages,
            'deviceId' => $userEpisode->getDeviceId() ?? 0,
            'providerId' => $userEpisode->getProviderId() ?? 0,
        ]);
    }

    #[Route('/episode/touch/{id}', name: 'touch_episode', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function touchUserEpisode(Request $request, UserEpisode $userEpisode): Response
    {
        $data = json_decode($request->getContent(), true);
//        $showId = $data['showId'];
        if (key_exists('date', $data) && $data['date']) {
            $now = $this->date($data['date']);
        } else {
            $now = $this->now();
        }
        $seasonNumber = $userEpisode->getEpisodeNumber();// $data['seasonNumber'];
        $episodeNumber = $userEpisode->getSeasonNumber();// $data['episodeNumber'];

//        $user = $this->getUser();
//        $country = $user->getCountry() ?? 'FR';
//        $series = $this->seriesRepository->findOneBy(['tmdbId' => $showId]);
        $userSeries = $userEpisode->getUserSeries();
//        $userSeries = $this->userSeriesRepository->findOneBy(['user' => $user, 'series' => $series]);
//        $userEpisode = $this->userEpisodeRepository->findOneBy(['userSeries' => $userSeries, 'episodeId' => $id]);

        $userEpisode->setWatchAt($now);

        $airDate = $userEpisode->getAirDate();

        $diff = $now->diff($airDate);
        $userEpisode->setQuickWatchDay($diff->days < 1);
        $userEpisode->setQuickWatchWeek($diff->days < 7);

        $this->userEpisodeRepository->save($userEpisode, true);

        $ue = $this->userEpisodeRepository->getUserEpisodeDB($userEpisode->getId(), $userEpisode->getUserSeries()->getUser()->getPreferredLanguage() ?? $request->getLocale());
        $ue['watch_at_db'] = $ue['watch_at'];
        $ue['watch_at'] = $this->dateService->newDateImmutable($ue['watch_at'], 'UTC');

        if ($seasonNumber) {
            $userSeries->setLastWatchAt($now);
            $userSeries->setLastEpisode($episodeNumber);
            $userSeries->setLastSeason($seasonNumber);
            $this->userSeriesRepository->save($userSeries, true);
        }
        $svg = '<svg viewBox="0 0 576 512" fill="currentColor" height="18px" width="18px" aria-hidden="true"><path fill="currentColor" d="M288 32c-80.8 0-145.5 36.8-192.6 80.6C48.6 156 17.3 208 2.5 243.7c-3.3 7.9-3.3 16.7 0 24.6C17.3 304 48.6 356 95.4 399.4C142.5 443.2 207.2 480 288 480s145.5-36.8 192.6-80.6c46.8-43.5 78.1-95.4 93-131.1c3.3-7.9 3.3-16.7 0-24.6c-14.9-35.7-46.2-87.7-93-131.1C433.5 68.8 368.8 32 288 32M144 256a144 144 0 1 1 288 0a144 144 0 1 1-288 0m144-64c0 35.3-28.7 64-64 64c-7.1 0-13.9-1.2-20.3-3.3c-5.5-1.8-11.9 1.6-11.7 7.4c.3 6.9 1.3 13.8 3.2 20.7c13.7 51.2 66.4 81.6 117.6 67.9s81.6-66.4 67.9-117.6c-11.1-41.5-47.8-69.4-88.6-71.1c-5.8-.2-9.2 6.1-7.4 11.7c2.1 6.4 3.3 13.2 3.3 20.3"></path></svg>';
        $viewedAt = $this->translator->trans('Today') . ', ' . $now->format('H:i');

        $watchedAtBlock = $this->renderView('_blocks/series/_watched-at.html.twig', [
            'episode' => ['id' => $userEpisode->getEpisodeId()],
            'e' => $ue,
        ]);

        return $this->json([
            'ok' => true,
            'viewedAt' => $svg . ' ' . $viewedAt,
            'dataViewedAt' => $now->format('Y-m-d H:i:s'),
            'watchedAtBlock' => $watchedAtBlock,
        ]);
    }

    #[Route('/episode/remove', name: 'remove_episode', methods: ['POST'])]
    public function removeUserEpisode(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);
        $showId = $data['showId'];
        $userEpisodeId = $data['userEpisodeId'];
        $seasonNumber = $data['seasonNumber'];
        $episodeNumber = $data['episodeNumber'];
        $locale = $request->getLocale();

        $user = $this->getUser();
        $series = $this->seriesRepository->findOneBy(['tmdbId' => $showId]);
        $userSeries = $this->userSeriesRepository->findOneBy(['user' => $user, 'series' => $series]);

        $userEpisode = $this->userEpisodeRepository->findOneBy(['id' => $userEpisodeId]);
        if ($userEpisode) {
            if ($userEpisode->getPreviousOccurrence()) {
                // on met à jour la "user" séries avec l'épisode précédemment vu
                $lastWatchedEpisode = $this->userEpisodeRepository->findOneBy(['userSeries' => $userSeries, 'previousOccurrence' => null], ['watchAt' => 'DESC']);
//                dump($lastWatchedEpisode);
                $userSeries->setLastUserEpisode($lastWatchedEpisode);
                $userSeries->setLastWatchAt($lastWatchedEpisode->getWatchAt());
                $userSeries->setLastEpisode($lastWatchedEpisode->getEpisodeNumber());
                $userSeries->setLastSeason($lastWatchedEpisode->getSeasonNumber());
//                dump($userSeries);
                $this->userSeriesRepository->save($userSeries, true);
                $this->userEpisodeRepository->remove($userEpisode);
                return $this->json([
                    'ok' => true,
                    'progress' => $userSeries->getProgress(),
                ]);
            }

            $userEpisode->setWatchAt(null);
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
                    $episode = $this->userEpisodeRepository->findOneBy(['userSeries' => $userSeries, 'seasonNumber' => $j, 'episodeNumber' => $i]);
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
                            'progress' => $this->userEpisodeRepository->seasonProgress($userSeries, $seasonNumber),
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
            'progress' => $this->userEpisodeRepository->seasonProgress($userSeries, $seasonNumber),
        ]);
    }

    #[Route('/episode/provider/{id}', name: 'episode_provider', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function userEpisodeProvider(Request $request, UserEpisode $userEpisode): JsonResponse
    {
        if ($request->isMethod('POST')) {
            $providerId = $request->getPayload()->get('providerId');
            $userEpisode->setProviderId($providerId);
            $this->userEpisodeRepository->save($userEpisode, true);
        }
        return new JsonResponse(['ok' => true]);
    }

    #[Route('/episode/device/{id}', name: 'episode_device', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function userEpisodeDevice(Request $request, UserEpisode $userEpisode): JsonResponse
    {
        if ($request->isMethod('POST')) {
            $deviceId = $request->getPayload()->get('deviceId');
            $userEpisode->setDeviceId($deviceId);
            $this->userEpisodeRepository->save($userEpisode, true);
        }
        return new JsonResponse(['ok' => true]);
    }

    #[Route('/episode/vote/{id}', name: 'episode_vote', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function userEpisodeVote(Request $request, UserEpisode $userEpisode): JsonResponse
    {
        if ($request->isMethod('POST')) {
            $vote = $request->getPayload()->get('vote');
            $userEpisode->setVote($vote);
            $this->userEpisodeRepository->save($userEpisode, true);
        }
        return new JsonResponse(['ok' => true]);
    }

    #[Route('/episode/height/{userSeriesId}', name: 'episode_height', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function episodeDivHeight(Request $request, int $userSeriesId): Response
    {
        $user = $this->getUser();
        $episodeSizeSettings = $this->settingsRepository->findOneBy(['user' => $user, 'name' => 'episode_div_size_' . $userSeriesId]);
        $settings = $episodeSizeSettings->getData();

        $data = json_decode($request->getContent(), true);
        $settings['height'] = $data['height'];
        $settings['aspect-ratio'] = $data['aspectRatio'];

        $episodeSizeSettings->setData($settings);
        $this->settingsRepository->save($episodeSizeSettings, true);

        return $this->json([
            'ok' => true,
        ]);
    }

    #[Route('/episode/update/info/{id}', name: 'episode_update_info', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function episodeUpdateInfo(Request $request, int $id): Response
    {
        $data = json_decode($request->getContent(), true);
        $content = $data['content'];
        $type = $data['type'];

        if ($type === 'name') {
            $esn = $this->episodeSubstituteNameRepository->findOneBy(['episodeId' => $id]);
            if ($esn) {
                $esn->setName($content);
            } else {
                $esn = new EpisodeSubstituteName($id, $content);
            }
            $this->episodeSubstituteNameRepository->save($esn, true);
        }
        if ($type === 'overview') {
            $elo = $this->episodeLocalizedOverviewRepository->findOneBy(['episodeId' => $id]);
            if ($elo) {
                $elo->setOverview($content);
            } else {
                $elo = new EpisodeLocalizedOverview($id, $content, $request->getLocale());
            }
            $this->episodeLocalizedOverviewRepository->save($elo, true);
        }

        return $this->json([
            'ok' => true,
        ]);
    }

    #[Route('/episode/update/infos', name: 'episode_update_infos', methods: ['POST'])]
    public function episodeUpdateInfos(Request $request): JsonResponse
    {
        $createdTitleCount = 0;
        $createdOverviewCount = 0;
        $updatedTitleCount = 0;
        $updatedOverviewCount = 0;

        if ($request->isMethod('POST')) {
            $payload = $request->getPayload();

            foreach ($payload as $key => $value) {
                list($type, $episodeId) = explode('-', $key);
                $content = $value;

                if ($content === "") {
                    continue;
                }
                if ($type === 'title' && !str_contains($content, 'pisode')) {
                    $esn = $this->episodeSubstituteNameRepository->findOneBy(['episodeId' => $episodeId]);
                    if ($esn) {
                        if ($content === $esn->getName()) {
                            continue;
                        }
                        $esn->setName($content);
                        $updatedTitleCount++;
                    } else {
                        $esn = new EpisodeSubstituteName($episodeId, $content);
                        $createdTitleCount++;
                    }
                    $this->episodeSubstituteNameRepository->save($esn, true);
                }
                if ($type === 'overview') {
                    $elo = $this->episodeLocalizedOverviewRepository->findOneBy(['episodeId' => $episodeId]);
                    if ($elo) {
                        if ($content === $elo->getOverview()) {
                            continue;
                        }
                        $elo->setOverview($content);
                        $updatedOverviewCount++;
                    } else {
                        $elo = new EpisodeLocalizedOverview($episodeId, $content, $request->getLocale());
                        $createdOverviewCount++;
                    }
                    $this->episodeLocalizedOverviewRepository->save($elo, true);
                }
            }
        }
        $message = 'Titles and overviews updated.<br>';
        if ($createdTitleCount) {
            $message .= 'Created titles: ' . $createdTitleCount . '<br>';
        }
        if ($createdOverviewCount) {
            $message .= 'Created overviews: ' . $createdOverviewCount . '<br>';
        }
        if ($updatedTitleCount) {
            $message .= 'Updated titles: ' . $updatedTitleCount . '<br>';
        }
        if ($updatedOverviewCount) {
            $message .= 'Updated overviews: ' . $updatedOverviewCount;
        }
        $this->addFlash('success', $message);
        return new JsonResponse([
            'ok' => true,
            'createdTitleCount' => $createdTitleCount,
            'createdOverviewCount' => $createdOverviewCount,
            'updatedTitleCount' => $updatedTitleCount,
            'updatedOverviewCount' => $updatedOverviewCount,
        ]);
    }

    #[Route('/episode/still/{id}', name: 'still', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function episodeStill(Request $request, int $id): Response
    {
        /** @var UploadedFile $uploadedFile */
        $uploadedFile = $request->files->get('file');
//        dump($uploadedFile);
        $basename = $uploadedFile->getClientOriginalName();
        $projectDir = $this->getParameter('kernel.project_dir');
        $imageTempPath = $projectDir . '/public/images/temp/';
        $tempName = $imageTempPath . $basename;
        $stillPath = $projectDir . '/public/series/stills/' . $basename . '.webp';

        // Ajout d'un suffixe si le fichier existe déjà
        $copyCount = 0;
        while (file_exists($stillPath)) {
            $stillPath = $projectDir . '/public/series/stills/' . $basename . '-' . ++$copyCount . '.webp';
        }

        try {
            $uploadedFile->move($imageTempPath, $basename);
        } catch (FileException $e) {
            return $this->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ]);
        }
        $copy = false;

        try {
            $webp = $this->imageService->webpImage($tempName, $stillPath, 90, -1);
            if ($webp) {
                if ($copyCount) $basename .= '-' . $copyCount;
                $imagePath = '/' . $basename . '.webp';
            } else {
                $imagePath = null;
            }
        } catch (FileException $e) {
            return $this->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ]);
        }

        if ($imagePath) {
            $episodeStill = new EpisodeStill($id, $imagePath);
            $this->episodeStillRepository->save($episodeStill, true);
            $copy = true;
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

    #[Route('/backdrops/get/{id}', name: 'get_backdrops', requirements: ['id' => Requirement::DIGITS], methods: 'POST')]
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

    #[Route('/backdrops/add', name: 'add_backdrops', methods: 'POST')]
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
                $this->imageService->saveImage("backdrops", $backdrop['file_path'], $backdropUrl);
                $addedBackdropCount++;
            }
        }
        foreach ($posters as $poster) {
            if (!$this->inImages($poster['file_path'], $images)) {
                $seriesImage = new SeriesImage($series, "poster", $poster['file_path']);
                $this->seriesImageRepository->save($seriesImage);
                $this->imageService->saveImage("posters", $poster['file_path'], $posterUrl);
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

    #[Route('/location/add/{id}', name: 'add_location', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function addLocation(Request $request, Series $series): Response
    {
        $messages = [];

        $data = $request->request->all();
        $files = $request->files->all();
        /*dump([
            'data' => $data,
            'files' => $files,
        ]);*/
        if (empty($data) && empty($files)) {
            return $this->json([
                'ok' => false,
                'message' => 'No data',
            ]);
        }

        $imageFiles = [];
        foreach ($files as $key => $file) {
            /*dump($file);*/
            if ($file instanceof UploadedFile) {
                // Est-ce qu'il s'agit d'une image ?
                $mimeType = $file->getMimeType();
                if (str_starts_with($mimeType, 'image')) {
                    $imageFiles[$key] = $file;
                }
            }
        }
        /*dump([
            'data' => $data,
            'image files' => $imageFiles,
        ]);*/
        if ($data['location'] == 'test') {
            // "image-url" => "blob:https://localhost:8000/71698467-714e-4b2e-b6b3-a285619ea272"
            $testUrl = $data['image-url'];
            if (str_starts_with($testUrl, 'blob')) {
//                $this->blobs['image-url-blob'] = $data['image-url-blob'];
                /*$imageResultPath =*/
                $this->imageService->blobToWebp2($data['image-url-blob'], $data['title'], $data['location'], 100);
//                dump($imageResultPath);
            }

            return $this->json([
                'ok' => true,
                'testMode' => true,
                'message' => 'Test location',
            ]);
        }
        $data = array_filter($data, fn($key) => $key != "google-map-url", ARRAY_FILTER_USE_KEY);

        $crudType = $data['crud-type'];
        $new = $crudType === 'create';
        $crudId = $data['crud-id'];
        $now = $this->now();

        if (!$new)
            $filmingLocation = $this->filmingLocationRepository->findOneBy(['id' => $crudId]);
        else
            $filmingLocation = null;
//        $seriesId = $data['series-id'];

        $title = $data['title'];
        $location = $data['location'];
        $description = $data['description'];
        $data['latitude'] = str_replace(',', '.', $data['latitude']);
        $data['longitude'] = str_replace(',', '.', $data['longitude']);
        $episodeNumber = intval($data['episode-number']);
        $seasonNumber = intval($data['season-number']);
        $latitude = $data['latitude'] = floatval($data['latitude']);
        $longitude = $data['longitude'] = floatval($data['longitude']);

        if ($crudType === 'create') {// Toutes les images
            $images = array_filter($data, fn($key) => str_contains($key, 'image-url'), ARRAY_FILTER_USE_KEY);
        } else { // Images supplémentaires
            $images = array_filter($data, fn($key) => str_contains($key, 'image-url-'), ARRAY_FILTER_USE_KEY);
        }
//        $images = array_values($images);
        $images = array_filter($images, fn($image) => $image != '' and $image != "undefined");
//        dump(['images' => $images]);
        // TODO: Vérifier le code suivant
        $firstImageIndex = 1;
        if ($filmingLocation) {
            // Récupérer les images existantes et les compter
            $existingAdditionalImages = $this->filmingLocationImageRepository->findBy(['filmingLocation' => $filmingLocation]);
//            dump(['existingAdditionalImages' => $existingAdditionalImages]);
            $firstImageIndex += count($existingAdditionalImages);
//            dump(['firstImageIndex' => $firstImageIndex]);
        }
        // Fin du code à vérifier

        if (!$filmingLocation) {
            $uuid = $data['uuid'] = Uuid::v4()->toString();
            $tmdbId = $data['tmdb-id'];
            $filmingLocation = new FilmingLocation($uuid, $tmdbId, $title, $location, $description, $latitude, $longitude, $seasonNumber, $episodeNumber, $now, true);
            $filmingLocation->setOriginCountry($series->getOriginCountry());
        } else {
            $filmingLocation->update($title, $location, $description, $latitude, $longitude, $seasonNumber, $episodeNumber, $now);
        }
        $this->filmingLocationRepository->save($filmingLocation, true);

        $n = $firstImageIndex;
        /****************************************************************************************
         * En mode dev, on peut ajouter des FilmingLocationImage sans passer par le             *
         * téléversement : "~/some picture.webp"                                                *
         * SINON :                                                                              *
         * Images ajoutées avec Url (https://website/some-pisture.png)                          *
         * ou par glisser-déposer ("blob:https://website/71698467-714e-4b2e-b6b3-a285619ea272") *
         ****************************************************************************************/
        foreach ($images as $name => $imageUrl) {
            if (str_starts_with($imageUrl, '~/')) {
                $image = str_replace('~/', '/', $imageUrl);
            } else {
                if (str_starts_with('blob:', $imageUrl)) {
//                    $this->blobs[$name . '-blob'] = $data[$name . '-blob'];
                    $image = $this->imageService->blobToWebp2($data[$name . '-blob'], $data['title'], $data['location'], $n);
                } else {
                    $image = $this->imageService->urlToWebp($imageUrl, $title, $location, $n);
                }
            }
            if ($image) {
                $filmingLocationImage = new FilmingLocationImage($filmingLocation, $image, $now);
                $this->filmingLocationImageRepository->save($filmingLocationImage, true);

                if ($crudType === 'create' && $n == 1) {
                    $filmingLocation->setStill($filmingLocationImage);
                    $this->filmingLocationRepository->save($filmingLocation, true);
                }
                $n++;
            }
        }

        /******************************************************************************
         * Images ajoutées depuis des fichiers locaux (type : UploadedFile)           *
         ******************************************************************************/
        foreach ($imageFiles as $key => $file) {
//            dump(['key' => $key, 'file' => $file]);
            $image = $this->imageService->fileToWebp($file, $title, $location, $n);
            if ($image) {
                $filmingLocationImage = new FilmingLocationImage($filmingLocation, $image, $now);
                $this->filmingLocationImageRepository->save($filmingLocationImage, true);

                if ($key === 'image-file') { // la vignette
                    $filmingLocation->setStill($filmingLocationImage);
                    $this->filmingLocationRepository->save($filmingLocation, true);
                }
                $n++;
            }
        }
        if ($n > $firstImageIndex) {
            $addedImageCount = $n - $firstImageIndex;
            $messages[] = $addedImageCount . $addedImageCount > 1 ? ' images ajoutées' : ' image ajoutée';
        }

        return $this->json([
            'ok' => true,
            'messages' => $messages,
        ]);
    }

    #[Route('/fetch/search/db/tv', name: 'fetch_search_db_tv', methods: ['POST'])]
    public function fetchSearchDbTv(Request $request): Response
    {
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

    #[Route('/fetch/search/multi', name: 'fetch_search_multi', methods: ['POST'])]
    public function fetchSearchMulti(Request $request): Response
    {
        $user = $this->getUser();
        $locale = $user->getPreferredLanguage() ?? $request->getLocale();
        $data = json_decode($request->getContent(), true);
        $query = $data['query'];

        if ($query === 'init') {
            $multi = ["results" => []];
        } else {
            $multi = json_decode($this->tmdbService->searchMulti(1, $query, $locale), true);
        }

        return $this->json([
            'ok' => true,
            'results' => $multi['results'],
            'posterUrl' => $this->imageConfiguration->getUrl('poster_sizes', 3),
            'profileUrl' => $this->imageConfiguration->getUrl('profile_sizes', 3),
        ]);
    }

    #[Route('/fetch/search/series', name: 'fetch_search_series', methods: ['POST'])]
    public function fetchSearchSeries(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);
        $query = $data['query'];

        $searchString = "&query=$query&include_adult=false&page=1";
        $series = json_decode($this->tmdbService->searchTv($searchString), true);

        return $this->json([
            'ok' => true,
            'results' => $series['results'],
        ]);
    }

    #[Route('/tmdb/check', name: 'tmdb_check', methods: ['POST'])]
    public function tmdbCheck(Request $request): Response
    {
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

        $t0 = microtime(true);
        foreach ($dbSeries as $series) {
            $lastUpdate = $series->getUpdatedAt();
            $interval = $now->diff($lastUpdate);

            if ($interval->days < 1) {
                $updates[] = [
                    'id' => $series->getId(),
                    'name' => $series->getName(),
                    'localized_name' => $localizedNames[$series->getId()] ?? null,
                    'poster_path' => $series->getPosterPath(),
                    'updates' => [], // '*** Updated less than 24 hours ago ***'
                ];
                continue;
            }
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
            $update = $updateSeries->getUpdates();
            $updates[] = [
                'id' => $series->getId(),
                'name' => $series->getName(),
                'localized_name' => $localizedNames[$series->getId()] ?? null,
                'poster_path' => $series->getPosterPath(),
                'updates' => $update];
            $series->setUpdatedAt($now);
            $this->seriesRepository->save($series);

            $t1 = microtime(true);
            $interval = $t1 - $t0;
            if ($interval > 25) {
                break;
            }
        }
        $this->seriesRepository->flush();

//        dump([
//            'tmdbIds' => $tmdbIds,
//            'dbSeries' => $dbSeries,
//            'updates' => $updates,
//        ]);

        return $this->json([
            'ok' => true,
            'updates' => $updates,
            'dbSeriesCount' => $dbSeriesCount,
            'tmdbCalls' => $tmdbCalls,
        ]);
    }

    public function addVideo(SeriesVideo $video): bool
    {
        $this->seriesVideoRepository->save($video, true);
        return true;
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

    public function updateSeries(Series $series, array $tv): Series
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
            $this->imageService->saveImage($type, $seriesImage->getImagePath(), $url);
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
//            $url = $this->imageConfiguration->getUrl($imageConfigType, $sizes[$type]);
            foreach ($tv['images'][$type] as $img) {
                if (!$this->inImages($img['file_path'], $seriesImages)) {
                    $seriesImage = new SeriesImage($series, $dbType, $img['file_path']);
                    $this->seriesImageRepository->save($seriesImage, true);
                    $series->addUpdate($this->translator->trans(ucfirst($dbType) . ' added'));
                }
            }
            $tv['images'][$type] = array_map(function ($image) use ($type, $sizes, $imageConfigType) {
                $this->imageService->saveImage($type, $image['file_path'], $this->imageConfiguration->getUrl($imageConfigType, $sizes[$type]));
                return '/series/' . $type . $image['file_path'];
            }, $tv['images'][$type]);
        }

        if ($tv['poster_path'] != $series->getPosterPath()) {
            $series->setPosterPath($tv['poster_path']);
            $this->imageService->saveImage("posters", $series->getPosterPath(), $this->imageConfiguration->getUrl('poster_sizes', 5));
            $series->addUpdate($this->translator->trans('Poster updated'));
        }
        if ($tv['backdrop_path'] != $series->getBackdropPath()) {
            $series->setBackdropPath($tv['backdrop_path']);
            $this->imageService->saveImage("backdrops", $series->getBackdropPath(), $this->imageConfiguration->getUrl('backdrop_sizes', 3));
            $series->addUpdate($this->translator->trans('Backdrop updated'));
        }
        $this->seriesRepository->save($series, true);

        return $series;
    }

    public function getSeriesImages(Series $series): array
    {
        $seriesImages = $this->seriesRepository->seriesImages($series);
        $seriesBackdrops = array_filter($seriesImages, fn($image) => $image['type'] == "backdrop");
        $seriesLogos = array_filter($seriesImages, fn($image) => $image['type'] == "logo");
        $seriesPosters = array_filter($seriesImages, fn($image) => $image['type'] == "poster");

        $seriesBackdrops = array_values(array_map(fn($image) => "/series/backdrops" . $image['image_path'], $seriesBackdrops));
        $seriesLogos = array_values(array_map(fn($image) => "/series/logos" . $image['image_path'], $seriesLogos));
        $seriesPosters = array_values(array_map(fn($image) => "/series/posters" . $image['image_path'], $seriesPosters));

        return [$seriesBackdrops, $seriesLogos, $seriesPosters];
    }

    public function getExternals(Series $series, array $keywords, $externalIds, string $locale): array
    {
        $keywordIds = array_map(fn($k) => $k['id'], $keywords);
        /*dump([
            'keywords' => $keywords,
            'keyword ids' => $keywordIds,
            'external ids' => $externalIds,
        ]);*/
        $seriesCountries = $series->getOriginCountry();
        $dbExternals = $this->seriesExternalRepository->findAll();
        $externals = [];
        $displayName = $series->getLocalizedName($locale)?->getName() ?? $series->getName();

        /** @var SeriesExternal $dbExternal */
        foreach ($dbExternals as $dbExternal) {
            $dbKeywordIds = array_map(fn($k) => $k['id'], $dbExternal->getKeywords());
            if (count($dbKeywordIds) && !array_intersect($keywordIds, $dbKeywordIds)) {
                continue;
            }
            $countries = $dbExternal->getCountries();
            $searchQuery = $dbExternal->getSearchQuery();
            $searchType = $dbExternal->getSearchType();
            if ($searchType == "name") {
                $searchSeparator = $dbExternal->getSearchSeparator();
                $searchName = strtolower($searchSeparator ? str_replace(' ', $searchSeparator, $displayName) : $displayName);
                if (!count($countries) || array_intersect($seriesCountries, $countries)) {
                    $dbExternal->fullUrl = $searchQuery ? $searchName : null;
                    $externals[] = $dbExternal;
                }
            } else {
                $id = $externalIds[$searchType] ?? null;
                if ($id) {
                    $dbExternal->fullUrl = $id;
                    $externals[] = $dbExternal;
                }
            }
        }
        /*dump([
            'series' => $series,
            'keywords' => $keywords,
            'keyword ids' => $keywordIds,
            'external ids' => $externalIds,
            'dbExternals' => $dbExternals,
            'externals' => $externals,
        ]);*/
        return $externals;
    }

    public function getTMDBExternals($displayName, $seriesCountries): array
    {
        $dbExternals = $this->seriesExternalRepository->findAll();
        $externals = [];

        /** @var SeriesExternal $dbExternal */
        foreach ($dbExternals as $dbExternal) {
            $countries = $dbExternal->getCountries();
            $searchQuery = $dbExternal->getSearchQuery();
            $searchSeparator = $dbExternal->getSearchSeparator();
            $searchName = strtolower($searchSeparator ? str_replace(' ', $searchSeparator, $displayName) : $displayName);
            if (!count($countries) || array_intersect($seriesCountries, $countries)) {
                $dbExternal->fullUrl = $searchQuery ? $searchName : null;
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
//        dump($episodeCount);
//        if ($episodeCount != $tv['number_of_episodes']) {
//            $this->addFlash('warning', $this->translator->trans('Number of episodes has changed') . '<br>' . $tv['number_of_episodes'] . ' → ' . $episodeCount);
//        }
        if ($episodeCount == 0 && $userSeries->getProgress() != 0) {
            $this->addFlash('warning', 'Number of episodes is zero');
            $userSeries->setProgress(0);
            $change = true;
        } else {
            if (/*$userSeries->getProgress() == 100 && */ $userSeries->getViewedEpisodes() < $episodeCount) {
                $newProgress = $userSeries->getViewedEpisodes() / $episodeCount * 100;
                $newProgress = number_format($newProgress, 2);
                if ($newProgress != $userSeries->getProgress()) {
                    $userSeries->setProgress($newProgress);
                    $this->addFlash('success', 'Progress updated to ' . $newProgress . '%');
                    $change = true;
                }
            }
            if ($userSeries->getProgress() != 100 && $episodeCount && $userSeries->getViewedEpisodes() === $episodeCount) {
                $userSeries->setProgress(100);
                $this->addFlash('success', 'Progress fixed to 100%');
                $change = true;
            }
        }
        if ($userSeries->getViewedEpisodes() == 0 && $userSeries->getProgress() != 0) {
            $userSeries->setProgress(0);
            $this->addFlash('warning', 'Progress reset to 0%');
            $change = true;
        }
        if ($change) {
            $this->userSeriesRepository->save($userSeries, true);
        }
        return $userSeries;
    }

    public function getUserSeasons(Series $series, array $userEpisodes): array
    {
        $seasonArr = [];
        $posterPath = '/series/posters' . $series->getPosterPath();
        foreach ($userEpisodes as $ue) {
            $seasonNumber = $ue->getSeasonNumber();
            $episodeNumber = $ue->getEpisodeNumber();
            $seasonArr[$seasonNumber][$episodeNumber]['air_date'] = $ue->getAirDate();
        }
        $seasons = [];
        foreach ($seasonArr as $seasonNumber => $seasonItem) {
            $season['air_date'] = $seasonItem[1]['air_date']->format('Y-m-d');
            $season['episode_count'] = count($seasonItem);
            $season['name'] = $this->translator->trans('Season') . ' ' . $seasonNumber;
            $season['overview'] = null;
            $season['poster_path'] = $posterPath;
            $season['season_number'] = $seasonNumber;
            $seasons[] = $season;
        }
        return $seasons;
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

    public function seriesSchedulesV2(User $user, Series $series, ?array $tv): array
    {
        $schedules = [];
        $locale = $user->getPreferredLanguage() ?? 'fr';
        $userSeries = $this->userSeriesRepository->findOneBy(['user' => $user, 'series' => $series]);
        $logoUrl = $this->imageConfiguration->getUrl('logo_sizes', 5);

        foreach ($series->getSeriesBroadcastSchedules() as $schedule) {
//            dump($schedule);
            $seasonNumber = $schedule->getSeasonNumber();
            if ($schedule->isMultiPart()) {
                $multiPart = true;
//                $seasonPart = $schedule->getSeasonPart();
                $firstEpisode = $schedule->getSeasonPartFirstEpisode();
                $episodeCount = $schedule->getSeasonPartEpisodeCount();
                $lastEpisode = $firstEpisode + $episodeCount - 1;
            } else {
                $multiPart = false;
//                $seasonPart = null;
                $firstEpisode = 1;
                $episodeCount = $tv ? $this->getSeasonEpisodeCount($tv['seasons'], $seasonNumber) : 0;
                $lastEpisode = $episodeCount;
            }
            $airAt = $schedule->getAirAt();
            $firstAirDate = $schedule->getFirstAirDate();
            $frequency = $schedule->getFrequency();
            $override = $schedule->isOverride();
            $dayOfWeekArr = [
                'en' => ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
                'fr' => ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'],
                'ko' => ['일요일', '월요일', '화요일', '수요일', '목요일', '금요일', '토요일'],
            ];
            $daysOfWeek = $schedule->getDaysOfWeek();
            $scheduleDayOfWeek = array_map(fn($day) => $dayOfWeekArr[$locale][$day], $daysOfWeek);
            $scheduleDayOfWeek = ucfirst(implode(', ', $scheduleDayOfWeek));
            $dayArr = array_fill(0, 7, false);
            foreach ($daysOfWeek as $day) {
                $dayArr[$day] = true;
            }

            $now = $this->dateService->newDateImmutable('now', 'Europe/Paris');

            $userLastEpisode = $this->userEpisodeRepository->getScheduleLastEpisode($schedule->getId(), $userSeries->getId());
            $userNextEpisode = $this->userEpisodeRepository->getScheduleNextEpisode($schedule->getId(), $userSeries->getId());
            $userLastEpisode = $userLastEpisode[0] ?? null;
            $userNextEpisode = $userNextEpisode[0] ?? null;
//            dump([
//                'userLastEpisode' => $userLastEpisode,
//                'userNextEpisode' => $userNextEpisode,
//            ]);
            $userLastEpisode = $this->setEpisodeDatetime($userLastEpisode, $airAt, $user->getTimezone() ?? 'Europe/Paris');
            $userNextEpisode = $this->setEpisodeDatetime($userNextEpisode, $airAt, $user->getTimezone() ?? 'Europe/Paris');
//            dump([
//                'episodeCount' => $episodeCount,
//                'userLastEpisode' => $userLastEpisode,
//                'userNextEpisode' => $userNextEpisode,
//                'multiPart' => $multiPart,
//                'seasonPart' => $seasonPart,
//            ]);
            $endOfSeason = $userLastEpisode && $userLastEpisode['episode_number'] == $episodeCount;

            $target = null;
            $targetTS = null;
            if (!$userNextEpisode && $userLastEpisode) {
                if ($multiPart) {
                    if ($userLastEpisode['episode_number'] >= $firstEpisode && $userLastEpisode['episode_number'] <= $lastEpisode) {
                        $targetTS = $userLastEpisode['date']->getTimestamp();
//                        dump('done!');
                    } else {
                        $userLastEpisode = null;
                    }
                } else {
                    $targetTS = $userLastEpisode['date']->getTimestamp();
                }
            }
            if ($userNextEpisode) {
                if ($multiPart) {
                    if ($userNextEpisode['episode_number'] >= $firstEpisode && $userNextEpisode['episode_number'] <= $lastEpisode) {
                        $targetTS = $userNextEpisode['date']->getTimestamp();
                    } else {
                        $userNextEpisode = null;
                    }
                } else {
                    $targetTS = $userNextEpisode['date']?->getTimestamp() ?? null;
                }
            }

            if ($userNextEpisode && $targetTS) {
                $userNextEpisodes = $this->userEpisodeRepository->getScheduleNextEpisodes($schedule->getId(), $userSeries->getId(), $userNextEpisode['air_date']);
//                dump($userNextEpisodes);
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
//            dump(['userLastEpisode' => $userLastEpisode, 'userNextEpisode' => $userNextEpisode, 'userLastNextEpisode' => $userLastNextEpisode]);
            if ($userNextEpisode == null && $userLastEpisode) {
                $targetTS = $userLastEpisode['date']->getTimestamp();
            }

            $providerId = $schedule->getProviderId();
            if ($providerId) {
                $provider = $this->providerRepository->findOneBy(['providerId' => $providerId]);
                $providerName = $provider->getName();
                $providerLogo = $this->getProviderLogoFullPath($provider->getLogoPath(), $logoUrl);
            } else {
                $providerName = null;
                $providerLogo = null;
            }

            $schedules[] = [
                'id' => $schedule->getId(),
                'seasonNumber' => $schedule->getSeasonNumber(),
                'multiPart' => $schedule->isMultiPart(),
                'seasonPart' => $schedule->getSeasonPart(),
                'seasonPartFirstEpisode' => $schedule->getSeasonPartFirstEpisode(),
                'seasonPartEpisodeCount' => $schedule->getSeasonPartEpisodeCount(),
                'upToDate' => $userNextEpisode == null,
                'seasonCompleted' => $endOfSeason,
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
//                'tvLastEpisode' => $tvLastEpisode,
//                'tvNextEpisode' => $tvNextEpisode,
                'toBeContinued' => $tv ? $this->isToBeContinued($tv, $userLastEpisode) : $userNextEpisode != null,
                'tmdbStatus' => $tv['status'] ?? 'series not found',
            ];
        }
        return $schedules;
    }

    public function getAlternateSchedule(SeriesBroadcastSchedule $schedule, ?array $tv, array $userEpisodes): array
    {
        $errorArr = ['seasonNumber' => 0, 'multiPart' => false, 'seasonPart' => 0, 'airDays' => []];
        $now = $this->now();
        $seasonNumber = $schedule->getSeasonNumber();
        $multiPart = $schedule->isMultiPart();
        $seasonPart = $schedule->getSeasonPart();

        if (!$tv) {
            $dayArr = [];
            $episodeNumber = 1;
            $airAt = $schedule->getAirAt();
            foreach ($userEpisodes as $userEpisode) {
                $ue = $userEpisode;
                $date = $ue->getAirDate()->setTime($airAt->format('H'), $airAt->format('i'));
                $dayArr[] = ['date' => $date, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $episodeNumber), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $episodeNumber), 'future' => $now < $date];
                $episodeNumber++;
            }
            return ['seasonNumber' => $seasonNumber, 'multiPart' => false, 'seasonPart' => 0, 'airDays' => $dayArr];
        }
        /*if (!$seasonNumber) {
            return $errorArr;
        }*/
        $frequency = $schedule->getFrequency();
        $firstAirDate = $schedule->getFirstAirDate();
        $airAt = $schedule->getAirAt();
        $daysOfWeek = $schedule->getDaysOfWeek();

        if ($schedule->isMultiPart()) {
            $firstEpisode = $schedule->getSeasonPartFirstEpisode();
            $episodeCount = $schedule->getSeasonPartEpisodeCount();
            $lastEpisode = $firstEpisode + $episodeCount - 1;
        } else {
            $firstEpisode = 1;
            $episodeCount = $this->getSeason($tv['seasons'], $seasonNumber)['episode_count'];
            $lastEpisode = $episodeCount;
        }
        /*dump([
            'firstEpisode' => $firstEpisode,
            'episodeCount' => $episodeCount,
            'lastEpisode' => $lastEpisode,
        ]);*/

        // Frequency values:
        //  1 - All at once
        //  2 - Daily
        //  3 - Weekly, one at a time
        //  4 - Weekly, two at a time
        //  5 - Weekly, three at a time
        //  6 - Weekly, two, then one
        //  7 - Weekly, three, then one
        //  8 - Weekly, four, then one
        //  9 - Weekly, four, then two
        // 10 - Weekly, selected days

        $firstAirDate = $firstAirDate->setTime($airAt->format('H'), $airAt->format('i'));
        $date = $firstAirDate;
        $dayArr = [];
        switch ($frequency) {
            case 1: // All at once
                for ($i = $firstEpisode; $i <= $lastEpisode; $i++) {
                    $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $i), 'episodeNumber' => $i, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $i), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $i), 'future' => $now < $date];
                }
                break;
            case 2: // Daily
                for ($i = $firstEpisode; $i <= $lastEpisode; $i++) {
                    $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $i), 'episodeNumber' => $i, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $i), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $i), 'future' => $now < $date];
                    $date = $this->dateModify($date, '+1 day');
                }
                break;
            case 3: // Weekly, one at a time
                for ($i = $firstEpisode; $i <= $lastEpisode; $i++) {
                    $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $i), 'episodeNumber' => $i, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $i), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $i), 'future' => $now < $date];
                    $date = $this->dateModify($date, '+1 week');
                }
                break;
            case 4: // Weekly, two at a time
                for ($i = $firstEpisode; $i <= $lastEpisode; $i += 2) {
                    $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $i), 'episodeNumber' => $i, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $i), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $i), 'future' => $now < $date];
                    $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $i + 1), 'episodeNumber' => $i + 1, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $i + 1), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $i + 1), 'future' => $now < $date];
                    $date = $this->dateModify($date, '+1 week');
                }
                break;
            case 5: // Weekly, three at a time
                for ($i = $firstEpisode; $i <= $lastEpisode; $i += 3) {
                    $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $i), 'episodeNumber' => $i, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $i), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $i), 'future' => $now < $date];
                    $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $i + 1), 'episodeNumber' => $i + 1, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $i + 1), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $i + 1), 'future' => $now < $date];
                    $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $i + 2), 'episodeNumber' => $i + 2, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $i + 2), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $i + 2), 'future' => $now < $date];
                    $date = $this->dateModify($date, '+1 week');
                }
                break;
            case 11: // Weekly, four at a time
                for ($i = $firstEpisode; $i <= $lastEpisode; $i += 4) {
                    $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $i), 'episodeNumber' => $i, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $i), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $i), 'future' => $now < $date];
                    $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $i + 1), 'episodeNumber' => $i + 1, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $i + 1), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $i + 1), 'future' => $now < $date];
                    $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $i + 2), 'episodeNumber' => $i + 2, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $i + 2), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $i + 2), 'future' => $now < $date];
                    $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $i + 3), 'episodeNumber' => $i + 3, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $i + 3), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $i + 3), 'future' => $now < $date];
                    $date = $this->dateModify($date, '+1 week');
                }
                break;
            case 6: // Weekly, two, then one
                $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $firstEpisode), 'episodeNumber' => $firstEpisode, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $firstEpisode), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $firstEpisode), 'future' => $now < $date];
                for ($i = $firstEpisode + 1; $i <= $lastEpisode; $i++) {
                    $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $i), 'episodeNumber' => $i, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $i), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $i), 'future' => $now < $date];
                    $date = $this->dateModify($date, '+1 week');
                }
                break;
            case 7: // Weekly, three, then one
                $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $firstEpisode), 'episodeNumber' => $firstEpisode, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $firstEpisode), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $firstEpisode), 'future' => $now < $date];
                $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $firstEpisode + 1), 'episodeNumber' => $firstEpisode + 1, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $firstEpisode + 1), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $firstEpisode + 1), 'future' => $now < $date];
                for ($i = $firstEpisode + 2; $i <= $lastEpisode; $i++) {
                    $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $i), 'episodeNumber' => $i, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $i), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $i), 'future' => $now < $date];
                    $date = $this->dateModify($date, '+1 week');
                }
                break;
            case 8: // 4, then 1
                $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $firstEpisode), 'episodeNumber' => $firstEpisode, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $firstEpisode), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $firstEpisode), 'future' => $now < $date];
                $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $firstEpisode + 1), 'episodeNumber' => $firstEpisode + 1, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $firstEpisode + 1), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $firstEpisode + 1), 'future' => $now < $date];
                $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $firstEpisode + 2), 'episodeNumber' => $firstEpisode + 2, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $firstEpisode + 2), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $firstEpisode + 2), 'future' => $now < $date];
                for ($i = $firstEpisode + 3; $i <= $lastEpisode; $i++) {
                    $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $i), 'episodeNumber' => $i, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $i), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $i), 'future' => $now < $date];
                    $date = $this->dateModify($date, '+1 week');
                }
                break;
            case 9: // 4, then 2
                $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $firstEpisode), 'episodeNumber' => $firstEpisode, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $firstEpisode), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $firstEpisode), 'future' => $now < $date];
                $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $firstEpisode + 1), 'episodeNumber' => $firstEpisode + 1, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $firstEpisode + 1), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $firstEpisode + 1), 'future' => $now < $date];
                for ($i = $firstEpisode + 2; $i <= $lastEpisode; $i += 2) {
                    $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $i), 'episodeNumber' => $i, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $i), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $i), 'future' => $now < $date];
                    $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $i + 1), 'episodeNumber' => $i, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $i + 1), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $i + 1), 'future' => $now < $date];
                    $date = $this->dateModify($date, '+1 week');
                }
                break;
            case 10:
                // dayOfWeek: 0: dimanche, 1: lundi, 2: mardi, 3: mercredi, 4: jeudi, 5: vendredi, 6: samedi
                // First episode of the week: dayOfWeek[0]
                // Second episode of the week: dayOfWeek[1]
                // Third ...
                // Le jour de la semaine du premier épisode de la semaine et le jour de la semaine de la date de premiere diffusion doivent correspondre
                // Si ce n'est pas le cas, on décale la date de première diffusion
                $selectedDayCount = count($daysOfWeek);
                $firstDayOfWeek = $date->format('w');
                if (!in_array($firstDayOfWeek, $daysOfWeek)) {
                    return $errorArr;
                }
                // First date: 2024/11/28 -> 4 (thursday)
                // Airing days 3 (wednesday), 4 (thursday)
                // First airing day: 2024/11/28 (thursday)
                // Second airing day: 2024/12/04 (wednesday)
                // Third airing day: 2024/12/05 (thursday)
                // Fourth airing day: 2024/12/11 (wednesday)
                // ...
                // DaysOfWeek: 3, 4
                if ($selectedDayCount == 2) {
                    if ($firstDayOfWeek == $daysOfWeek[1]) {
                        $last = array_pop($daysOfWeek);
                        array_unshift($daysOfWeek, $last);
                    }
                }
                if ($selectedDayCount >= 3) {
                    return $errorArr;
                }
                // DaysOfWeek: 4, 3
                for ($i = $firstEpisode, $k = 1; $i <= $lastEpisode; $i += $selectedDayCount, $k++) {
                    $j = $i;
                    foreach ($daysOfWeek as $day) {
                        if ($j <= $lastEpisode) {
                            $d = $day - $firstDayOfWeek;
                            if ($d < 0) $d += 7;
                            if ($d) $date = $this->dateModify($date, '+' . $d . ' day');
                            $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $j), 'episodeNumber' => $j, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $j), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $j), 'future' => $now < $date];
                            $j++;
                        }
                    }
//                    $date = $firstAirDate->modify('+' . $k . ' week');
                    $date = $this->dateModify($firstAirDate, '+' . $k . ' week');
                }
                break;
        }
        return ['seasonNumber' => $seasonNumber, 'multiPart' => $multiPart, 'seasonPart' => $seasonPart, 'airDays' => $dayArr];
    }

    public function isEpisodeWatched(array $episodes, int $seasonNumber, int $episodeNumber): bool
    {
        /** @var UserEpisode $episode */
        foreach ($episodes as $episode) {
            if ($episode->getSeasonNumber() == $seasonNumber && $episode->getEpisodeNumber() == $episodeNumber) {
                return $episode->getWatchAt() != null;
            }
        }
        return false;
    }

    public function getEpisodeId(array $episodes, int $seasonNumber, int $episodeNumber): ?int
    {
        /** @var UserEpisode $episode */
        foreach ($episodes as $episode) {
            if ($episode->getSeasonNumber() == $seasonNumber && $episode->getEpisodeNumber() == $episodeNumber) {
                return $episode->getEpisodeId();
            }
        }
        return null;
    }

    public function emptySchedule(): array
    {
        $now = $this->now();
        $dayArrEmpty = array_fill(0, 7, false);
        return [
            'id' => 0,
            'seasonNumber' => 1,
            'multiPart' => false,
            'seasonPart' => 1,
            'seasonPartFirstEpisode' => 1,
            'seasonPartEpisodeCount' => 1,
            'airAt' => "12:00",
            'firstAirDate' => $now,
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

    public function setEpisodeDatetime(?array $episode, DateTimeInterface $time, string $timezone): ?array
    {
        if (!$episode) return null;
        if (!$episode['air_date']) {
            $episode['date'] = null;
            return $episode;
        }
        $date = $episode['air_date'];
        $date = $this->dateService->newDateImmutable($date, $timezone, true);

        $date = $date->setTime($time->format('H'), $time->format('i'));
        $episode['date'] = $date;
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
        return array_any($images, fn($img) => $img->getimagePath() == $image);
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

    public function getEpisodeHistory(User $user, int $dayCount, string $locale): array
    {
        $arr = $this->userEpisodeRepository->historyEpisode($user, $dayCount, $locale);
        $logoUrl = $this->imageConfiguration->getUrl('logo_sizes', 2);

        return array_map(function ($series) use ($logoUrl) {
            if (!$series['posterPath']) {
                $series['posterPath'] = $this->getAlternatePosterPath($series['id']);
            }
            $series['posterPath'] = $series['posterPath'] ? '/series/posters' . $series['posterPath'] : null;
            $series['providerLogoPath'] = $this->getProviderLogoFullPath($series['providerLogoPath'], $logoUrl);
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

    public function getFilmingLocations(int $tmdbId): array
    {
        $filmingLocations = $this->filmingLocationRepository->locations($tmdbId);
        if (count($filmingLocations) == 0) {
            return ['filmingLocations' => [],
                'bounds' => []
            ];
        }
        $filmingLocationIds = array_column($filmingLocations, 'id');
        $filmingLocationImages = $this->filmingLocationRepository->locationImages($filmingLocationIds);
        $flImages = [];
        foreach ($filmingLocationImages as $image) {
            $flImages[$image['filming_location_id']][] = $image;
        }
        foreach ($filmingLocations as &$location) {
            $location['filmingLocationImages'] = $flImages[$location['id']] ?? [];
        }
        // Bounding box → center
        if (count($filmingLocations) == 1) {
            $loc = $filmingLocations[0];
            $bounds = [[$loc['longitude'] + .1, $loc['latitude'] + .1], [$loc['longitude'] - .1, $loc['latitude'] - .1]];
        } else {
            $minLat = min(array_column($filmingLocations, 'latitude'));
            $maxLat = max(array_column($filmingLocations, 'latitude'));
            $minLng = min(array_column($filmingLocations, 'longitude'));
            $maxLng = max(array_column($filmingLocations, 'longitude'));
            $bounds = [[$maxLng + .1, $maxLat + .1], [$minLng - .1, $minLat - .1]];
        }

        return [
            'filmingLocations' => $filmingLocations,
            'bounds' => $bounds
        ];
    }

    public function now(): DateTimeImmutable
    {
        $user = $this->getUser();
        return $this->dateService->newDateImmutable('now', $user->getTimezone() ?? 'Europe/Paris');
    }

    public function date(string $dateString): DateTimeImmutable
    {
        $user = $this->getUser();
        return $this->dateService->newDateImmutable($dateString, $user->getTimezone() ?? 'Europe/Paris');
    }

    public function dateModify(DateTimeImmutable $date, string $modify): DateTimeImmutable
    {
        try {
            return $date->modify($modify);
        } catch (DateMalformedStringException $e) {
            $this->logger->error($e->getMessage());
            return $date;
        }
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
        if (!$tv) {
            return ['cast' => [], 'crew' => [], 'guest_stars' => []];
        }
        $castIds = array_column($tv['credits']['cast'], 'id');
        $castIds = array_merge($castIds, array_column($tv['credits']['guest_stars'] ?? [], 'id'));
        $castIds = array_unique($castIds);
        $arr = $this->peopleUserPreferredNameRepository->getPreferredNames($castIds);
        $preferredNames = [];
        foreach ($arr as $name) {
            $preferredNames[$name['tmdb_id']] = $name['name'];
        }

        $slugger = new AsciiSlugger();
        $profileUrl = $this->imageConfiguration->getUrl('profile_sizes', 2);
        $tv['credits']['cast'] = array_map(function ($cast) use ($slugger, $profileUrl, $preferredNames) {
            $cast['slug'] = $slugger->slug($cast['name'])->lower()->toString();
            $cast['profile_path'] = $cast['profile_path'] ? $profileUrl . $cast['profile_path'] : null; // w185
            $cast['preferred_name'] = null;
            if (key_exists($cast['id'], $preferredNames)) {
                $cast['preferred_name'] = $preferredNames[$cast['id']];
            }
            return $cast;
        }, $tv['credits']['cast'] ?? []);
        $tv['credits']['guest_stars'] = array_map(function ($cast) use ($slugger, $profileUrl, $preferredNames) {
            $cast['slug'] = $slugger->slug($cast['name'])->lower()->toString();
            $cast['profile_path'] = $cast['profile_path'] ? $profileUrl . $cast['profile_path'] : null; // w185
            $cast['preferred_name'] = null;
            if (key_exists($cast['id'], $preferredNames)) {
                $cast['preferred_name'] = $preferredNames[$cast['id']];
            }
            return $cast;
        }, $tv['credits']['guest_stars'] ?? []);
        $crew = [];
        foreach ($tv['credits']['crew'] as $c) {
            $id = $c['id'];
            if (!key_exists($id, $crew)) {
                $crew[$id] = $c;
                $crew[$id]['jobs'] = [];
            }
            $crew[$id]['jobs'][] = $this->translator->trans($c['job']) . ' - ' . $this->translator->trans($c['department']);
        }
        $crew = array_values($crew);
        $tv['credits']['crew'] = array_map(function ($c) use ($slugger, $profileUrl, $preferredNames) {
            $c['slug'] = $slugger->slug($c['name'])->lower()->toString();
            $c['profile_path'] = $c['profile_path'] ? $profileUrl . $c['profile_path'] : null; // w185
            $c['preferred_name'] = null;
            if (key_exists($c['id'], $preferredNames)) {
                $c['preferred_name'] = $preferredNames[$c['id']];
            }
            return $c;
        }, $crew);

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

    public function getSeasonEpisodeCount(array $seasons, int $seasonNumber): int
    {
        foreach ($seasons as $season) {
            if ($season['season_number'] == $seasonNumber) {
                return $season['episode_count'];
            }
        }
        return 0;
    }

    public function seasonsPosterPath(array $seasons): array
    {
        $slugger = new AsciiSlugger();
        $posterUrl = $this->imageConfiguration->getUrl('poster_sizes', 5);
        return array_map(function ($season) use ($slugger, $posterUrl) {
            $season['slug'] = $slugger->slug($season['name'])->lower()->toString();
            $season['poster_path'] = $season['poster_path'] ? $posterUrl . $season['poster_path'] : null;
            return $season;
        }, $seasons);
    }

    public function seasonEpisodes(array $season, UserSeries $userSeries): array
    {
        $user = $userSeries->getUser();
        $series = $userSeries->getSeries();
        $next_episode_to_air = $series->getNextEpisodeAirDate();
        $slugger = new AsciiSlugger();
        $locale = $user->getPreferredLanguage() ?? 'fr';
        $seasonEpisodes = [];
        $userEpisodes = $this->userEpisodeRepository->getUserEpisodesDB($userSeries->getId(), $season['season_number'], $locale, true);
//        dump($userEpisodes);

        $episodeIds = array_column($userEpisodes, 'episode_id');
        $stills = $this->episodeStillRepository->getSeasonStills($episodeIds);

//        dump($season['episodes']);
        $newCount = 0;
        foreach ($season['episodes'] as $episode) {
            $userEpisode = $this->getUserEpisode($userEpisodes, $episode['episode_number']);
//            dump($episode['episode_number'], $userEpisode);
            if (!$userEpisode) {
                $nue = new UserEpisode($userSeries, $episode['id'], $season['season_number'], $episode['episode_number'], null);
                $nue->setAirDate($episode['air_date'] ? $this->dateService->newDateImmutable($episode['air_date'], $user->getTimezone() ?? 'Europe/Paris') : null);
                if ($episode['episode_number'] > 1) {
                    $previousEpisode = $this->getUserEpisode($userEpisodes, $episode['episode_number'] - 1);
                    if ($previousEpisode) {
                        $nue->setProviderId($previousEpisode['provider_id']);
                        $nue->setDeviceId($previousEpisode['device_id']);
                    }
                }
                $this->userEpisodeRepository->save($nue, true);
//                dump(['new user episode' => $nue]);
                $userEpisode = $this->userEpisodeRepository->getUserEpisodeDB($nue->getId(), $locale);
                $newCount++;
//                dump(['db user episode' => $userEpisode]);
            }
            if (!$userEpisode['custom_date'] && !$next_episode_to_air && !$episode['air_date']) {
                continue;
            }

            $userEpisodeList = $this->getUserEpisodes($userEpisodes, $episode['episode_number']);

            $stillUrl = $this->imageConfiguration->getUrl('still_sizes', 3);
            $profileUrl = $this->imageConfiguration->getUrl('profile_sizes', 2);

            $episode['still_path'] = $episode['still_path'] ? $stillUrl . $episode['still_path'] : null; // w300
            $episode['stills'] = array_filter($stills, function ($still) use ($episode) {
                return $still['episode_id'] == $episode['id'];
            });
            if ($userEpisode['custom_date']) {
                $episode['air_date'] = $userEpisode['custom_date'];
            }
            $episode['crew'] = array_map(function ($crew) use ($slugger, $user, $profileUrl) {
                if (key_exists('person_id', $crew)) return null;
                $crew['profile_path'] = $crew['profile_path'] ? $profileUrl . $crew['profile_path'] : null; // w185
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
            $episode['guest_stars'] = array_map(function ($guest) use ($slugger, $series, $profileUrl) {
                $guest['profile_path'] = $guest['profile_path'] ? $profileUrl . $guest['profile_path'] : null; // w185
                $guest['slug'] = $slugger->slug($guest['name'])->lower()->toString();
                if (!$guest['profile_path']) {
                    $guest['google'] = 'https://www.google.com/search?q=' . urlencode($guest['name'] . ' ' . $series->getName());
                }
                return $guest;
            }, $episode['guest_stars']);

            /*if ($userEpisode == null) {
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
            }*/

            $userEpisode['watch_at_db'] = $userEpisode['watch_at'];
            if ($userEpisode['watch_at']) {
                $userEpisode['watch_at'] = $this->dateService->newDateImmutable($userEpisode['watch_at'], $user->getTimezone() ?? 'Europe/Paris');
            }
            $episode['user_episode'] = $userEpisode;
            $episode['user_episodes'] = $userEpisodeList;
//            $episode['substitute_name'] = $this->userEpisodeRepository->getSubstituteName($episode['id']);
            $seasonEpisodes[] = $episode;
        }
        if ($newCount) {
            $this->addFlash('warning', $newCount . ' new episode' . ($newCount > 1 ? 's' : '') . ' added to your watchlist');
        }
        return $seasonEpisodes;
    }

    public function getUserEpisode(array $userEpisodes, int $episodeNumber): ?array
    {
        $logoUrl = $this->imageConfiguration->getUrl('logo_sizes', 2);
        foreach ($userEpisodes as $userEpisode) {
            if ($userEpisode['episode_number'] == $episodeNumber) {
                $userEpisode['provider_logo_path'] = $this->getProviderLogoFullPath($userEpisode['provider_logo_path'], $logoUrl);
                if ($userEpisode['custom_date']) {
                    $cd = $this->dateService->newDateImmutable($userEpisode['custom_date'], 'Europe/Paris');
                    $userEpisode['custom_date'] = $cd->format('Y-m-d H:i O');
                }
                if ($userEpisode['air_at']) {
                    // 10:00:00 → 10:00
                    $userEpisode['air_at'] = $this->dateService->newDateImmutable($userEpisode['air_at'], 'Europe/Paris');
                    $userEpisode['air_at'] = $userEpisode['air_at']->format('H:i');
                }
                $userEpisode['watch_at_db'] = $userEpisode['watch_at'];
                /*if ($userEpisode['watch_at']) {
                    $ue['watch_at'] = $this->dateService->newDateImmutable($userEpisode['watch_at'], 'UTC');
                }*/
                return $userEpisode;
            }
        }
        return null;
    }

    public function getUserEpisodes(array $userEpisodes, int $episodeNumber): array
    {
        $episodes = array_values(array_filter($userEpisodes, function ($userEpisode) use ($episodeNumber) {
            return $userEpisode['episode_number'] == $episodeNumber;
        }));
        $logoUrl = $this->imageConfiguration->getUrl('logo_sizes', 2);
        $ues = [];
        foreach ($episodes as $episode) {
            $episode['provider_logo_path'] = $this->getProviderLogoFullPath($episode['provider_logo_path'], $logoUrl);
            /*if ($episode['provider_id'] > 0)
                $episode['provider_logo_path'] = $episode['provider_logo_path'] ? $logoUrl . $episode['provider_logo_path'] : null;
            else
                $episode['provider_logo_path'] = '/images/providers/' . $episode['provider_logo_path'];*/
            if (!key_exists('watch_at_db', $episode)) {
                $episode['watch_at_db'] = $episode['watch_at'];
                if ($episode['watch_at']) {
                    $episode['watch_at'] = $this->dateService->newDateImmutable($episode['watch_at'], 'UTC');
                }
            }
            $ues[] = $episode;
        }
        return $ues;
    }

//    public function seasonLocalizedOverview($series, $season, $seasonNumber, $request): array|null
//    {
//        $locale = $request->getLocale();
//        $localized = false;
//        $localizedResult = null;
//        $localizedOverview = $this->seasonLocalizedOverviewRepository->findOneBy(['series' => $series, 'seasonNumber' => $seasonNumber, 'locale' => $locale]);
//
//        if (!$localizedOverview) {
//            if (!strlen($season['overview'])) {
//                $usSeason = json_decode($this->tmdbService->getTvSeason($series->getTmdbId(), $seasonNumber, 'en-US'), true);
//                $season['overview'] = $usSeason['overview'];
//                if (strlen($season['overview'])) {
//                    try {
//                        $usage = $this->deeplTranslator->translator->getUsage();
////                    dump($usage);
//                        if ($usage->character->count + strlen($season['overview']) < $usage->character->limit) {
//                            $localizedOverview = $this->deeplTranslator->translator->translateText($season['overview'], null, $locale);
//                            $localized = true;
//
//                            $seasonLocalizedOverview = new SeasonLocalizedOverview($series, $seasonNumber, $localizedOverview, $locale);
//                            $this->seasonLocalizedOverviewRepository->save($seasonLocalizedOverview, true);
//                        } else {
//                            $localizedResult = 'Limit exceeded';
//                        }
//                    } catch (DeepLException $e) {
//                        $localizedResult = 'Error: code ' . $e->getCode() . ', message: ' . $e->getMessage();
//                        $usage = [
//                            'character' => [
//                                'count' => 0,
//                                'limit' => 500000
//                            ]
//                        ];
//                    }
//                }
//                return [
//                    'us_overview' => $usSeason['overview'],
//                    'us_episode_overviews' => []/*array_map(function ($ep) use ($locale) {
//                    return $this->episodeLocalizedOverview($ep, $locale);
//                }, $usSeason['episodes'])*/,
//                    'localized' => $localized,
//                    'localizedOverview' => $localizedOverview,
//                    'localizedResult' => $localizedResult,
//                    'usage' => $usage ?? null
//                ];
//            }
//        } else {
//            return [
//                'us_overview' => null,
//                'us_episode_overviews' => [],
//                'localized' => true,
//                'localizedOverview' => $localizedOverview->getOverview(),
//                'localizedResult' => null,
//                'usage' => null
//            ];
//        }
//        return null;
//    }

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

        $logoUrl = $this->imageConfiguration->getUrl('logo_sizes', 2);

        $flatrate = array_map(function ($wp) use ($logoUrl) {
            return [
                'provider_id' => $wp['provider_id'],
                'provider_name' => $wp['provider_name'],
                'logo_path' => $this->getProviderLogoFullPath($wp['logo_path'], $logoUrl),
            ];
        }, $flatrate);
        $rent = array_map(function ($wp) use ($logoUrl) {
            return [
                'provider_id' => $wp['provider_id'],
                'provider_name' => $wp['provider_name'],
                'logo_path' => $this->getProviderLogoFullPath($wp['logo_path'], $logoUrl),
            ];
        }, $rent);
        $buy = array_map(function ($wp) use ($logoUrl) {
            return [
                'provider_id' => $wp['provider_id'],
                'provider_name' => $wp['provider_name'],
                'logo_path' => $this->getProviderLogoFullPath($wp['logo_path'], $logoUrl),
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
        $logoUrl = $this->imageConfiguration->getUrl('logo_sizes', 2);
        foreach ($providers as $provider) {
            $watchProviderLogos[$provider['provider_id']] = $this->getProviderLogoFullPath($provider['logo_path'], $logoUrl);
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

    public function getProviderLogoFullPath(?string $path, string $tmdbUrl): ?string
    {
        if (!$path) return null;
        if (str_starts_with($path, '/')) {
            return $tmdbUrl . $path;
        }
        return '/images/providers' . substr($path, 1);
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
            $this->imageService->saveImage("posters", $tv['poster_path'], $this->imageConfiguration->getUrl('poster_sizes', 5));
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
}
