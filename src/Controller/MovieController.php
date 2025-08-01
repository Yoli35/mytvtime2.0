<?php

namespace App\Controller;

use App\DTO\MovieSearchDTO;
use App\Entity\Movie;
use App\Entity\MovieAdditionalOverview;
use App\Entity\MovieCollection;
use App\Entity\MovieDirectLink;
use App\Entity\MovieLocalizedName;
use App\Entity\MovieLocalizedOverview;
use App\Entity\Settings;
use App\Entity\User;
use App\Entity\UserMovie;
use App\Form\MovieSearchType;
use App\Repository\MovieAdditionalOverviewRepository;
use App\Repository\MovieCollectionRepository;
use App\Repository\MovieDirectLinkRepository;
use App\Repository\MovieLocalizedNameRepository;
use App\Repository\MovieLocalizedOverviewRepository;
use App\Repository\MovieRepository;
use App\Repository\SettingsRepository;
use App\Repository\SourceRepository;
use App\Repository\UserMovieRepository;
use App\Repository\WatchProviderRepository;
use App\Service\DateService;
use App\Service\ImageConfiguration;
use App\Service\ImageService;
use App\Service\KeywordService;
use App\Service\MovieService;
use App\Service\TMDBService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Contracts\Translation\TranslatorInterface;

/** @method User|null getUser() */
#[Route('/{_locale}/movie', name: 'app_movie_', requirements: ['_locale' => 'fr|en|ko'])]
class MovieController extends AbstractController
{
    public function __construct(
        private readonly DateService                       $dateService,
        private readonly ImageConfiguration                $imageConfiguration,
        private readonly ImageService                      $imageService,
        private readonly KeywordService                    $keywordService,
        private readonly MovieAdditionalOverviewRepository $movieAdditionalOverviewRepository,
        private readonly MovieCollectionRepository         $movieCollectionRepository,
        private readonly MovieDirectLinkRepository         $movieDirectLinkRepository,
        private readonly MovieLocalizedNameRepository      $movieLocalizedNameRepository,
        private readonly MovieLocalizedOverviewRepository  $movieLocalizedOverviewRepository,
        private readonly MovieRepository                   $movieRepository,
        private readonly MovieService                      $movieService,
        private readonly SettingsRepository                $settingsRepository,
        private readonly SourceRepository                  $sourceRepository,
        private readonly TMDBService                       $tmdbService,
        private readonly TranslatorInterface               $translator,
        private readonly UserMovieRepository               $userMovieRepository,
        private readonly WatchProviderRepository           $watchProviderRepository,
    )
    {
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/index', name: 'index')]
    public function index(Request $request): Response
    {
        $user = $this->getUser();

        $filtersBoxSettings = $this->settingsRepository->findOneBy(['user' => $user, 'name' => 'my movies boxes']);
        if (!$filtersBoxSettings) {
            $filtersBoxSettings = new Settings($user, 'my movies boxes', ['filters' => true, 'pages' => true]);
            $this->settingsRepository->save($filtersBoxSettings, true);
            $filterBoxOpen = true;
            $pageBoxOpen = true;
        } else {
            $data = $filtersBoxSettings->getData();
            $filterBoxOpen = $data['filters'];
            $pageBoxOpen = $data['pages'];
        }
        $page = 1;
        $settings = $this->settingsRepository->findOneBy(['user' => $user, 'name' => 'my movies']);
        // Parameters count
        if (!count($request->query->all())) {
            if (!$settings) {
                $settings = new Settings($user, 'my movies', ['perPage' => 10, 'sort' => 'releaseDate', 'order' => 'DESC']);
                $this->settingsRepository->save($settings, true);
            }
        } else {
            // /fr/series/all?sort=episodeAirDate&order=DESC&startStatus=series-not-started&endStatus=series-not-watched&perPage=10
            $paramSort = $request->get('sort');
            $paramOrder = $request->get('order');
            $paramPerPage = $request->get('perPage');
            $paramTitle = $request->get('title');
            $settings->setData([
                'title' => $paramTitle,
                'perPage' => $paramPerPage,
                'sort' => $paramSort,
                'order' => $paramOrder,
            ]);
            $this->settingsRepository->save($settings, true);
            $page = $request->get('page') ?? 1;
        }
        $data = $settings->getData();
        $filters = [
            'title' => $data['title'],
            'page' => $page,
            'perPage' => $data['perPage'],
            'sort' => $data['sort'],
            'order' => $data['order'],
        ];
        $userMovies = array_map(function ($movie) {
            $this->imageService->saveImage("posters", $movie['posterPath'], $this->imageConfiguration->getUrl('poster_sizes', 5), '/movies/');
            return $movie;
        }, $this->movieRepository->getMovieCards($user, $filters));

        $userMovieCount = $this->movieRepository->countMovieCards($user, $filters);

        $filterMeanings = [
            'name' => 'Name',
            'releaseDate' => 'Release date',
            'addedAt' => 'Date added',
            'DESC' => 'Descending',
            'ASC' => 'Ascending',
        ];

        return $this->render('movie/index.html.twig', [
            'userMovies' => $userMovies,
            'userMovieCount' => $userMovieCount,
            'pages' => ceil($userMovieCount / $filters['perPage']),
            'filterMeanings' => $filterMeanings,
            'filterBoxOpen' => $filterBoxOpen,
            'pageBoxOpen' => $pageBoxOpen,
            'filters' => $filters,
        ]);
    }

    #[Route('/search/all', name: 'search')]
    public function search(Request $request): Response
    {
        if ($request->get('q')) {
            $simpleSeriesSearch = new MovieSearchDTO($request->getLocale(), 1);
            $simpleSeriesSearch->setQuery($request->get('q'));
        } else {
            // on récupère le contenu du formulaire (POST parameters).
            $formContent = $request->get('movie_search', ['query' => '', 'releaseDateYear' => null, 'language' => $request->getLocale(), 'page' => 1]);
            $simpleSeriesSearch = new MovieSearchDTO($formContent['language'], $formContent['page']);
            $simpleSeriesSearch->setQuery($formContent['query']);
            $releaseYear = $formContent['releaseDateYear'] ? intval($formContent['releaseDateYear']) : null;
            $releaseYear = !$releaseYear ? null : $releaseYear;
            $simpleSeriesSearch->setReleaseDateYear($releaseYear);
        }

        $simpleForm = $this->createForm(MovieSearchType::class, $simpleSeriesSearch);
        $searchResult = $this->handleSearch($simpleSeriesSearch);
        if ($searchResult['total_results'] == 1) {
            return $this->getOneResult($searchResult['results'][0]);
        }
        $movies = $this->getSearchResult($searchResult);

        return $this->render('movie/search.html.twig', [
            'form' => $simpleForm->createView(),
            'title' => 'Search a movie',
            'movieList' => $movies,
            'results' => [
                'total_results' => $searchResult['total_results'] ?? -1,
                'total_pages' => $searchResult['total_pages'] ?? 0,
                'page' => $searchResult['page'] ?? 0,
            ],
        ]);
    }

    public function handleSearch(MovieSearchDTO $simpleMovieSearch): mixed
    {
        $query = $simpleMovieSearch->getQuery();
        $language = $simpleMovieSearch->getLanguage();
        $page = $simpleMovieSearch->getPage();
        $releaseDateYear = $simpleMovieSearch->getReleaseDateYear();

        $searchString = "&query=$query&include_adult=false&page=$page";
        if (strlen($releaseDateYear)) $searchString .= "&year=$releaseDateYear";
        if (strlen($language)) $searchString .= "&language=$language";

        return json_decode($this->tmdbService->searchMovie($searchString), true);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/show/{id}', name: 'show', requirements: ['id' => '\d+'])]
    public function show(Request $request, UserMovie $userMovie): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $locale = $request->getLocale();
        $language = "en-US";//($user->getPreferredLanguage() ?? $locale) . '-' . ($user->getCountry() ?? ($locale === 'fr' ? 'FR' : 'US'));
        $country = $user->getCountry() ?? 'FR';

        $tmdbId = $userMovie->getMovie()->getTmdbId();
        $dbMovie = $userMovie->getMovie();

        $movie = json_decode($this->tmdbService->getMovie($tmdbId, $language, ['videos,images,credits,recommendations,keywords,watch/providers,release_dates']), true);
        if (!$movie) {
            $movie = $this->createMovieFromDBMovie($dbMovie);
            //TODO: if movie not found on tmdb, ask for removal
        } else {
            $movie['found'] = true;
            $this->imageService->saveImage("posters", $movie['poster_path'], $this->imageConfiguration->getUrl('poster_sizes', 5), '/movies/');
            $this->imageService->saveImage("backdrops", $movie['backdrop_path'], $this->imageConfiguration->getUrl('backdrop_sizes', 3), '/movies/');
            if ($movie['belongs_to_collection']) {
                $this->imageService->saveImage("posters", $movie['belongs_to_collection']['poster_path'], $this->imageConfiguration->getUrl('poster_sizes', 5), '/movies/');
                $this->imageService->saveImage("backdrops", $movie['belongs_to_collection']['backdrop_path'], $this->imageConfiguration->getUrl('backdrop_sizes', 3), '/movies/');
            }
            $updated = $this->movieService->checkMovieImage('', $movie, $dbMovie, 'backdrop');
            $updated = $this->movieService->checkMovieImage('', $movie, $dbMovie, 'poster') || $updated;

            $updated = $this->movieService->checkMovieCollection('', $movie, $dbMovie) || $updated;

            $updated = $this->movieService->checkMovieInfos('', $movie, $dbMovie, $user->getCountry() ?? "FR") || $updated;

            if ($updated) {
                $now = $this->dateService->newDateImmutable('now', $user->getTimezone() ?? 'Europe/Paris');
                $dbMovie->setUpdatedAt($now);
                $this->movieRepository->save($dbMovie, true);
            }
        }

        $this->getBelongToCollection($movie);
        $this->getCredits($movie);
        $this->getProviders($movie, $country);
        $this->getProductionCompanies($movie);
        $this->getReleaseDates($movie);
        $this->getRecommandations($movie);
        $this->getDirectLinks($movie, $dbMovie);
        $this->getAdditionalOverviews($movie, $dbMovie);
        $this->getLocalizedName($movie, $dbMovie);
        $this->getLocalizedOverviews($movie, $dbMovie);
        $this->getSources($movie);
        $movie['missing_translations'] = $this->keywordService->keywordsTranslation($movie['keywords']['keywords'], $request->getLocale());

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
        ];
        $providers = $this->getWatchProviders($user->getPreferredLanguage() ?? $request->getLocale(), $user->getCountry() ?? 'FR');

        return $this->render('movie/show.html.twig', [
            'userMovie' => $userMovie,
            'movie' => $movie,
            'dbMovie' => $dbMovie,
            'providers' => $providers,
            'translations' => $translations,
        ]);
    }

    #[Route('/tmdb/{id}', name: 'tmdb', requirements: ['id' => '\d+'])]
    public function tmdb(Request $request, int $id): Response
    {
        $user = $this->getUser();
        if ($user) {
            $movie = $this->movieRepository->findOneBy(['tmdbId' => $id]);
            if ($movie) {
                $userMovie = $this->userMovieRepository->findOneBy(['movie' => $movie, 'user' => $user]);
                if ($userMovie) {
                    return $this->redirectToRoute('app_movie_show', ['id' => $userMovie->getId()]);
                }
            }
        }
        $locale = $user->getPreferredLanguage() ?? $request->getLocale();
        $language = $locale === 'fr' ? 'fr-FR' : 'en-US';
        $country = $user->getCountry() ?? 'FR';
        $movie = json_decode($this->tmdbService->getMovie($id, $language, ['videos,images,credits,recommendations,watch/providers,release_dates']), true);

        $this->imageService->saveImage("posters", $movie['poster_path'], $this->imageConfiguration->getUrl('poster_sizes', 5), '/movies/');
        $this->imageService->saveImage("backdrops", $movie['backdrop_path'], $this->imageConfiguration->getUrl('backdrop_sizes', 3), '/movies/');
        if ($movie['belongs_to_collection']) {
            $this->imageService->saveImage("posters", $movie['belongs_to_collection']['poster_path'], $this->imageConfiguration->getUrl('poster_sizes', 5), '/movies/');
            $this->imageService->saveImage("backdrops", $movie['belongs_to_collection']['backdrop_path'], $this->imageConfiguration->getUrl('backdrop_sizes', 3), '/movies/');
        }
        $this->getCredits($movie);
        $this->getProviders($movie, $country);
        $this->getReleaseDates($movie);
        $this->getRecommandations($movie);

        return $this->render('movie/tmdb.html.twig', [
            'movie' => $movie,
            'providers' => [],
            'translations' => [],
        ]);
    }

    #[Route('/collection/{id}', name: 'collection', requirements: ['id' => '\d+'])]
    public function collection(int $id): Response
    {
        $collection = json_decode($this->tmdbService->getMovieCollection($id), true);

        $this->imageService->saveImage("posters", $collection['poster_path'], $this->imageConfiguration->getUrl('poster_sizes', 5), '/movies/');
        $this->imageService->saveImage("backdrops", $collection['backdrop_path'], $this->imageConfiguration->getUrl('backdrop_sizes', 3), '/movies/');

        foreach ($collection['parts'] as $part) {
            $this->imageService->saveImage("posters", $part['poster_path'], $this->imageConfiguration->getUrl('poster_sizes', 5), '/movies/');
            $this->imageService->saveImage("backdrops", $part['backdrop_path'], $this->imageConfiguration->getUrl('backdrop_sizes', 3), '/movies/');
        }

        return $this->render('movie/collection.html.twig', [
            'collection' => $collection,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/add/{id}', name: 'add', requirements: ['id' => '\d+'])]
    public function add(int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $movie = $this->movieRepository->findOneBy(['tmdbId' => $id]);
        if ($movie) {
            $userMovie = $this->userMovieRepository->findOneBy(['movie' => $movie, 'user' => $user]);
            if ($userMovie) {
                return $this->redirectToRoute('app_movie_show', ['id' => $userMovie->getId()]);
            }
        } else {
            $tmdbMovie = json_decode($this->tmdbService->getMovie($id, 'fr-FR', ['videos,images,credits,recommendations,watch/providers,release_dates']), true);
            $movie = new Movie($tmdbMovie);
            $movie->setCollection($this->getCollection($tmdbMovie));
            $this->movieRepository->save($movie, true);
        }

        $timezone = $user->getTimezone() ?? 'Europe/Paris';
        $now = $this->dateService->getNowImmutable($timezone);
        $userMovie = new UserMovie($user, $movie, $now);
        $this->userMovieRepository->save($userMovie, true);

        return $this->redirectToRoute('app_movie_show', ['id' => $userMovie->getId()]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/remove/{id}', name: 'remove', requirements: ['id' => '\d+'])]
    public function remove(int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $userMovie = $this->userMovieRepository->findOneBy(['id' => $id]);

        if ($userMovie && $userMovie->getUser() === $user) {
            $this->userMovieRepository->remove($userMovie, true);
        }

        return $this->json([
            'ok' => true,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/filter', name: 'filter', methods: ['POST'])]
    public function filter(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);
        $filters = [];
        foreach ($data as $filter) {
            $filters[$filter['key']] = $filter['value'];
        }

        $settings = $this->settingsRepository->findOneBy(['user' => $user, 'name' => 'my movies']);
        $settings->setData($filters);
        $this->settingsRepository->save($settings, true);

        $userMovies = array_map(function ($movie) use ($user) {
            $this->imageService->saveImage("posters", $movie['posterPath'], $this->imageConfiguration->getUrl('poster_sizes', 5), '/movies/');
            // release_date: 2024-07-24 → 24 juillet 2024
            $movie['releaseDateString'] = ucfirst($this->dateService->formatDateLong($movie['releaseDate'], $user->getTimezone() ?? 'Europe/Paris', $user->getPreferredLanguage() ?? 'fr'));
            $movie['lastViewedAtString'] = $movie['lastViewedAt'] ? ucfirst($this->dateService->formatDateLong($movie['lastViewedAt'], $user->getTimezone() ?? 'Europe/Paris', $user->getPreferredLanguage() ?? 'fr')) : null;
            return $movie;
        }, $this->movieRepository->getMovieCards($user, $filters));

        $userMovieCount = $this->movieRepository->countMovieCards($user, $filters);
        $totalPages = ceil($userMovieCount / $filters['perPage']);

        $paginations[0] = $this->getPagination(1, $filters['page'], $totalPages, 'app_movie_index', $request->getLocale());
        $paginations[1] = $this->getPagination(2, $filters['page'], $totalPages, 'app_movie_index', $request->getLocale());

        return $this->json([
            'ok' => true,
            'body' => [
                'userMovies' => $userMovies,
                'userMovieCount' => $userMovieCount,
                'pages' => $totalPages,
                'filters' => $filters,
                'paginationSections' => $paginations,
            ],
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/rating/{id}', name: 'rating', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function ratingMovie(Request $request, UserMovie $userMovie): Response
    {
        $data = json_decode($request->getContent(), true);
        $rating = $data['rating'];
        $userMovie->setRating($rating);
        $this->userMovieRepository->save($userMovie, true);

        return $this->json([
            'ok' => true,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/favorite/{id}', name: 'favorite', requirements: ['id' => Requirement::DIGITS])]
    public function favoriteSeries(Request $request, UserMovie $userMovie): Response
    {
        $data = json_decode($request->getContent(), true);
        $newFavoriteValue = $data['favorite'];
        $userMovie->setFavorite($newFavoriteValue);
        $this->userMovieRepository->save($userMovie, true);

        return $this->json([
            'ok' => true,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/add/direct/link/{id}', name: 'add_watch_link', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function addWatchLink(Request $request, UserMovie $userMovie): Response
    {
        $data = json_decode($request->getContent(), true);
        $url = $data['url'];
        $title = $data['title'];
        $providerId = $data['provider'];
        if ($providerId == "") $providerId = null;

        $movie = $userMovie->getMovie();

        $watchLink = new MovieDirectLink($url, $title, $movie, $providerId);
        $this->movieDirectLinkRepository->save($watchLink, true);

        return $this->json([
            'ok' => true,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/viewed/{id}', name: 'viewed', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function addViewedDate(Request $request, UserMovie $userMovie): Response
    {
        $data = json_decode($request->getContent(), true);
        $viewed = $data['viewed'];

        $now = $this->dateService->getNowImmutable($userMovie->getUser()->getTimezone() ?? 'Europe/Paris');

        if ($viewed) {
            $viewArray = $userMovie->getViewArray();
            $lastViewedAt = $userMovie->getLastViewedAt();
            $viewArray[] = $lastViewedAt;
            $userMovie->setViewArray($viewArray);
        } else {
            $userMovie->setLastViewedAt($now);
        }
        $this->userMovieRepository->save($userMovie, true);

        return $this->json([
            'ok' => true,
            'body' => [
                'viewed' => $viewed,
                'lastViewedAt' => $this->dateService->formatDateRelativeLong($now->format("Y-m-d H:i:s"), 'UTC', $userMovie->getUser()->getPreferredLanguage() ?? 'fr')
            ],
        ]);
    }

    #[Route('/add/localized/name/{id}', name: 'add_localized_name', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function addLocalizedName(Request $request, UserMovie $userMovie): Response
    {
        $data = json_decode($request->getContent(), true);
        $name = $data['name'];
        $movie = $this->movieRepository->findOneBy(['id' => $userMovie->getMovie()->getId()]);
        $slugger = new AsciiSlugger();

        $localizedName = $this->movieLocalizedNameRepository->findOneBy(['movie' => $movie, 'locale' => $request->getLocale()]);
        if ($localizedName) {
            $localizedName->setName($name);
            $localizedName->setSlug($slugger->slug($name));
        } else {
            $slug = $slugger->slug($name)->lower()->toString();
            $localizedName = new MovieLocalizedName($movie, $name, $slug, $request->getLocale());
        }
        $this->movieLocalizedNameRepository->save($localizedName, true);

        return $this->json([
            'ok' => true,
        ]);
    }

//    #[IsGranted('ROLE_USER')]
    #[Route('/add/edit/overview/{id}', name: 'add_overview', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function addOverview(Request $request, UserMovie $userMovie): Response
    {
        $movie = $userMovie->getMovie();
        $data = json_decode($request->getContent(), true);
        $overviewId = $data['overviewId'] ?? "";
        $overviewId = $overviewId == "" ? null : intval($overviewId);
        $overviewType = $data['type'];
        $overview = $data['overview'];
        $locale = $data['locale'];
        $source = null;

        if ($overviewType == "additional") {
            $sourceId = $data['source'];
            $source = $this->sourceRepository->findOneBy(['id' => $sourceId]);
            if ($overviewId) {
                $movieAdditionalOverview = $this->movieAdditionalOverviewRepository->findOneBy(['id' => $overviewId]);
                $movieAdditionalOverview->setOverview($overview);
                $movieAdditionalOverview->setSource($source);
                $this->movieAdditionalOverviewRepository->save($movieAdditionalOverview, true);
            } else {
                $seriesAdditionalOverview = new MovieAdditionalOverview($movie, $overview, $locale, $source);
                $this->movieAdditionalOverviewRepository->save($seriesAdditionalOverview, true);
                $overviewId = $seriesAdditionalOverview->getId();
            }
        }
        if ($overviewType == "localized") {
            if ($overviewId) {
                $movieLocalizedOverview = $this->movieLocalizedOverviewRepository->findOneBy(['id' => $overviewId]);
                $movieLocalizedOverview->setOverview($overview);
                $this->movieLocalizedOverviewRepository->save($movieLocalizedOverview, true);
            } else {
                $movieLocalizedOverview = new MovieLocalizedOverview($movie, $overview, $locale);
                $this->movieLocalizedOverviewRepository->save($movieLocalizedOverview, true);
                $overviewId = $movieLocalizedOverview->getId();
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

    #[Route('/add/infos/{id}', name: 'add_infos', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function addInfos(Request $request, Movie $movie): Response
    {
        $data = json_decode($request->getContent(), true);

        // "production_companies": [
        //    {
        //      "id": 14,
        //      "logo_path": "/m6AHu84oZQxvq7n1rsvMNJIAsMu.png",
        //      "name": "Miramax",
        //      "origin_country": "US"
        //    },

        return $this->json([
            'ok' => true,
            'title' => $movie->getTitle(),
            'name' => $data['name'] ?? null,
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

        $movieKeywords = json_decode($this->tmdbService->getMovieKeywords($tmdbId), true);

        $missingKeywords = $this->keywordService->keywordsTranslation($movieKeywords['keywords'], $language);
        $keywordBlock = $this->renderView('_blocks/movie/_keywords.html.twig', [
            'id' => $tmdbId,
            'keywords' => $movieKeywords['keywords'],
            'missing' => $missingKeywords,
        ]);

        // fetch response
        return $this->json([
            'ok' => true,
            'keywords' => $keywordBlock,
        ]);
    }

    #[Route('/fetch/search/movies', name: 'fetch_search_movies', methods: ['POST'])]
    public function fetchSearchMovies(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);
        $query = $data['query'];

        $searchString = "&query=$query&include_adult=false&page=1";
        $movies = json_decode($this->tmdbService->searchMovie($searchString), true);

        return $this->json([
            'ok' => true,
            'results' => $movies['results'],
        ]);
    }

    function getPagination(int $index, int $page, int $totalPages, string $route, string $locale): string
    {
        return $this->renderView('_blocks/_pagination.html.twig', [
            'index' => $index,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'route' => $route,
            '_locale' => $locale,
        ]);
    }

    public function getCredits(array &$movie): void
    {
        $slugger = new ASCIISlugger();
        $profileUrl = $this->imageConfiguration->getUrl('profile_sizes', 2);
        $movie['credits']['cast'] = array_map(function ($people) use ($slugger, $profileUrl) {
            $people['profile_path'] = $people['profile_path'] ? $profileUrl . $people['profile_path'] : null;
            $people['slug'] = $slugger->slug($people['name']);
            return $people;
        }, $movie['credits']['cast']);
        $movie['credits']['crew'] = array_map(function ($people) use ($slugger, $profileUrl) {
            $people['profile_path'] = $people['profile_path'] ? $profileUrl . $people['profile_path'] : null;
            $people['slug'] = $slugger->slug($people['name']);
            return $people;
        }, $movie['credits']['crew']);
    }

    public function getBelongToCollection(array $movie): void
    {
        if (key_exists('belongs_to_collection', $movie)) {
            $collection = $movie['belongs_to_collection'];
            if ($collection) {
                $this->imageService->saveImage("posters", $collection['poster_path'], $this->imageConfiguration->getUrl('poster_sizes', 5), '/movies/');
                $this->imageService->saveImage("backdrops", $collection['backdrop_path'], $this->imageConfiguration->getUrl('backdrop_sizes', 3), '/movies/');
            }
        }
    }

    public function getProviders(array &$movie, string $country): void
    {
        $providers = $movie['watch/providers']['results'][$country] ?? [];
        $logoUrl = $this->imageConfiguration->getUrl('logo_sizes', 3);

        if (key_exists('flatrate', $providers)) {
            $flatrate = array_map(function ($p) use ($logoUrl) {
                $p['logo_path'] = $p['logo_path'] ? $logoUrl . $p['logo_path'] : null;
                return $p;
            }, $providers['flatrate'] ?? []);
            $movie['providers']['flatrate'] = $flatrate;
        } else {
            $movie['providers']['flatrate'] = [];
        }

        if (key_exists('buy', $providers)) {
            $buy = array_map(function ($p) use ($logoUrl) {
                $p['logo_path'] = $p['logo_path'] ? $logoUrl . $p['logo_path'] : null;
                return $p;
            }, $providers['buy'] ?? []);
            $movie['providers']['buy'] = $buy;
        } else {
            $movie['providers']['buy'] = [];
        }
        $buyIds = array_column($movie['providers']['buy'], 'provider_id');

        if (key_exists('rent', $providers)) {
            $rent = array_map(function ($p) use ($logoUrl) {
                $p['logo_path'] = $p['logo_path'] ? $logoUrl . $p['logo_path'] : null;
                return $p;
            }, $providers['rent'] ?? []);
            $movie['providers']['rent'] = $rent;
        } else {
            $movie['providers']['rent'] = [];
        }
        $rentIds = array_column($movie['providers']['rent'], 'provider_id');

        $movie['providers']['rent_buy_difference'] = count(array_diff($rentIds, $buyIds)) > 0;

        $movie['watch/providers'] = null;
    }

    public function getProductionCompanies(array &$movie): void
    {
        $pc = $movie['production_companies'] ?? [];
        usort($pc, function ($a, $b) {
            return $b['logo_path'] <=> $a['logo_path'];
        });
        $logoUrl = $this->imageConfiguration->getUrl('logo_sizes', 3);
        $pc = array_map(function ($p) use ($logoUrl) {
            $p['logo_path'] = $p['logo_path'] ? $logoUrl . $p['logo_path'] : null;
            return $p;
        }, $pc);
        $movie['production_companies'] = $pc;
    }

    public function getReleaseDates(array &$movie): void
    {
        $releaseDates = array_filter($movie['release_dates']['results'], function ($rd) {
            return $rd['iso_3166_1'] === 'FR';
        });
        $releaseDates = array_values($releaseDates);
        if (count($releaseDates)) {
            $releaseDates = $releaseDates[0]['release_dates'];
        } else {
            $releaseDates = [];
        }
        $releaseDates = array_map(function ($rd) {
            $types = [1 => 'Premiere', 2 => 'Theatrical (limited)', 3 => 'Theatrical', 4 => 'Digital', 5 => 'Physical', 6 => 'TV'];
            $rd['type_string'] = $types[$rd['type']];
            return $rd;
        }, $releaseDates);

        $movie['release_dates'] = $releaseDates;
    }

    public function getRecommandations(array &$movie): void
    {
        $posterUrl = $this->imageConfiguration->getUrl('poster_sizes', 5);
        $recommandations = array_map(function ($movie) use ($posterUrl) {
            $this->imageService->saveImage("posters", $movie['poster_path'], $posterUrl, '/movies/');
            return [
                'id' => $movie['id'],
                'title' => $movie['title'],
                'poster_path' => $movie['poster_path'],
                'release_date' => $movie['release_date'],
            ];
        }, $movie['recommendations']['results']);
        $movie['recommendations'] = $recommandations;
    }

    public function getCollection(array $tmdbMovie): ?MovieCollection
    {
        $movieCollection = null;
        if (key_exists('belongs_to_collection', $tmdbMovie)) {
            $collection = $tmdbMovie['belongs_to_collection'];
            if ($collection) {
                $collectionId = $collection['id'];
                $movieCollection = $this->movieCollectionRepository->findOneBy(['tmdbId' => $collectionId]);
                if (!$movieCollection) {
                    $movieCollection = new MovieCollection();
                    $movieCollection->setTmdbId($collectionId);
                    $movieCollection->setName($collection['name']);
                    $movieCollection->setPosterPath($collection['poster_path']);
                    $this->movieCollectionRepository->save($movieCollection, true);
                    $movieCollection = $this->movieCollectionRepository->findOneBy(['tmdbId' => $collectionId]);
                }
            }
        }
        return $movieCollection;
    }

    public function getDirectLinks(array &$movie, Movie $dbMovie): void
    {
        $movie['direct_links'] = $dbMovie->getMovieDirectLinks()->toArray();
    }

    public function getAdditionalOverviews(array &$movie, Movie $dbMovie): void
    {
        if ($movie['overview'] == null || $movie['overview'] == "") {
            $movie['overview'] = $dbMovie->getOverview();
        }
        $movie['additional_overviews'] = $dbMovie->getMovieAdditionalOverviews()->toArray();
    }

    public function getLocalizedName(array &$movie, Movie $dbMovie): void
    {
        /*$arr = array_filter($dbMovie->getMovieLocalizedNames()->toArray(), function ($name) {
            return $name['iso_3166_1'] === 'FR';
        });*/
        $localizedName = $this->movieLocalizedNameRepository->findOneBy(['movie' => $dbMovie, 'locale' => 'fr']);
        $movie['localized_name'] = $localizedName;
    }

    public function getLocalizedOverviews(array &$movie, Movie $dbMovie): void
    {
        $movie['localized_overviews'] = $dbMovie->getMovieLocalizedOverviews()->toArray();
    }

    public function getWatchProviders(string $language, string $watchRegion): array
    {
        $providers = json_decode($this->tmdbService->getMovieWatchProviderList($language, $watchRegion), true);
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
            $watchProviderLogos[$provider['provider_id']] = $this->imageConfiguration->getCompleteUrl($provider['logo_path'], 'logo_sizes', 2);
        }
        ksort($watchProviders);
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

    public function getSources(array &$movie): void
    {
        $sources = $this->sourceRepository->findBy([], ['name' => 'ASC']);
        $movie['sources'] = $sources;
    }

    public function createMovieFromDBMovie(Movie $dbMovie): array
    {
        $movie['backdrop_path'] = $dbMovie->getBackdropPath();
        $movie['belongs_to_collection'] = $dbMovie->getCollection();
        $movie['genres'] = [];
        $movie['homepage'] = null;
        $movie['id'] = $dbMovie->getTmdbId();
        $movie['origin_country'] = $dbMovie->getOriginCountry();
        $movie['original_language'] = $dbMovie->getOriginalLanguage();
        $movie['original_title'] = $dbMovie->getOriginalTitle();
        $movie['overview'] = $dbMovie->getOverview();
        $movie['poster_path'] = $dbMovie->getPosterPath();
        $movie['production_companies'] = [];
        $movie['production_countries'] = [];
        $movie['release_date'] = $dbMovie->getReleaseDate();
        $movie['revenue'] = null;
        $movie['runtime'] = $dbMovie->getRuntime();
        $movie['spoken_languages'] = [];
        $movie['status'] = $dbMovie->getStatus();
        $movie['tagline'] = $dbMovie->getTagline();
        $movie['title'] = $dbMovie->getTitle();
        $movie['video'] = false;
        $movie['vote_average'] = $dbMovie->getVoteAverage();
        $movie['vote_count'] = $dbMovie->getVoteCount();
        $movie['videos']['results'] = [];
        $movie['images']['backdrops'] = [];
        $movie['images']['logos'] = [];
        $movie['images']['posters'] = [];
        $movie['credits']['cast'] = [];
        $movie['credits']['crew'] = [];
        $movie['recommendations']['results'] = [];
        $movie['keywords']['keywords'] = [];
        $movie['watch/providers']['results'] = [];
        $movie['release_dates']['results'] = [];
        $movie['found'] = false;

        return $movie;
    }

    public function getSearchResult(array $searchResult): array
    {
        return array_map(function ($movie) {
            $this->imageService->saveImage("posters", $movie['poster_path'], $this->imageConfiguration->getUrl('poster_sizes', 5), '/movies/');
            $movie['poster_path'] = $movie['poster_path'] ? '/movies/posters' . $movie['poster_path'] : null;

            return [
                'tmdb' => true,
                'id' => $movie['id'],
                'title' => $movie['title'],
                'release_date' => $movie['release_date'],
                'poster_path' => $movie['poster_path'],
            ];
        }, $searchResult['results'] ?? []);
    }

    public function getOneResult(array $movie): Response
    {
        return $this->redirectToRoute('app_movie_tmdb', [
            'id' => $movie['id'],
        ]);
    }
}
