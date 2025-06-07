<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\FilmingLocationRepository;
use App\Repository\MovieRepository;
use App\Repository\PointOfInterestRepository;
use App\Repository\SeriesRepository;
use App\Repository\UserRepository;
use App\Repository\VideoCategoryRepository;
use App\Repository\VideoRepository;
use App\Repository\WatchProviderRepository;
use App\Service\DateService;
use App\Service\ImageConfiguration;
use App\Service\TMDBService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Intl\Languages as Languages;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/** @method User|null getUser() */
#[IsGranted('ROLE_ADMIN')]
#[Route('/{_locale}/admin', name: 'admin_', requirements: ['_locale' => 'fr|en|ko'])]
class AdminController extends AbstractController
{

    public function __construct(
        private readonly VideoCategoryRepository   $categoryRepository,
        private readonly DateService               $dateService,
        private readonly FilmingLocationRepository $filmingLocationRepository,
        private readonly ImageConfiguration        $imageConfiguration,
        private readonly MovieRepository           $movieRepository,
        private readonly PointOfInterestRepository $pointOfInterestRepository,
        private readonly SeriesController          $seriesController,
        private readonly SeriesRepository          $seriesRepository,
        private readonly UserRepository            $userRepository,
        private readonly TMDBService               $tmdbService,
        private readonly TranslatorInterface       $translator,
        private readonly VideoController           $videoController,
        private readonly VideoRepository           $videoRepository,
        private readonly WatchProviderRepository   $watchProviderRepository
    )
    {
    }

    #[Route('/', name: 'index')]
    public function index(): Response
    {
        return $this->redirectToRoute('admin_users');
    }

    #[Route('/users', name: 'users')]
    public function adminUsers(Request $request): Response
    {
        list($sort, $order, $page, $limit) = $this->getParameters($request);

        $users = $this->userRepository->adminUsers($page, $limit, $sort, $order);
        $userCount = $this->userRepository->count();
        $pageCount = ceil($userCount / $limit);

        $paginationLinks = $this->generateLinks($pageCount, $page, $this->generateUrl('admin_series'), [
            's' => $sort,
            'o' => $order,
            'l' => $limit,
        ]);

        return $this->render('admin/index.html.twig', [
            'users' => $users,
            'pagination' => $paginationLinks,
        ]);
    }

    #[Route('/series', name: 'series')]
    public function adminSeries(Request $request): Response
    {
        list($sort, $order, $page, $limit) = $this->getParameters($request);

        $series = $this->seriesRepository->adminSeries($request->getLocale(), $page, $sort, $order, $limit);
        $seriesCount = $this->seriesRepository->count();
        $pageCount = ceil($seriesCount / $limit);

        $logoUrl = $this->imageConfiguration->getUrl('logo_sizes', 3);
        $series = array_map(function ($s) use ($logoUrl) {
            $s['origin_country'] = json_decode($s['origin_country'], true);
            $p1 = $s['logo1'] ? explode('|', $s['logo1']) : [null, null];
            $p2 = $s['logo2'] ? explode('|', $s['logo2']) : [null, null];

            $s['provider_logo'] = $this->seriesController->getProviderLogoFullPath($p1[0] ?? $p2[0], $logoUrl);
            $s['provider_name'] = $p1[1] ?? $p2[1];
            return $s;
        }, $series);

        $seriesArr = [];
        foreach ($series as $s) {
            if (!key_exists($s['id'], $seriesArr)) {
                $seriesArr[$s['id']] = $s;
            }
        }

        $paginationLinks = $this->generateLinks($pageCount, $page, $this->generateUrl('admin_series'), [
            's' => $sort,
            'o' => $order,
            'l' => $limit,
        ]);

        return $this->render('admin/index.html.twig', [
            'series' => $seriesArr,
            'pagination' => $paginationLinks,
            'seriesCount' => $seriesCount,
            'page' => $page,
            'limit' => $limit,
            'pageCount' => $pageCount,
            'sort' => $sort,
            'order' => $order,
        ]);
    }

    #[Route('/series/append', name: 'series_append', methods: ['POST'])]
    public function adminSeriesAppend(Request $request): Response
    {
        $data = $request->request->all();

        $seriesId = $data['id'];
        $extra = $data['append_to_response'];

        $param = '';
        $invalidRequest = false;
        switch ($extra) {
            case 'changes':
                $page = $data['page'] ?? null;
                $endDate = $data['end_date'] ?? null;
                $startDate = $data['start_date'] ?? null;
                $param = "&page=$page&start_date=$startDate&end_date=$endDate";
                $invalidRequest = !$endDate || !$startDate;
                break;
            case 'credits':
            case 'aggregate_credits':
            case 'videos':
                $language = $data['language'] ?? null;
                $param = ($language ? "&language=$language" : "");
                break;
            case 'images':
                $language = $data['language'] ?? null;
                $include_image_language = $data['include_image_language'] ?? null;
                $param = ($include_image_language ? "&include_image_language=$include_image_language" : "") . ($language ? "&language=$language" : "");
                break;
            case 'lists':
            case 'reviews':
                $page = $data['page'] ?? null;
                $language = $data['language'] ?? null;
                $param = "&page=$page" . ($language ? "&language=$language" : "");
                break;
        }

        if ($invalidRequest) {
            return $this->json([
                'error' => $this->translator->trans("admin.series.append.invalid_request"),
                'status' => 400,
            ]);
        } else {
            $results = json_decode($this->tmdbService->getSeriesExtras($seriesId, $extra, $param), true);
        }

        return $this->render('_blocks/admin/_series-append-results.html.twig', [
                'extra' => $extra,
                'results' => $results,
                'urls' => [
                    'backdrop' => $this->imageConfiguration->getUrl('backdrop_sizes', 2),//w1280
                    'logo' => $this->imageConfiguration->getUrl('logo_sizes', 5), // w500
                    'poster' => $this->imageConfiguration->getUrl('poster_sizes', 5), // w780
                    'still' => $this->imageConfiguration->getUrl('still_sizes', 2), // w300
                    'profile' => $this->imageConfiguration->getUrl('profile_sizes', 2), // h632
                ],
            ]
        );
    }

    #[Route('/series/{id}', name: 'series_edit')]
    public function adminSeriesEdit(Request $request, int $id): Response
    {
        list($sort, $order, $page, $limit) = $this->getParameters($request);

        $series = $this->seriesRepository->adminSeriesById($id);
        if (!$series) {
            throw $this->createNotFoundException('Series not found');
        }

        $tmdbSeries = json_decode(
            $this->tmdbService->getTv(
                $series['tmdb_id'],
                $request->getLocale()
            /*, ["images", "videos", "credits", "watch/providers", "content/ratings", "keywords", "similar", "translations"]*/),
            true);

        $logoUrl = $this->imageConfiguration->getUrl('logo_sizes', 3);
        $series['origin_country'] = json_decode($series['origin_country'], true);

        $seriesAdditionalOverviews = $this->seriesRepository->seriesAdditionalOverviews($id);
        $seriesBroadcastSchedules = $this->seriesRepository->seriesBroadcastSchedules($id);
        foreach ($seriesBroadcastSchedules as &$sbs) {
            $sbs['provider_logo'] = $this->seriesController->getProviderLogoFullPath($sbs['provider_logo'], $logoUrl);
            $sbs['broadcast_dates'] = $this->seriesRepository->seriesBroadcastDates($sbs['id']);
        }
        $seriesImages = $this->seriesRepository->seriesImagesById($id);
        $seriesLocalizedNames = $this->seriesRepository->seriesLocalizedNames($id);
        $seriesLocalizedOverviews = $this->seriesRepository->seriesLocalizedOverviews($id);
        $seriesNetworks = $this->seriesRepository->seriesNetworks($id);
        $seriesNetworks = array_map(function ($sn) use ($logoUrl) {
            $sn['logo_path'] = $this->seriesController->getProviderLogoFullPath($sn['logo_path'], $logoUrl);
            return $sn;
        }, $seriesNetworks);
        $seriesWatchLinks = array_map(function ($swl) use ($logoUrl) {
            $swl['provider_logo'] = $this->seriesController->getProviderLogoFullPath($swl['provider_logo'], $logoUrl);
            return $swl;
        }, $this->seriesRepository->seriesWatchLinks($id));

        $now = $this->dateService->getNowImmutable("Europe/Paris", true);
        $start = $now->modify('-14 days');
        $series['append_to_response'] = 'translations';
        $appendToResponse = [
            'Alternative Titles' => ['value' => 'alternative_titles', 'extra_fields' => []],
            'Changes' => ['value' => 'changes', 'extra_fields' => ['end_date' => $now->format("Y-m-d"), 'start_date' => $start->format("Y-m-d"), 'page' => 1]], // +page
            'Credits' => ['value' => 'credits', 'extra_fields' => ['language' => 'en-US']], // +language
            'Agregate credits' => ['value' => 'aggregate_credits', 'extra_fields' => ['language' => 'en-US']], // +language
            'External IDs' => ['value' => 'external_ids', 'extra_fields' => []],
            'Images' => ['value' => 'images', 'extra_fields' => ['include_image_language' => 'fr,null', 'language' => 'en-US']], // +include_image_language (specify a comma separated list of ISO-639-1 values to query, for example: en,null), language
            'Keywords' => ['value' => 'keywords', 'extra_fields' => []],
            'Lists' => ['value' => 'lists', 'extra_fields' => ['language' => 'en-US', 'page' => 1]], // +language, page
            'Reviews' => ['value' => 'reviews', 'extra_fields' => ['language' => 'en-US', 'page' => 1]], // +language, page
            'Translations' => ['value' => 'translations', 'extra_fields' => []],
            'Videos' => ['value' => 'videos', 'extra_fields' => ['language' => 'en-US']], // +language
            'Watch Providers' => ['value' => 'watch/providers', 'extra_fields' => []],
        ];

        $seriesLink = $this->generateAdminUrl($this->generateUrl('admin_series'), [
            'l' => $limit,
            'o' => $order,
            'p' => $page,
            's' => $sort,
        ]);

        return $this->render('admin/index.html.twig', [
            'seriesLink' => $seriesLink,
            'series' => $series,
            'tmdbSeries' => $tmdbSeries,
            'seriesAdditionalOverviews' => $seriesAdditionalOverviews,
            'seriesBroadcastSchedule' => $seriesBroadcastSchedules,
            'seriesImages' => $seriesImages,
            'seriesLocalizedNames' => $seriesLocalizedNames,
            'seriesLocalizedOverviews' => $seriesLocalizedOverviews,
            'seriesNetworks' => $seriesNetworks,
            'seriesWatchLinks' => $seriesWatchLinks,
            'appendToResponse' => $appendToResponse,
            'appendToResponseDates' => ['end_date' => $now->format("Y-m-d"), 'start_date' => $start->format("Y-m-d")],
            'languages' => Languages::getNames(),
        ]);
    }

    #[Route('/series/search/id', name: 'series_search_by_id')]
    public function adminSeriesSearchById(Request $request): Response
    {
        $id = $request->query->get('id');
        if (!$id) {
            throw $this->createNotFoundException('TMDB ID not found');
        }
        $tmdbIds = array_column($this->seriesRepository->adminSeriesTmdbId(), 'tmdb_id');
        if (in_array($id, $tmdbIds)) {
            return $this->redirectToRoute('admin_series_edit', [
                'id' => $this->seriesRepository->adminSeriesByTmdbId($id)['id'],
            ]);
        }
        $tmdbSeries = json_decode(
            $this->tmdbService->getTv($id, 'en-US'),
            true
        );

        if (!$tmdbSeries) {
            throw $this->createNotFoundException('TMDB Series not found');
        }

        return $this->render('admin/index.html.twig', [
            'series' => $tmdbSeries,
            'posterUrl' => $this->imageConfiguration->getUrl('poster_sizes', 3),
            'backdropUrl' => $this->imageConfiguration->getUrl('backdrop_sizes', 3),
        ]);
    }

    #[Route('/series/search/name', name: 'series_search_by_name')]
    public function adminSeriesSearchByName(Request $request): Response
    {
        $name = $request->query->get('name', '');
        $page = $request->query->getInt('p', 1);

        if (!$name) {
            throw $this->createNotFoundException('TMDB ID not found');
        }

        $tmdbSeries = json_decode(
            $this->tmdbService->searchTv("&query=$name&include_adult=false&page=$page"),
            true
        );

        if (!$tmdbSeries) {
            throw $this->createNotFoundException('TMDB Series not found');
        }

        if ($tmdbSeries['total_results'] == 1) {
            return $this->redirectToRoute('admin_series_search_by_id', [
                'id' => $tmdbSeries['results'][0]['id'],
            ]);
        }

        $pagination = $this->generateLinks($tmdbSeries['total_pages'], $page, $this->generateUrl('admin_series_search_by_name'), [
            'name' => $name,
        ]);

        return $this->render('admin/index.html.twig', [
            'name' => $name,
            'seriesList' => $tmdbSeries,
            'pagination' => $pagination,
            'posterUrl' => $this->imageConfiguration->getUrl('poster_sizes', 3),
            'backdropUrl' => $this->imageConfiguration->getUrl('backdrop_sizes', 3),
        ]);
    }

    #[Route('/movies', name: 'movies')]
    public function adminMovies(Request $request): Response
    {
        list($sort, $order, $page, $limit) = $this->getParameters($request);

        $movies = $this->movieRepository->adminMovies($request->getLocale(), $page, $sort, $order, $limit);
        $movieCount = $this->movieRepository->count();
        $pageCount = ceil($movieCount / $limit);

        $logoUrl = $this->imageConfiguration->getUrl('logo_sizes', 3);
        $movies = array_map(function ($m) use ($logoUrl) {
            $m['origin_country'] = json_decode($m['origin_country'], true);
            $p = $m['provider'] ? explode('|', $m['provider']) : [null, null];

            $m['provider_logo'] = $this->seriesController->getProviderLogoFullPath($p[1], $logoUrl);
            $m['provider_name'] = $p[0];
            return $m;
        }, $movies);

        $pagination = $this->generateLinks($pageCount, $page, $this->generateUrl('admin_movies'), [
            's' => $sort,
            'o' => $order,
            'l' => $limit,
        ]);

        return $this->render('admin/index.html.twig', [
            'movies' => $movies,
            'movieCount' => $movieCount,
            'pagination' => $pagination,
            'page' => $page,
            'limit' => $limit,
            'pageCount' => $pageCount,
            'sort' => $sort,
            'order' => $order,
        ]);
    }

    #[Route('/movie/append', name: 'movie_append', methods: ['POST'])]
    public function adminMovieAppend(Request $request): Response
    {
        $data = $request->request->all();

        $movieId = $data['id'];
        $extra = $data['append_to_response'];
        $page = $data['page'] ?? null;
        $include_image_language = $data['include_image_language'] ?? null;
        $language = $data['language'] ?? null;

        $results = json_decode($this->tmdbService->getMovieExtras($movieId, $extra, $page, $include_image_language, $language), true);

        return $this->render('_blocks/admin/_movie-append-results.html.twig', [
                'extra' => $extra,
                'results' => $results,
                'urls' => [
                    'backdrop' => $this->imageConfiguration->getUrl('backdrop_sizes', 2),//w1280
                    'logo' => $this->imageConfiguration->getUrl('logo_sizes', 5), // w500
                    'poster' => $this->imageConfiguration->getUrl('poster_sizes', 5), // w780
                    'still' => $this->imageConfiguration->getUrl('still_sizes', 2), // w300
                    'profile' => $this->imageConfiguration->getUrl('profile_sizes', 2), // h632
                ],
            ]
        );
    }

    #[Route('/movie/{id}', name: 'movie_edit')]
    public function adminMovieEdit(Request $request, int $id): Response
    {
        list($sort, $order, $page, $limit) = $this->getParameters($request);

        $movie = $this->movieRepository->adminMovieById($id);
        if (!$movie) {
            throw $this->createNotFoundException('Series not found');
        }

        $tmdbMovie = json_decode(
            $this->tmdbService->getMovie(
                $movie['tmdb_id'],
                $request->getLocale()
            /*, ["images", "videos", "credits", "watch/providers", "content/ratings", "keywords", "similar", "translations"]*/),
            true);

        $logoUrl = $this->imageConfiguration->getUrl('logo_sizes', 3);
        $movie['origin_country'] = json_decode($movie['origin_country'], true);

        $movieAdditionalOverviews = $this->movieRepository->movieAdditionalOverviews($id);
        $movieImages = $this->movieRepository->movieImagesById($id);
        $movieLocalizedNames = $this->movieRepository->movieLocalizedNames($id);
        $movieLocalizedOverviews = $this->movieRepository->movieLocalizedOverviews($id);
        $movieDirectLinks = array_map(function ($swl) use ($logoUrl) {
            $swl['provider_logo'] = $this->seriesController->getProviderLogoFullPath($swl['provider_logo'], $logoUrl);
            return $swl;
        }, $this->movieRepository->movieDirectLinks($id));

        $movieLink = $this->generateAdminUrl($this->generateUrl('admin_movies'), [
            'l' => $limit,
            'o' => $order,
            'p' => $page,
            's' => $sort,
        ]);
        $movie['release_types'] = [
            '1' => 'Premiere',
            '2' => 'Theatrical (limited)',
            '3' => 'Theatrical',
            '4' => 'Digital',
            '5' => 'Physical',
            '6' => 'TV',
        ];
        $now = $this->dateService->getNowImmutable("Europe/Paris", true);
        $start = $now->modify('-14 days');
        $movie['append_to_response'] = 'translations';
        $appendToResponse = [
            'Changes' => ['value' => 'changes', 'extra_fields' => ['end_date' => $now->format("Y-m-d"), 'start_date' => $start->format("Y-m-d"), 'page' => 1]], // +page
            'Credits' => ['value' => 'credits', 'extra_fields' => ['language' => 'en-US']], // +language
            'External IDs' => ['value' => 'external_ids', 'extra_fields' => []],
            'Images' => ['value' => 'images', 'extra_fields' => ['include_image_language' => 'fr,null', 'language' => '']], // +include_image_language (specify a comma separated list of ISO-639-1 values to query, for example: en,null), language
            'Keywords' => ['value' => 'keywords', 'extra_fields' => []],
            'Lists' => ['value' => 'lists', 'extra_fields' => ['language' => 'en-US', 'page' => 1]], // +language, page
            'Release Dates' => ['value' => 'release_dates', 'extra_fields' => []], // see $movie['release_types']
            'Reviews' => ['value' => 'reviews', 'extra_fields' => ['language' => 'en-US', 'page' => 1]], // +language, page
            'Translations' => ['value' => 'translations', 'extra_fields' => []],
            'Videos' => ['value' => 'videos', 'extra_fields' => ['language' => 'en-US']], // +language
            'Watch Providers' => ['value' => 'watch/providers', 'extra_fields' => []],
        ];

        return $this->render('admin/index.html.twig', [
            'movieLink' => $movieLink,
            'movie' => $movie,
            'tmdbMovie' => $tmdbMovie,
            'movieAdditionalOverviews' => $movieAdditionalOverviews,
            'movieImages' => $movieImages,
            'movieLocalizedNames' => $movieLocalizedNames,
            'movieLocalizedOverviews' => $movieLocalizedOverviews,
            'movieDirectLinks' => $movieDirectLinks,
            'appendToResponse' => $appendToResponse,
            'appendToResponseDates' => ['end_date' => $now->format("Y-m-d"), 'start_date' => $start->format("Y-m-d")],
            'languages' => Languages::getNames(),
        ]);
    }

    #[Route('/providers', name: 'providers')]
    public function adminProviders(Request $request): Response
    {
        list($sort, $order, $page, $limit) = $this->getParameters($request);

        $providers = $this->watchProviderRepository->adminProviders($page, $sort, $order, $limit);
        $providerCount = $this->watchProviderRepository->count();
        $pageCount = ceil($providerCount / $limit);
        $logoUrl = $this->imageConfiguration->getUrl('logo_sizes', 3);
        $providers = array_map(function ($p) use ($logoUrl) {
            $p['custom_provider'] = str_starts_with($p['logo_path'], '+');
            $p['logo_path'] = $this->seriesController->getProviderLogoFullPath($p['logo_path'], $logoUrl);
            $p['display_priorities'] = json_decode($p['display_priorities'], true);
            return $p;
        }, $providers);
        $pagination = $this->generateLinks($pageCount, $page, $this->generateUrl('admin_providers'), [
            's' => $sort,
            'o' => $order,
            'l' => $limit,
        ]);

        return $this->render('admin/index.html.twig', [
            'providers' => $providers,
            'providerCount' => $providerCount,
            'pagination' => $pagination,
            'page' => $page,
            'limit' => $limit,
            'pageCount' => $pageCount,
            'sort' => $sort,
            'order' => $order,
        ]);
    }

    #[Route('/provider/{id}', name: 'provider_edit')]
    public function adminProviderEdit(Request $request, int $id): Response
    {
        list($sort, $order, $page, $limit) = $this->getParameters($request);

        $provider = $this->watchProviderRepository->adminProviderById($id);
        if (!$provider) {
            throw $this->createNotFoundException('Provider not found');
        }

        $logoUrl = $this->imageConfiguration->getUrl('logo_sizes', 5); // w500
        $provider['custom_provider'] = str_starts_with($provider['logo_path'], '+');
        $provider['logo_path'] = $this->seriesController->getProviderLogoFullPath($provider['logo_path'], $logoUrl);
        $provider['display_priorities'] = json_decode($provider['display_priorities'], true);

        $tvProviderList = json_decode(
            $this->tmdbService->getTvWatchProviderList($request->getLocale()),
            true
        );
        $tvProvider = array_find($tvProviderList['results'], function ($p) use ($provider) {
            return $p['provider_id'] === $provider['provider_id'];
        });
        if ($tvProvider) {
            $tvProvider['logo_path'] = $this->seriesController->getProviderLogoFullPath($tvProvider['logo_path'], $logoUrl);
        }

        $providersLink = $this->generateAdminUrl($this->generateUrl('admin_providers'), [
            'l' => $limit,
            'o' => $order,
            'p' => $page,
            's' => $sort,
        ]);

        return $this->render('admin/index.html.twig', [
            'providersLink' => $providersLink,
            'provider' => $provider,
            'tvProvider' => $tvProvider,
            'logoUrl' => $logoUrl,
        ]);
    }

    #[Route('/filming/locations', name: 'filming_locations')]
    public function adminFilmingLocations(Request $request): Response
    {
        list($sort, $order, $page, $limit) = $this->getParameters($request);

        // Implement the logic to fetch filming locations from the database or an API.
        // For now, we will return an empty array as a placeholder.
        $locations = $this->filmingLocationRepository->adminLocations($page, $sort, $order, $limit);
        $locationCount = $this->filmingLocationRepository->count(['isSeries' => true]);
        $pageCount = ceil($locationCount / $limit);

        $locations = array_map(function ($l) {
            $l['created_at'] = $this->dateService->formatDateRelativeMedium($l['created_at'], 'UTC', 'fr') . " " . $this->translator->trans('at') . " " . substr($l['created_at'], 11, 5);
            if (!is_numeric($l['created_at'][0])) $l['created_at'] = ucfirst($l['created_at']);
            $l['updated_at'] = $this->dateService->formatDateRelativeMedium($l['updated_at'], 'UTC', 'fr') . " " . $this->translator->trans('at') . " " . substr($l['updated_at'], 11, 5);
            if (!is_numeric($l['updated_at'][0])) $l['updated_at'] = ucfirst($l['updated_at']);
            $l['origin_country'] = json_decode($l['origin_country'], true);
            return $l;
        }, $locations);

        $pagination = $this->generateLinks($pageCount, $page, $this->generateUrl('admin_filming_locations'), [
            's' => $sort,
            'o' => $order,
            'l' => $limit,
        ]);

        return $this->render('admin/index.html.twig', [
            'locations' => $locations,
            'locationCount' => $locationCount,
            'pagination' => $pagination,
            'page' => $page,
            'limit' => $limit,
            'pageCount' => $pageCount,
            'sort' => $sort,
            'order' => $order,
        ]);
    }

    #[Route('/filming/location/{id}', name: 'filming_location_edit')]
    public function adminFilmingLocationEdit(Request $request, int $id): Response
    {
        list($sort, $order, $page, $limit) = $this->getParameters($request);

        $location = $this->filmingLocationRepository->getOne("SELECT fl.*, fli.path as still_path FROM filming_location fl LEFT JOIN filming_location_image fli on fl.id = fli.filming_location_id WHERE fl.id = $id");
        if (!$location) {
            throw $this->createNotFoundException('Filming location not found');
        }
        $location['origin_country'] = json_decode($location['origin_country'], true);
        $location['created_at'] = $this->dateService->formatDateRelativeShort($location['created_at'], 'Europe/Paris', 'fr') . " " . $this->translator->trans('at') . " " . substr($location['created_at'], 11, 5);
        if (!is_numeric($location['created_at'][0])) {
            $location['created_at'] = ucfirst($location['created_at']);
        }
        $location['updated_at'] = $this->dateService->formatDateRelativeShort($location['updated_at'], 'Europe/Paris', 'fr') . " " . $this->translator->trans('at') . " " . substr($location['updated_at'], 11, 5);
        if (!is_numeric($location['updated_at'][0])) {
            $location['updated_at'] = ucfirst($location['updated_at']);
        }

        $locationImages = $this->filmingLocationRepository->locationImages([$id]);
        $location['images'] = array_map(function ($img) {
            return [
                'id' => $img['id'],
                'path' => $img['path'],
            ];
        }, $locationImages);

        $filmingLocationsLink = $this->generateAdminUrl($this->generateUrl('admin_filming_locations'), [
            'l' => $limit,
            'o' => $order,
            'p' => $page,
            's' => $sort,
        ]);

        return $this->render('admin/index.html.twig', [
            'filmingLocationsLink' => $filmingLocationsLink,
            'location' => $location,
            'images' => $location['images'],
        ]);
    }

    #[Route('/points-of-interest', name: 'points_of_interest')]
    public function adminPointOfInterests(Request $request): Response
    {
        list($sort, $order, $page, $limit) = $this->getParameters($request);

        // Implement the logic to fetch filming locations from the database or an API.
        // For now, we will return an empty array as a placeholder.
        $pois = $this->pointOfInterestRepository->adminPointsOfInterest($page, $sort, $order, $limit);
        dump($pois);
        $poiCount = $this->pointOfInterestRepository->count();
        $pageCount = ceil($poiCount / $limit);

        $pois = array_map(function ($poi) {
            $poi['created_at'] = $this->dateService->formatDateRelativeMedium($poi['created_at'], 'UTC', 'fr') . " " . $this->translator->trans('at') . " " . substr($poi['created_at'], 11, 5);
            if (!is_numeric($poi['created_at'][0])) $poi['created_at'] = ucfirst($poi['created_at']);
            $poi['updated_at'] = $this->dateService->formatDateRelativeMedium($poi['updated_at'], 'UTC', 'fr') . " " . $this->translator->trans('at') . " " . substr($poi['updated_at'], 11, 5);
            if (!is_numeric($poi['updated_at'][0])) $poi['updated_at'] = ucfirst($poi['updated_at']);
            return $poi;
        }, $pois);

        $pagination = $this->generateLinks($pageCount, $page, $this->generateUrl('admin_points_of_interest'), [
            's' => $sort,
            'o' => $order,
            'l' => $limit,
        ]);

        return $this->render('admin/index.html.twig', [
            'pois' => $pois,
            'poiCount' => $poiCount,
            'pagination' => $pagination,
            'page' => $page,
            'limit' => $limit,
            'pageCount' => $pageCount,
            'sort' => $sort,
            'order' => $order,
        ]);
    }

    #[Route('/point-of-interest/{id}', name: 'point_of_interest_edit')]
    public function adminPointOfInterestEdit(Request $request, int $id): Response
    {
        list($sort, $order, $page, $limit) = $this->getParameters($request);

        $poi = $this->pointOfInterestRepository->adminPointOfInterest($id);
        if (!$poi) {
            throw $this->createNotFoundException('Point of Interest not found');
        }
        $poi['created_at'] = $this->dateService->formatDateRelativeShort($poi['created_at'], 'Europe/Paris', 'fr') . " " . $this->translator->trans('at') . " " . substr($poi['created_at'], 11, 5);
        if (!is_numeric($poi['created_at'][0])) {
            $poi['created_at'] = ucfirst($poi['created_at']);
        }
        $poi['updated_at'] = $this->dateService->formatDateRelativeShort($poi['updated_at'], 'Europe/Paris', 'fr') . " " . $this->translator->trans('at') . " " . substr($poi['updated_at'], 11, 5);
        if (!is_numeric($poi['updated_at'][0])) {
            $poi['updated_at'] = ucfirst($poi['updated_at']);
        }
        $poiImages = $this->pointOfInterestRepository->adminPointOfInterestImages($id);
        $poi['images'] = array_map(function ($img) {
            return [
                'id' => $img['id'],
                'path' => $img['path'],
            ];
        }, $poiImages);
        $poiLink = $this->generateAdminUrl($this->generateUrl('admin_points_of_interest'), [
            'l' => $limit,
            'o' => $order,
            'p' => $page,
            's' => $sort,
        ]);
        return $this->render('admin/index.html.twig', [
            'poiLink' => $poiLink,
            'poi' => $poi,
            'images' => $poiImages,
        ]);
    }

    #[Route('/videos', name: 'videos')]
    public function videos(Request $request): Response
    {
        list($sort, $order, $page, $limit) = $this->getParameters($request);

        // Implement the logic to fetch filming locations from the database or an API.
        // For now, we will return an empty array as a placeholder.
        $videos = $this->videoRepository->adminVideos($page, $sort, $order, $limit);
        $videoCount = $this->videoRepository->count();
        $pageCount = ceil($videoCount / $limit);

        $videos = array_map(function ($v) {
            $v['published_at'] = $this->dateService->formatDateRelativeMedium($v['published_at'], 'UTC', 'fr') . " " . $this->translator->trans('at') . " " . substr($v['published_at'], 11, 5);
            if (!is_numeric($v['published_at'][0])) $l['created_at'] = ucfirst($v['published_at']);
            $v['updated_at'] = $this->dateService->formatDateRelativeMedium($v['updated_at'], 'UTC', 'fr') . " " . $this->translator->trans('at') . " " . substr($v['updated_at'], 11, 5);
            if (!is_numeric($v['updated_at'][0])) $l['created_at'] = ucfirst($v['updated_at']);
            $v['duration'] = $this->videoController->formatDuration($v['duration']);
            return $v;
        }, $videos);

        $pagination = $this->generateLinks($pageCount, $page, $this->generateUrl('admin_videos'), [
            's' => $sort,
            'o' => $order,
            'l' => $limit,
        ]);

        return $this->render('admin/index.html.twig', [
            'videos' => $videos,
            'videoCount' => $videoCount,
            'pagination' => $pagination,
            'page' => $page,
            'limit' => $limit,
            'pageCount' => $pageCount,
            'sort' => $sort,
            'order' => $order,
        ]);
    }

    #[Route('/video/{id}', name: 'video_edit')]
    public function videoEdit(Request $request, int $id): Response
    {
        $video = $this->videoRepository->find($id);
        if (!$video) {
            throw $this->createNotFoundException('Video not found');
        }
        $categories = $this->categoryRepository->findAll();

        $publishedDate = $video->getPublishedAt();
        $publishedAt = $this->dateService->formatDateRelativeShort($publishedDate->format('Y-m-d H:i:s'), 'Europe/Paris', 'fr');
        if (is_numeric($publishedAt[0])) {
            $publishedAt = $this->translator->trans("Published at") . ' ' . $publishedAt;
        } else {
            $publishedAt = $this->translator->trans("Published") . ' ' . $publishedAt;
        }

        return $this->render('video/show.html.twig', [
            'userVideo' => null,
            'video' => $video,
            'publishedAt' => $publishedAt,
            'categories' => $categories,
            'previousVideo' => null,
            'nextVideo' => null,
        ]);
    }

    private function getParameters(Request $request): array
    {
        return [
            $request->query->get('s', 'id'),
            $request->query->get('o', 'desc'),
            $request->query->getInt('p', 1),
            $request->query->getInt('l', 20),
        ];
    }

    public function generateLinks(int $totalPages, int $currentPage, string $route, array $queryParams = []): string
    {
        if ($totalPages <= 1) {
            return ''; // No need to display pagination if there's only one page.
        }

        $paginationHtml = '<div class="pagination">';

        // Add "Previous" button
        if ($currentPage > 1) {
            $paginationHtml .= $this->generateLink($currentPage - 1, $this->translator->trans('Previous page'), $route, $queryParams);
        }

        // Display pages 1-4
        for ($page = 1; $page <= min(4, $totalPages); $page++) {
            $activeClass = ($page === $currentPage) ? ' active' : '';
            $paginationHtml .= $this->generateLink($page, (string)$page, $route, $queryParams, $activeClass);
        }

        // If we're past page 4, add an ellipsis
        if ($totalPages > 4 && $currentPage > 4) {
            $paginationHtml .= '<span>...</span>';
        }

        // Add the current page and a couple of neighbors if we're past page 4
        $startPage = max(5, $currentPage);
        $endPage = min($currentPage + 1, $totalPages - 1);  // Stop one before the last page

        for ($page = $startPage; $page <= $endPage; $page++) {
            $activeClass = ($page === $currentPage) ? ' active' : '';
            $paginationHtml .= $this->generateLink($page, (string)$page, $route, $queryParams, $activeClass);
        }

        // Add ellipsis before the last page, if necessary
        if ($endPage < $totalPages - 1) {
            $paginationHtml .= '<span>...</span>';
        }

        // Add the last page link
        if ($totalPages > 4) {
            $activeClass = ($totalPages === $currentPage) ? ' active' : '';
            $paginationHtml .= $this->generateLink($totalPages, (string)$totalPages, $route, $queryParams, $activeClass);
        }

        // Add "Next" button
        if ($currentPage < $totalPages) {
            $paginationHtml .= $this->generateLink($currentPage + 1, $this->translator->trans('Next page'), $route, $queryParams);
        }

        $paginationHtml .= '</div>';

        return $paginationHtml;
    }

    private function generateLink(int $page, string $label, string $route, array $queryParams, string $activeClass = ''): string
    {
        $queryParams['p'] = $page;
        $url = $route . '?' . http_build_query($queryParams);
        return sprintf('<a href="%s" class="page%s">%s</a>', $url, $activeClass, $label);
    }

    private function generateAdminUrl(string $route, array $queryParams): string
    {
        return $route . '?' . http_build_query($queryParams);
    }
}
