<?php

namespace App\Controller;

use App\Api\ApiWatchLink;
use App\DTO\SeriesAdvancedSearchDTO;
use App\DTO\SeriesSearchDTO;
use App\Entity\ContactMessage;
use App\Entity\Series;
use App\Entity\SeriesBroadcastDate;
use App\Entity\SeriesBroadcastSchedule;
use App\Entity\SeriesImage;
use App\Entity\Settings;
use App\Entity\User;
use App\Entity\UserSeries;
use App\Form\SeriesAdvancedDbSearchType;
use App\Form\SeriesAdvancedSearchType;
use App\Form\SeriesSearchType;
use App\Repository\ContactMessageRepository;
use App\Repository\NetworkRepository;
use App\Repository\SeriesBroadcastDateRepository;
use App\Repository\SeriesBroadcastScheduleRepository;
use App\Repository\SeriesImageRepository;
use App\Repository\SeriesRepository;
use App\Repository\SettingsRepository;
use App\Repository\UserEpisodeRepository;
use App\Repository\UserSeriesRepository;
use App\Service\DateService;
use App\Service\ImageConfiguration;
use App\Service\ImageService;
use App\Service\KeywordService;
use App\Service\ProviderService;
use App\Service\SeriesService;
use App\Service\SettingsAdvancedDbSearchService;
use App\Service\TMDBService;
use Collator;
use DateInvalidTimeZoneException;
use DateMalformedStringException;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Psr\Log\LoggerInterface as MonologLogger;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Contracts\Translation\TranslatorInterface;

/** @method User|null getUser() */
#[Route('/{_locale}/series', name: 'app_series_', requirements: ['_locale' => 'fr|en|ko'])]
class SeriesController extends AbstractController
{
    public function __construct(
        private readonly ApiWatchLink                      $watchLinkApi,
        private readonly ContactMessageRepository          $contactMessageRepository,
        private readonly DateService                       $dateService,
        private readonly ImageConfiguration                $imageConfiguration,
        private readonly ImageService                      $imageService,
        private readonly KeywordService                    $keywordService,
        private readonly MailerInterface                   $mailer,
        private readonly MonologLogger                     $logger,
        private readonly NetworkRepository                 $networkRepository,
        private readonly ProviderService                   $providerService,
        private readonly SeriesBroadcastDateRepository     $seriesBroadcastDateRepository,
        private readonly SeriesBroadcastScheduleRepository $seriesBroadcastScheduleRepository,
        private readonly SeriesImageRepository             $seriesImageRepository,
        private readonly SeriesRepository                  $seriesRepository,
        private readonly SeriesService                     $seriesService,
        private readonly SettingsAdvancedDbSearchService   $settingsAdvancedDbSearchService,
        private readonly SettingsRepository                $settingsRepository,
        private readonly TMDBService                       $tmdbService,
        private readonly TranslatorInterface               $translator,
        private readonly UserEpisodeRepository             $userEpisodeRepository,
        private readonly UserSeriesRepository              $userSeriesRepository,
    )
    {
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/', name: 'index')]
    public function index(#[CurrentUser] User $user): Response
    {
        $locale = $user->getPreferredLanguage() ?? 'fr';
        $now = $this->now();
        $today = $now->format('Y-m-d');
        $this->seriesService->syncSeasonsFromEpisodesOne($user, true, $today, $this->dateModify($now, '+7 days')->format('Y-m-d'));

        $logoUrl = $this->imageConfiguration->getUrl('logo_sizes', 4);
        $posterUrl = $this->imageConfiguration->getUrl('poster_sizes', 5);

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
            $ue['watch_providers'] = $ue['provider_id'] ? [['logo_path' => $this->providerService->getProviderLogoFullPath($ue['provider_logo_path'], $logoUrl), 'provider_name' => $ue['provider_name']]] : [];
            return $ue;
        }, $this->userEpisodeRepository->episodesOfTheDay($user, $locale));

        $tmdbIds = array_column($AllEpisodesOfTheDay, 'tmdb_id');

        $todayEpisodes = array_filter($AllEpisodesOfTheDay, function ($e) use ($today) {
            return $e['date'] == $today;
        });
        $episodesOfTheDay = [];
        foreach ($todayEpisodes as $us) {
            $episodesOfTheDay[$us['date'] . '-' . $us['id']][] = $us;
        }
        $next7dDaysEpisodes = array_filter($AllEpisodesOfTheDay, function ($e) use ($today) {
            return $e['date'] > $today;
        });
        $seriesOfTheWeek = [];
        foreach ($next7dDaysEpisodes as $us) {
            $seriesOfTheWeek[$us['date'] . '-' . $us['id']][] = $us;
        }

        return $this->render('series/index.html.twig', [
            'episodesOfTheDay' => $episodesOfTheDay,
            'seriesOfTheWeek' => $seriesOfTheWeek,
            'tmdbIds' => $tmdbIds,
            'userSeriesCount' => $this->userSeriesRepository->count(['user' => $user])
        ]);
    }

    #[Route('/to/start', name: 'to_start')]
    public function start(#[CurrentUser] User $user, Request $request): Response
    {
        $locale = $user->getPreferredLanguage() ?? $request->getLocale();
        $sort = $request->query->get('sort', 'firstAirDate');
        $sort = match ($sort) {
            'addedAt' => 'addedAt',
            default => 'firstAirDate',
        };
        $order = $request->query->get('order', 'DESC');
        $order = match ($order) {
            'ASC' => 'ASC',
            default => 'DESC',
        };

        $posterUrl = $this->imageConfiguration->getUrl('poster_sizes', 5);
        $logoUrl = $this->imageConfiguration->getUrl('logo_sizes', 3);
        $seriesToStart = array_map(function ($s) use ($posterUrl, $logoUrl) {
            $this->imageService->saveImage("posters", $s['poster_path'], $posterUrl);
            $s['provider_logo_path'] = $this->providerService->getProviderLogoFullPath($s['provider_logo_path'], $logoUrl);
            return $s;
        }, $this->userSeriesRepository->seriesToStart($user, $locale, $sort, $order));
        $tmdbIds = array_column($seriesToStart, 'tmdb_id');

        $series = [];
        // Plusieurs résultats pour une même série, à cause de différents liens de streaming (link_name, provider_logo_path, "provider_name)
        // On ne garde que le premier résultat pour chaque série et on ajoute les providers dans un tableau "watch_links".
        foreach ($seriesToStart as $s) {
            if (!array_key_exists($s['id'], $series)) {
                $series[$s['id']]['url'] = $this->generateUrl('app_tv_series', ['id' => $s['id'], 'slug' => $s['slug']]);
                $series[$s['id']]['tmdb_id'] = $s['tmdb_id'];
                $series[$s['id']]['name'] = $s['name'];
                $series[$s['id']]['sln_name'] = $s['sln_name'];
                $series[$s['id']]['poster_path'] = $s['poster_path'];
                $series[$s['id']]['final_air_date'] = $s['final_air_date'];
                $series[$s['id']]['added_at'] = $s['added_at'];
                $series[$s['id']]['number_of_episode'] = $s['number_of_episode'];
                $series[$s['id']]['is_series_in_list'] = $s['is_series_in_list'];
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
    public function continue(#[CurrentUser] User $user, Request $request): Response
    {
        $locale = $user->getPreferredLanguage() ?? $request->getLocale();

        $inAWhileDate = $this->dateModify($this->now(), '-15 days')->format('Y-m-d');
        $series = array_map(function ($s) {
            $this->imageService->saveImage("posters", $s['poster_path'], $this->imageConfiguration->getUrl('poster_sizes', 5));
            return $s;
        }, $this->userSeriesRepository->seriesNotSeenInAWhile($user, $locale, $inAWhileDate));
        $tmdbIds = array_column($series, 'tmdb_id');

        return $this->render('series/series_in_a_while.html.twig', [
            'seriesInAWhile' => $series,
            'tmdbIds' => $tmdbIds,
            'userSeriesCount' => $this->userSeriesRepository->count(['user' => $user])
        ]);
    }

    #[Route('/up/coming', name: 'up_coming')]
    public function upComingSeries(#[CurrentUser] User $user, Request $request): Response
    {
        $locale = $user->getPreferredLanguage() ?? $request->getLocale();

        $series = array_map(function ($s) {
            $this->imageService->saveImage("posters", $s['poster_path'], $this->imageConfiguration->getUrl('poster_sizes', 5));
            return $s;
        }, $this->userSeriesRepository->upComingSeries($user, $locale, 'firstAirDate'));
        $tmdbIds = array_column($series, 'tmdb_id');

        return $this->render('series/up_coming_series.html.twig', [
            'seriesList' => $series,
            'tmdbIds' => $tmdbIds,
            'userSeriesCount' => $this->userSeriesRepository->count(['user' => $user])
        ]);
    }

    #[Route('/ranking/vote', name: 'ranking_by_vote')]
    public function rankingByVote(#[CurrentUser] User $user, Request $request): Response
    {
        $locale = $user->getPreferredLanguage() ?? $request->getLocale();

        $arr = $this->userSeriesRepository->rankingByVote($user, $locale);
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
    public function favorite(#[CurrentUser] User $user, Request $request): Response
    {
        $locale = $user->getPreferredLanguage() ?? $request->getLocale();

        $series = array_map(function ($s) {
            $this->imageService->saveImage("posters", $s['poster_path'], $this->imageConfiguration->getUrl('poster_sizes', 5));
            $this->imageService->saveImage("backdrops", $s['backdrop_path'], $this->imageConfiguration->getUrl('backdrop_sizes', 3));
            return $s;
        }, $this->userSeriesRepository->favoriteSeries($user, $locale));

        $tmdbIds = array_column($series, 'tmdb_id');

        return $this->render('series/favorite.html.twig', [
            'seriesList' => $series,
            'tmdbIds' => $tmdbIds,
            'userSeriesCount' => $this->userSeriesRepository->count(['user' => $user])
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/by/country/{country}', name: 'by_country', requirements: ['country' => '[A-Z]{2}'])]
    public function seriesByCountry(#[CurrentUser] User $user, Request $request, string $country): Response
    {
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
            } else {
                $s['sln_name'] = $s['name'];
            }
            $this->imageService->saveImage("posters", $s['poster_path'], $this->imageConfiguration->getUrl('poster_sizes', 5));
            $s['upToDate'] = $s['watched_aired_episode_count'] == $s['aired_episode_count'];
            return $s;
        }, $this->userSeriesRepository->seriesByCountry($user, $country, $locale));
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

    #[IsGranted('ROLE_USER')]
    #[Route('/by/privider/{provider}', name: 'by_provider', requirements: ['provider' => Requirement::DIGITS])]
    public function seriesByProvider(#[CurrentUser] User $user, Request $request, int $provider): Response
    {
        $locale = $user->getPreferredLanguage() ?? $request->getLocale();
        $page1 = $request->query->get('p1', 1);
        $page2 = $request->query->get('p2', 1);
        $tab = $request->query->get('t', 1);
        $limit = 50;

        $settings = $this->settingsRepository->findOneBy(['user' => $user, 'name' => 'by provider']);
        if (!$settings) {
            $settings = new Settings($user, 'by provider', ['provider' => $provider]);
        } else {
            $data = $settings->getData();
            $data['provider'] = $provider;
            $settings->setData($data);
        }
        $this->settingsRepository->save($settings, true);

        $providers = $this->providerService->get($user);
        $seriesByProvider = $this->providerService->seriesByProvider($user, $provider, $locale, $page1, $limit);
        $seriesWithoutProvider = $this->providerService->seriesWithoutProviders($user, $locale, $page2, $limit);

        $selectedProvider = array_find($providers['userProviders'], function ($p) use ($provider) {
            return $p['provider_id'] == $provider;
        });
        $selectedProvider['logo_path'] = $this->providerService->getProviderLogoFullPath($selectedProvider['logo_path'], $this->imageConfiguration->getUrl('logo_sizes', 5));

        $tmdbIds = array_column($seriesByProvider['results'], 'tmdb_id');

        return $this->render('series/series_by_provider.html.twig', [
            'tab' => $tab,
            'provider' => $provider,
            'selectedProvider' => $selectedProvider,
            'tmdbIds' => $tmdbIds,
            'providers' => $providers,
            'seriesWithoutProvider' => $seriesWithoutProvider,
            'seriesByProvider' => $seriesByProvider,
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
        $simpleSeriesSearch = new SeriesSearchDTO($request->getLocale(), 1);
        if ($request->query->get('q')) $simpleSeriesSearch->setQuery($request->query->get('q'));
        $simpleForm = $this->createForm(SeriesSearchType::class, $simpleSeriesSearch);
        $searchResult = $this->handleSearch($simpleSeriesSearch);
        $slugger = new AsciiSlugger();
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
        $page = $simpleSeriesSearch->getPage();
        $firstAirDateYear = $simpleSeriesSearch->getFirstAirDateYear();

        $searchString = "query=$query&include_adult=false&page=$page";
        if ($firstAirDateYear && strlen($firstAirDateYear)) $searchString .= "&first_air_date_year=$firstAirDateYear";

        return json_decode($this->tmdbService->searchTv($searchString), true);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/all', name: 'all', methods: ['GET', 'POST'])]
    public function all(#[CurrentUser] User $user, Request $request): Response
    {
        $localisation = [
            'locale' => $user->getPreferredLanguage() ?: $request->getLocale(),
            'country' => $user->getCountry() ?: "FR",
            'language' => $user->getPreferredLanguage() ?: $request->getLocale(),
            'timezone' => $user->getTimezone() ?: "Europe/Paris"
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
                $settings = new Settings($user, 'series to end', ['limit' => 10, 'sort' => 'lastWatched', 'order' => 'DESC', 'network' => 'all']);
                $this->settingsRepository->save($settings, true);
            }
        } else {
            // /fr/series/all?sort=episodeAirDate&order=DESC&startStatus=series-not-started&endStatus=series-not-watched&limit=10
            $paramSort = $request->query->get('sort');
            $paramOrder = $request->query->get('order');
            $paramNetwork = $request->query->get('network');
            $paramLimit = $request->query->get('limit');
            $settings->setData([
                'limit' => $paramLimit,
                'sort' => $paramSort,
                'order' => $paramOrder,
                'network' => $paramNetwork
            ]);
            $this->settingsRepository->save($settings, true);
            $page = $request->query->get('page') ?? 1;
        }
        $data = $settings->getData();
        $filters = [
            'page' => $page,
            'limit' => $data['limit'],
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

        $tmdbIds = array_column($userSeries, 'tmdbId');

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
            'pages' => ceil($userSeriesCount / $filters['limit']),
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
    public function searchDB(#[CurrentUser] User $user, Request $request): Response
    {
        $series = [];
        $searchResult = ['total_results' => 0, 'total_pages' => 0, 'page' => 0];
        $simpleSeriesSearch = new SeriesSearchDTO($request->getLocale(), 1);
        $simpleForm = $this->createForm(SeriesSearchType::class, $simpleSeriesSearch);

        $simpleForm->handleRequest($request);
        if ($simpleForm->isSubmitted() && $simpleForm->isValid()) {
            $query = $simpleSeriesSearch->getQuery();
            $page = $simpleSeriesSearch->getPage();
            $firstAirDateYear = $simpleSeriesSearch->getFirstAirDateYear();
            $series = $this->getDbSearchResult($user, $query, $page, $firstAirDateYear);
            $count = $this->seriesRepository->searchCount($user, $query, null);
            $searchResult['total_results'] = $count['count'];
            $searchResult['total_pages'] = ceil($searchResult['total_results'] / 20);
            $searchResult['page'] = $page;
        }

        if (count($series) == 1) {
            return $this->redirectToRoute('app_tv_series', [
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
                'total_results' => $searchResult['total_results'],
                'total_pages' => $searchResult['total_pages'],
                'page' => $searchResult['page'],
            ],
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/db/advanced/search', name: 'advanced_search_db')]
    public function advancedDbSearch(#[CurrentUser] User $user, Request $request): Response
    {
        $searchResult = ['page' => 1];
        $userSeriesIds = array_column($this->userSeriesRepository->userSeriesTMDBIds($user), 'id');

        $countries = $this->getAdvancedSearchCountries($user, $request->getLocale());

        $slugger = new AsciiSlugger();

        $advancedDisplaySettings = $this->settingsRepository->findOneBy(['user' => $user, 'name' => 'advanced db search display']);
        if (!$advancedDisplaySettings) {
            $advancedDisplaySettings = new Settings($user, 'advanced db search display', ['dates' => true, 'origin' => false, 'provider' => true, 'keywords' => true, 'status' => true]);
            $this->settingsRepository->save($advancedDisplaySettings, true);
        }
        $displaySettings = $advancedDisplaySettings->getData();

        $seriesSearch = $this->settingsAdvancedDbSearchService->get($user);
        $form = $this->createForm(SeriesAdvancedDbSearchType::class, $seriesSearch, ['countries' => $countries]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $seriesSearch = $this->settingsAdvancedDbSearchService->update($user, $form->getData());
            $searchResult['results'] = $this->seriesRepository->advancedDbSearch($user, $seriesSearch);
            $searchResult['total_results'] = $t = $this->seriesRepository->advancedDbSearchCount($user, $seriesSearch);
            $searchResult['total_pages'] = ceil($t / 20);

            if ($searchResult['total_results'] == 1) {
                return $this->getOneResult($searchResult['results'][0], $slugger);
            }
        } else {
            $searchResult['results'] = $this->seriesRepository->advancedDbSearch($user, $seriesSearch);
            $searchResult['total_results'] = $t = $this->seriesRepository->advancedDbSearchCount($user, $seriesSearch);
            $searchResult['total_pages'] = ceil($t / 20);
        }
        $series = $this->getAdvancedDbSearchResult($searchResult, $slugger);

        return $this->render('series/advanced_db_search.html.twig', [
            'form' => $form->createView(),
            'displaySettings' => ['id' => $advancedDisplaySettings->getId(), 'data' => $displaySettings],
            'seriesList' => $series,
            'userSeriesIds' => $userSeriesIds,
            'results' => $searchResult,
            'searchDetails' => $this->settingsAdvancedDbSearchService->getSearchDetails($seriesSearch),
        ]);
    }

    public function getDbSearchResult(User $user, string $query, int $page, ?int $firstAirDateYear): array
    {
        return array_map(function ($s) {
            $s['poster_path'] = $s['poster_path'] ? $this->imageConfiguration->getUrl('poster_sizes', 5) . $s['poster_path'] : null;
            return $s;
        }, $this->seriesRepository->search($user, $query, $firstAirDateYear, $page));
    }

    #[Route('/advanced/search', name: 'advanced_search')]
    public function advancedSearch(#[CurrentUser] User $user, Request $request): Response
    {
        $series = [];
        $watchProviders = $this->watchLinkApi->getWatchProviders($user->getCountry() ?: 'FR');
        $keywords = $this->keywordService->getKeywords();
        $userSeriesIds = array_column($this->userSeriesRepository->userSeriesTMDBIds($user), 'id');

        $seriesSearch = new SeriesAdvancedSearchDTO($user->getPreferredLanguage() ?: $request->getLocale(), $user->getCountry() ?: 'FR', $user->getTimezone() ?: 'Europe/Paris', 1);
        $seriesSearch->setWatchProviders($watchProviders['select']);
        $seriesSearch->setKeywords($keywords);
        $slugger = new AsciiSlugger();

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

        return $this->render('series/advanced_search.html.twig', [
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

    #[IsGranted('ROLE_USER')]
    #[Route('/list/{id}/{seriesId}', name: 'list', requirements: ['id' => Requirement::DIGITS, 'showId' => Requirement::DIGITS])]
    public function list(#[CurrentUser] User $user, Request $request, int $id, int $seriesId): Response
    {
        $page = $request->query->get('page') ?? 1;
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
    #[Route('/add/{id}', name: 'add', requirements: ['id' => Requirement::DIGITS])]
    public function add(#[CurrentUser] User $user, int $id): Response
    {
        $date = $this->now();
        $locale = $user->getPreferredLanguage() ?: 'fr';
        $language = $locale . '-' . ($user->getCountry() ?: 'FR');

        $result = $this->addSeries($id, $date, $locale, $language);
        $tv = $result['tv'];
        $series = $result['series'];
        $localizedName = $result['localizedName'];
        $localizedSlug = $result['localizedSlug'];
        $localizedOverview = $result['localizedOverview'];

        $userSeries = $this->addSeriesToUser($user, $series, $tv, $date);
        $this->sendMail('Nouvelle série', $this->prepareMail($userSeries, $localizedName, $localizedOverview), $userSeries);

        $firstAirDate = $tv['first_air_date'] ? $this->dateService->newDateImmutable($tv['first_air_date'], 'Europe/Paris', true) : null;
        $oldSeries = false;
        $nowYear = $this->now()->format('Y');
        $firstAirDateYear = $firstAirDate?->format('Y');

        if ($firstAirDateYear) {
            if ($nowYear - $firstAirDateYear > 2) {
                $oldSeries = true;
            }
        }

        return $this->redirectToRoute('app_tv_series', [
            'id' => $series->getId(),
            'slug' => $localizedSlug ?: $series->getSlug(),
            'oldSeriesAdded' => $oldSeries,
        ]);
    }

    #[Route('/old/{id}', name: 'old', requirements: ['id' => Requirement::DIGITS])]
    public function markAllEpisodeAsViewed(#[CurrentUser] User $user, Series $series): Response
    {
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
        $seriesBroadcastSchedule->setAirAt(new DateTime()->setTime($hour, $minute));
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
            $userEpisodes = $this->userEpisodeRepository->findBy(['userSeries' => $userSeries, 'seasonNumber' => $seasonNumber, 'previousOccurrence' => null], ['seasonNumber' => 'ASC', 'episodeNumber' => 'ASC']);

            $tv = json_decode($this->tmdbService->getTv($seriesBroadcastSchedule->getSeries()->getTmdbId(), 'fr-FR', ['seasons']), true);
            $as = $this->seriesService->getAlternateSchedule($seriesBroadcastSchedule, $tv['seasons'], $userEpisodes);

            $airDays = $as['airDays'];
            foreach ($airDays as $airDay) {
                if (!$airDay['episodeId']) {    // Diffusion hebdomadaire : "Plusieurs épisodes, puis un"
                    continue;                   // alors qu'on dispose d'infos TMDB uniquement pour le premier épisode.
                }
                if (!$previousOverrideStatus) {
                    $seriesBroadcastDate = new SeriesBroadcastDate($seriesBroadcastSchedule, $airDay['episodeId'], $seasonNumber, $seasonPart, $airDay['episodeNumber'], $airDay['date']);
                } else {
                    $seriesBroadcastDate = $this->seriesBroadcastDateRepository->findOneBy(['episodeId' => $airDay['episodeId']]);
                    if (!$seriesBroadcastDate) {
                        $seriesBroadcastDate = new SeriesBroadcastDate($seriesBroadcastSchedule, $airDay['episodeId'], $seasonNumber, $seasonPart, $airDay['episodeNumber'], $airDay['date']);
                    } else {
                        $seriesBroadcastDate->setDate($airDay['date']);
                    }
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

        return new JsonResponse([
            'ok' => true,
            'success' => true,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/schedules/convert', name: 'schedule_convert', methods: ['POST'])]
    public function convertDate(#[CurrentUser] User $user, Request $request): JsonResponse
    {
        $inputBag = $request->getPayload();

        $date = $inputBag->get('date');
        $time = $inputBag->get('time');
        $timezone = $inputBag->get('timezone');
        $userTimezone = $user->getTimezone() ?? 'Europe/Paris';

        $dateTime = $this->convertDateTime($date, $time, $timezone, $userTimezone);
        if (!$dateTime) {
            return new JsonResponse([
                'ok' => false,
                'success' => false,
                'message' => 'Invalid date or timezone',
            ]);
        }

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

    private function convertDateTime(string $date, string $time, string $fromTimezone, string $toTimezone): ?DateTimeImmutable
    {
        try {
            $dateTime = new DateTime($date . ' ' . $time, new DateTimeZone($fromTimezone));
        } catch (DateInvalidTimeZoneException|DateMalformedStringException) {
            return null;
        }
        if ($fromTimezone != $toTimezone) {
            try {
                $dateTime->setTimezone(new DateTimeZone($toTimezone));
            } catch (DateInvalidTimeZoneException) {
                return null;
            }
        }
        return DateTimeImmutable::createFromMutable($dateTime);
    }

    #[Route('/overview/{id}', name: 'get_overview', methods: 'GET')]
    public function getOverview(Request $request, int $id): Response
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

    #[Route('/images/get/{id}', name: 'get_images', requirements: ['id' => Requirement::DIGITS], methods: 'POST')]
    public function getAllImages(int $id): Response
    {
        $images = json_decode($this->tmdbService->getAllTvImages($id), true);
        $backdrops = $images['backdrops'];
        $logos = $images['logos'];
        $posters = $images['posters'];

        return $this->json([
            'ok' => true,
            'success' => true,
            'backdrops' => $backdrops,
            'backdropUrl' => $this->imageConfiguration->getUrl('backdrop_sizes', 2),
            'logos' => $logos,
            'logoUrl' => $this->imageConfiguration->getUrl('logo_sizes', 2),
            'posters' => $posters,
            'posterUrl' => $this->imageConfiguration->getUrl('poster_sizes', 2),
        ]);
    }

    #[Route('/images/add', name: 'add_images', methods: 'POST')]
    public function addAllBackdrops(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);

        $tmdbId = $data['seriesId'];
        $method = $data['method'] ?? 'all';
        $series = $this->seriesRepository->findOneBy(['tmdbId' => $tmdbId]);
        $images = $series->getSeriesImages()->toArray();

        if ($method === 'all') {
            $backdrops = $data['backdrops'];
            $logos = $data['logos'];
            $posters = $data['posters'];

            $backdropUrl = $this->imageConfiguration->getUrl('backdrop_sizes', 3);
            $logoUrl = $this->imageConfiguration->getUrl('logo_sizes', 5);
            $posterUrl = $this->imageConfiguration->getUrl('poster_sizes', 5);

            $addedBackdropCount = 0;
            $addedLogoCount = 0;
            $addedPosterCount = 0;

            foreach ($backdrops as $backdrop) {
                if (!$this->seriesService->inImages($backdrop['file_path'], $images)) {
                    $seriesImage = new SeriesImage($series, "backdrop", $backdrop['file_path']);
                    $this->seriesImageRepository->save($seriesImage);
                    $this->imageService->saveImage("backdrops", $backdrop['file_path'], $backdropUrl);
                    $addedBackdropCount++;
                }
            }

            foreach ($logos as $logo) {
                if (!$this->seriesService->inImages($logo['file_path'], $images)) {
                    $seriesImage = new SeriesImage($series, "logo", $logo['file_path']);
                    $this->seriesImageRepository->save($seriesImage);
                    $this->imageService->saveImage("logos", $logo['file_path'], $logoUrl);
                    $addedLogoCount++;
                }
            }

            foreach ($posters as $poster) {
                if (!$this->seriesService->inImages($poster['file_path'], $images)) {
                    $seriesImage = new SeriesImage($series, "poster", $poster['file_path']);
                    $this->seriesImageRepository->save($seriesImage);
                    $this->imageService->saveImage("posters", $poster['file_path'], $posterUrl);
                    $addedPosterCount++;
                }
            }

            if ($addedBackdropCount + $addedLogoCount + $addedPosterCount > 0) {
                $this->seriesImageRepository->flush();
            }
        } else {
            $image = $data['image'];
            $type = $data['type']; // "backdrop", "logo" or "poster"
            $imagePath = $image['file_path'];
            if (!$this->seriesService->inImages($imagePath, $images)) {
                $seriesImage = new SeriesImage($series, $type, $imagePath);
                $this->seriesImageRepository->save($seriesImage, true);
                if ($type === 'backdrop') {
                    $this->imageService->saveImage("backdrops", $imagePath, $this->imageConfiguration->getUrl('backdrop_sizes', 3));
                    $addedBackdropCount = 1;
                    $addedLogoCount = 0;
                    $addedPosterCount = 0;
                } else if ($type === 'logo') {
                    $this->imageService->saveImage("logos", $imagePath, $this->imageConfiguration->getUrl('logo_sizes', 5));
                    $addedBackdropCount = 0;
                    $addedLogoCount = 1;
                    $addedPosterCount = 0;
                } else {
                    $this->imageService->saveImage("posters", $imagePath, $this->imageConfiguration->getUrl('poster_sizes', 5));
                    $addedBackdropCount = 0;
                    $addedLogoCount = 0;
                    $addedPosterCount = 1;
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
            'addedLogos' => $addedLogoCount,
            'addedPosters' => $addedPosterCount,
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
        $messages = [];

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
            $tv = json_decode($this->tmdbService->getTv($series->getTmdbId(), $locale, ['images', 'keywords']), true);
            $tmdbCalls++;
            if ($tv == null || isset($tv['error'])) {
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
            $messages = array_merge($messages, $this->keywordService->saveKeywords($tv['keywords']['results'], 'api'));
            $updateSeries = $this->seriesService->updateSeries($series, $tv, []);
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
            'messages' => $messages,
            'dbSeriesCount' => $dbSeriesCount,
            'tmdbCalls' => $tmdbCalls,
        ]);
    }

    public function addSeries(int $id, DateTimeImmutable $date, string $locale, string $language): array
    {
        $series = $this->seriesRepository->findOneBy(['tmdbId' => $id]);

        $slugger = new AsciiSlugger();
        $tv = json_decode($this->tmdbService->getTv($id, $language, ['translations']), true);

        if (!$series) $series = new Series();
        $series->setBackdropPath($tv['backdrop_path']);
        $series->setCreatedAt($date);
        $series->setFirstAirDate($tv['first_air_date'] ? $this->dateService->newDateImmutable($tv['first_air_date'], "Europe/Paris", true) : null);
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

        $localization = $this->localizeSeries($tv);
        $this->seriesService->localizeSeries($series, $localization, $locale);

        return [
            'series' => $this->seriesRepository->findOneBy(['tmdbId' => $id]),
            'localizedName' => $localization['localizedName'],
            'localizedSlug' => $localization['localizedSlug'],
            'localizedOverview' => $localization['localizedOverview'],
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
        }

        foreach ($tv['seasons'] as $season) {
            $this->seriesService->addSeasonToUser($user, $userSeries, $season['season_number'], []);
        }
        return $userSeries;
    }

    public function prepareMail(UserSeries $userSeries, ?string $localizedName, ?string $localizedOverview): ContactMessage
    {
        $user = $userSeries->getUser();
        $series = $userSeries->getSeries();
        $sln = $series->getLocalizedName($user->getPreferredLanguage() ?: 'fr');
        $message = new ContactMessage();
        $message->setName($user->getUsername());
        $message->setEmail($user->getEmail());
        $message->setSubject('Nouvel ajout par ' . $user->getUsername() . "(" . $user->getId() . ")");
        $message->setMessage(
            "IDs : " . $series->getId() . " / " . $userSeries->getId() . "\n\n"
            . "Nom : " . $series->getName() . ($localizedName ? " - " . $localizedName : '') . "\n\n"
            . ($sln ? "Nom localisé : " . $series->getLocalizedName($user->getPreferredLanguage() ?: 'fr')->getName() . "\n\n" : "")
            . "Résumé : " . $localizedOverview ?: $series->getOverview() . "\n\n"
        );

        return $message;
    }

    public function sendMail(string $title, ContactMessage $message, UserSeries $userSeries): void
    {
        $this->contactMessageRepository->save($message, true);

        $mail = new TemplatedEmail()
            ->from($message->getEmail())
            ->to('contact@mytvtime.fr')
            ->subject($this->translator->trans('Contact form') . ' - ' . $message->getSubject())
            ->htmlTemplate('emails/contact.html.twig')
            ->context([
                'title' => $title,
                'data' => $message,
            ])
            ->locale('fr');
        try {
            $this->mailer->send($mail);
        } catch (TransportExceptionInterface) {
            $this->logger->error("Error sending email (serie {$userSeries->getSeries()->getTmdbId()}) new addition by {$userSeries->getUser()->getUsername()})");
        }
    }

    public function localizeSeries(array $tv): array
    {
        $localizedName = null;
        $localizedSlug = null;
        $localizedOverview = null;
        $translations = array_find($tv['translations']['translations'], function ($item) {
            return $item['iso_639_1'] == 'en';
        });
        if ($translations) {
            $localizedName = $this->seriesService->getLatinPart($tv['name']);
            if ($this->seriesService->hasNoLatinChars($tv['name'])) {
                $localizedName = $translations['data']['name'];
            }
            $localizedOverview = $translations['data']['overview'];
            $localizedSlug = new AsciiSlugger()->slug($localizedName)->lower()->toString();
        }
        return [
            'localizedName' => $localizedName,
            'localizedOverview' => $localizedOverview,
            'localizedSlug' => $localizedSlug,
        ];
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
            $series['sln_name'] = $series['localizedName'] ?: $series['name'];
            $series['providerLogoPath'] = $this->providerService->getProviderLogoFullPath($series['providerLogoPath'], $logoUrl);
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

    public function getSearchString(?User $user, SeriesAdvancedSearchDTO $data): string
    {
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

    public function getSearchResult(array $searchResult, AsciiSlugger $slugger): array
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
            $tv['slug'] = $slug;

            return $tv;
        }, $searchResult['results'] ?? []);
    }

    public function getAdvancedDbSearchResult(array $searchResult, AsciiSlugger $slugger): array
    {
        return array_map(function ($tv) use ($slugger) {
            $this->imageService->saveImage("posters", $tv['poster_path'], $this->imageConfiguration->getUrl('poster_sizes', 5));
            $tv['poster_path'] = $tv['poster_path'] ? '/series/posters' . $tv['poster_path'] : null;

            $name = $tv['name'];
            $display_name = $tv['display_name'];
            $slug = $slugger->slug($display_name ?: $name)->lower()->toString();

            return [
                'tmdb' => true,
                'id' => $tv['id'],
                'name' => $name,
                'display_name' => $display_name,
                'air_date' => $tv['first_air_date'],
                'slug' => $slug,
                'poster_path' => $tv['poster_path'],
            ];
        }, $searchResult['results'] ?? []);
    }

    public function getAdvancedSearchCountries(User $user, string $locale): array
    {
        $codes = $this->seriesRepository->advancedSearchCountries($user); // ex: ['FR','TH',...]
        $choices = [];

        foreach ($codes as $code) {
            $choices[Countries::getName($code)] = $code; // 'France' => 'FR'
        }

        $userLocale = strtolower($user->getPreferredLanguage() ?? $locale);
        $userRegion = strtoupper($user->getCountry() ?? $this->getDefaultRegion($locale));
        $collator = new Collator($userLocale . '_' . $userRegion);
        /*if (!$collator) {
            $collator = new Collator('en_US'); // fallback
        }*/
        $collator->setStrength(Collator::PRIMARY); // ignore accents + casse

        uksort($choices, function (string $a, string $b) use ($collator): int {
            return $collator->compare($a, $b);
        });

        // Optionnel : ajouter un choix "vide" en haut
        return ['' => ''] + $choices;
    }

    private function getDefaultRegion(string $locale): string
    {
        // $locale: 'fr|en|ko'
        return match ($locale) {
            'en' => 'US',
            'ko' => 'KR',
            default => 'FR',
        };
    }

    public function getOneResult(array $tv, AsciiSlugger $slugger): Response
    {
        return $this->redirectToRoute('app_tv_tmdb', [
            'id' => $tv['id'],
            'slug' => $slugger->slug($tv['name'])->lower()->toString(),
        ]);
    }

    public function getProjectDir(): string
    {
        return $this->getParameter('kernel.project_dir');
    }
}
