<?php

namespace App\Controller;

use App\Entity\Movie;
use App\Entity\MovieCollection;
use App\Entity\MovieDirectLink;
use App\Entity\Settings;
use App\Entity\User;
use App\Entity\UserMovie;
use App\Repository\MovieCollectionRepository;
use App\Repository\MovieDirectLinkRepository;
use App\Repository\MovieRepository;
use App\Repository\SettingsRepository;
use App\Repository\SourceRepository;
use App\Repository\UserMovieRepository;
use App\Repository\WatchProviderRepository;
use App\Service\DateService;
use App\Service\ImageConfiguration;
use App\Service\KeywordService;
use App\Service\TMDBService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/{_locale}/movie', name: 'app_movie_', requirements: ['_locale' => 'fr|en'])]
class MovieController extends AbstractController
{
    public function __construct(
        private readonly DateService               $dateService,
        private readonly ImageConfiguration        $imageConfiguration,
        private readonly KeywordService            $keywordService,
        private readonly MovieCollectionRepository $movieCollectionRepository,
        private readonly MovieDirectLinkRepository $movieDirectLinkRepository,
        private readonly MovieRepository           $movieRepository,
        private readonly SettingsRepository        $settingsRepository,
        private readonly SourceRepository          $sourceRepository,
        private readonly TMDBService               $tmdbService,
        private readonly TranslatorInterface       $translator,
        private readonly UserMovieRepository       $userMovieRepository,
        private readonly WatchProviderRepository   $watchProviderRepository,
    )
    {
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/index', name: 'index')]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $slugger = new ASCIISlugger();

//        $localisation = [
//            'locale' => $user?->getPreferredLanguage() ?? $request->getLocale(),
//            'country' => $user?->getCountry() ?? "FR",
//            'language' => $user?->getPreferredLanguage() ?? $request->getLocale(),
//            'timezone' => $user?->getTimezone() ?? "Europe/Paris"
//        ];
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
        $userMovies = array_map(function ($movie) use ($slugger) {
            $this->saveImage("posters", $movie['posterPath'], $this->imageConfiguration->getUrl('poster_sizes', 5));
            $movie['slug'] = $slugger->slug($movie['title']);
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

        dump([
            'userMovies' => $userMovies,
            'userMovieCount' => $userMovieCount,
            'pages' => ceil($userMovieCount / $filters['perPage']),
            'filterMeanings' => $filterMeanings,
            'filterBoxOpen' => $filterBoxOpen,
            'filters' => $filters,
        ]);
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

    #[IsGranted('ROLE_USER')]
    #[Route('/show/{userMovieId}', name: 'show', requirements: ['userMovieId' => '\d+'])]
    public function show(Request $request, int $userMovieId): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $locale = $request->getLocale();
        $language = ($user->getPreferredLanguage() ?? $locale) . '-' . ($user->getCountry() ?? ($locale === 'fr' ? 'FR' : 'US'));
        $userMovie = $this->userMovieRepository->find($userMovieId);
        $tmdbId = $userMovie->getMovie()->getTmdbId();
        $dbMovie = $userMovie->getMovie();
        $movie = json_decode($this->tmdbService->getMovie($tmdbId, $language, ['videos,images,credits,recommendations,keywords,watch/providers,release_dates']), true);
        if (!$movie) {
            $movie = $this->createMovieFromDBMovie($dbMovie);
            //TODO: if movie not found on tmdb, ask for removal
        } else {
            $movie['found'] = true;
            $this->saveImage("posters", $movie['poster_path'], $this->imageConfiguration->getUrl('poster_sizes', 5));
            $this->saveImage("backdrops", $movie['backdrop_path'], $this->imageConfiguration->getUrl('backdrop_sizes', 3));
        }

        $this->getBelongToCollection($movie);
        $this->getCredits($movie);
        $this->getProviders($movie);
        $this->getProductionCompanies($movie);
        $this->getReleaseDates($movie);
        $this->getRecommandations($movie);
        $this->getDirectLinks($movie, $dbMovie);
        $this->getAdditionalOverviews($movie, $dbMovie);
        $this->getLocalizedName($movie, $dbMovie);
        $this->getLocalizedOverviews($movie, $dbMovie);
        $this->getSources($movie);
        $movie['missing_translations'] = $this->keywordService->keywordsTranslation($movie['keywords']['keywords'], $request->getLocale());
//        dump([
//            'language' => $language,
//            'tmdbId' => $tmdbId,
//            'movie' => $movie,
//            'userMovie' => $userMovie,
//        ]);

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

//        dump([
//                'language' => $language,
//            'movie' => $movie,
//            'userMovie' => $userMovie,
//                'providers' => $providers,
//                'translations' => $translations,
//        ]);
        return $this->render('movie/show.html.twig', [
            'userMovie' => $userMovie,
            'movie' => $movie,
            'providers' => $providers,
            'translations' => $translations,
        ]);
    }

    #[Route('/tmdb/{id}', name: 'tmdb', requirements: ['id' => '\d+'])]
    public function tmdb(Request $request, int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($user) {
            $movie = $this->movieRepository->findOneBy(['tmdbId' => $id]);
            if ($movie) {
                dump(['movie' => $movie]);
                $userMovie = $this->userMovieRepository->findOneBy(['movie' => $movie, 'user' => $user]);
                dump(['userMovie' => $userMovie]);
                if ($userMovie) {
                    return $this->redirectToRoute('app_movie_show', ['userMovieId' => $userMovie->getId()]);
                }
            }
        }
        $locale = $user->getPreferredLanguage() ?? $request->getLocale();
        $language = $locale . '-' . ($locale === 'fr' ? 'FR' : 'US');
        $movie = json_decode($this->tmdbService->getMovie($id, $language, ['videos,images,credits,recommendations,watch/providers,release_dates']), true);

        $this->saveImage("posters", $movie['poster_path'], $this->imageConfiguration->getUrl('poster_sizes', 5));
        $this->saveImage("backdrops", $movie['backdrop_path'], $this->imageConfiguration->getUrl('backdrop_sizes', 3));

        $this->getCredits($movie);
        $this->getProviders($movie);
        $this->getReleaseDates($movie);
        $this->getRecommandations($movie);

        dump(
            [
                'language' => $language,
                'movie' => $movie,
            ]
        );
        return $this->render('movie/tmdb.html.twig', [
            'movie' => $movie,
        ]);
    }

    #[Route('/collection/{id}', name: 'collection', requirements: ['id' => '\d+'])]
    public function collection(Request $request, int $id): Response
    {
        $collection = json_decode($this->tmdbService->getMovieCollection($id), true);

        $this->saveImage("posters", $collection['poster_path'], $this->imageConfiguration->getUrl('poster_sizes', 5));
        $this->saveImage("backdrops", $collection['backdrop_path'], $this->imageConfiguration->getUrl('backdrop_sizes', 3));

        foreach ($collection['parts'] as $part) {
            $this->saveImage("posters", $part['poster_path'], $this->imageConfiguration->getUrl('poster_sizes', 5));
            $this->saveImage("backdrops", $part['backdrop_path'], $this->imageConfiguration->getUrl('backdrop_sizes', 3));
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
                return $this->redirectToRoute('app_movie_show', ['userMovieId' => $userMovie->getId()]);
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

        dump(['userMovie' => $userMovie]);
        return $this->redirectToRoute('app_movie_show', ['userMovieId' => $userMovie->getId()]);
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
        $slugger = new ASCIISlugger();

        $data = json_decode($request->getContent(), true);
        $filters = [];
        foreach ($data as $filter) {
            $filters[$filter['key']] = $filter['value'];
        }
        $settings = $this->settingsRepository->findOneBy(['user' => $user, 'name' => 'my movies']);
        $settings->setData($filters);
        $this->settingsRepository->save($settings, true);

        $userMovies = array_map(function ($movie) use ($user, $slugger) {
            $this->saveImage("posters", $movie['posterPath'], $this->imageConfiguration->getUrl('poster_sizes', 5));
            $movie['slug'] = $slugger->slug($movie['title']);
            // release_date: 2024-07-24 → 24 juillet 2024
            $movie['releaseDateString'] = ucfirst($this->dateService->formatDateLong($movie['releaseDate'], $user->getTimezone() ?? 'Europe/Paris', $user->getPreferredLanguage() ?? 'fr'));
            $movie['lastViewedAtString'] = $movie['lastViewedAt'] ? ucfirst($this->dateService->formatDateLong($movie['lastViewedAt'], $user->getTimezone() ?? 'Europe/Paris', $user->getPreferredLanguage() ?? 'fr')) : null;
            return $movie;
        }, $this->movieRepository->getMovieCards($user, $filters));

        $userMovieCount = $this->movieRepository->countMovieCards($user, $filters);

//        dump([
//            'userMovies' => $userMovies,
//            'userMovieCount' => $userMovieCount,
//            'pages' => ceil($userMovieCount / $filters['perPage']),
//            'filters' => $filters,
//        ]);

        return $this->json([
            'ok' => true,
            'body' => [
                'userMovies' => $userMovies,
                'userMovieCount' => $userMovieCount,
                'pages' => ceil($userMovieCount / $filters['perPage']),
                'filters' => $filters,
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
        dump([
            'url' => $url,
            'title' => $title,
            'provider' => $providerId,
        ]);
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

    #[Route('/image/config', name: 'image_config')]
    public function getImageConfig(): Response
    {
        return $this->json([
            'ok' => true,
            'body' => [
                'poster_url' => $this->imageConfiguration->getUrl('poster_sizes', 0),
                'profile_url' => $this->imageConfiguration->getUrl('profile_sizes', 0),
            ],
        ]);
    }

    public function getCredits(array &$movie): void
    {
        $slugger = new ASCIISlugger();
        $movie['credits']['cast'] = array_map(function ($people) use ($slugger) {
            $people['profile_path'] = $people['profile_path'] ? $this->imageConfiguration->getUrl('profile_sizes', 2) . $people['profile_path'] : null;
            $people['slug'] = $slugger->slug($people['name']);
            return $people;
        }, $movie['credits']['cast']);
        $movie['credits']['crew'] = array_map(function ($people) use ($slugger) {
            $people['profile_path'] = $people['profile_path'] ? $this->imageConfiguration->getUrl('profile_sizes', 2) . $people['profile_path'] : null;
            $people['slug'] = $slugger->slug($people['name']);
            return $people;
        }, $movie['credits']['crew']);
    }

    public function getBelongToCollection(array $movie): void
    {
        if (key_exists('belongs_to_collection', $movie)) {
            $collection = $movie['belongs_to_collection'];
            if ($collection) {
                $this->saveImage("posters", $collection['poster_path'], $this->imageConfiguration->getUrl('poster_sizes', 5));
                $this->saveImage("backdrops", $collection['backdrop_path'], $this->imageConfiguration->getUrl('backdrop_sizes', 3));
            }
        }
    }

    public function getProviders(array &$movie): void
    {
        $providers = array_filter($movie['watch/providers']['results'], function ($key) {
            return $key === 'FR';
        }, ARRAY_FILTER_USE_KEY);
        $providers = array_map(function ($p) {
            $p['logo_path'] = $this->imageConfiguration->getUrl('logo_sizes', 3) . $p['logo_path'];
            return $p;
        }, $providers['FR']['flatrate'] ?? []);
        $movie['watch/providers'] = null;
        $movie['providers'] = $providers;
    }

    public function getProductionCompanies(array &$movie): void
    {
        usort($movie['production_companies'], function ($a, $b) {
            return $b['logo_path'] <=> $a['logo_path'];
        });
        $pc = array_map(function ($p) {
            $p['logo_path'] = $p['logo_path'] ? $this->imageConfiguration->getUrl('logo_sizes', 1) . $p['logo_path'] : null;
            return $p;
        }, $movie['production_companies'] ?? []);
        $movie['production_companies'] = $pc;
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

    public function getReleaseDates(array &$movie): void
    {
        $types = [1 => 'Premiere', 2 => 'Theatrical (limited)', 3 => 'Theatrical', 4 => 'Digital', 5 => 'Physical', 6 => 'TV'];
        $releaseDates = array_filter($movie['release_dates']['results'], function ($rd) {
            return $rd['iso_3166_1'] === 'FR';
        });
        $releaseDates = array_values($releaseDates);
        if (count($releaseDates)) {
            $releaseDates = $releaseDates[0]['release_dates'];
        } else {
            $releaseDates = [];
        }
        $releaseDates = array_map(function ($rd) use ($types) {
            $rd['type_string'] = $types[$rd['type']];
            return $rd;
        }, $releaseDates);

        $movie['release_dates'] = $releaseDates;
    }

    public function getRecommandations(array &$movie): void
    {
        $recommandations = array_map(function ($movie) {
            $this->saveImage("posters", $movie['poster_path'], $this->imageConfiguration->getUrl('poster_sizes', 5));
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
        $movie['additional_overviews'] = $dbMovie->getMovieAdditionalOverviews()->toArray();
    }

    public function getLocalizedName(array &$movie, Movie $dbMovie): void
    {
        $arr = array_filter($dbMovie->getMovieLocalizedNames()->toArray(), function ($name) {
            return $name['iso_3166_1'] === 'FR';
        });
        $movie['localized_name'] = count($arr) ? $arr[0] : null;
    }

    public function getLocalizedOverviews(array &$movie, Movie $dbMovie): void
    {
        $movie['localized_overviews'] = $dbMovie->getMovieLocalizedOverviews()->toArray();
    }

    public function getWatchProviders($language, $watchRegion): array
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
        $sources = $this->sourceRepository->findAll();
        $movie['sources'] = $sources;
    }

    public function createMovieFromDBMovie(Movie $dbMovie): array
    {
        // {
        //  "adult": false,
        //  "backdrop_path": "/wNAhuOZ3Zf84jCIlrcI6JhgmY5q.jpg",
        //  "belongs_to_collection": {
        //    "id": 8945,
        //    "name": "Mad Max Collection",
        //    "poster_path": "/9U9QmbCDIBhqDShuIxOiS9gjKYz.jpg",
        //    "backdrop_path": "/fhv3dWOuzeW9eXOSlr8MCHwo24t.jpg"
        //  },
        //  "budget": 170000000,
        //  "genres": [
        //    {
        //      "id": 28,
        //      "name": "Action"
        //    },
        //    {
        //      "id": 12,
        //      "name": "Adventure"
        //    },
        //    {
        //      "id": 878,
        //      "name": "Science Fiction"
        //    }
        //  ],
        //  "homepage": "https://www.furiosaamadmaxsaga.com",
        //  "id": 786892,
        //  "imdb_id": "tt12037194",
        //  "origin_country": [
        //    "AU",
        //    "US"
        //  ],
        //  "original_language": "en",
        //  "original_title": "Furiosa: A Mad Max Saga",
        //  "overview": "As the world fell, young Furiosa is snatched from the Green Place of Many Mothers and falls into the hands of a great Biker Horde led by the Warlord Dementus. Sweeping through the Wasteland they come across the Citadel presided over by The Immortan Joe. While the two Tyrants war for dominance, Furiosa must survive many trials as she puts together the means to find her way home.",
        //  "popularity": 940.878,
        //  "poster_path": "/iADOJ8Zymht2JPMoy3R7xceZprc.jpg",
        //  "production_companies": [
        //    {
        //      "id": 174,
        //      "logo_path": "/zhD3hhtKB5qyv7ZeL4uLpNxgMVU.png",
        //      "name": "Warner Bros. Pictures",
        //      "origin_country": "US"
        //    },
        //    {
        //      "id": 28382,
        //      "logo_path": "/xqE1fjLynj3RaZca9chctZQyfzZ.png",
        //      "name": "Kennedy Miller Mitchell",
        //      "origin_country": "AU"
        //    },
        //    {
        //      "id": 216687,
        //      "logo_path": null,
        //      "name": "Domain Entertainment",
        //      "origin_country": "US"
        //    }
        //  ],
        //  "production_countries": [
        //    {
        //      "iso_3166_1": "AU",
        //      "name": "Australia"
        //    },
        //    {
        //      "iso_3166_1": "US",
        //      "name": "United States of America"
        //    }
        //  ],
        //  "release_date": "2024-05-22",
        //  "revenue": 172775791,
        //  "runtime": 149,
        //  "spoken_languages": [
        //    {
        //      "english_name": "English",
        //      "iso_639_1": "en",
        //      "name": "English"
        //    }
        //  ],
        //  "status": "Released",
        //  "tagline": "Fury is born.",
        //  "title": "Furiosa: A Mad Max Saga",
        //  "video": false,
        //  "vote_average": 7.62,
        //  "vote_count": 2538
        //}
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

    public function saveImage($type, $imagePath, $imageUrl): void
    {
        if (!$imagePath) return;
        $root = $this->getParameter('kernel.project_dir');
        $this->saveImageFromUrl(
            $imageUrl . $imagePath,
            $root . "/public/movies/" . $type . $imagePath
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
}
