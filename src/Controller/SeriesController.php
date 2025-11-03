<?php

namespace App\Controller;

use App\Api\WatchLink;
use App\DTO\SeriesAdvancedSearchDTO;
use App\DTO\SeriesSearchDTO;
use App\Entity\FilmingLocation;
use App\Entity\FilmingLocationImage;
use App\Entity\Network;
use App\Entity\Series;
use App\Entity\SeriesBroadcastDate;
use App\Entity\SeriesBroadcastSchedule;
use App\Entity\SeriesCast;
use App\Entity\SeriesExternal;
use App\Entity\SeriesImage;
use App\Entity\SeriesLocalizedName;
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
use App\Repository\EpisodeStillRepository;
use App\Repository\FilmingLocationImageRepository;
use App\Repository\FilmingLocationRepository;
use App\Repository\KeywordRepository;
use App\Repository\NetworkRepository;
use App\Repository\PeopleRepository;
use App\Repository\PeopleUserPreferredNameRepository;
use App\Repository\SeasonLocalizedOverviewRepository;
use App\Repository\SeriesBroadcastDateRepository;
use App\Repository\SeriesBroadcastScheduleRepository;
use App\Repository\SeriesCastRepository;
use App\Repository\SeriesExternalRepository;
use App\Repository\SeriesImageRepository;
use App\Repository\SeriesLocalizedNameRepository;
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
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use DeepL\DeepLException;
use Deepl\TextResult;
use Psr\Log\LoggerInterface as MonologLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
use Twig\Extra\Intl\IntlExtension;

/** @method User|null getUser() */
#[Route('/{_locale}/series', name: 'app_series_', requirements: ['_locale' => 'fr|en|ko'])]
class SeriesController extends AbstractController
{
    private bool $reloadUserEpisodes = false;

    public function __construct(
        private readonly DateService                       $dateService,
        private readonly DeeplTranslator                   $deeplTranslator,
        private readonly DeviceRepository                  $deviceRepository,
        private readonly EpisodeStillRepository            $episodeStillRepository,
        private readonly FilmingLocationImageRepository    $filmingLocationImageRepository,
        private readonly FilmingLocationRepository         $filmingLocationRepository,
        private readonly ImageConfiguration                $imageConfiguration,
        private readonly ImageService                      $imageService,
        private readonly KeywordRepository                 $keywordRepository,
        private readonly KeywordService                    $keywordService,
        private readonly MonologLogger                     $logger,
        private readonly NetworkRepository                 $networkRepository,
        private readonly PeopleController                  $peopleController,
        private readonly PeopleRepository                  $peopleRepository,
        private readonly PeopleUserPreferredNameRepository $peopleUserPreferredNameRepository,
        private readonly SeasonLocalizedOverviewRepository $seasonLocalizedOverviewRepository,
        private readonly SeriesBroadcastDateRepository     $seriesBroadcastDateRepository,
        private readonly SeriesBroadcastScheduleRepository $seriesBroadcastScheduleRepository,
        private readonly SeriesCastRepository              $seriesCastRepository,
        private readonly SeriesExternalRepository          $seriesExternalRepository,
        private readonly SeriesImageRepository             $seriesImageRepository,
        private readonly SeriesLocalizedNameRepository     $seriesLocalizedNameRepository,
        private readonly SeriesVideoRepository             $seriesVideoRepository,
        private readonly SeriesRepository                  $seriesRepository,
        private readonly SettingsRepository                $settingsRepository,
        private readonly SourceRepository                  $sourceRepository,
        private readonly TMDBService                       $tmdbService,
        private readonly TranslatorInterface               $translator,
        private readonly UserEpisodeRepository             $userEpisodeRepository,
        private readonly UserPinnedSeriesRepository        $userPinnedSeriesRepository,
        private readonly UserSeriesRepository              $userSeriesRepository,
        private readonly WatchLink                         $watchLinkApi,
        private readonly WatchProviderRepository           $watchProviderRepository,
    )
    {
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/', name: 'index')]
    public function index(): Response
    {
        $user = $this->getUser();
        $locale = $user->getPreferredLanguage() ?? 'fr';

        $logoUrl = $this->imageConfiguration->getUrl('logo_sizes', 4);
        $posterUrl = $this->imageConfiguration->getUrl('poster_sizes', 5);

        $arr = $this->userEpisodeRepository->episodesOfTheDay($user, $locale);
        // LEFT JOIN watch_links peut générer plusieurs résultats pour une même série, à cause de différents liens de streaming (link_name, provider_logo_path, "provider_name).
        $episodesOfTheDay = [];
        foreach ($arr as $ue) {
            $episodesOfTheDay[$ue['episode_id']] = $ue;
        }
        $AllEpisodesOfTheDay = array_map(function ($ue) use ($posterUrl, $logoUrl) {
            $this->imageService->saveImage("posters", $ue['poster_path'], $posterUrl);
            $ue['episode_of_the_day'] = true;
            if ($ue['air_at']) {
                $time = explode(':', $ue['air_at']);
                $now = $this->now()->setTime($time[0], $time[1], $time[2]);
                $ue['air_at'] = $now->format('Y-m-d H:i:s');
            }
            if (!$ue['poster_path']) {
                $ue['poster_path'] = $this->getAlternatePosterPath($ue['id']);
            }
            $ue['poster_path'] = $ue['poster_path'] ? '/series/posters' . $ue['poster_path'] : null;
            $ue['up_to_date'] = $ue['aired_episode_count'] > 0 && $ue['watched_aired_episode_count'] == $ue['aired_episode_count'];
            $ue['remaining_episodes'] = $ue['aired_episode_count'] - $ue['watched_aired_episode_count'];
            $ue['watch_providers'] = $ue['provider_id'] ? [['logo_path' => $this->getProviderLogoFullPath($ue['provider_logo_path'], $logoUrl), 'provider_name' => $ue['provider_name']]] : [];
            return $ue;
        }, $episodesOfTheDay);

        $tmdbIds = array_column($AllEpisodesOfTheDay, 'tmdb_id');

        $today = $this->now()->format('Y-m-d');
        $todayEpisodes = array_filter($AllEpisodesOfTheDay, function ($e) use ($today) {
            return $e['date'] == $today;
        });
        $episodesOfTheDay = [];
        foreach ($todayEpisodes as $us) {
            if ($us['aired_episode_count'] > 1) {
                $episodesOfTheDay[$us['date'] . '-' . $us['id']][] = $us;
            } else {
                $episodesOfTheDay[$us['date'] . '-' . $us['id']][0] = $us;
            }
        }
        $next7dDaysEpisodes = array_filter($AllEpisodesOfTheDay, function ($e) use ($today) {
            return $e['date'] > $today;
        });
        $seriesOfTheWeek = [];
        foreach ($next7dDaysEpisodes as $us) {
            if ($us['aired_episode_count'] > 1) {
                $seriesOfTheWeek[$us['date'] . '-' . $us['id']][] = $us;
            } else {
                $seriesOfTheWeek[$us['date'] . '-' . $us['id']][0] = $us;
            }
        }

        return $this->render('series/index.html.twig', [
            'episodesOfTheDay' => $episodesOfTheDay,
            'seriesOfTheWeek' => $seriesOfTheWeek,
            'tmdbIds' => $tmdbIds,
            'userSeriesCount' => $this->userSeriesRepository->count(['user' => $user])
        ]);
    }

    #[Route('/to/start', name: 'to_start')]
    public function serieToStart(Request $request): Response
    {
        $user = $this->getUser();
        $locale = $user->getPreferredLanguage() ?? $request->getLocale();
        $sort = $request->get('sort', 'firstAirDate');
        $sort = match ($sort) {
            'addedAt' => 'addedAt',
            default => 'firstAirDate',
        };
        $order = $request->get('order', 'DESC');
        $order = match ($order) {
            'ASC' => 'ASC',
            default => 'DESC',
        };

        $posterUrl = $this->imageConfiguration->getUrl('poster_sizes', 5);
        $logoUrl = $this->imageConfiguration->getUrl('logo_sizes', 3);
        $seriesToStart = array_map(function ($s) use ($posterUrl, $logoUrl) {
            $this->imageService->saveImage("posters", $s['poster_path'], $posterUrl);
            $s['provider_logo_path'] = $this->getProviderLogoFullPath($s['provider_logo_path'], $logoUrl);
            return $s;
        }, $this->userSeriesRepository->seriesToStart($user, $locale, $sort, $order, 1, -1));
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

        return $this->render('series/series_to_start.html.twig', [
            'seriesToStart' => $seriesToStart,
            'tmdbIds' => $tmdbIds,
            'userSeriesCount' => $this->userSeriesRepository->count(['user' => $user])
        ]);
    }

    #[Route('/not/seen/in/a/while', name: 'not_seen_in_a_while')]
    public function serieNotSeen(Request $request): Response
    {
        $user = $this->getUser();
        $locale = $user->getPreferredLanguage() ?? $request->getLocale();

        $inAWhileDate = $this->dateModify($this->now(), '-15 days')->format('Y-m-d');
        $series = array_map(function ($s) {
            $this->imageService->saveImage("posters", $s['poster_path'], $this->imageConfiguration->getUrl('poster_sizes', 5));
            return $s;
        }, $this->userSeriesRepository->seriesNotSeenInAWhile($user, $locale, $inAWhileDate, 1, -1));
        $tmdbIds = array_column($series, 'tmdb_id');

        return $this->render('series/series_in_a_while.html.twig', [
            'seriesInAWhile' => $series,
            'tmdbIds' => $tmdbIds,
            'userSeriesCount' => $this->userSeriesRepository->count(['user' => $user])
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

        return $this->render('series/up_coming_series.html.twig', [
            'seriesList' => $series,
            'tmdbIds' => $tmdbIds,
            'userSeriesCount' => $this->userSeriesRepository->count(['user' => $user])
        ]);
    }

    #[Route('/ranking/vote', name: 'ranking_by_vote')]
    public function rankingByVote(Request $request): Response
    {
        $user = $this->getUser();
        $locale = $user->getPreferredLanguage() ?? $request->getLocale();

        $arr = $this->userSeriesRepository->rankingByVote($user, $locale, 1, -1);
        $series = array_filter($arr, function ($s) {
            return $s['average_vote'] > 0;
        });

        $series = array_map(function ($s) {
            $this->imageService->saveImage("posters", $s['poster_path'], $this->imageConfiguration->getUrl('poster_sizes', 5));
            $s['average_vote'] = round($s['average_vote'], 2);
            return $s;
        }, $series);

        $tmdbIds = array_column($series, 'tmdb_id');

        return $this->render('series/ranking_by_vote.html.twig', [
            'seriesList' => $series,
            'tmdbIds' => $tmdbIds,
            'userSeriesCount' => $this->userSeriesRepository->count(['user' => $user])
        ]);
    }

    #[Route('/user/favorites', name: 'user_favorites')]
    public function favorite(Request $request): Response
    {
        $user = $this->getUser();
        $locale = $user->getPreferredLanguage() ?? $request->getLocale();

        $series = array_map(function ($s) {
            $this->imageService->saveImage("posters", $s['poster_path'], $this->imageConfiguration->getUrl('poster_sizes', 5));
            $this->imageService->saveImage("backdrops", $s['backdrop_path'], $this->imageConfiguration->getUrl('backdrop_sizes', 3));
            return $s;
        }, $this->userSeriesRepository->favoriteSeries($user, $locale, 1, -1));

        $tmdbIds = array_column($series, 'tmdb_id');

        return $this->render('series/favorite.html.twig', [
            'seriesList' => $series,
            'tmdbIds' => $tmdbIds,
            'userSeriesCount' => $this->userSeriesRepository->count(['user' => $user])
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
            if ($s['sln_name']) {
                if (!str_contains($s['name'], $s['sln_name'])) {
                    $s['name'] = '<span>' . $s['sln_name'] . '</span>' . $s['name'];
                }
            }
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

        return $this->render('series/series_by_country.html.twig', [
            'seriesByCountry' => $series,
            'userSeriesCountries' => $userSeriesCountries,
            'country' => $country,
            'tmdbIds' => $tmdbIds,
            'userSeriesCount' => $this->userSeriesRepository->count(['user' => $user])
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

        $searchString = "query=$query&include_adult=false&page=$page";
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
            $filters);

        $userSeries = array_map(function ($series) {
            $series['poster_path'] = $series['poster_path'] ? '/series/posters' . $series['poster_path'] : null;
            return $series;
        }, $userSeries);
        $tmdbIds = array_column($userSeries, 'tmdb_id');

        $userNetworks = $user->getNetworks();
        $networks = $this->networkRepository->findBy([], ['name' => 'ASC']);
        $nlpArr = $this->networkRepository->networkLogoPaths();
        $networkLogoPaths = ['all' => null];
        $imageConfiguration = $this->imageConfiguration->getUrl('logo_sizes', 3);

        foreach ($nlpArr as $nlp) {
            if ($nlp['logo_path'])
                $networkLogoPaths[$nlp['id']] = $imageConfiguration . $nlp['logo_path'];
            else
                $networkLogoPaths[$nlp['id']] = null;
        }

        return $this->render('series/all.html.twig', [
            'userSeries' => $userSeries,
            'userSeriesCount' => $userSeriesCount,
            'tmdbIds' => $tmdbIds,
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
        $watchProviders = $this->watchLinkApi->getWatchProviders($user?->getCountry() ?? 'FR');
        $keywords = $this->getKeywords();
        $userSeriesIds = array_column($this->userSeriesRepository->userSeriesTMDBIds($user), 'id');

        $seriesSearch = new SeriesAdvancedSearchDTO($user?->getPreferredLanguage() ?? $request->getLocale(), $user?->getCountry() ?? 'FR', $user?->getTimezone() ?? 'Europe/Paris', 1);
        $seriesSearch->setWatchProviders($watchProviders['select']);
        $seriesSearch->setKeywords($keywords);

        $advancedDisplaySettings = $this->settingsRepository->findOneBy(['user' => $user, 'name' => 'advanced search display']);
        if (!$advancedDisplaySettings) {
            $advancedDisplaySettings = new Settings($user, 'advanced search display', ['place' => true, 'dates' => true, 'origin' => false, 'provider' => true, 'keywords' => true, 'runtime' => true, 'status' => true]);
            $this->settingsRepository->save($advancedDisplaySettings, true);
        }
        $displaySettings = $advancedDisplaySettings->getData();

        $advancedSettings = $this->settingsRepository->findOneBy(['user' => $user, 'name' => 'advanced search']);
        if ($advancedSettings) {
            $settings = $advancedSettings->getData();
            $seriesSearch->setPage($settings['page']);
            $seriesSearch->setLanguage($settings['language']);
            $seriesSearch->setTimezone($settings['timezone']);
            $seriesSearch->setWatchRegion($settings['watch region']);
            $seriesSearch->setFirstAirDateYear($settings['first air date year']);
            $seriesSearch->setFirstAirDateGTE($settings['first air date  GTE'] ? $this->dateService->newDateImmutable($settings['first air date  GTE'], 'Europe/Paris', true) : null);
            $seriesSearch->setFirstAirDateLTE($settings['first air date LTE'] ? $this->dateService->newDateImmutable($settings['first air date LTE'], 'Europe/Paris', true) : null);
            $seriesSearch->setWithOriginCountry($settings['with origin country']);
            $seriesSearch->setWithOriginalLanguage($settings['with original language']);
            $seriesSearch->setWithWatchMonetizationTypes($settings['with watch monetization types']);
            $seriesSearch->setWithWatchProviders($settings['with watch providers']);
            $seriesSearch->setWithKeywords($settings['with keywords']);
            $seriesSearch->setWithRuntimeGTE($settings['with runtime GTE']);
            $seriesSearch->setWithRuntimeLTE($settings['with runtime LTE']);
            $seriesSearch->setWithStatus($settings['with status']);
            $seriesSearch->setWithType($settings['with type']);
            $seriesSearch->setSortBy($settings['sort by']);

            $searchString = $this->getSearchString($user, $seriesSearch);
            $searchResult = json_decode($this->tmdbService->getFilterTv($searchString), true);

            $series = $this->getSearchResult($searchResult, $slugger);
        }
        $form = $this->createForm(SeriesAdvancedSearchType::class, $seriesSearch);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $searchString = $this->getSearchString($user, $form->getData());
            $searchResult = json_decode($this->tmdbService->getFilterTv($searchString), true);

            if ($searchResult['total_results'] == 1) {
                return $this->getOneResult($searchResult['results'][0], $slugger);
            }
            $series = $this->getSearchResult($searchResult, $slugger);
        }

        return $this->render('series/search_advanced.html.twig', [
            'form' => $form->createView(),
            'displaySettings' => ['id' => $advancedDisplaySettings->getId(), 'data' => $displaySettings],
            'seriesList' => $series,
            'userSeriesIds' => $userSeriesIds,
            'results' => [
                'total_results' => $searchResult['total_results'] ?? -1,
                'total_pages' => $searchResult['total_pages'] ?? 0,
                'page' => $searchResult['page'] ?? 0,
            ],
        ]);
    }

    #[Route('/advanced/search/settings', name: 'advanced_search_display_settings', methods: ['POST'])]
    public function advancedSearchDisplaySettings(Request $request): JsonResponse
    {
        if ($request->isMethod('POST')) {
            $payload = $request->getPayload()->all();
            $settingsId = $payload['id'];
            $settingsData = $payload['data'];
            foreach ($settingsData as $key => $value) {
                $settingsData[$key] = (bool)$value;
            }
            $displaySettings = $this->settingsRepository->find($settingsId);
            if (!$displaySettings) {
                return new JsonResponse(['ok' => false, 'message' => 'Settings not found'], 404);
            }
            $displaySettings->setData($settingsData);
            $this->settingsRepository->save($displaySettings, true);
        }
        return new JsonResponse(['ok' => true]);
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
        $this->checkTmdbSlug($tv, $slug, $localizedName?->getSlug());

        if ($tv['overview'] == "" && $localizedOverview) {
            $tv['overview'] = $localizedOverview->getOverview();
        }
        if ($tv['overview'] == "" && !$localizedOverview) {
            $enTranslations = array_find($tv['translations']['translations'], function ($item) {
                return $item['iso_639_1'] == 'en';
            });
//
            $this->addFlash('info', 'The series overview is missing. "' . ($enTranslations['data']['overview'] ?? 'null') . '" found.');
        }
        $this->imageService->saveImage("posters", $tv['poster_path'], $this->imageConfiguration->getUrl('poster_sizes', 5));
        $this->imageService->saveImage("backdrops", $tv['backdrop_path'], $this->imageConfiguration->getUrl('backdrop_sizes', 3));
        $tv['blurredPosterPath'] = $this->imageService->blurPoster($tv['poster_path'], 'series', 8);

        $tv['credits'] = $this->castAndCrew($tv, $series);
        $tv['networks'] = $this->networks($tv);
        $tv['seasons'] = $this->seasonsPosterPath($tv['seasons']);
        $tv['watch/providers'] = $this->watchProviders($tv, 'FR');
        $tv['translations'] = $this->getTranslations($tv, $user);
        $c = count($tv['episode_run_time']);
        $tv['average_episode_run_time'] = $c ? array_reduce($tv['episode_run_time'], function ($carry, $item) {
                return $carry + $item;
            }, 0) / $c : 0;

        return $this->render('series/tmdb.html.twig', [
            'tv' => $tv,
            'localizedName' => $localizedName,
            'localizedOverview' => $localizedOverview,
//            'externals' => $this->getTMDBExternals($tv['name'], $tv['origin_country']),
        ]);
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

        list($addBackdropForm, $addVideoForm) = $this->handleSerieShowForms($request, $series);

        $userSeries = $this->userSeriesRepository->findOneBy(['user' => $user, 'series' => $series]);
        $userEpisodes = $this->userEpisodeRepository->findBy(['userSeries' => $userSeries, 'previousOccurrence' => null], ['seasonNumber' => 'ASC', 'episodeNumber' => 'ASC']);

        $seriesAround = $this->getSeriesAround($userSeries, $locale);

        $blurredPosterPath = $this->imageService->blurPoster($series->getPosterPath(), 'series', 8);

        $series->setUpdates([]);
        $seriesArr = $series->toArray();
        $seriesArr['blurredPosterPath'] = $blurredPosterPath;

        $this->checkSlug($series, $slug, $locale);

        $tv = $this->getTv($series, $locale);

        if (!$tv) {
            $series->setUpdates(['Series not found']);
            $noTv['additional_overviews'] = $series->getSeriesAdditionalLocaleOverviews($locale);
            $noTv['credits'] = $this->castAndCrew($tv, $series);
            $noTv['localized_name'] = $series->getLocalizedName($locale);
            $noTv['localized_overviews'] = $series->getLocalizedOverviews($locale);
            $noTv['seasons'] = $this->getUserSeasons($series, $userEpisodes);
            $noTv['sources'] = $this->sourceRepository->findBy([], ['name' => 'ASC']);
            $noTv['average_episode_run_time'] = 0;

            return $this->render("series/show-not-found.html.twig", [
                'series' => $seriesArr,
                'userSeries' => $userSeries,
                'previousSeries' => $seriesAround['previous'],
                'nextSeries' => $seriesAround['next'],
                'tv' => $noTv,
            ]);
        }
        if ($tv['lists']['total_results'] == 0) {
            // Get with en-US language to get the lists
            $tv['lists'] = json_decode($this->tmdbService->getTvLists($series->getTmdbId()), true);
        }
        if ($tv['similar']['total_results'] == 0) {
            // Get with en-US language to get the similar series
            $tv['similar'] = json_decode($this->tmdbService->getTvSimilar($series->getTmdbId()), true);
        }

        $posterUrl = $this->imageConfiguration->getUrl('poster_sizes', 5);
        $backdropUrl = $this->imageConfiguration->getUrl('backdrop_sizes', 3);
        $tv['similar']['results'] = $this->getSimilarSeries($tv, $posterUrl);

        $this->imageService->saveImage("posters", $tv['poster_path'], $posterUrl);
        $this->imageService->saveImage("backdrops", $tv['backdrop_path'], $backdropUrl);

        $series = $this->updateSeries($series, $tv);

        $userSeries = $this->updateUserSeries($userSeries, $tv);
        $userEpisodes = $this->checkSeasons($userSeries, $userEpisodes, $tv);
        if ($this->reloadUserEpisodes) {
            $userEpisodes = $this->userEpisodeRepository->findBy(['userSeries' => $userSeries, 'previousOccurrence' => null], ['seasonNumber' => 'ASC', 'episodeNumber' => 'ASC']);
            $this->addFlash('info', $this->translator->trans('Your episodes have been updated according to the series information.'));
            $this->reloadUserEpisodes = false;
        }
        $userVotes = $this->getUserVotes($tv, $userEpisodes);

        $tv['additional_overviews'] = $series->getSeriesAdditionalLocaleOverviews($locale);
        $tv['credits'] = $this->castAndCrew($tv, $series);
        $tv['translations'] = $this->getTranslations($tv, $user);
        $tv['localized_name'] = $this->getTvLocalizedName($tv, $series, $locale);
        $tv['localized_overviews'] = $series->getLocalizedOverviews($locale);
        $tv['keywords']['results'] = $this->keywordService->keywordsCleaning($tv['keywords']['results']);
        $tv['missing_translations'] = $this->keywordService->keywordsTranslation($tv['keywords']['results'], $locale);
        $tv['networks'] = $this->networks($tv);
        $tv['overview'] = $this->localizedOverview($tv, $series, $request);
        $tv['seasons'] = $this->seasonsPosterPath($tv['seasons']);
        $tv['sources'] = $this->sourceRepository->findBy([], ['name' => 'ASC']);
        $tv['watch/providers'] = $this->watchProviders($tv, $country);
        $tv['last_episode_to_air'] = $this->getEpisodeToAir($tv['last_episode_to_air'], $series);
        $tv['next_episode_to_air'] = $this->getEpisodeToAir($tv['next_episode_to_air'], $series);
        $tv['average_episode_run_time'] = $this->getEpisodeRunTime($tv);
        $tv['status_css'] = $this->statusCss($userSeries, $tv);

        $providers = $this->watchLinkApi->getWatchProviders($country);
        $schedules = $this->seriesSchedulesV2($user, $series, $tv);
        $timezoneMenu = $this->getTimezoneMenu();
        $emptySchedule = $this->emptySchedule();
        $alternateSchedules = $this->alternateSchedules($tv, $series, $userEpisodes);
        $tv['seasons'] = $this->overrideSeasonAirDate($tv['seasons'], $schedules);

        $seriesArr['userVotes'] = $userVotes;
        $seriesArr['schedules'] = $schedules;
        $seriesArr['timezoneMenu'] = $timezoneMenu;
        $seriesArr['emptySchedule'] = $emptySchedule;
        $seriesArr['alternateSchedules'] = $alternateSchedules;
        $seriesArr['seriesInProgress'] = $this->userEpisodeRepository->isFullyReleased($userSeries);
        $seriesArr['images'] = $this->getSeriesImages($series);
        $seriesArr['videos'] = $this->getSeriesVideoList($series);
        $videoListFolded = $this->isVideoListFolded($seriesArr, $user);

        $filmingLocationsWithBounds = $this->getFilmingLocations($series);

        $addLocationFormData = [
            'hiddenFields' => [
                ['item' => 'hidden', 'name' => 'series-id', 'value' => $series->getId()],
                ['item' => 'hidden', 'name' => 'tmdb-id', 'value' => $tv['id']],
                ['item' => 'hidden', 'name' => 'crud-type', 'value' => 'create'],
                ['item' => 'hidden', 'name' => 'crud-id', 'value' => 0],
            ],
            'rows' => [
                [
                    ['item' => 'input', 'name' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true],
                ],
                [
                    ['item' => 'input', 'name' => 'location', 'label' => 'Location', 'type' => 'text', 'required' => false],
                    [
                        'item' => 'row',
                        'fields' => [
                            ['item' => 'input', 'name' => 'season-number', 'label' => 'Season number', 'type' => 'text', 'required' => false],
                            ['item' => 'input', 'name' => 'episode-number', 'label' => 'Episode number', 'type' => 'text', 'required' => false],
                        ]
                    ],
                ],
                [
                    ['item' => 'textarea', 'name' => 'description', 'label' => 'Description', 'rows' => '5', 'required' => false],
                ],
                [
                    ['item' => 'input', 'name' => 'source-name', 'label' => 'Source', 'type' => 'text', 'class' => 'flex-1', 'required' => false],
                    ['item' => 'input', 'name' => 'source-url', 'label' => 'Url', 'type' => 'text', 'class' => 'flex-2', 'required' => false],
                ]
            ],
        ];

        return $this->render("series/show.html.twig", [
            'series' => $seriesArr,
            'previousSeries' => $seriesAround['previous'],
            'nextSeries' => $seriesAround['next'],
            'videoListFolded' => $videoListFolded,
            'tv' => $tv,
            'userSeries' => $userSeries,
            'providers' => $providers,
            'locations' => $filmingLocationsWithBounds['filmingLocations'],
            'locationsBounds' => $filmingLocationsWithBounds['bounds'],
            'emptyLocation' => $filmingLocationsWithBounds['emptyLocation'],
            'addLocationFormData' => $addLocationFormData,
            'fieldList' => ['series-id', 'tmdb-id', 'crud-type', 'crud-id', 'title', 'location', 'season-number', 'episode-number', 'description', 'latitude', 'longitude', 'radius', "source-name", "source-url"],
            'mapSettings' => $this->settingsRepository->findOneBy(['name' => 'mapbox']),
            'externals' => $this->getExternals($series, $tv['keywords']['results'], $tv['external_ids'] ?? [], $locale),
            'translations' => $this->getSeriesShowTranslations(),
            'addBackdropForm' => $addBackdropForm->createView(),
            'addVideoForm' => $addVideoForm->createView(),
            'oldSeriesAdded' => $request->get('oldSeriesAdded') === 'true',
        ]);
    }

    private function getTv(Series $series, string $locale): ?array
    {
        return json_decode($this->tmdbService->getTv($series->getTmdbId(), $locale, [
            "changes",
            "credits",
            "external_ids",
            "images",
            "keywords",
            "lists",
            "similar",
            "translations",
            "videos",
            "watch/providers",
        ]), true);
    }

    public function getTranslations(array $tv, ?User $user): ?array
    {
        if (!$user) {
            $country = 'FR';
            $locale = 'fr';
        } else {
            $country = $user->getCountry() ?? 'FR'; // user iso_3166_1
            $locale = $user->getPreferredLanguage() ?? 'fr'; // user iso_639_1
        }
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

    private function getSeriesAround(?UserSeries $userSeries, string $locale): array
    {
        $seriesAround = $this->userSeriesRepository->getSeriesAround($userSeries, $locale);
        $previousSeries = null;
        $nextSeries = null;
        if (count($seriesAround) == 2) {
            $previousSeries = $seriesAround[0];
            $nextSeries = $seriesAround[1];
        }
        if (count($seriesAround) == 1 and $seriesAround[0]['id'] < $userSeries->getId()) {
            $previousSeries = $seriesAround[0];
            $nextSeries = $this->userSeriesRepository->getFirstSeries($userSeries->getUser(), $locale)[0];
        }
        if (count($seriesAround) == 1 and $seriesAround[0]['id'] > $userSeries->getId()) {
            $previousSeries = $this->userSeriesRepository->getLastSeries($userSeries->getUser(), $locale)[0];
            $nextSeries = $seriesAround[0];
        }
        return [
            'previous' => $previousSeries,
            'next' => $nextSeries,
        ];
    }

    private function getSimilarSeries(array $tv, string $posterUrl): array
    {
        $tv['similar']['results'] = array_map(function ($s) use ($posterUrl) {
            $s['poster_path'] = $s['poster_path'] ? $posterUrl . $s['poster_path'] : null;
            $s['tmdb'] = true;
            $s['slug'] = new AsciiSlugger()->slug($s['name']);
            return $s;
        }, $tv['similar']['results']);

        return $tv['similar']['results'];
    }

    private function getSeriesShowTranslations(): array
    {
        return [
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
            "and" => $this->translator->trans('and'),
            'since' => $this->translator->trans('since'),
            'Season completed' => $this->translator->trans('Season completed'),
            'Up to date' => $this->translator->trans('Up to date'),
            'Not a valid file type. Update your selection' => $this->translator->trans('Not a valid file type. Update your selection'),
        ];
    }

    private function getSeriesVideoList(Series $series): array
    {
        return array_map(function ($v) {
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
    }

    private function isVideoListFolded(array $seriesArr, User $user): bool
    {
        if (count($seriesArr['videos'])) {
            $settings = $this->settingsRepository->findOneBy(['user' => $user, 'name' => 'series_video_list_folded']);
            if (!$settings) {
                $settings = new Settings($user, 'series_video_list_folded', ['folded' => true]);
                $this->settingsRepository->save($settings, true);
            }
            return $settings->getData()['folded'];
        }
        return true;
    }

    private function getTvLocalizedName(array $tv, Series $series, string $locale): ?SeriesLocalizedName
    {
        $newLocalizedName = $series->getLocalizedName($locale);

        if (!$newLocalizedName && $tv['translations'] && $tv['name'] != $tv['translations']['data']['name']) {
            if (strlen($tv['translations']['data']['name'])) {
                $slugger = new AsciiSlugger();
                $slug = $slugger->slug($tv['translations']['data']['name'])->lower()->toString();
                $newLocalizedName = new SeriesLocalizedName($series, $tv['translations']['data']['name'], $slug, $locale);
                $this->seriesLocalizedNameRepository->save($newLocalizedName, true);
                $this->addFlash('success', 'The series name “' . $newLocalizedName->getName() . '” has been added to the database.');
            }
        }
        return $newLocalizedName;
    }

    private function getEpisodeToAir(?array $ep, Series $series): ?array
    {
        if (!$ep) return null;

        $ep['still_path'] = $ep['still_path'] ? $this->imageConfiguration->getUrl('still_sizes', 2) . $ep['still_path'] : null;
        $ep['url'] = $this->generateUrl('app_series_season', [
                'id' => $series->getId(),
                'slug' => $series->getSlug(),
                'seasonNumber' => $ep['season_number'],
            ]) . "#episode-" . $ep['season_number'] . '-' . $ep['episode_number'];

        return $ep;
    }

    private function getEpisodeRuntime(array $tv): int
    {
        $c = count($tv['episode_run_time']);
        return $c ? array_reduce($tv['episode_run_time'], function ($carry, $item) {
                return $carry + $item;
            }, 0) / $c : 0;
    }

    private function getUserVotes(array $tv, array $userEpisodes): array
    {
        $userVotes = [];
        foreach ($userEpisodes as $ue) {
            $userVotes[$ue->getSeasonNumber()]['ues'][] = $ue;
            $userVotes[$ue->getSeasonNumber()]['avs'][] = 0;
        }
        foreach ($tv['seasons'] as $season) {
            if (key_exists('vote_average', $season)) {
                $userVotes[$season['season_number']]['avs'] = array_fill(0, $season['episode_count'], $season['vote_average']);
            } else {
                $userVotes[$season['season_number']]['avs'] = array_fill(0, $season['episode_count'], 0);
            }
            if (!key_exists('ues', $userVotes[$season['season_number']])) {
                $userVotes[$season['season_number']]['ues'] = $season['episode_count'] ? [] : array_fill(0, $season['episode_count'], null);
            }
        }
        return $userVotes;
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
        $userSeries->setLastUserEpisode(null);
        $userSeries->setNextUserEpisode(null);
        $userEpisodes = $this->userEpisodeRepository->findBy(['userSeries' => $userSeries]);
        foreach ($userEpisodes as $userEpisode) {
            $userEpisode->setPreviousOccurrence(null);
        }
        foreach ($userEpisodes as $userEpisode) {
            $this->userEpisodeRepository->remove($userEpisode);
        }
        $this->userEpisodeRepository->flush();
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
        $timezone = $inputBag->get('timezone');
        $override = $inputBag->get('override');
        $frequency = $inputBag->get('frequency');
        $provider = $inputBag->get('provider');
        $seriesId = $inputBag->get('seriesId');
        $all = $inputBag->all();
        $daysArr = $all['days'] ?? [['day' => 0, 'count' => 0], ['day' => 1, 'count' => 0], ['day' => 2, 'count' => 0], ['day' => 3, 'count' => 0], ['day' => 4, 'count' => 0], ['day' => 5, 'count' => 0], ['day' => 6, 'count' => 0]];
        $dayArr = array_fill(0, 7, 0);
        foreach ($daysArr as $arr) {
            $dayArr[intval($arr['day'])] = intval($arr['count']);
        }

        $user = $this->getUser();
        $userTimezone = $user->getTimezone() ?? 'Europe/Paris';

        $dateTime = $this->convertDateTime($date, $time, $timezone, $userTimezone);
        $date = $dateTime->format('Y-m-d');
        $time = $dateTime->format('H:i');

        // Extract hour and minute from time string (format "HH:MM")
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
        $seriesBroadcastSchedule->setFirstAirDate($this->date($date, true));
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

    #[IsGranted('ROLE_USER')]
    #[Route('/schedules/convert', name: 'schedule_convert', methods: ['POST'])]
    public function convertDate(Request $request): JsonResponse
    {
        $inputBag = $request->getPayload();

        $date = $inputBag->get('date');
        $time = $inputBag->get('time');
        $timezone = $inputBag->get('timezone');
        $user = $this->getUser();
        $userTimezone = $user->getTimezone() ?? 'Europe/Paris';

        $dateTime = $this->convertDateTime($date, $time, $timezone, $userTimezone);

        $dayIndex = $dateTime->format('N');
        $dayOfTheWeek = $this->translator->trans($dateTime->format('l'));
        $dateTimeString = $dateTime->format('Y-m-d H:i');
        $relativeDateTimeString = $this->dateService->formatDateRelativeLong($dateTimeString, null, $user->getPreferredLanguage() ?? 'fr');

        return new JsonResponse([
            'ok' => true,
            'success' => true,
            'date' => ucfirst($dayOfTheWeek) . ', ' . $relativeDateTimeString,
            'dayIndex' => $dayIndex,
        ]);
    }

    private function convertDateTime(string $date, string $time, string $fromTimezone, string $toTimezone): DateTimeImmutable
    {
        $dateTime = new DateTime($date . ' ' . $time, new DateTimeZone($fromTimezone));
        if ($fromTimezone != $toTimezone) {
            $dateTime->setTimezone(new DateTimeZone($toTimezone));
        }
        return DateTimeImmutable::createFromMutable($dateTime);
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
                $this->addSeriesImage($series, $season['poster_path'], 'poster', $this->imageConfiguration->getUrl('poster_sizes', 5));
            }
        } else {
            $season['poster_path'] = $series->getPosterPath();
        }
        $season['blurred_poster_path'] = $this->imageService->blurPoster($season['poster_path'], 'series', 8);

        $season['deepl'] = null;//$this->seasonLocalizedOverview($series, $season, $seasonNumber, $request);
        $season['episodes'] = $this->seasonEpisodes($season, $userSeries);
        $season['air_date'] = $this->adjustSeasonAirDate($season, 'date');
        $season['air_date_string'] = $this->adjustSeasonAirDate($season, 'string');

        $season['credits'] = $this->castAndCrew($season, $series);
        $season['watch/providers'] = $this->watchProviders($season, $country);
        if ($season['overview'] == "") {
            $season['overview'] = $series->getOverview();
            $season['is_series_overview'] = true;
        } else {
            $season['is_series_overview'] = false;
        }
        $season['season_localized_overview'] = $this->seasonLocalizedOverviewRepository->getSeasonLocalizedOverview($series->getId(), $seasonNumber, $request->getLocale());
        $season['series_localized_name'] = $series->getLocalizedName($request->getLocale());
        $season['series_localized_overviews'] = $series->getLocalizedOverviews($request->getLocale());
        $season['series_additional_overviews'] = $series->getSeriesAdditionalLocaleOverviews($request->getLocale());

        $providers = $this->watchLinkApi->getWatchProviders($country);
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

//        $tvKeywords = json_decode($this->tmdbService->getTvKeywords($series->getTmdbId()), true);
//        $tvExternalIds = json_decode($this->tmdbService->getTvExternalIds($series->getTmdbId()), true);

        $tv = json_decode($this->tmdbService->getTv($series->getTmdbId(), 'en-US'), true);
        if ($series->getNumberOfEpisode() != $tv['number_of_episodes'] || $series->getNumberOfSeason() != $tv['number_of_seasons']) {
            $this->addFlash('info', 'The number of episodes has changed, the series has been updated.');
            if ($series->getNumberOfEpisode() != $tv['number_of_episodes'])
                $series->setUpdates(['Number of episodes changed from ' . $series->getNumberOfEpisode() . ' to ' . $tv['number_of_episodes']]);
            if ($series->getNumberOfSeason() != $tv['number_of_seasons'])
                $series->setUpdates(['Number of seasons changed from ' . $series->getNumberOfSeason() . ' to ' . $tv['number_of_seasons']]);

            $series->setNumberOfEpisode($tv['number_of_episodes']);
            $series->setNumberOfSeason($tv['number_of_seasons']);
            $this->seriesRepository->save($series, true);
        }

        $filmingLocation = $this->filmingLocationRepository->location($series->getTmdbId());

        return $this->render('series/season.html.twig', [
            'series' => $series,
            'userSeries' => $userSeries,
            'quickLinks' => $this->getQuickLinks($season['episodes']),
            'season' => $season,
            'today' => $this->now()->format('Y-m-d H:I:s'),
            'filmingLocation' => $filmingLocation,
            'language' => $locale . '-' . $country,
            'changes' => $this->getChanges($season['id']),
            'now' => $this->now()->format('Y-m-d H:i O'),
            'episodeDiv' => [
                'height' => $episodeDivSize,
                'aspectRatio' => $aspectRatio
            ],
            'providers' => $providers,
            'devices' => $devices,
//            'externals' => $this->getExternals($series, $tvKeywords['results'] ?? [], $tvExternalIds, $request->getLocale()),
        ]);
    }

    private function getQuickLinks(array $episodes): array
    {
        if (!count($episodes)) {
            return ['items' => [], 'count' => 0, 'itemPerLine' => 0, 'lineCount' => 0];
        }
        $now = $this->now();
        $nowString = $now->format('Y-m-d H:i');

        $quickLinks = array_map(function ($link) use ($nowString) {
            $airAt = $link['user_episode']['air_at'] ?? ' 09:00';
            $airString = $link['air_date'] . $airAt;
            return [
                'name' => $link['name'],
                'episode_number' => $link['episode_number'],
                'air_date' => $link['air_date'],
                'watched' => (bool)$link['user_episode']['watch_at_db'],
                'future' => $airString > $nowString,
                'class' => 'quick-episode' . ((bool)$link['user_episode']['watch_at_db'] ? ' watched' : ($airString > $nowString ? ' future' : ''))
            ];
        }, $episodes);

        $count = count($quickLinks);
        if ($count % 2) {
            $quickLinks[] = ['name' => null, 'episode_number' => null, 'air_date' => null, 'watched' => null, 'future' => null, 'class' => 'quick-episode empty'];
        }
        if ($count <= 10) {
            $quickLinks[0]['class'] .= ' first';
            $quickLinks[$count - 1]['class'] .= ' last';
            $itemPerLine = $count;
            $lineCount = 1;
        } else {
            if ($count % 2 == 0)
                $itemPerLine = $count / 2;
            else {
                $itemPerLine = ($count + 1) / 2;
                $count += 1;
            }
            if ($itemPerLine > 10) {
                if ($count % 10 == 0) $itemPerLine = 10;
                if ($count % 9 == 0) $itemPerLine = 9;
                if ($count % 8 == 0) $itemPerLine = 8;
                if ($count % 7 == 0) $itemPerLine = 7;
            }
            $lineCount = ceil($count / $itemPerLine);
            $quickLinks[0]['class'] .= ' top-left';
            $quickLinks[$itemPerLine - 1]['class'] .= ' top-right';
            $quickLinks[$count - $itemPerLine]['class'] .= ' bottom-left';
            $quickLinks[$count - 1]['class'] .= ' bottom-right';
        }
        return ['items' => $quickLinks, 'count' => $count, 'itemPerLine' => $itemPerLine, 'lineCount' => $lineCount];
    }

    #[Route('/cast/add/{id}/{seasonNumber}/{peopleId}', name: 'add_cast', requirements: ['id' => Requirement::DIGITS, 'seasonNumber' => Requirement::DIGITS, 'peopleId' => Requirement::DIGITS], methods: ['GET'])]
    public function addCast(Request $request, Series $series, int $seasonNumber, int $peopleId): Response
    {
        $characterName = $request->get('name');
        // TODO: implement cast editing
        $people = json_decode($this->tmdbService->getPerson($peopleId, 'en-US'), true);
        $peopleDb = $this->peopleRepository->findOneBy(['tmdbId' => $peopleId]);
        if (!$peopleDb) {
            $peopleDb = $this->peopleController->savePeople($people);
        }
        $seriesCast = $this->seriesCastRepository->findOneBy(['series' => $series, 'people' => $peopleDb]);
        if (!$seriesCast) {
            $seriesCast = new SeriesCast($series, $peopleDb, $seasonNumber, $characterName);
            $this->seriesCastRepository->save($seriesCast, true);
            $message = $people['name'] . ($characterName ? ' as ' . $characterName : '') . ' has been added to ';
        } else {
            if ($characterName && $characterName != $seriesCast->getCharacterName()) {
                $seriesCast->setCharacterName($characterName);
                $this->seriesCastRepository->save($seriesCast, true);
                $message = $people['name'] . ' as ' . $characterName . ' has been updated to ';
            } else {
                $message = $people['name'] . ' is already in ';
            }
        }

        if ($seasonNumber) {
            $message = $this->translator->trans($message . 'season cast');
            $this->addFlash('info', $message);
            return $this->redirectToRoute('app_series_season', [
                'id' => $series->getId(),
                'slug' => $series->getSlug(),
                'seasonNumber' => $seasonNumber,
            ]);
        }
        $message = $this->translator->trans($message . 'series cast');
        $this->addFlash('info', $message);
        return $this->redirectToRoute('app_series_show', [
            'id' => $series->getId(),
            'slug' => $series->getSlug(),
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
        $method = $data['method'] ?? 'all';
        $series = $this->seriesRepository->findOneBy(['tmdbId' => $tmdbId]);
        $images = $series->getSeriesImages()->toArray();

        if ($method === 'all') {
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
        } else {
            $image = $data['image'];
            $type = $data['type']; // "backdrop" or "poster"
            $imagePath = $image['file_path'];
            if (!$this->inImages($imagePath, $images)) {
                $seriesImage = new SeriesImage($series, $type, $imagePath);
                $this->seriesImageRepository->save($seriesImage, true);
                if ($type === 'backdrop') {
                    $this->imageService->saveImage("backdrops", $imagePath, $this->imageConfiguration->getUrl('backdrop_sizes', 3));
                    $addedBackdropCount = 1;
                    $addedPosterCount = 0;
                } else {
                    $this->imageService->saveImage("posters", $imagePath, $this->imageConfiguration->getUrl('poster_sizes', 5));
                    $addedPosterCount = 1;
                    $addedBackdropCount = 0;
                }
            } else {
                return $this->json([
                    'ok' => false,
                    'success' => true,
                    'message' => 'Image already exists',
                ]);
            }
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
        if (empty($data) && empty($files)) {
            return $this->json([
                'ok' => false,
                'message' => 'No data',
            ]);
        }

        $imageFiles = [];
        foreach ($files as $key => $file) {
            if ($file instanceof UploadedFile) {
                // Est-ce qu'il s'agit d'une image ?
                $mimeType = $file->getMimeType();
                if (str_starts_with($mimeType, 'image')) {
                    $imageFiles[$key] = $file;
                }
            }
        }
        if ($data['location'] == 'test') {
            // "image-url" => "blob:https://localhost:8000/71698467-714e-4b2e-b6b3-a285619ea272"
            $testUrl = $data['image-url'];
            if (str_starts_with($testUrl, 'blob')) {
                $this->imageService->blobToWebp2($data['image-url-blob'], $data['title'], $data['location'], 100);
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
        $data['radius'] = str_replace(',', '.', $data['radius']);
        $episodeNumber = intval($data['episode-number']);
        $seasonNumber = intval($data['season-number']);
        $latitude = $data['latitude'] = floatval($data['latitude']);
        $longitude = $data['longitude'] = floatval($data['longitude']);
        $radius = $data['radius'] = floatval($data['radius']);
        $sourceName = $data['source-name'] ?? '';
        $sourceUrl = $data['source-url'] ?? '';

        if ($crudType === 'create') {// Toutes les images
            $images = array_filter($data, fn($key) => str_contains($key, 'image-url'), ARRAY_FILTER_USE_KEY);
        } else { // Images supplémentaires
            $images = array_filter($data, fn($key) => str_contains($key, 'image-url-'), ARRAY_FILTER_USE_KEY);
        }
        $images = array_filter($images, fn($image) => $image != '' and $image != "undefined");
        // TODO: Vérifier le code suivant
        $firstImageIndex = 1;
        if ($filmingLocation) {
            // Récupérer les images existantes et les compter
            $existingAdditionalImages = $this->filmingLocationImageRepository->findBy(['filmingLocation' => $filmingLocation]);
            $firstImageIndex += count($existingAdditionalImages);
        }
        // Fin du code à vérifier

        if (!$filmingLocation) {
            $uuid = $data['uuid'] = Uuid::v4()->toString();
            $tmdbId = $data['tmdb-id'];
            $filmingLocation = new FilmingLocation($uuid, $tmdbId, $title, $location, $description, $latitude, $longitude, $radius, $seasonNumber, $episodeNumber, $sourceName, $sourceUrl, $now, true);
            $filmingLocation->setOriginCountry($series->getOriginCountry());
        } else {
            $filmingLocation->update($title, $location, $description, $latitude, $longitude, $radius, $seasonNumber, $episodeNumber, $sourceName, $sourceUrl, $now);
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

    #[Route('/fetch/search/tv', name: 'fetch_search_db_tv', methods: ['POST'])]
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
        $locale = 'en-US';//$user?->getPreferredLanguage() ?? $request->getLocale();
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

    #[Route('/fetch/search/tmdb', name: 'fetch_search_series', methods: ['POST'])]
    public function fetchSearchSeries(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);
        $query = $data['query'];

        $searchString = "query=$query&include_adult=false&page=1";
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

        return $this->json([
            'ok' => true,
            'updates' => $updates,
            'dbSeriesCount' => $dbSeriesCount,
            'tmdbCalls' => $tmdbCalls,
        ]);
    }

    public function getChanges(int $seasonId): array
    {
        $now = $this->now();
        try {
            $startDate = $now->modify('-14 day')->format('Y-m-d');
        } catch (DateMalformedStringException $e) {
            $this->addFlash('error', 'Series changes, error while computing date: ' . $e->getMessage());
            $startDate = $now->format('Y-m-d');
        }
        $endDate = $now->format('Y-m-d');
        $results = json_decode($this->tmdbService->getTvSeasonChanges($seasonId, $endDate, $startDate), true);

        if (!isset($results['changes'])) {
            return [];
        }
        $changes = $results['changes'];
        $changes['keys'] = array_column($changes, 'key');
        return $results['changes'];
    }

    private function handleSerieShowForms(Request $request, Series $series): array
    {
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
        return [$addBackdropForm, $addVideoForm];
    }

    private function addBackdrop(Series $series, UploadedFile $backdropFile): void
    {
        $source = $backdropFile->getPathname();
        $serverPath = '/public/series/backdrops/';
        $destination = $this->getParameter('kernel.project_dir') . $serverPath . $backdropFile->getClientOriginalName();
        if (copy($source, $destination)) {
            $seriesImage = new SeriesImage($series, "backdrop", '/' . $backdropFile->getClientOriginalName());
            $this->seriesImageRepository->save($seriesImage, true);
            $this->addFlash('success', 'The backdrop has been added.');
        }
    }

    private function addVideo(SeriesVideo $video): void
    {
        $this->seriesVideoRepository->save($video, true);
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
            $this->imageService->saveImage($type, $seriesImage->getImagePath(), $url);
        }

        if (!$this->inImages($tv['poster_path'], $seriesImages)) {
            $this->addSeriesImage($series, $tv['poster_path'], "poster", $this->imageConfiguration->getUrl("poster_sizes", $sizes["posters"]));
        }
        if (!$this->inImages($tv['backdrop_path'], $seriesImages)) {
            $this->addSeriesImage($series, $tv['backdrop_path'], "backdrop", $this->imageConfiguration->getUrl("backdrop_sizes", $sizes["backdrops"]));
        }

        foreach (['backdrops', 'logos', 'posters'] as $type) {
            $dbType = substr($type, 0, -1);
            $imageConfigType = $dbType . '_sizes';
            $url = $this->imageConfiguration->getUrl($imageConfigType, $sizes[$type]);
            foreach ($tv['images'][$type] as $img) {
                if (!$this->inImages($img['file_path'], $seriesImages)) {
                    $this->addSeriesImage($series, $img['file_path'], $dbType, $url);
                }
            }
            $tv['images'][$type] = array_map(function ($image) use ($type, $sizes, $imageConfigType) {
                return '/series/' . $type . $image['file_path'];
            }, $tv['images'][$type]);
        }

        if ($tv['poster_path'] != $series->getPosterPath()) {
            $series->setPosterPath($tv['poster_path']);
            $series->addUpdate($this->translator->trans('Poster updated'));
        }
        if ($tv['backdrop_path'] != $series->getBackdropPath()) {
            $series->setBackdropPath($tv['backdrop_path']);
            $series->addUpdate($this->translator->trans('Backdrop updated'));
        }
        if (!$series->getNumberOfEpisode() || $tv['number_of_episodes'] != $series->getNumberOfEpisode()) {
            $series->setNumberOfEpisode($tv['number_of_episodes']);
            $series->setNumberOfSeason($tv['number_of_seasons']);
        }

        $series->setVisitNumber($series->getVisitNumber() + 1);
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

        return [
            'backdrops' => $seriesBackdrops,
            'logos' => $seriesLogos,
            'posters' => $seriesPosters
        ];
    }

    public function getExternals(Series $series, array $keywords, $externalIds, string $locale): array
    {
        $keywordIds = array_map(fn($k) => $k['id'], $keywords);

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
//        if ($progress == 100) {
//            $statusCss .= ' watched';
//        }
        return $statusCss;
    }

    public function updateUserSeries(UserSeries $userSeries, array $tv): UserSeries
    {
        $change = false;
        $episodeCount = $this->checkNumberOfEpisodes($tv);

        $series = $userSeries->getSeries();
        if ($series->getNumberOfEpisode() != $episodeCount) {
            $series->setNumberOfEpisode($episodeCount);
            $this->seriesRepository->save($series, true);
            $this->addFlash('success', 'Number of episode updated to ' . $episodeCount);
        }

        if ($episodeCount == 0 && $userSeries->getProgress() != 0) {
            $this->addFlash('warning', 'Number of episodes is zero');
            $userSeries->setProgress(0);
            $change = true;
        } else {
            if (/*$userSeries->getProgress() == 100 && */ $userSeries->getViewedEpisodes() < $episodeCount) {
                $newProgress = round(100 * $userSeries->getViewedEpisodes() / $episodeCount, 2);
                if ($newProgress != $userSeries->getProgress()) {
                    $userSeries->setProgress($newProgress);
                    $this->addFlash('success', 'Progress updated to ' . $newProgress . '%');
                    $change = true;
                }
            }
            if ($userSeries->getProgress() != 100 && $episodeCount && $userSeries->getViewedEpisodes() === $episodeCount) {
                $userSeries->setNextUserEpisode(null);
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
                        if ($episode['episode_type'] == 'finale') {
                            $count = count($s['episodes']);
                            if ($count > $episode['episode_number']) {
                                $this->addFlash('warning', 'Finale episode number: ' . sprintf("S%02dE%02d", $s['season_number'], $episode['episode_number']) . ' - episode count: ' . $count);
                            }
                            break;
                        };
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
            $seasonNumber = $schedule->getSeasonNumber();
            if ($schedule->isMultiPart()) {
                $multiPart = true;
                $firstEpisode = $schedule->getSeasonPartFirstEpisode();
                $episodeCount = $schedule->getSeasonPartEpisodeCount();
                $lastEpisode = $firstEpisode + $episodeCount - 1;
            } else {
                $multiPart = false;
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
            $scheduleDayOfWeek = [];
            foreach ($daysOfWeek as $key => $day) {
                if ($day) {
                    $scheduleDayOfWeek[] = $dayOfWeekArr[$locale][$key];
                }
            }
            $scheduleDayOfWeek = ucfirst(implode(', ', $scheduleDayOfWeek));
            $dayArr = $daysOfWeek;

            $now = $this->dateService->newDateImmutable('now', 'Europe/Paris');

            $userLastEpisode = $this->userEpisodeRepository->getScheduleLastEpisode($schedule->getId(), $userSeries->getId());
            $userNextEpisode = $this->userEpisodeRepository->getScheduleNextEpisode($schedule->getId(), $userSeries->getId());
            $userLastEpisode = $userLastEpisode[0] ?? null;
            $userNextEpisode = $userNextEpisode[0] ?? null;

            $userLastEpisode = $this->setEpisodeDatetime($userLastEpisode, $airAt);
            $userNextEpisode = $this->setEpisodeDatetime($userNextEpisode, $airAt);

            $endOfSeason = $userLastEpisode && $userLastEpisode['episode_number'] == $episodeCount;

            $target = null;
            $targetTS = null;
            if (!$userNextEpisode && $userLastEpisode) {
                if ($multiPart) {
                    if ($userLastEpisode['episode_number'] >= $firstEpisode && $userLastEpisode['episode_number'] <= $lastEpisode) {
                        $targetTS = $userLastEpisode['date']->getTimestamp();
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
                        if ($userNextEpisode['date'])
                            $targetTS = $userNextEpisode['date']->getTimestamp();
                        else
                            $userNextEpisode = null;
                    } else {
                        $userNextEpisode = null;
                    }
                } else {
                    $targetTS = $userNextEpisode['date']?->getTimestamp() ?? null;
                }
            }

            if ($userNextEpisode && $targetTS) {
                $userNextEpisodes = $this->userEpisodeRepository->getScheduleNextEpisodes($schedule->getId(), $userSeries->getId(), $userNextEpisode['air_date']);
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
            if ($userNextEpisode == null && $userLastEpisode) {
                $targetTS = $userLastEpisode['date']->getTimestamp();
            }

            $providerId = $schedule->getProviderId();
            if ($providerId) {
                $provider = $this->watchProviderRepository->findOneBy(['providerId' => $providerId]);
                $providerName = $provider->getProviderName();
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
                'timezone' => $user->getTimezone() ?? 'Europe/Paris',
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

    private function getTimezoneMenu(): array
    {
        return (new IntlExtension)->getTimezoneNames('fr_FR');
    }

    public function getAlternateSchedule(SeriesBroadcastSchedule $schedule, ?array $tv, array $userEpisodes): array
    {
        $errorArr = ['override' => false, 'seasonNumber' => 0, 'multiPart' => false, 'seasonPart' => 0, 'airDays' => []];
        $now = $this->now();
        $seasonNumber = $schedule->getSeasonNumber();
        $override = $schedule->isOverride();
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
            return ['override' => false, 'seasonNumber' => $seasonNumber, 'multiPart' => false, 'seasonPart' => 0, 'airDays' => $dayArr];
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
        // Frequency values:
        //  1 - All at once
        //  2 - Daily
        //  3 - Weekly, one at a time
        //  4 - Weekly, two at a time
        //  5 - Weekly, three at a time
        // 11 - Weekly, four at a time
        //  6 - Weekly, two, then one
        //  7 - Weekly, three, then one
        //  8 - Weekly, four, then one
        //  9 - Weekly, four, then two
        // 10 - Weekly, selected days
        // 12 - Selected days, then weekly, one at a time

        $firstAirDate = $firstAirDate->setTime($airAt->format('H'), $airAt->format('i'));
        $date = $firstAirDate;
        $dayArr = [];
        switch ($frequency) {
            case 1: // All at once
                for ($i = $firstEpisode; $i <= $lastEpisode; $i++) {
                    $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $i), 'episodeNumber' => $i, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $i), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $i), 'future' => $now < $date];
                }
                break;
            case 2: //
                $n = array_reduce($daysOfWeek, function ($carry, $day) {
                    return $carry + $day;
                }, 0);
                if ($n) {
                    // day of week of first air date
                    $firstAirDayOfWeek = intval($firstAirDate->format('w'));
                    $weekNumber = 0;
                    $episodeIndex = $firstEpisode;
                    /*$this->addFlash('success',
                        'date: ' . $firstAirDate->format('Y-m-d')
                        . ' firstAirDayOfWeek: ' . $firstAirDayOfWeek
                        . ' daysOfWeek: ' . implode(',', $daysOfWeek),
                    );*/
                    while ($episodeIndex <= $lastEpisode) {
                        /*$this->addFlash('success', 'weekNumber: ' . $weekNumber);*/
                        for ($i = 0; $i < 7 && $episodeIndex <= $lastEpisode; $i++) {
                            for ($j = 0; $j < $daysOfWeek[$i] && $episodeIndex <= $lastEpisode; $j++) {
                                $date = $this->dateModify($firstAirDate, '+' . (($i - $firstAirDayOfWeek + 7) % 7) . ' days');
                                $date = $this->dateModify($date, "+$weekNumber week");
                                $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $episodeIndex), 'episodeNumber' => $episodeIndex, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $episodeIndex), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $episodeIndex), 'future' => $now < $date];
                                $episodeIndex++;
                                /*$this->addFlash('success', 'episode index: ' . $episodeIndex);*/
                            }
                        }
                        $weekNumber++;
                    }
                } else {
                    for ($i = $firstEpisode; $i <= $lastEpisode; $i++) {
                        $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $i), 'episodeNumber' => $i, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $i), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $i), 'future' => $now < $date];
                        $date = $this->dateModify($date, '+1 day');
                    }
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
                $firstDayOfWeek = intval($date->format('w'));
                $selectedDayCount = array_reduce($daysOfWeek, function ($carry, $day) {
                    return $carry + ($day > 0);
                }, 0);
                $selectedEpisodeCount = array_reduce($daysOfWeek, function ($carry, $day) {
                    return $carry + $day;
                }, 0);
                if (!$this->isValidDaysOfWeek($daysOfWeek, $selectedDayCount, $firstDayOfWeek, $date)) {
                    return $errorArr;
                }
                // First  day of week: 5
                // DaysOfWeek: [1,0,0,0,0,1,1], [1,1,0,0,0,1,1], [1,1,1,0,0,0,1], [1,1,1,1,0,0,0]
                //$dayIndexArr = array_keys($daysOfWeek, 1);
                // → dayIndexArr: [0,5,6], [0,1,5,6], [0,1,2,6], [0,1,2,3]
                //for ($i = 0; $i < $selectedDayCount; $i++) {
                //    if ($dayIndexArr[$i] < $firstDayOfWeek) {
                //        $dayIndexArr[$i] += 7;
                //    }
                //}
                // → dayIndexArr: [7,5,6], [7,1,5,6], [7,1,2,6], [7,1,2,3]
                //sort($dayIndexArr);
                // → dayIndexArr: [5,6,7], [1,5,6,7], [1,2,6,7], [1,2,3,7]*/
                $dayIndexArr = $this->daysOfWeekToDayIndexArr($daysOfWeek, $selectedDayCount, $firstDayOfWeek);

                for ($i = $firstEpisode, $k = 1; $i <= $lastEpisode; $i += $selectedEpisodeCount, $k++) {
                    $j = $i;
                    $firstDateOfWeek = $date;
                    foreach ($dayIndexArr as $day) {
                        if ($j <= $lastEpisode) {
                            $d = $day - $firstDayOfWeek;
                            if ($d < 0) $d += 7;
                            if ($d) $date = $this->dateModify($firstDateOfWeek, '+' . $d . ' day');
                            for ($jj = 0; $jj < $daysOfWeek[$day]; $jj++) { // $jj → number of episodes on this day
                                if ($j <= $lastEpisode) {
                                    $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $j), 'episodeNumber' => $j, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $j), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $j), 'future' => $now < $date];
                                    $j++;
                                }
                            }
//                            $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $j), 'episodeNumber' => $j, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $j), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $j), 'future' => $now < $date];
//                            $j++;
                        }
                    }
//                    $date = $firstAirDate->modify('+' . $k . ' week');
                    $date = $this->dateModify($firstAirDate, '+' . $k . ' week');
                }
                break;
            case 12: // Selected days, then weekly, one at a time
                $firstDayOfWeek = intval($date->format('w'));
                $selectedDayCount = array_reduce($daysOfWeek, function ($carry, $day) {
                    return $carry + ($day > 0);
                }, 0);
                if (!$this->isValidDaysOfWeek($daysOfWeek, $selectedDayCount, $firstDayOfWeek, $date)) {
                    return $errorArr;
                }
                $dayIndexArr = $this->daysOfWeekToDayIndexArr($daysOfWeek, $selectedDayCount, $firstDayOfWeek);
                $j = $firstEpisode;
                foreach ($dayIndexArr as $day) {
                    $d = $day - $firstDayOfWeek;
                    if ($d < 0) $d += 7;
                    if ($d) $date = $this->dateModify($date, '+' . $d . ' day');
                    for ($jj = 0; $jj < $daysOfWeek[$day]; $jj++) { // number of episodes on this day
                        if ($j <= $lastEpisode) {
                            $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $j), 'episodeNumber' => $j, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $j), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $j), 'future' => $now < $date];
                            $j++;
                        }
                    }
                }
                $secondAirDate = $this->dateModify($firstAirDate, '+' . ($dayIndexArr[$selectedDayCount - 1] - $dayIndexArr[0]) . ' day');
                for ($i = $selectedDayCount + 1; $i <= $lastEpisode; $i++) { // $i → episode number
                    $date = $this->dateModify($secondAirDate, '+' . $i - $selectedDayCount . ' week');
                    $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $i), 'episodeNumber' => $i, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $i), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $i), 'future' => $now < $date];
                }
                break;
        }
        return ['override' => $override, 'seasonNumber' => $seasonNumber, 'multiPart' => $multiPart, 'seasonPart' => $seasonPart, 'airDays' => $dayArr];
    }

    private function isValidDaysOfWeek(array $daysOfWeek, int $selectedDayCount, int $firstDayOfWeek, DateTimeImmutable $date): bool
    {
        if (!$selectedDayCount) {
            // No selected days of week
            $this->addFlash('error', $this->translator->trans('No selected days of week.'));
            return false;
        }

        if (!$daysOfWeek[$firstDayOfWeek]) {
            $dayStrings = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
            $selectedDaysString = '';
            foreach ($daysOfWeek as $key => $day) {
                if ($day) {
                    $selectedDaysString .= $this->translator->trans($dayStrings[$key]) . ', ';
                }
            }
            $selectedDaysString = rtrim($selectedDaysString, ', ');
            $firstDayString = $this->translator->trans($dayStrings[$firstDayOfWeek]);
            $this->addFlash('error', $this->translator->trans('The first day of the week must be in the selected days of the week.')
                . '<br>' . $this->translator->trans('Selected days of the week → %days%', ['%days%' => $selectedDaysString])
                . '<br>' . $this->translator->trans('First day of the week → %day%', ['%day%' => $firstDayString]));
            return false;
        }
        return true;
    }

    private function daysOfWeekToDayIndexArr(array $daysOfWeek, int $selectedDayCount, int $firstDayOfWeek): array
    {
        // First  day of week: 5
        // DaysOfWeek: [1,0,0,0,0,1,1], [1,1,0,0,0,1,1], [1,1,1,0,0,0,1], [1,1,1,1,0,0,0]
        $dayIndexArr = [];
        foreach ($daysOfWeek as $key => $day) {
            if ($day) {
                $dayIndexArr[] = $key;
            }
        }
        // → dayIndexArr: [0,5,6], [0,1,5,6], [0,1,2,6], [0,1,2,3]
        for ($i = 0; $i < $selectedDayCount; $i++) {
            if ($dayIndexArr[$i] < $firstDayOfWeek) {
                $dayIndexArr[$i] += 7;
            }
        }
        // → dayIndexArr: [7,5,6], [7,1,5,6], [7,1,2,6], [7,1,2,3]
        sort($dayIndexArr);
        // → dayIndexArr: [5,6,7], [1,5,6,7], [1,2,6,7], [1,2,3,7]

        return $dayIndexArr;
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

    private function emptySchedule(): array
    {
        $now = $this->now();
        $dayArrEmpty = array_fill(0, 7, 0);
        return [
            'id' => 0,
            'seasonNumber' => 1,
            'multiPart' => false,
            'seasonPart' => 1,
            'seasonPartFirstEpisode' => 1,
            'seasonPartEpisodeCount' => 1,
            'airAt' => "12:00",
            'timezone' => $this->getUser()->getTimezone() ?? 'UTC',
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

    private function alternateSchedules(array $tv, Series $series, array $userEpisodes): array
    {
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
                    ]) . "#episode-" . $s['seasonNumber'] . '-' . $day['episodeNumber'];
                return $day;
            }, $s['airDays']);
        }
        return $alternateSchedules;
    }

    private function overrideSeasonAirDate(array $tvSeasons, array $schedules): array
    {
        $alternativeSchedules = array_filter($schedules, function ($s) {
            return $s['override'];
        });
        if ($alternativeSchedules) {
            foreach ($tvSeasons as &$season) {
                $seasonNumber = $season['season_number'];
                $seasonSchedules = array_filter($alternativeSchedules, function ($s) use ($seasonNumber) {
                    return $s['seasonNumber'] == $seasonNumber;
                });
                $seasonSchedules = array_values($seasonSchedules);
                if ($seasonSchedules) {
                    // Remplacer la date de la saison par la date du schedule
                    $time = explode(':', $seasonSchedules[0]['airAt']);
                    $season['final_air_date'] = $seasonSchedules[0]['firstAirDate']->setTime($time[0], $time[1], 0)->format('Y-m-d H:i:s');
                }
            }
        }
        return $tvSeasons;
    }

    public function setEpisodeDatetime(?array $episode, DateTimeInterface $time): ?array
    {
        if (!$episode) return null;
        if (!$episode['air_date']) {
            $episode['date'] = null;
            return $episode;
        }
        $date = $episode['air_date'];
        $date = $this->date($date, true);

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

    public function addSeriesImage(Series $series, string $imagePath, string $imageType, string $imageUrl): void
    {
        $seriesImage = new SeriesImage($series, $imageType, $imagePath);
        $this->seriesImageRepository->save($seriesImage, true);
        $this->imageService->saveImage($imageType . "s", $imagePath, $imageUrl);
        $series->addUpdate($this->translator->trans(ucfirst($imageType) . ' added'));
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
            $this->addSeasonToUser($user, $userSeries, $season['season_number'], []);
        }
        return $userSeries;
    }

    public function checkSeasons(UserSeries $userSeries, array $userEpisodes, array $tv): array
    {
        $user = $userSeries->getUser();
        $series = $userSeries->getSeries();
        $newEpisodeCount = 0;
        foreach ($tv['seasons'] as $season) {
            $seasonNumber = $season['season_number'];
            $newEpisodeCount += $this->addSeasonToUser($user, $userSeries, $seasonNumber, array_filter($userEpisodes, function ($ue) use ($seasonNumber) {
                return $ue->getSeasonNumber() == $seasonNumber;
            }));
        }
        if ($newEpisodeCount) {
            $series->addUpdate($newEpisodeCount . ' ' . $this->translator->trans('new episodes have been added to the series'));
            return $this->userEpisodeRepository->findBy(['userSeries' => $userSeries, 'previousOccurrence' => null], ['seasonNumber' => 'ASC', 'episodeNumber' => 'ASC']);
        }
        return $userEpisodes;
    }

    public function addSeasonToUser(User $user, UserSeries $userSeries, int $seasonNumber, array $userEpisodes): int
    {
        $series = $userSeries->getSeries();
        $language = $user->getPreferredLanguage() ?? "fr" . "-" . $user->getCountry() ?? "FR";
        $tvSeason = json_decode($this->tmdbService->getTvSeason($series->getTmdbId(), $seasonNumber, $language), true);
        $newEpisodeCount = 0;
        if (!$tvSeason) {
            return 0;
        }
        if (!isset($tvSeason['episodes']) || count($tvSeason['episodes']) == 0) {
            return 0;
        }

        $seasonNumber = $tvSeason['season_number'];
        $finaleEpisodeNumber = $this->getFinaleEpisodeNumber($tvSeason);

        foreach ($tvSeason['episodes'] as $episode) {
            $dbUserEpisode = array_find($userEpisodes, fn($e) => $e->getEpisodeId() == $episode['id']);
            if ($dbUserEpisode) {
                // Episode already exists in user's series
                continue;
            }
            if ($episode['episode_number'] > $finaleEpisodeNumber) {
                $this->addFlash('warning', "// Skip episode " . sprintf("S%02dE%02d", $tvSeason['season_number'], $episode['episode_number']) . " after a finale");
                continue;
            }
            $newEpisodeCount += $this->addEpisodeToUser($userSeries, $episode, $seasonNumber);
        }

        if ($newEpisodeCount) {
            $this->userEpisodeRepository->flush();
        }
        $epIds = $tvSeason['episodes'] ? array_map(fn($e) => $e['id'], $tvSeason['episodes']) : [];
        $ueIds = array_column($this->userEpisodeRepository->getSeasonEpisodeIds($userSeries->getId(), $seasonNumber), 'episode_id');
        $removedEpisodeIds = array_values(array_diff($ueIds, $epIds));
        $updatedEpisodeIds = array_values(array_intersect($ueIds, $epIds));
        $removedEpisodeCount = count($removedEpisodeIds);

        if ($removedEpisodeCount) {
            if ($removedEpisodeCount == 1) {
                $this->addFlash('info', $this->translator->trans('An episode has been removed from the series (%id%)', ['%id%' => $removedEpisodeIds[0]]));
            } else {
                $this->addFlash('info', $this->translator->trans('%count% episodes have been removed from the series (%list%)', ['%count%' => $removedEpisodeCount, '%list%' => implode(', ', $removedEpisodeIds)]));
            }
            $this->userEpisodeRepository->removeByEpisodeIds($userSeries, $removedEpisodeIds);
            $this->userEpisodeRepository->flush();
            $this->reloadUserEpisodes = true;

            // Get TMDB infos for remaining episodes
            foreach ($updatedEpisodeIds as $episodeId) {
                /** @var UserEpisode $dbUserEpisode */
                $dbUserEpisode = array_find($userEpisodes, fn($e) => $e->getEpisodeId() == $episodeId);
                if ($dbUserEpisode) {
                    $episode = array_find($tvSeason['episodes'], fn($e) => $e['id'] == $episodeId);
                    if ($episode) {
                        $airDate = $episode['air_date'] ? $this->date($episode['air_date'], true) : null;
                        $dbUserEpisode->setAirDate($airDate);
                        $dbUserEpisode->setEpisodeNumber($episode['episode_number']);
                        $this->userEpisodeRepository->save($dbUserEpisode);
                        $this->addFlash('info', $this->translator->trans('Episode %number% has been updated', ['%number%' => sprintf('S%02dE%02d', $seasonNumber, $episode['episode_number'])]));
                    }
                }
            }
        }

        return $newEpisodeCount;
    }

    private function getFinaleEpisodeNumber(array $tvSeason): int
    {
        $finaleEpisodeNumber = count($tvSeason['episodes']);
        foreach ($tvSeason['episodes'] as $episode) {
            if (key_exists("episode_type", $episode)) {
                if ($episode['episode_type'] == "finale") {
                    $finaleEpisodeNumber = $episode['episode_number'];
                }
            }
        }
        return $finaleEpisodeNumber;
    }

    public function addEpisodeToUser(UserSeries $userSeries, array $episode, int $seasonNumber): int
    {
        $userEpisode = new UserEpisode($userSeries, $episode['id'], $seasonNumber, $episode['episode_number'], null);
        $airDate = $episode['air_date'] ? $this->date($episode['air_date'], true) : null;
        $userEpisode->setAirDate($airDate);
        $this->userEpisodeRepository->save($userEpisode);
        if ($seasonNumber == 0) {
            return 1;
        }
        if ($userSeries->getNextUserEpisode() === null && $airDate && $airDate > $this->now()) {
            $this->userEpisodeRepository->flush();
            $userSeries->setNextUserEpisode($userEpisode);
            $this->userSeriesRepository->save($userSeries, true);
            $this->addFlash('info', $this->translator->trans('Next episode to watch: %episode%', ['%episode%' => sprintf('S%02dE%02d', $seasonNumber, $episode['episode_number'])]));
        }
        return 1;
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

    public function getFilmingLocations(Series $series): array
    {
        $tmdbId = $series->getTmdbId();
        $filmingLocations = $this->filmingLocationRepository->locations($tmdbId);
        $emptyLocation = $this->newLocation($series);
        if (count($filmingLocations) == 0) {
            return [
                'filmingLocations' => [],
                'emptyLocation' => $emptyLocation,
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
            'emptyLocation' => $emptyLocation,
            'bounds' => $bounds
        ];
    }

    private function newLocation(Series $series): array
    {
        $uuid = Uuid::v4()->toString();
        $now = $this->now();
        $tmdbId = $series->getTmdbId();
        $emptyLocation = new FilmingLocation($uuid, $tmdbId, "", "", "", 0, 0, null, 0, 0, "", "", $now, true);
        $emptyLocation->setOriginCountry($series->getOriginCountry());
        return $emptyLocation->toArray();
    }

    public function now(): DateTimeImmutable
    {
        $user = $this->getUser();
        $timezone = $user ? $user->getTimezone() : 'Europe/Paris';
        return $this->dateService->newDateImmutable('now', $timezone);
    }

    public function date(string $dateString, bool $allDays = false): DateTimeImmutable
    {
        $user = $this->getUser();
        $timezone = $user ? $user->getTimezone() : 'Europe/Paris';
        return $this->dateService->newDateImmutable($dateString, $timezone, $allDays);
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

    public function castAndCrew(array $tv, ?Series $series): array
    {
        if (!$tv) {
            return ['cast' => [], 'crew' => [], 'guest_stars' => []];
        }
        if ($series) {
            $seriesCastArr = array_map(function ($sc) {
                $sc['original_name'] = '';
                $sc['popularity'] = 0;
                $sc['character'] = $sc['character_name'];
                $sc['credit_id'] = '';
                $sc['order'] = 0;
                return $sc;
            }, $this->seriesCastRepository->getSeriesCatsBySeriesId($series->getId()));
            $tv['credits']['cast'] = array_merge($tv['credits']['cast'] ?? [], $seriesCastArr);
        }
        $peopleIds = array_column($tv['credits']['cast'], 'id');
        $peopleIds = array_merge($peopleIds, array_column($tv['credits']['guest_stars'] ?? [], 'id'));
        $peopleIds = array_merge($peopleIds, array_column($tv['credits']['crew'] ?? [], 'id'));
        $peopleIds = array_unique($peopleIds);
        $arr = $this->peopleUserPreferredNameRepository->getPreferredNames($peopleIds);
        $preferredNames = [];
        foreach ($arr as $name) {
            $preferredNames[$name['tmdb_id']] = $name['name'];
        }

        /******************************************************************************************
         * slug() doesn't work well with some non-Latin characters (e.g. Laos, Cambodia, Myanmar) *
         ******************************************************************************************/

        $slugger = new AsciiSlugger();
        $profileUrl = $this->imageConfiguration->getUrl('profile_sizes', 2);
        $tv['credits']['cast'] = array_map(function ($cast) use ($slugger, $profileUrl, $preferredNames) {
            $cast['profile_path'] = $cast['profile_path'] ? $profileUrl . $cast['profile_path'] : null; // w185
            $cast['preferred_name'] = null;
            if (key_exists($cast['id'], $preferredNames)) {
                $cast['preferred_name'] = $preferredNames[$cast['id']];
                $cast['slug'] = $slugger->slug($cast['preferred_name'])->lower()->toString();
            } else {
                $cast['slug'] = $slugger->slug($cast['name'])->lower()->toString();
            }
            if ($cast['slug'] == '') {
                $cast['slug'] = 'person-' . $cast['id'];
            }
            return $cast;
        }, $tv['credits']['cast']);
        $tv['credits']['guest_stars'] = array_map(function ($cast) use ($slugger, $profileUrl, $preferredNames) {
            $cast['profile_path'] = $cast['profile_path'] ? $profileUrl . $cast['profile_path'] : null; // w185
            $cast['preferred_name'] = null;
            if (key_exists($cast['id'], $preferredNames)) {
                $cast['preferred_name'] = $preferredNames[$cast['id']];
                $cast['slug'] = $slugger->slug($cast['preferred_name'])->lower()->toString();
            } else {
                $cast['slug'] = $slugger->slug($cast['name'])->lower()->toString();
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
            $c['profile_path'] = $c['profile_path'] ? $profileUrl . $c['profile_path'] : null; // w185
            $c['preferred_name'] = null;
            if (key_exists($c['id'], $preferredNames)) {
                $c['preferred_name'] = $preferredNames[$c['id']];
                $c['slug'] = $slugger->slug($c['preferred_name'])->lower()->toString();
            } else {
                $c['slug'] = $slugger->slug($c['name'])->lower()->toString();
            }
            if ($c['slug'] == '') {
                $c['slug'] = 'person-' . $c['id'];
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
        if (!count($tv['networks'])) return [];

        $logoUrl = $this->imageConfiguration->getUrl('logo_sizes', 5);
        $ids = array_column($tv['networks'], 'id');
        $networkDbs = $this->networkRepository->findBy(['networkId' => $ids]);

        $now = $this->now();

        foreach ($tv['networks'] as $tvNetwork) {
            $id = $tvNetwork['id'];
            /** @var Network $networkDb */
            $arr = array_filter($networkDbs, fn($n) => $n->getNetworkId() == $id);//$this->networkRepository->findOneBy(['networkId' => $id]);
            $networkDb = array_values($arr)[0] ?? null;

            if (!$networkDb) {
                $tmdbNetwork = json_decode($this->tmdbService->getNetworkDetails($id), true);

                if (!$tmdbNetwork) {
                    $this->addFlash('error', $this->translator->trans('network.not_found') . ' → ' . $tvNetwork['name'] . ' (ID: ' . $id . ')');
                    continue;
                }
                $networkDb = new Network($tmdbNetwork['logo_path'], $tmdbNetwork['name'], $id, $tmdbNetwork['origin_country'], $now);
                $networkDb->setHeadquarters($tmdbNetwork['headquarters']);
                $networkDb->setHomepage($tmdbNetwork['homepage']);
                $this->networkRepository->save($networkDb);
                $this->addFlash('success', $this->translator->trans('network.added') . ' → ' . $networkDb->getName());
            } else {
                $diff = $networkDb->getUpdatedAt()->diff($now);
                if ($diff->days > 30) {
                    $tmdbNetwork = json_decode($this->tmdbService->getNetworkDetails($networkDb->getNetworkId()), true);

                    if (!$tmdbNetwork) {
                        $this->addFlash('error', $this->translator->trans('network.not_found') . ' → ' . $networkDb->getName() . ' (ID: ' . $networkDb->getNetworkId() . ')');
                        continue;
                    }
                    $networkDb->setHeadquarters($tmdbNetwork['headquarters']);
                    $networkDb->setHomepage($tmdbNetwork['homepage']);
                    $networkDb->setLogoPath($tmdbNetwork['logo_path']);
                    $networkDb->setName($tmdbNetwork['name'] ?? 'The name was null');
                    $networkDb->setOriginCountry($tmdbNetwork['origin_country']);
                    $networkDb->setUpdatedAt($now);
                    $this->networkRepository->save($networkDb);
                    $this->addFlash('success', $this->translator->trans('network.updated') . ' → ' . $networkDb->getName());
                }
            }
        }

        $dbNetworks = $this->networkRepository->findBy(['networkId' => $ids]);
        return array_map(function ($network) use ($logoUrl, $dbNetworks) {
            $network['logo_path'] = $network['logo_path'] ? $logoUrl . $network['logo_path'] : null; // w92
            $dbNetwork = array_values(array_filter($dbNetworks, fn($n) => $n->getNetworkId() == $network['id']))[0] ?? null;
            if ($dbNetwork) {
                $network['headquarters'] = $dbNetwork->getHeadquarters();
                $network['homepage'] = $dbNetwork->getHomepage();
            } else {
                $network['headquarters'] = null;
                $network['homepage'] = null;
            }
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

    private function seasonEpisodes(array $season, UserSeries $userSeries): array
    {
        $user = $userSeries->getUser();
        $series = $userSeries->getSeries();
        $next_episode_to_air = $series->getNextEpisodeAirDate();
        $slugger = new AsciiSlugger();
        $locale = $user->getPreferredLanguage() ?? 'fr';
        $seasonEpisodes = [];
        $userEpisodes = $this->userEpisodeRepository->getUserEpisodesDB($userSeries->getId(), $season['season_number'], $locale, true);
        $arr = $this->peopleUserPreferredNameRepository->getUserPreferredNames($user->getId());
        $peopleUserPreferredNames = [];
        foreach ($arr as $people) {
            $peopleUserPreferredNames[$people['tmdb_id']] = $people;
        }

        $episodeIds = array_column($userEpisodes, 'episode_id');
        $stills = $this->episodeStillRepository->getSeasonStills($episodeIds);

        $newCount = 0;
        $finaleEpisodeNumber = $this->getFinaleEpisodeNumber($season);
        foreach ($season['episodes'] as $episode) {
            if (key_exists('episode_type', $episode) && $episode['episode_type'] == 'finale') {
                $finaleEpisodeNumber = $episode['episode_number'];
            }
            if ($episode['episode_number'] > $finaleEpisodeNumber) {
                $this->addFlash('warning', "// Skip episode " . sprintf("S%02dE%02d", $season['season_number'], $episode['episode_number']) . " after a finale");
                continue;
            }
            $userEpisode = $this->getUserEpisode($userEpisodes, $episode['episode_number']);
            if (!$userEpisode) {
                $nue = new UserEpisode($userSeries, $episode['id'], $season['season_number'], $episode['episode_number'], null);
                $nue->setAirDate($episode['air_date'] ? $this->date($episode['air_date']) : null);
                if ($episode['episode_number'] > 1) {
                    $previousEpisode = $this->getUserEpisode($userEpisodes, $episode['episode_number'] - 1);
                    if ($previousEpisode) {
                        $nue->setProviderId($previousEpisode['provider_id']);
                        $nue->setDeviceId($previousEpisode['device_id']);
                    }
                }
                $this->userEpisodeRepository->save($nue, true);
                $userEpisode = $this->userEpisodeRepository->getUserEpisodeDB($nue->getId(), $locale);
                $newCount++;
            }
            if (!$userEpisode['custom_date'] && !$next_episode_to_air && !$episode['air_date']) {
                continue;
            }
            if (!$userEpisode['air_date'] && $episode['air_date']) {
                $ue = $this->userEpisodeRepository->findOneBy(['userSeries' => $userSeries, 'episodeId' => $episode['id']]);
                if ($ue) {
                    $airDate = $this->date($episode['air_date']);
                    $ue->setAirDate($airDate);
                    $this->userEpisodeRepository->save($ue, true);
                    $userEpisode['air_date'] = $airDate;
                    $this->addFlash('success',
                        $this->translator->trans('Episode air date updated')
                        . ' (' . sprintf('S%02dE%02d', $season['season_number'], $episode['episode_number'])
                        . ' → ' . $airDate->format('Y-m-d') . ')');
                }
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

            $episode['guest_stars'] = array_filter($episode['guest_stars'] ?? [], function ($guest) {
                return key_exists('id', $guest);
            });
            usort($episode['guest_stars'], function ($a, $b) {
                return !$a['profile_path'] <=> !$b['profile_path'];
            });
            $episode['guest_stars'] = array_map(function ($guest) use ($slugger, $series, $profileUrl, $peopleUserPreferredNames) {
                $guest['profile_path'] = $guest['profile_path'] ? $profileUrl . $guest['profile_path'] : null; // w185
                $guest['slug'] = $slugger->slug($guest['name'])->lower()->toString();
                if (!$guest['profile_path']) {
                    $guest['google'] = 'https://www.google.com/search?q=' . urlencode($guest['name'] . ' ' . $series->getName());
                }
                if (key_exists($guest['id'], $peopleUserPreferredNames)) {
                    $guest['preferred_name'] = $peopleUserPreferredNames[$guest['id']]['name'];
                } else {
                    $guest['preferred_name'] = null;
                }
                return $guest;
            }, $episode['guest_stars']);

            $userEpisode['watch_at_db'] = $userEpisode['watch_at'];
            if ($userEpisode['watch_at']) {
                $userEpisode['watch_at'] = $this->date($userEpisode['watch_at']);
            }
            $episode['user_episode'] = $userEpisode;
            $episode['user_episodes'] = $userEpisodeList;
//            $episode['substitute_name'] = $this->userEpisodeRepository->getSubstituteName($episode['id']);
            $seasonEpisodes[] = $episode;
        }
        if ($newCount) {
            $this->addFlash('warning', $newCount . ' new episode' . ($newCount > 1 ? 's' : '') . ' added to your watchlist');
            $tv = json_decode($this->tmdbService->getTv($series->getTmdbId(), 'en-US'), true);
            $series->setNumberOfSeason($tv['number_of_seasons']);
            $series->setNumberOfEpisode($tv['number_of_episodes']);
            $this->seriesRepository->save($series, true);
            $this->addFlash('success', sprintf("Series updated (%d season%s, %d episode%s)", $tv['number_of_seasons'], $tv['number_of_seasons'] > 1 ? 's' : '', $tv['number_of_episodes'], $tv['number_of_episodes'] > 1 ? 's' : ''));
        }
        return $seasonEpisodes;
    }

    private function adjustSeasonAirDate(array $season, string $type): ?string
    {
        if ($type == 'string') {
            $user = $this->getUser();
            $timezone = $user->getTimezone() ?? "Europe/Berlin";
            $locale = $user->getPreferredLanguage() ?? 'fr';
            if (count($season['episodes']) < 1) {
                return $season['air_date'] ? $this->dateService->formatDateRelativeLong($season['air_date'], $timezone, $locale) : $this->translator->trans('No date yet');
            }
            $firstEpisode = $season['episodes'][0];
            $airDate = $firstEpisode['air_date'];
            return $airDate ? $this->dateService->formatDateRelativeLong($airDate, $timezone, $locale) : $this->translator->trans('No date yet');
        }
        if (count($season['episodes']) < 1) {
            return $season['air_date'];
        }
        $firstEpisode = $season['episodes'][0];
        return $firstEpisode['air_date'];
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
//
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
//
//            return $localizedOverview->getOverview();
//        }
//        $overview = $episode['overview'];
//        if (strlen($overview)) {
//            try {
//                $usage = $this->deeplTranslator->translator->getUsage();
//
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

//    public function getWatchProviders($watchRegion): array
//    {
//        // May be unavailable - when Youtube was added for example
//        // TODO: make a command to regularly update db
////        $providers = json_decode($this->tmdbService->getTvWatchProviderList($language, $watchRegion), true);
////        $providers = $providers['results'];
////        if (count($providers) == 0) {
//        $providers = $this->watchProviderRepository->getWatchProviderList($watchRegion);
////        }
//        $watchProviders = [];
//        foreach ($providers as $provider) {
//            $watchProviders[$provider['provider_name']] = $provider['provider_id'];
//        }
//        $watchProviderNames = [];
//        foreach ($providers as $provider) {
//            $watchProviderNames[$provider['provider_id']] = $provider['provider_name'];
//        }
//        $watchProviderLogos = [];
//        $logoUrl = $this->imageConfiguration->getUrl('logo_sizes', 2);
//        foreach ($providers as $provider) {
//            $watchProviderLogos[$provider['provider_id']] = $this->getProviderLogoFullPath($provider['logo_path'], $logoUrl);
//        }
//        uksort($watchProviders, function ($a, $b) {
//            return strcasecmp($a, $b);
//        });
//        $list = [];
//        foreach ($watchProviders as $key => $value) {
//            $list[] = ['provider_id' => $value, 'provider_name' => $key, 'logo_path' => $watchProviderLogos[$value]];
//        }
//
//        return [
//            'select' => $watchProviders,
//            'logos' => $watchProviderLogos,
//            'names' => $watchProviderNames,
//            'list' => $list,
//        ];
//    }

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

    public function getSearchString(?User $user, SeriesAdvancedSearchDTO $data): string
    {
        // App\DTO\SeriesAdvancedSearchDTO {#811 ▼
        //  *-language: "fr"
        //  *-timezone: "Europe/Paris"
        //  *-watchRegion: "FR"
        //  -firstAirDateYear: 2023                     → first_air_date_year
        //  -firstAirDateGTE: null                      → first_air_date.gte
        //  -firstAirDateLTE: null                      → first_air_date.lte
        //  -withOriginCountry: null                    → with_origin_country
        //  -withOriginalLanguage: null                 → with_original_language
        //  -withWatchMonetizationTypes: "flatrate"     → with_watch_monetization_types
        //  -withWatchProviders: "119"                  → with_watch_providers
        //  -watchProviders: array:59 [▶]
        //  -withRuntimeGTE: 0                          → with_runtime.gte
        //  -withRuntimeLTE: 0                          → with_runtime.lte
        //  -withStatus: null                           → with_status
        //  -withType: null                             → with_type
        //  -sortBy: "popularity.desc"                  → sort_by
        //  *-page: 1
        //}
        $settings['page'] = $page = $data->getPage();
        $settings['language'] = $language = $data->getLanguage();
        $settings['timezone'] = $timezone = $data->getTimezone();
        $settings['watch region'] = $watchRegion = $data->getWatchRegion();
        $settings['first air date year'] = $firstAirDateYear = $data->getFirstAirDateYear();
        $settings['first air date  GTE'] = $firstAirDateGTE = $data->getFirstAirDateGTE()?->format('Y-m-d');
        $settings['first air date LTE'] = $firstAirDateLTE = $data->getFirstAirDateLTE()?->format('Y-m-d');
        $settings['with origin country'] = $withOriginCountry = $data->getWithOriginCountry();
        $settings['with original language'] = $withOriginalLanguage = $data->getWithOriginalLanguage();
        $settings['with watch monetization types'] = $withWatchMonetizationTypes = $data->getWithWatchMonetizationTypes();
        $settings['with watch providers'] = $withWatchProviders = $data->getWithWatchProviders();
        $settings['with keywords'] = $withKeywords = $data->getWithKeywords();
        $settings['with runtime GTE'] = $withRuntimeGTE = $data->getWithRuntimeGTE();
        $settings['with runtime LTE'] = $withRuntimeLTE = $data->getWithRuntimeLTE();
        $settings['with status'] = $withStatus = $data->getWithStatus();
        $settings['with type'] = $withType = $data->getWithType();
        $settings['sort by'] = $sortBy = $data->getSortBy();

        if ($user) {
            $advancedSearchSettings = $this->settingsRepository->findOneBy(['user' => $user, 'name' => 'advanced search']);
            if (!$advancedSearchSettings) {
                $advancedSearchSettings = new Settings($user, 'advanced search', $settings);
            } else {
                $advancedSearchSettings->setData($settings);
            }
            $this->settingsRepository->save($advancedSearchSettings, true);
        }

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
