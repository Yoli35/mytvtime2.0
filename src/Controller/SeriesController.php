<?php

namespace App\Controller;

use App\Api\ApiWatchLink;
use App\DTO\SeriesAdvancedSearchDTO;
use App\DTO\SeriesSearchDTO;
use App\Entity\ContactMessage;
use App\Entity\FilmingLocation;
use App\Entity\Series;
use App\Entity\SeriesBroadcastDate;
use App\Entity\SeriesBroadcastSchedule;
use App\Entity\SeriesExternal;
use App\Entity\SeriesImage;
use App\Entity\SeriesLocalizedName;
use App\Entity\SeriesVideo;
use App\Entity\Settings;
use App\Entity\User;
use App\Entity\UserEpisode;
use App\Entity\UserList;
use App\Entity\UserSeries;
use App\Form\AddBackdropType;
use App\Form\SeriesAdvancedSearchType;
use App\Form\SeriesSearchType;
use App\Form\SeriesVideoType;
use App\Repository\ContactMessageRepository;
use App\Repository\DeviceRepository;
use App\Repository\EpisodeStillRepository;
use App\Repository\FilmingLocationRepository;
use App\Repository\KeywordRepository;
use App\Repository\NetworkRepository;
use App\Repository\PeopleUserPreferredNameRepository;
use App\Repository\SeasonLocalizedOverviewRepository;
use App\Repository\SeriesBroadcastDateRepository;
use App\Repository\SeriesBroadcastScheduleRepository;
use App\Repository\SeriesCastRepository;
use App\Repository\SeriesExternalRepository;
use App\Repository\SeriesImageRepository;
use App\Repository\SeriesRepository;
use App\Repository\SeriesVideoRepository;
use App\Repository\SettingsRepository;
use App\Repository\SourceRepository;
use App\Repository\UserEpisodeRepository;
use App\Repository\UserSeriesRepository;
use App\Repository\WatchProviderRepository;
use App\Service\DateService;
use App\Service\ImageConfiguration;
use App\Service\ImageService;
use App\Service\SeriesService;
use App\Service\TMDBService;
use DateInvalidTimeZoneException;
use DateMalformedStringException;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Psr\Log\LoggerInterface as MonologLogger;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
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
        private readonly ApiWatchLink                      $watchLinkApi,
        private readonly DateService                       $dateService,
        private readonly DeviceRepository                  $deviceRepository,
        private readonly EpisodeStillRepository            $episodeStillRepository,
        private readonly FilmingLocationRepository         $filmingLocationRepository,
        private readonly ImageConfiguration                $imageConfiguration,
        private readonly ImageService                      $imageService,
        private readonly KeywordRepository                 $keywordRepository,
        private readonly MonologLogger                     $logger,
        private readonly NetworkRepository                 $networkRepository,
        private readonly PeopleUserPreferredNameRepository $peopleUserPreferredNameRepository,
        private readonly SeasonLocalizedOverviewRepository $seasonLocalizedOverviewRepository,
        private readonly SeriesBroadcastDateRepository     $seriesBroadcastDateRepository,
        private readonly SeriesBroadcastScheduleRepository $seriesBroadcastScheduleRepository,
        private readonly SeriesCastRepository              $seriesCastRepository,
        private readonly SeriesExternalRepository          $seriesExternalRepository,
        private readonly SeriesImageRepository             $seriesImageRepository,
        private readonly SeriesRepository                  $seriesRepository,
        private readonly SeriesService                     $seriesService,
        private readonly SeriesVideoRepository             $seriesVideoRepository,
        private readonly SettingsRepository                $settingsRepository,
        private readonly SourceRepository                  $sourceRepository,
        private readonly TMDBService                       $tmdbService,
        private readonly TranslatorInterface               $translator,
        private readonly UserEpisodeRepository             $userEpisodeRepository,
        private readonly UserSeriesRepository              $userSeriesRepository,
        private readonly WatchProviderRepository           $watchProviderRepository,
        private readonly ContactMessageRepository          $contactMessageRepository,
        private readonly MailerInterface                   $mailer,
    )
    {
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/', name: 'index')]
    public function index(#[CurrentUser] User $user): Response
    {
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
            $s['provider_logo_path'] = $this->getProviderLogoFullPath($s['provider_logo_path'], $logoUrl);
            return $s;
        }, $this->userSeriesRepository->seriesToStart($user, $locale, $sort, $order, 1, -1));
        $tmdbIds = array_column($seriesToStart, 'tmdb_id');

        $series = [];
        // Plusieurs résultats pour une même série, à cause de différents liens de streaming (link_name, provider_logo_path, "provider_name)
        // On ne garde que le premier résultat pour chaque série et on ajoute les providers dans un tableau "watch_links".
        foreach ($seriesToStart as $s) {
            if (!array_key_exists($s['id'], $series)) {
                $series[$s['id']]['url'] = $this->generateUrl('app_series_show', ['id' => $s['id'], 'slug' => $s['slug']]);
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
        }, $this->userSeriesRepository->seriesNotSeenInAWhile($user, $locale, $inAWhileDate, 1, -1));
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
        }, $this->userSeriesRepository->upComingSeries($user, $locale, 'firstAirDate', 1, -1));
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
    public function favorite(#[CurrentUser] User $user, Request $request): Response
    {
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
                $settings = new Settings($user, 'series to end', ['perPage' => 10, 'sort' => 'lastWatched', 'order' => 'DESC', 'network' => 'all']);
                $this->settingsRepository->save($settings, true);
            }
        } else {
            // /fr/series/all?sort=episodeAirDate&order=DESC&startStatus=series-not-started&endStatus=series-not-watched&perPage=10
            $paramSort = $request->query->get('sort');
            $paramOrder = $request->query->get('order');
            $paramNetwork = $request->query->get('network');
            $paramPerPage = $request->query->get('perPage');
            $settings->setData([
                'perPage' => $paramPerPage,
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
    public function searchDB(#[CurrentUser] User $user, Request $request): Response
    {
        $series = [];
        $searchResult['total_results'] = 0;
        $searchResult['total_pages'] = 0;
        $searchResult['page'] = 0;
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
                'total_results' => $searchResult['total_results'],
                'total_pages' => $searchResult['total_pages'],
                'page' => $searchResult['page'],
            ],
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
        $keywords = $this->getKeywords();
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

    #[Route('/tmdb/{id}-{slug}', name: 'tmdb', requirements: ['id' => Requirement::DIGITS])]
    public function tmdb(#[CurrentUser] User $user, Request $request, $id, $slug): Response
    {
        $series = $this->seriesRepository->findOneBy(['tmdbId' => $id]);
        $userSeries = $series ? $this->userSeriesRepository->findOneBy(['user' => $user, 'series' => $series]) : null;
        $country = $user->getCountry() ?: 'FR';
        $locale = $user->getPreferredLanguage() ?: $request->getLocale();

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
            $tv['overview'] = $enTranslations['data']['overview'];
            $this->addFlash('info', 'The series overview is missing. "' . ($enTranslations['data']['overview'] ?? 'null') . '" found.');
        }
        $this->imageService->saveImage("posters", $tv['poster_path'], $this->imageConfiguration->getUrl('poster_sizes', 5));
        $this->imageService->saveImage("backdrops", $tv['backdrop_path'], $this->imageConfiguration->getUrl('backdrop_sizes', 3));
        $tv['blurredPosterPath'] = $this->imageService->blurPoster($tv['poster_path'], 'series', 8);

        $tv['credits'] = $this->castAndCrew($tv, $series);
        $tv['networks'] = $this->seriesService->networks($tv);
        $tv['seasons'] = $this->seriesService->seasonsPosterPath($tv['seasons']);
        $tv['watch/providers'] = $this->watchProviders($tv, 'FR');
        $tv['translations'] = $this->seriesService->getTranslations($tv['translations']['translations'], $country, $locale);
        $translatedName = $tv['translations']['data']['name'] ?? null;
        $c = count($tv['episode_run_time']);
        $tv['average_episode_run_time'] = $c ? array_reduce($tv['episode_run_time'], function ($carry, $item) {
                return $carry + $item;
            }, 0) / $c : 0;

        return $this->render('series/tmdb.html.twig', [
            'tv' => $tv,
            'localizedName' => $localizedName,
            'translatedName' => $translatedName,
            'localizedOverview' => $localizedOverview,
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
    #[Route('/show/{id}-{slug}', name: 'show', requirements: ['id' => Requirement::DIGITS])]
    public function show(#[CurrentUser] User $user, Request $request, Series $series, string $slug): Response
    {
        $locale = $user->getPreferredLanguage() ?? $request->getLocale();
        $country = $user->getCountry() ?? 'FR';

        $forms = $this->handleSerieShowForms($request, $series);

        $userSeries = $this->userSeriesRepository->findOneBy(['user' => $user, 'series' => $series]);
        $userEpisodes = $this->userEpisodeRepository->findBy(['userSeries' => $userSeries, 'previousOccurrence' => null], ['seasonNumber' => 'ASC', 'episodeNumber' => 'ASC']);
        $this->adjustNextEpisodeToWatch($userSeries, $userEpisodes);

        $seriesAround = $this->seriesService->getSeriesAround($user->getId(), $userSeries->getId(), $locale);

        $blurredPosterPath = $this->imageService->blurPoster($series->getPosterPath(), 'series', 8);

        $series->setUpdates([]);
        $seriesArr = $series->toArray();
        $seriesArr['blurredPosterPath'] = $blurredPosterPath;

        $seriesArr['userLists'] = $series->getUserLists()->filter(function (UserList $ul) use ($user) {
            return $ul->getUser() === $user;
        })->toArray();

        $this->checkSlug($series, $slug, $locale);

        $tv = $this->seriesService->getTv($series, $country, $locale);

        if (!$tv) {
            $series->setUpdates(['Series not found']);
            $noTv['additional_overviews'] = $series->getSeriesAdditionalLocaleOverviews($locale);
            $noTv['credits'] = $this->castAndCrew($tv, $series);
            $noTv['localized_name'] = $series->getLocalizedName($locale);
            $noTv['localized_overviews'] = $series->getLocalizedOverviews($locale);
            $noTv['seasons'] = $this->getUserSeasons($series, $userEpisodes);
            $noTv['sources'] = $this->sourceRepository->findBy([], ['name' => 'ASC']);
            $noTv['average_episode_run_time'] = 0;

            return $this->render("series/show_not_found.html.twig", [
                'series' => $seriesArr,
                'userSeries' => $userSeries,
                'previousSeries' => $seriesAround['previous'],
                'nextSeries' => $seriesAround['next'],
                'tv' => $noTv,
                'providers' => $this->watchLinkApi->getWatchProviders($country),
            ]);
        }

        $tv['credits'] = $this->castAndCrew($tv, $series);
        $tv['watch/providers'] = $this->watchProviders($tv, $country);
        $tv['status_css'] = $this->statusCss($tv);

        $series = $this->updateSeries($series, $tv, $seriesArr['images']);

        $userSeries = $this->updateUserSeries($userSeries, $tv);
        $userEpisodes = $this->checkSeasons($userSeries, $userEpisodes, $tv);
        if ($this->reloadUserEpisodes) {
            $userEpisodes = $this->userEpisodeRepository->findBy(['userSeries' => $userSeries, 'previousOccurrence' => null], ['seasonNumber' => 'ASC', 'episodeNumber' => 'ASC']);
            $this->addFlash('info', $this->translator->trans('Your episodes have been updated according to the series information.'));
            $this->reloadUserEpisodes = false;
        }

        $schedules = $this->seriesSchedulesV2($userSeries, $tv);
        $alternateSchedules = $this->seriesService->alternateSchedules($tv['seasons'], $series, $userEpisodes);
        $seriesArr['seasons'] = $this->overrideSeasonAirDate($tv['seasons'], $schedules);
        $seriesArr['seriesAround'] = $seriesAround;
        $seriesArr['userVotes'] = $this->seriesService->getUserVotes($tv['seasons'], $userEpisodes);
        $seriesArr['schedules'] = $schedules;
        $seriesArr['timezoneMenu'] = (new IntlExtension)->getTimezoneNames('fr_FR');
        $seriesArr['emptySchedule'] = $this->seriesService->emptySchedule();
        $seriesArr['alternateSchedules'] = $alternateSchedules;
//        $seriesArr['seriesInProgress'] = $this->userEpisodeRepository->isFullyReleased($userSeries);
        $seriesArr['images'] = $this->getSeriesImages($seriesArr['images']);
        $seriesArr['videos'] = $this->seriesService->getSeriesVideoList($series);
        $seriesArr['videoListFolded'] = $this->seriesService->isVideoListFolded(count($seriesArr['videos']), $user);

        if ($tv['backdrop_path'] == null && count($seriesArr['images']['backdrops']) > 0) {
            $tv['backdrop_path'] = substr($seriesArr['images']['backdrops'][0], strlen("/series/backdrops"));
        }

        $filmingLocationsWithBounds = $this->getFilmingLocations($series, $tv['localized_name']);

        $list = array_column($this->filmingLocationRepository->getSourceList(), "source_name");
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
                    ['item' => 'input_list', 'name' => 'source-name', 'label' => 'Source', 'type' => 'text', 'class' => 'flex-1', 'list' => $list, 'required' => false],
                    ['item' => 'input', 'name' => 'source-url', 'label' => 'Url', 'type' => 'text', 'class' => 'flex-2', 'required' => false],
                ]
            ],
        ];

        return $this->render("series/show.html.twig", [
            'series' => $seriesArr,
            'tv' => $tv,
            'userSeries' => $userSeries,
            'providers' => $this->watchLinkApi->getWatchProviders($country),
            'locations' => $filmingLocationsWithBounds['filmingLocations'],
            'locationsBounds' => $filmingLocationsWithBounds['bounds'],
            'emptyLocation' => $filmingLocationsWithBounds['emptyLocation'],
            'addLocationFormData' => $addLocationFormData,
            'fieldList' => ['series-id', 'tmdb-id', 'crud-type', 'crud-id', 'title', 'location', 'season-number', 'episode-number', 'description', 'latitude', 'longitude', 'radius', "source-name", "source-url"],
            'mapSettings' => $this->settingsRepository->findOneBy(['name' => 'mapbox']),
            'externals' => $this->getExternals($series, $tv['keywords']['results'], $tv['external_ids'] ?? [], $locale),
            'translations' => $this->seriesService->getSeriesShowTranslations(),
            'forms' => $forms,
            'oldSeriesAdded' => $request->query->get('oldSeriesAdded') === 'true',
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/season/{id}-{slug}/{seasonNumber}', name: 'season_show', requirements: ['id' => Requirement::DIGITS, 'seasonNumber' => Requirement::DIGITS])]
    public function showSeason(#[CurrentUser] User $user, Request $request, Series $series, int $seasonNumber, string $slug): Response
    {
        $locale = $user->getPreferredLanguage() ?? $request->getLocale();
        $country = $user->getCountry() ?? 'FR';
        $this->logger->info('showSeason', ['series' => $series->getId(), 'season' => $seasonNumber, 'slug' => $slug]);

        $userSeries = $this->userSeriesRepository->findOneBy(['user' => $user, 'series' => $series]);
        $this->checkSlug($series, $slug, $locale);
        $this->adjustNextEpisodeToWatch($userSeries, null);

        $seriesImages = $series->getSeriesImages()->toArray();

        $season = json_decode($this->tmdbService->getTvSeason($series->getTmdbId(), $seasonNumber, $request->getLocale(), ['credits', 'watch/providers']), true);
        if (!$season) {
            return $this->redirectToRoute('app_series_show', [
                'id' => $series->getId(),
                'slug' => $series->getSlug(),
            ]);
        }
        if (key_exists('error', $season)) {
            $this->addFlash('warning', 'Season not found on TMDB. You tried to access season ' . $seasonNumber . ' but the series "' . $series->getName() . '" has only ' . $series->getNumberOfSeason() . ' seasons.');
            return $this->redirectToRoute('app_series_show', [
                'id' => $series->getId(),
                'slug' => $series->getSlug(),
            ]);
        }
        //$tv = $this->seriesService->getTvMini($series);

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
        $season['sources'] = $this->sourceRepository->findBy([], ['name' => 'ASC']);
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
            'tv' => $tv,
            'translations' => $this->seriesService->getSeriesSeasonTranslations(),
            'quickLinks' => $this->getQuickLinks($season['episodes']),
            'season' => $season,
            'today' => $this->now()->format('Y-m-d H:I:s'),
            'filmingLocation' => $filmingLocation,
            'language' => $locale . '-' . $country,
            'changes' => $this->getChanges($season['id']),
            'now' => $this->now()->format('Y-m-d H:i O'),
            'episodeDiv' => $this->getEpisodeDivSize($userSeries),
            'providers' => $providers,
            'devices' => $devices,
//            'externals' => $this->getExternals($series, $tvKeywords['results'] ?? [], $tvExternalIds, $request->getLocale()),
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/episode/{id}-{slug}/{seasonNumber}/{episodeNumber}', name: 'episode_show', requirements: ['id' => Requirement::DIGITS, 'seasonNumber' => Requirement::DIGITS, 'episodeNumber' => Requirement::DIGITS])]
    public function showEpisode(#[CurrentUser] User $user, Request $request, Series $series, int $seasonNumber, int $episodeNumber, string $slug): Response
    {
        $locale = $user->getPreferredLanguage() ?? $request->getLocale();
        $this->logger->info('showEpisode', ['series' => $series->getId(), 'season' => $seasonNumber, 'episode' => $episodeNumber, 'slug' => $slug]);

        $userSeries = $this->userSeriesRepository->findOneBy(['user' => $user, 'series' => $series]);
        $season = json_decode($this->tmdbService->getTvSeason($series->getTmdbId(), $seasonNumber, $locale, ['credits', 'watch/providers']), true);
        $episode = json_decode($this->tmdbService->getTvEpisode($series->getTmdbId(), $seasonNumber, $episodeNumber, $locale, ['credits', 'watch/providers']), true);

        $finaleEpisodeNumber = $this->getFinaleEpisodeNumber($season);
        $userEpisodes = $this->userEpisodeRepository->getUserEpisodesDB($userSeries->getId(), $season['season_number'], $locale, true);
        $stills = $this->episodeStillRepository->getSeasonStills([$episode['id']]);

        $episode = $this->seasonEpisode($episode, $userSeries, $userEpisodes, $seasonNumber, $finaleEpisodeNumber, $stills);
        $profileUrl = $this->imageConfiguration->getUrl('profile_sizes', 2);
        $peopleUserPreferredNames = $this->getPreferredNames($user);
        $episode['guest_stars'] = $this->episodeGuestStars($episode, new AsciiSlugger(), $series, $profileUrl, $peopleUserPreferredNames);

        $season['credits'] = $this->castAndCrew($season, $series);
        $season['series_localized_name'] = $series->getLocalizedName($request->getLocale());
        $season['blurred_poster_path'] = $this->imageService->blurPoster($season['poster_path'], 'series', 8);

        $filmingLocation = $this->filmingLocationRepository->location($series->getTmdbId());
        dump([
            'userSeries' => $userSeries,
            'series' => $series,
            'season' => $season,
            'episode' => $episode,
            'filmingLocation' => $filmingLocation,
        ]);

        return $this->render('series/episode.html.twig', [
            'userSeries' => $userSeries,
            'series' => $series,
            'season' => $season,
            'episode' => $episode,
            'filmingLocation' => $filmingLocation,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/add/{id}', name: 'add', requirements: ['id' => Requirement::DIGITS])]
    public function add(#[CurrentUser] User $user, int $id): Response
    {
        $date = $this->now();
        $language = ($user->getPreferredLanguage() ?: 'fr') . '-' . ($user->getCountry() ?: 'FR');

        $result = $this->addSeries($id, $date, $language);
        $tv = $result['tv'];
        $series = $result['series'];
        $userSeries = $this->addSeriesToUser($user, $series, $tv, $date);
        $this->sendMail('Nouvelle série', $this->prepareMail($userSeries), $userSeries);

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
            $userEpisodes = $this->userEpisodeRepository->findBy(['userSeries' => $userSeries, 'seasonNumber' => $seasonNumber, 'previousOccurrence' => null], ['seasonNumber' => 'ASC', 'episodeNumber' => 'ASC']);

            $tv = json_decode($this->tmdbService->getTv($seriesBroadcastSchedule->getSeries()->getTmdbId(), 'fr-FR', ['seasons']), true);
            $as = $this->seriesService->getAlternateSchedule($seriesBroadcastSchedule, $tv['seasons'], $userEpisodes);

            $airDays = $as['airDays'];
            foreach ($airDays as $airDay) {
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

    private function getQuickLinks(array $episodes): array
    {
        if (!count($episodes)) {
            return ['items' => [], 'count' => 0, 'itemPerLine' => 0, 'lineCount' => 0];
        }
        $now = $this->now();
        $nowString = $now->format('Y-m-d H:i');

        $quickLinks = array_map(function ($link) use ($nowString) {
            if (!$link['air_date']) {
                $class = "quick-episode future";
                $future = true;
            } else {
                $airAt = $link['user_episode']['air_at'] ?? ' 09:00';
                $airString = $link['air_date'] . " " . $airAt;
                $class = "quick-episode";
                if ($link['user_episode']['watch_at_db']) {
                    $class .= " watched";
                }
                if ($airString > $nowString) {
                    $class .= " future";
                } else {
                    $class .= " enabled";
                }
                $future = $airString > $nowString;
            }
            return [
                'name' => $link['name'],
                'episode_number' => $link['episode_number'],
                'air_date' => $link['air_date'],
                'watched' => (bool)$link['user_episode']['watch_at_db'],
                'future' => $future,
                'class' => $class,
            ];
        }, $episodes);

        $count = count($quickLinks);
        if ($count <= 10) {
            $quickLinks[0]['class'] .= ' first';
            $quickLinks[$count - 1]['class'] .= ' last';
            $itemPerLine = $count;
            $lineCount = 1;
        } else {
            if ($count % 2 == 0)
                $itemPerLine = $count / 2;
            else {
                $quickLinks[] = ['name' => null, 'episode_number' => null, 'air_date' => null, 'watched' => null, 'future' => null, 'class' => 'quick-episode empty'];
                $itemPerLine = ($count + 1) / 2;
                $count += 1;
            }
            if ($itemPerLine > 10) {
                if ($count % 19 == 0) $itemPerLine = 19;
                if ($count % 17 == 0) $itemPerLine = 17;
                if ($count % 15 == 0) $itemPerLine = 15;
                if ($count % 13 == 0) $itemPerLine = 13;
                if ($count % 11 == 0) $itemPerLine = 11;
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
            $updateSeries = $this->updateSeries($series, $tv, []);
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
        return ['backdropForm' => $addBackdropForm->createView(), 'videoForm' => $addVideoForm->createView()];
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

    public function updateSeries(Series $series, array $tv, array $seriesImages): Series
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

        if (count($seriesImages)) {
            $sizes = ['backdrops' => 3, 'logos' => 5, 'posters' => 5];
            /*$seriesImages = $series->getSeriesImages()->toArray();*/
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

    public function getSeriesImages(array $seriesImages): array
    {
        $seriesBackdrops = array_filter($seriesImages, fn($image) => $image->getType() == "backdrop");
        $seriesLogos = array_filter($seriesImages, fn($image) => $image->getType() == "logo");
        $seriesPosters = array_filter($seriesImages, fn($image) => $image->getType() == "poster");

        $seriesBackdrops = array_values(array_map(fn($image) => "/series/backdrops" . $image->getImagePath(), $seriesBackdrops));
        $seriesLogos = array_values(array_map(fn($image) => "/series/logos" . $image->getImagePath(), $seriesLogos));
        $seriesPosters = array_values(array_map(fn($image) => "/series/posters" . $image->getImagePath(), $seriesPosters));

        return [
            'backdrops' => $seriesBackdrops,
            'logos' => $seriesLogos,
            'posters' => $seriesPosters
        ];
    }

    public function getExternals(Series $series, array $keywords, array $externalIds, string $locale): array
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

    public function statusCss(array $tv): string
    {
        $status = $tv['status'];
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
                        }
                    }
                    $seasonEpisodeCount += $episodeCount;
                } else {
                    $seasonEpisodeCount += $season['episode_count'];
                }
            }
        }
        return $seasonEpisodeCount;
    }

    public function seriesSchedulesV2(UserSeries $userSeries, ?array $tv): array
    {
        $schedules = [];
        $user = $userSeries->getUser();
        $series = $userSeries->getSeries();
        $locale = $user->getPreferredLanguage() ?? 'fr';
//        $userSeries = $this->userSeriesRepository->findOneBy(['user' => $user, 'series' => $series]);
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
                $userNextEpisode['episode'] = sprintf("S%02dE%02d", $seasonNumber, $userNextEpisode['episode_number']);
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
                $provider = $this->watchProviderRepository->getNameAndLogo($providerId);
                $providerName = $provider['provider_name'];
                $providerLogo = $this->getProviderLogoFullPath($provider['logo_path'], $logoUrl);
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
                'toBeContinued' => $tv ? $this->isToBeContinued($tv, $userLastEpisode) : $userNextEpisode != null,
                'tmdbStatus' => $tv['status'] ?? 'series not found',
            ];
        }
        return $schedules;
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

    private function getEpisodeDivSize(UserSeries $userSeries): array
    {
        $episodeSizeSettings = $this->settingsRepository->findOneBy(['user' => $userSeries->getUser(), 'name' => 'episode_div_size_' . $userSeries->getId()]);
        if ($episodeSizeSettings) {
            $value = $episodeSizeSettings->getData();
            $episodeDivSize = $value['height'];
            $aspectRatio = $value['aspect-ratio'] ?? '16 / 9';
        } else {
            $episodeSizeSettings = new Settings($userSeries->getUser(), 'episode_div_size_' . $userSeries->getId(), ['height' => '15rem', 'aspect-ratio' => '16 / 9']);
            $this->settingsRepository->save($episodeSizeSettings, true);
            $episodeDivSize = '15rem';
            $aspectRatio = '16 / 9';
        }
        return [
            'height' => $episodeDivSize,
            'aspectRatio' => $aspectRatio
        ];
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

    public function addSeries(int $id, DateTimeImmutable $date, string $language): array
    {
        $series = $this->seriesRepository->findOneBy(['tmdbId' => $id]);

        $slugger = new AsciiSlugger();
        $tv = json_decode($this->tmdbService->getTv($id, $language), true);

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
        }

        foreach ($tv['seasons'] as $season) {
            $this->addSeasonToUser($user, $userSeries, $season['season_number'], []);
        }
        return $userSeries;
    }

    public function prepareMail(UserSeries $userSeries): ContactMessage
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
            . "Nom : " . $series->getName() . "\n\n"
            . ($sln ? "Nom localisé : " . $series->getLocalizedName($user->getPreferredLanguage() ?: 'fr')->getName() . "\n\n" : "")
            . "Résumé : " . $series->getOverview() . "\n\n"
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
            $this->logger->error("Error sending email (Serie {$userSeries->getSeries()->getTmdbId()}) new addition by {$userSeries->getUser()->getUsername()})");
        }
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
            $ueIds = array_column($this->userEpisodeRepository->getSeasonEpisodeIds($userSeries->getId(), $seasonNumber), 'episode_id');
        } else {
            $ueIds = array_filter($userEpisodes, fn($e) => $e->getSeasonNumber() == $seasonNumber);
            $ueIds = array_map(fn($e) => $e->getEpisodeId(), $ueIds);
//            dd(['user episodes' => $userEpisodes, 'ue ids' => $ueIds]);
        }

        $epIds = $tvSeason['episodes'] ? array_map(fn($e) => $e['id'], $tvSeason['episodes']) : [];
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
                    return $episode['episode_number'];
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
            $this->addFlash('info', $this->translator->trans('Next episode to watch is %episode%', ['%episode%' => sprintf('S%02dE%02d', $seasonNumber, $episode['episode_number'])]));
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
            $series['sln_name'] = $series['localizedName'] ?: $series['name'];
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

    public function getFilmingLocations(Series $series, ?SeriesLocalizedName $sln): array
    {
        $tmdbId = $series->getTmdbId();
        $filmingLocations = $this->filmingLocationRepository->locations($tmdbId);
        $emptyLocation = $this->newLocation($series, $sln);
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

    private function newLocation(Series $series, ?SeriesLocalizedName $sln): array
    {
        $uuid = Uuid::v4()->toString();
        $now = $this->now();
        $tmdbId = $series->getTmdbId();
        $title = $sln ? $sln->getName() : $series->getName();
        $emptyLocation = new FilmingLocation($uuid, $tmdbId, $title, "", "", 0, 0, null, 0, 0, "", "", $now, true);
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

    public function checkSlug(Series $series, string $slug, string $locale = 'fr'): bool|Response
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

    public function checkTmdbSlug(array $series, string $slug, ?string $localizedSlug = null): bool|Response
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

    public function castAndCrew(?array $tv, ?Series $series): array
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
                $sc['order'] = -1;
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

    private function seasonEpisodes(array $season, UserSeries $userSeries): array
    {
        $user = $userSeries->getUser();
        $series = $userSeries->getSeries();
        $slugger = new AsciiSlugger();
        $locale = $user->getPreferredLanguage() ?? 'fr';
        $seasonEpisodes = [];
        $episodeArr = [];
        $userEpisodes = $this->userEpisodeRepository->getUserEpisodesDB($userSeries->getId(), $season['season_number'], $locale, true);
        $peopleUserPreferredNames = $this->getPreferredNames($user);

        $episodeIds = array_column($userEpisodes, 'episode_id');
        $stills = $this->episodeStillRepository->getSeasonStills($episodeIds);

        $finaleEpisodeNumber = $this->getFinaleEpisodeNumber($season);
        foreach ($season['episodes'] as $episode) {

//            $episode['substitute_name'] = $this->userEpisodeRepository->getSubstituteName($episode['id']);
            $episode['locale'] = $locale;
            $episodeArr[] = $this->seasonEpisode($episode, $userSeries, $userEpisodes, $season['season_number'], $finaleEpisodeNumber, $stills);
        }
        $profileUrl = $this->imageConfiguration->getUrl('profile_sizes', 2);
        foreach ($episodeArr as $episode) {
            $episode['guest_stars'] = $this->episodeGuestStars($episode, $slugger, $series, $profileUrl, $peopleUserPreferredNames);
            $seasonEpisodes[] = $episode;
            dump($episode['guest_stars']);
        }

        $newCount = array_reduce($seasonEpisodes, function ($carry, $episode) {
            return $carry + $episode['new'] ? 1 : 0;
        }, 0);

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

    private function getPreferredNames(User $user): array
    {
        $arr = $this->peopleUserPreferredNameRepository->getUserPreferredNames($user->getId());
        $peopleUserPreferredNames = [];
        foreach ($arr as $people) {
            $peopleUserPreferredNames[$people['tmdb_id']] = $people;
        }
        return $peopleUserPreferredNames;
    }

    private function seasonEpisode(array $episode, UserSeries $userSeries, array $userEpisodes, int $seasonNumber, int $finaleEpisodeNumber, array $stills): array
    {
//        if (key_exists('episode_type', $episode) && $episode['episode_type'] == 'finale') {
//            $finaleEpisodeNumber = $episode['episode_number'];
//        }
        if ($episode['episode_number'] > $finaleEpisodeNumber) {
            $this->addFlash('warning', "// Skip episode " . sprintf("S%02dE%02d", $seasonNumber, $episode['episode_number']) . " after a finale");
            return [];
        }
        $episode['new'] = false;
        $userEpisode = $this->getUserEpisode($userEpisodes, $episode['episode_number']);
        if (!$userEpisode) {
            $nue = new UserEpisode($userSeries, $episode['id'], $seasonNumber, $episode['episode_number'], null);
            $nue->setAirDate($episode['air_date'] ? $this->date($episode['air_date']) : null);
            if ($episode['episode_number'] > 1) {
                $previousEpisode = $this->getUserEpisode($userEpisodes, $episode['episode_number'] - 1);
                if ($previousEpisode) {
                    $nue->setProviderId($previousEpisode['provider_id']);
                    $nue->setDeviceId($previousEpisode['device_id']);
                }
            }
            $this->userEpisodeRepository->save($nue, true);
            $userEpisode = $this->userEpisodeRepository->getUserEpisodeDB($nue->getId(), $episode['locale']);
            $episode['new'] = true;
        }
        $series = $userSeries->getSeries();
        $next_episode_to_air = $series->getNextEpisodeAirDate();
        if (!$userEpisode['custom_date'] && !$next_episode_to_air && !$episode['air_date']) {
            return [];
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
                    . ' (' . sprintf('S%02dE%02d', $seasonNumber, $episode['episode_number'])
                    . ' → ' . $airDate->format('Y-m-d') . ')');
            }
        }

        $userEpisodeList = $this->getUserEpisodes($userEpisodes, $episode['episode_number']);

        $stillUrl = $this->imageConfiguration->getUrl('still_sizes', 3);

        $episode['still_path'] = $episode['still_path'] ? $stillUrl . $episode['still_path'] : null; // w300
        $episode['stills'] = array_filter($stills, function ($still) use ($episode) {
            return $still['episode_id'] == $episode['id'];
        });
        if ($userEpisode['custom_date']) {
            $episode['air_date'] = $userEpisode['custom_date'];
        }

        $userEpisode['watch_at_db'] = $userEpisode['watch_at'];
        if ($userEpisode['watch_at']) {
            $userEpisode['watch_at'] = $this->date($userEpisode['watch_at']);
        }
        $episode['user_episode'] = $userEpisode;
        $episode['user_episodes'] = $userEpisodeList;

        return $episode;
    }

    private function episodeGuestStars(array $episode, AsciiSlugger $slugger, Series $series, string $profileUrl, array $peopleUserPreferredNames): array
    {
        $guestStars = array_filter($episode['guest_stars'] ?? [], function ($guest) {
            return key_exists('id', $guest);
        });
        usort($guestStars, function ($a, $b) {
            return !$a['profile_path'] <=> !$b['profile_path'];
        });

        return array_map(function ($guest) use ($slugger, $series, $profileUrl, $peopleUserPreferredNames) {
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
        }, $guestStars);
    }

    private function adjustNextEpisodeToWatch(UserSeries $userSeries, ?array $userEpisodes): void
    {
        if ($userSeries->getNextUserEpisode() === null) {
            if (!$userEpisodes) {
                $userEpisodes = $this->userEpisodeRepository->findBy(['userSeries' => $userSeries, 'watchAt' => null, 'previousOccurrence' => null], ['seasonNumber' => 'ASC', 'episodeNumber' => 'ASC']);
            } else {
                $userEpisodes = array_filter($userEpisodes, function ($ue) {
                    return $ue->getWatchAt() === null && $ue->getPreviousOccurrence() === null;
                });
                usort($userEpisodes, function ($a, $b) {
                    if ($a->getSeasonNumber() == $b->getSeasonNumber()) {
                        return $a->getEpisodeNumber() <=> $b->getEpisodeNumber();
                    }
                    return $a->getSeasonNumber() <=> $b->getSeasonNumber();
                });
            }
            if (count($userEpisodes) > 0) {
                $ep = $userEpisodes[0];
                $userSeries->setNextUserEpisode($ep);
                $this->userSeriesRepository->save($userSeries, true);
                $this->addFlash('info', $this->translator->trans('Next episode to watch is %episode%', ['%episode%' => sprintf('S%02dE%02d', $ep->getSeasonNumber(), $ep->getEpisodeNumber())]));
            }
        }
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

    public function watchProviders(array $tv, string $country): array
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

    public function getOneResult(array $tv, AsciiSlugger $slugger): Response
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
}
