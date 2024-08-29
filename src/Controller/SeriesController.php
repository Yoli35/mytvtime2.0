<?php

namespace App\Controller;

use App\DTO\SeriesAdvancedSearchDTO;
use App\DTO\SeriesSearchDTO;
use App\Entity\EpisodeLocalizedOverview;
use App\Entity\EpisodeSubstituteName;
use App\Entity\SeasonLocalizedOverview;
use App\Entity\Series;
use App\Entity\SeriesAdditionalOverview;
use App\Entity\SeriesDayOffset;
use App\Entity\SeriesImage;
use App\Entity\SeriesLocalizedName;
use App\Entity\SeriesLocalizedOverview;
use App\Entity\SeriesWatchLink;
use App\Entity\Settings;
use App\Entity\User;
use App\Entity\UserEpisode;
use App\Entity\UserPinnedSeries;
use App\Entity\UserSeries;
use App\Form\SeriesAdvancedSearchType;
use App\Form\SeriesSearchType;
use App\Repository\DeviceRepository;
use App\Repository\EpisodeLocalizedOverviewRepository;
use App\Repository\EpisodeSubstituteNameRepository;
use App\Repository\KeywordRepository;

//use App\Repository\ProviderRepository;
use App\Repository\SeasonLocalizedOverviewRepository;
use App\Repository\SeriesAdditionalOverviewRepository;
use App\Repository\SeriesDayOffsetRepository;
use App\Repository\SeriesImageRepository;
use App\Repository\SeriesLocalizedNameRepository;
use App\Repository\SeriesLocalizedOverviewRepository;
use App\Repository\SeriesRepository;
use App\Repository\SeriesWatchLinkRepository;
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
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\Map\InfoWindow;
use Symfony\UX\Map\Map;
use Symfony\UX\Map\MapOptionsInterface;
use Symfony\UX\Map\Marker;
use Symfony\UX\Map\Point;

#[Route('/{_locale}/series', name: 'app_series_', requirements: ['_locale' => 'fr|en|de|es'])]
class SeriesController extends AbstractController
{
    public function __construct(
        private readonly ClockInterface                     $clock,
        private readonly DateService                        $dateService,
        private readonly DeviceRepository                   $deviceRepository,
        private readonly DeeplTranslator                    $deeplTranslator,
        private readonly EpisodeLocalizedOverviewRepository $episodeLocalizedOverviewRepository,
        private readonly EpisodeSubstituteNameRepository    $episodeSubstituteNameRepository,
        private readonly ImageConfiguration                 $imageConfiguration,
        private readonly KeywordRepository                  $keywordRepository,
        private readonly KeywordService                     $keywordService,
        private readonly SeasonLocalizedOverviewRepository  $seasonLocalizedOverviewRepository,
        private readonly SeriesAdditionalOverviewRepository $seriesAdditionalOverviewRepository,
        private readonly SeriesDayOffsetRepository          $seriesDayOffsetRepository,
        private readonly SeriesImageRepository              $seriesImageRepository,
        private readonly SeriesRepository                   $seriesRepository,
        private readonly SeriesLocalizedNameRepository      $seriesLocalizedNameRepository,
        private readonly SeriesLocalizedOverviewRepository  $seriesLocalizedOverviewRepository,
        private readonly SeriesWatchLinkRepository          $seriesWatchLinkRepository,
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

        // Historique des épisodes vus pendant les 2 semaines passées
        $episodeHistory = $this->getEpisodeHistory($user, 14, $country, $language);

        $seriesOfTheDay = array_map(function ($us) {
            $this->saveImage("posters", $us['poster_path'], $this->imageConfiguration->getUrl('poster_sizes', 5));
            return [
                'series_of_the_day' => true,
                'id' => $us['id'],
                'name' => $us['name'],
                'slug' => $us['slug'],
                'localized_name' => $us['localized_name'],
                'localized_slug' => $us['localized_slug'],
                'poster_path' => $us['poster_path'] ? '/series/posters' . $us['poster_path'] : null,
                'progress' => $us['progress'],
            ];
        }, $this->userSeriesRepository->getUserSeriesOfTheDay($user, $country, $locale));

        $episodesOfTheDay = array_map(function ($ue) {
            $this->saveImage("posters", $ue['posterPath'], $this->imageConfiguration->getUrl('poster_sizes', 5));
            return [
                'episode_of_the_day' => true,
                'id' => $ue['id'],
                'name' => $ue['name'],
                'slug' => $ue['slug'],
                'localized_name' => $ue['localizedName'],
                'localized_slug' => $ue['localizedSlug'],
                'poster_path' => $ue['posterPath'] ? '/series/posters' . $ue['posterPath'] : null,
                'progress' => $ue['progress'],
                'favorite' => $ue['favorite'],
                'episode_number' => $ue['episodeNumber'],
                'season_number' => $ue['seasonNumber'],
                'watch_at' => $ue['watchAt'],
            ];
        }, $this->userEpisodeRepository->episodesOfTheDay($user, $country, $locale));

        $seriesOfTheWeek = array_map(function ($us) {
            $this->saveImage("posters", $us['poster_path'], $this->imageConfiguration->getUrl('poster_sizes', 5));
            return [
                'series_of_the_week' => true,
                'episode_of_the_day' => true,
                'id' => $us['id'],
                'date' => $us['air_date'],
                'name' => $us['name'],
                'slug' => $us['slug'],
                'localized_name' => $us['localized_name'],
                'localized_slug' => $us['localized_slug'],
                'poster_path' => $us['poster_path'] ? '/series/posters' . $us['poster_path'] : null,
                'progress' => $us['progress'],
                'watch_at' => $us['watch_at'],
                'season_number' => $us['season_number'],
                'episode_number' => $us['episode_number'],
            ];
        }, $this->userSeriesRepository->getUserSeriesOfTheNext7Days($user, $country, $locale));

        dump([
            'seriesOfTheDay' => $seriesOfTheDay,
            'episodesOfTheDay' => $episodesOfTheDay,
            'seriesOfTheWeek' => $seriesOfTheWeek,
            'episodeHistory' => $episodeHistory,
            'seriesList' => $series,
            'total_results' => $searchResult['total_results'] ?? -1,
            'hier' => $this->now()->modify('-1 day')->format('Y-m-d'),
        ]);

        return $this->render('series/index.html.twig', [
            'seriesOfTheDay' => $seriesOfTheDay,
            'episodesOfTheDay' => $episodesOfTheDay,
            'seriesOfTheWeek' => $seriesOfTheWeek,
            'episodeHistory' => $episodeHistory,
            'seriesList' => $series,
            'total_results' => $searchResult['total_results'] ?? -1,
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
            $query = $simpleSeriesSearch->getQuery();
            $language = $simpleSeriesSearch->getLanguage();
            $page = $simpleSeriesSearch->getPage();
            $firstAirDateYear = $simpleSeriesSearch->getFirstAirDateYear();

            $searchString = "&query=$query&include_adult=false&page=$page";
            if (strlen($firstAirDateYear)) $searchString .= "&first_air_date_year=$firstAirDateYear";
            if (strlen($language)) $searchString .= "&language=$language";

            $searchResult = json_decode($this->tmdbService->searchTv($searchString), true);
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
                $settings = new Settings($user, 'series to end', ['perPage' => 10, 'sort' => 'lastWatched', 'order' => 'DESC', 'startStatus' => 'series-started', 'endStatus' => 'series-not-watched']);
                $this->settingsRepository->save($settings, true);
            }
        } else {
            // /fr/series/all?sort=episodeAirDate&order=DESC&startStatus=series-not-started&endStatus=series-not-watched&perPage=10
            $paramSort = $request->get('sort');
            $paramOrder = $request->get('order');
            $paramStartStatus = $request->get('startStatus');
            $paramEndStatus = $request->get('endStatus');
            $paramPerPage = $request->get('perPage');
            $settings->setData([
                'perPage' => $paramPerPage,
                'sort' => $paramSort,
                'order' => $paramOrder,
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
            'sort' => $data['sort'],
            'order' => $data['order'],
            'startStatus' => $data['startStatus'],
            'endStatus' => $data['endStatus'],
        ];
        $filterValues = [
            'series-started' => 'us.progress > 0',
            'series-not-started' => 'us.progress = 0',
            'series-watched' => 'us.progress = 100',
            'series-not-watched' => 'us.progress < 100',
            'series-favorite' => 'us.favorite = 1',
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
        $userSeries = $this->userSeriesRepository->getAllSeries($user, $localisation, [/*'us.progress > 0', */ 'us.progress < 100'], $filters['sort'], $filters['order'], $page, $filters['perPage']);
        $userSeriesCount = $this->userSeriesRepository->countAllSeries($user, $localisation, [/*'us.progress > 0', */ 'us.progress < 100']);

        $userSeries = array_map(function ($series) {
            $series['poster_path'] = $series['poster_path'] ? $this->imageConfiguration->getCompleteUrl($series['poster_path'], 'poster_sizes', 5) : null;
            return $series;
        }, $userSeries);

        dump([
            'userSeries' => $userSeries,
            'userSeriesCount' => $userSeriesCount,
            'filters' => $filters,
            'filterBoxOpen' => $filterBoxOpen,
        ]);

        return $this->render('series/all.html.twig', [
            'userSeries' => $userSeries,
            'userSeriesCount' => $userSeriesCount,
            'pages' => ceil($userSeriesCount / $filters['perPage']),
            'filters' => $filters,
            'filterBoxOpen' => $filterBoxOpen,
            'filterMeanings' => $filterMeanings,
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

        dump($series);

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
        $watchProviders = $this->getWatchProviders($user?->getPreferredLanguage() ?? $request->getLocale(), $user?->getCountry() ?? 'FR');
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
        $tv = json_decode($this->tmdbService->getTv($id, $request->getLocale(), ["images", "videos", "credits", "watch/providers", "content/ratings", "keywords"]), true);

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
    public function show(Request $request, $id, $slug): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $series = $this->seriesRepository->findOneBy(['id' => $id]);
        $series->setVisitNumber($series->getVisitNumber() + 1);
        $this->seriesRepository->save($series, true);

        $this->checkSlug($series, $slug, $user->getPreferredLanguage() ?? $request->getLocale());
        $tv = json_decode($this->tmdbService->getTv($series->getTmdbId(), $request->getLocale(), ["images", "videos", "credits", "watch/providers", "keywords, list"]), true);
        $tvLists = json_decode($this->tmdbService->getTvLists($series->getTmdbId()), true);
//        dump($tv, $tvLists);

        $this->saveImage("posters", $tv['poster_path'], $this->imageConfiguration->getUrl('poster_sizes', 5));
        $this->saveImage("backdrops", $tv['backdrop_path'], $this->imageConfiguration->getUrl('backdrop_sizes', 3));
        $series = $this->updateSeries($series, $tv);

        $tv['credits'] = $this->castAndCrew($tv);
        $tv['localized_name'] = $series->getLocalizedName($request->getLocale());
        $tv['localized_overviews'] = $series->getLocalizedOverviews($request->getLocale());
        $tv['additional_overviews'] = $series->getSeriesAdditionalLocaleOverviews($request->getLocale());
        $tv['sources'] = $this->sourceRepository->findBy([], ['name' => 'ASC']);
        $tv['networks'] = $this->networks($tv);
        $tv['overview'] = $this->localizedOverview($tv, $series, $request);
        $tv['seasons'] = $this->seasonsPosterPath($tv['seasons']);
        $tv['watch/providers'] = $this->watchProviders($tv, $user->getCountry() ?? 'FR');
        $tv['missing_translations'] = $this->keywordService->keywordsTranslation($tv['keywords']['results'], $request->getLocale());

        $userSeries = $this->userSeriesRepository->findOneBy(['user' => $user, 'series' => $series]);
        $userSeries = $this->updateUserSeries($userSeries, $tv);

        $providers = $this->getWatchProviders($user->getPreferredLanguage() ?? $request->getLocale(), $user->getCountry() ?? 'FR');

        $schedules = $this->seriesSchedules($series);
        $seriesArr = $series->toArray();
        $seriesArr['schedules'] = $schedules;

        $translations = [
            'Localized overviews' => $this->translator->trans('Localized overviews'),
            'Additional overviews' => $this->translator->trans('Additional overviews'),
            'Edit' => $this->translator->trans('Edit'),
            'Delete' => $this->translator->trans('Delete'),
            'Add' => $this->translator->trans('Add'),
            'Update' => $this->translator->trans('Update'),
            'Remove from favorites' => $this->translator->trans('Remove from favorites'),
            'Add to favorites' => $this->translator->trans('Add to favorites'),
            'This field is required' => $this->translator->trans('This field is required'),
            'Watch on' => $this->translator->trans('Watch on'),
        ];

//        dump([
//            'series' => $seriesArr,
//            'tv' => $tv,
//            'userSeries' => $userSeries,
//            'providers' => $providers,
//        ]);
        return $this->render('series/show.html.twig', [
            'series' => $seriesArr,
            'tv' => $tv,
            'tvLists' => $tvLists,
            'userSeries' => $userSeries,
            'providers' => $providers,
            'seriesLocations' => $this->getSeriesLocations($series, $user->getPreferredLanguage() ?? $request->getLocale()),
            'translations' => $translations,
        ]);
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

//    #[IsGranted('ROLE_USER')]
    #[Route('/broadcast/delay/{id}', name: 'broadcast-delay', requirements: ['id' => Requirement::DIGITS])]
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

//    #[IsGranted('ROLE_USER')]
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

//    #[IsGranted('ROLE_USER')]
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

//    #[IsGranted('ROLE_USER')]
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
    public function showSeason(Request $request, int $id, int $seasonNumber, string $slug): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $series = $this->seriesRepository->findOneBy(['id' => $id]);
        $seriesDayOffset = $this->seriesDayOffsetRepository->findOneBy(['series' => $series, 'country' => $user->getCountry() ?? 'FR']);
        $dayOffset = $seriesDayOffset ? $seriesDayOffset->getOffset() : 0;

        $userSeries = $this->userSeriesRepository->findOneBy(['user' => $user, 'series' => $series]);
        $this->checkSlug($series, $slug, $user->getPreferredLanguage() ?? $request->getLocale());

        $season = json_decode($this->tmdbService->getTvSeason($series->getTmdbId(), $seasonNumber, $request->getLocale(), ['credits', 'watch/providers']), true);
        if ($season['poster_path']) {
            $this->saveImage("posters", $season['poster_path'], $this->imageConfiguration->getUrl('poster_sizes', 5));
        } else {
            $season['poster_path'] = $series->getPosterPath();
        }
        $season['deepl'] = $this->seasonLocalizedOverview($series, $season, $seasonNumber, $request);
        $season['episodes'] = $this->seasonEpisodes($season, $userSeries, $dayOffset);
        $season['credits'] = $this->castAndCrew($season);
        $season['watch/providers'] = $this->watchProviders($season, $user->getCountry() ?? 'FR');
        $season['localized_name'] = $series->getLocalizedName($request->getLocale());

        $providers = $this->getWatchProviders($user->getPreferredLanguage() ?? $request->getLocale(), $user->getCountry() ?? 'FR');
        $devices = $this->deviceRepository->deviceArray();
        dump([
            'series' => $series,
            'season' => $season,
//            'userSeries' => $userSeries,
            'providers' => $providers,
//            'devices' => $devices,
        ]);
        return $this->render('series/season.html.twig', [
            'series' => $series,
            'season' => $season,
            'providers' => $providers,
            'devices' => $devices,
        ]);
    }

//    #[IsGranted('ROLE_USER')]
    #[Route('/add/watch/link/{id}', name: 'add_watch_link', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function addWatchLink(Request $request, int $id): Response
    {
        $data = json_decode($request->getContent(), true);
        $url = $data['url'];
        $name = $data['name'];
        $providerId = $data['provider'];
        if ($providerId == "") $providerId = null;
        dump([
            'url' => $url,
            'name' => $name,
            'provider' => $providerId,
        ]);
        $series = $this->seriesRepository->findOneBy(['id' => $id]);

        $watchLink = new SeriesWatchLink($url, $name, $series, $providerId);
        $this->seriesWatchLinkRepository->save($watchLink, true);

        return $this->json([
            'ok' => true,
        ]);
    }

//    #[IsGranted('ROLE_USER')]
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

//    #[IsGranted('ROLE_USER')]
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
        dump($data);
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

//    #[IsGranted('ROLE_USER')]
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

//    #[IsGranted('ROLE_USER')]
    #[Route('/add/episode/{id}', name: 'add_episode', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function addUserEpisode(Request $request, int $id): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

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
        //    $userEpisode->setWatchAt($this->now());
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
        dump([
            'day offset' => $dayOffset,
            'airDate' => $airDate,
            'now' => $now,
            'days' => $diff->days,
            'hours' => $diff->h,
            'minutes' => $diff->i,
            'secondes' => $diff->s
        ]);
        $userEpisode->setQuickWatchDay($diff->days < 1);
        $userEpisode->setQuickWatchWeek($diff->days < 7);

        if ($userEpisode->getEpisodeNumber() > 1) {
            $previousEpisode = $this->userEpisodeRepository->findOneBy(['user' => $user, 'userSeries' => $userSeries, 'seasonNumber' => $seasonNumber, 'episodeNumber' => $episodeNumber - 1]);
            if ($previousEpisode) {
                $userEpisode->setProviderId($previousEpisode->getProviderId());
                $userEpisode->setDeviceId($previousEpisode->getDeviceId());
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
            dump($episodes);
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
        dump($userEpisodes, $episodeCount, $numberOfEpisode);
        if ($episodeCount == $numberOfEpisode) {
            $isBinge = true;
        }
        return $isBinge;
    }

//    #[IsGranted('ROLE_USER')]
    #[Route('/remove/episode/{id}', name: 'remove_episode', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function removeUserEpisode(Request $request, int $id): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

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

//    #[IsGranted('ROLE_USER')]
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

//    #[IsGranted('ROLE_USER')]
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

//    #[IsGranted('ROLE_USER')]
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

//    #[IsGranted('ROLE_USER')]
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

//    #[IsGranted('ROLE_USER')]
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

//    #[IsGranted('ROLE_USER')]
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
        dump([
            'name' => $name,
            'value' => $value,
        ]);
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
            $line = $keywords[$i]['original'] . ': ' . $keywords[$i]['translated'] . "\n";
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

    #[Route('/add/location/{id}', name: 'add_location', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function addLocation(Request $request, Series $series): Response
    {
        $locations = $series->getLocations();
        $data = json_decode($request->getContent(), true);
//        dump(['locations' => $locations, 'data' => $data]);
        $data = array_filter($data, fn($key) => $key != "google-map-url", ARRAY_FILTER_USE_KEY);
//        dump(['data' => $data]);

        $data['latitude'] = str_replace(',', '.', $data['latitude']);
        $data['longitude'] = str_replace(',', '.', $data['longitude']);
        $data['latitude'] = floatval($data['latitude']);
        $data['longitude'] = floatval($data['longitude']);
        $locations['locations'][] = $data;
        $series->setLocations($locations);
        $this->seriesRepository->save($series, true);

        return $this->json([
            'ok' => true,
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
            $series->setFirstAirDate(new DatePoint($tv['first_air_date']));
            $series->addUpdate($this->translator->trans('First air date updated'));
        }

        if (strlen($tv['overview']) && strcmp($tv['overview'], $series->getOverview())) {
            $series->setOverview($tv['overview']);
            $series->addUpdate($this->translator->trans('Overview updated'));
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

        $seriesImages = $series->getSeriesImages()->toArray();
        $seriesPosters = array_filter($seriesImages, fn($image) => $image->getType() == "poster");
        $seriesBackdrops = array_filter($seriesImages, fn($image) => $image->getType() == "backdrop");

        if (!$this->inImages($tv['poster_path'], $seriesPosters)) {
            $seriesImage = new SeriesImage($series, "poster", $tv['poster_path']);
            $this->seriesImageRepository->save($seriesImage, true);
            $series->addUpdate($this->translator->trans('Poster added'));
        }
        if (!$this->inImages($tv['backdrop_path'], $seriesBackdrops)) {
            $seriesImage = new SeriesImage($series, "backdrop", $tv['backdrop_path']);
            $this->seriesImageRepository->save($seriesImage, true);
            $series->addUpdate($this->translator->trans('Backdrop added'));
//            dump($series->getUpdates());
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
        return $series;
    }

    public function updateUserSeries(UserSeries $userSeries, array $tv): UserSeries
    {
        $change = false;
        $episodeCount = $this->checkNumberOfEpisodes($tv);
        if ($episodeCount != $tv['number_of_episodes']) {
            $this->addFlash('warning', $this->translator->trans('Number of episodes has changed') . '<br>' . $tv['number_of_episodes'] . ' → ' . $episodeCount);
        }
        if (/*$userSeries->getProgress() == 100 && */ $userSeries->getViewedEpisodes() < $episodeCount) {
            $userSeries->setProgress($userSeries->getViewedEpisodes() / $episodeCount * 100);
            $change = true;
        }
        if ($userSeries->getProgress() != 100 && $userSeries->getViewedEpisodes() === $episodeCount) {
            $userSeries->setProgress(100);
            $change = true;
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
                $seasonEpisodeCount += $season['episode_count'];
            }
        }
        return $seasonEpisodeCount;
    }

    public function seriesSchedules(Series $series): array
    {
        $schedules = [];
        foreach ($series->getSeriesBroadcastSchedules() as $schedule) {
            $firstAirDate = $schedule->getFirstAirDate();
            $airAt = $schedule->getAirAt();

            $country = $schedule->getCountry();
            $utc = $schedule->getUtc(); // int

            $firstAirDate = $firstAirDate->setTime($airAt->format('H'), $airAt->format('i'));

            $originalDate = $firstAirDate->format('Y-m-d H:i');
            $originalDate = str_replace(' ', 'T', $originalDate);
            $originalDate .= ($utc > 0 ? "+" : "-") . (abs($utc) < 10 ? '0' : '') . $utc . ':00';

            $schedules[] = [
                'country' => $country,
                'firstAirDate' => $firstAirDate,
                'originalDate' => $originalDate,
            ];

        }
        return $schedules;

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
        $series->setTmdbId($id);
        $series->setName($tv['name']);
        $series->setSlug($slugger->slug($tv['name']));
        $series->setOriginalName($tv['original_name']);
        $series->setOverview($tv['overview']);
        $series->setPosterPath($tv['poster_path']);
        $series->setBackdropPath($tv['backdrop_path']);
        $series->setFirstAirDate($tv['first_air_date'] ? new DatePoint($tv['first_air_date']) : null);
        $series->setVisitNumber(0);
        $series->setCreatedAt($date);
        $series->setUpdatedAt($date);
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

    public function getEpisodeHistory(User $user, int $dayCount, string $country, string $language): array
    {
        return array_map(function ($series) {
            $series['posterPath'] = $series['posterPath'] ? $this->imageConfiguration->getCompleteUrl($series['posterPath'], 'poster_sizes', 5) : null;
            $series['providerLogoPath'] = $series['providerLogoPath'] ? ($series['providerId'] > 0 ? $this->imageConfiguration->getCompleteUrl($series['providerLogoPath'], 'logo_sizes', 2):'/images/providers'.$series['providerLogoPath']) : null;
            $series['upToDate'] = $series['watched_aired_episode_count'] == $series['aired_episode_count'];
            $series['remainingEpisodes'] = $series['aired_episode_count'] - $series['watched_aired_episode_count'];
            return $series;
        }, $this->userEpisodeRepository->historyEpisode($user, $dayCount, $country, $language));
    }

    public function getSeriesLocations(Series $series, string $locale): array
    {
        $seriesLocation = $this->seriesRepository->oneSeriesLocations($series, $locale);
        if (empty($seriesLocation)) {
            return ['map'=>null, 'locations'=> null];
        }
        $map = new Map();
        $count = count($seriesLocation['locations']);
        if ($count > 1) {
            $map->fitBoundsToMarkers();
        } else {
            $map->zoom(10)
                ->center(new Point($seriesLocation['locations'][0]['latitude'], $seriesLocation['locations'][0]['longitude']));
        }

        foreach ($seriesLocation['locations'] as $location) {
            $map->addMarker(new Marker(new Point($location['latitude'], $location['longitude']), $seriesLocation['name'], new InfoWindow('<strong>' . $seriesLocation['name'] . '</strong> - ' . $location['description'], '<img src="' . $location['image'] . '" alt="' . $location['description'] . '" style="height: auto; width: 100%">')));
        }
        dump($seriesLocation['locations']);
        return ['map'=>$map, 'locations'=> $seriesLocation['locations']];
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

    public function seasonsPosterPath(array $seasons): array
    {
        $slugger = new AsciiSlugger();
        return array_map(function ($season) use ($slugger) {
            $season['slug'] = $slugger->slug($season['name'])->lower()->toString();
            $season['poster_path'] = $season['poster_path'] ? $this->imageConfiguration->getCompleteUrl($season['poster_path'], 'poster_sizes', 5) : null; // w500
            return $season;
        }, $seasons);
    }

    public function seasonEpisodes(array $season, UserSeries $userSeries, int $dayOffset): array
    {
        $user = $userSeries->getUser();
        $series = $userSeries->getSeries();
        $slugger = new AsciiSlugger();
        $seasonEpisodes = [];
        $userEpisodes = $this->userEpisodeRepository->getUserEpisodes($user->getId(), $userSeries->getId(), $season['season_number'], $user->getPreferredLanguage() ?? 'fr');

        foreach ($season['episodes'] as $episode) {
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

    public function getWatchProviders($language, $watchRegion): array
    {
        $providers = ['results' => []];//json_decode($this->tmdbService->getTvWatchProviderList($language, $watchRegion), true);
//        dump(['TV providers' => $providers]);
        $providers = $providers['results'];
        if (count($providers) == 0) {
            $providers = $this->watchProviderRepository->getWatchProviderList($watchRegion);
        }
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
                $imageContent = file_get_contents($imageUrl);

                // Ouvrir un fichier en mode écriture binaire
                $file = fopen($localeFile, 'wb');

                // Écrire le contenu de l'image dans le fichier
                fwrite($file, $imageContent);

                // Fermer le fichier
                fclose($file);

                return true;
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
}
