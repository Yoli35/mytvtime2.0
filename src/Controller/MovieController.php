<?php

namespace App\Controller;

use App\Entity\Movie;
use App\Entity\MovieCollection;
use App\Entity\User;
use App\Entity\UserMovie;
use App\Repository\MovieCollectionRepository;
use App\Repository\MovieRepository;
use App\Repository\UserMovieRepository;
use App\Service\DateService;
use App\Service\ImageConfiguration;
use App\Service\TMDBService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[Route('/{_locale}/movie', name: 'app_movie_', requirements: ['_locale' => 'fr|en'])]
class MovieController extends AbstractController
{
    public function __construct(
        private readonly DateService               $dateService,
        private readonly ImageConfiguration        $imageConfiguration,
        private readonly MovieCollectionRepository $movieCollectionRepository,
        private readonly MovieRepository           $movieRepository,
        private readonly TMDBService               $tmdbService,
        private readonly UserMovieRepository       $userMovieRepository,
    )
    {
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/index', name: 'index')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $slugger = new ASCIISlugger();

        $userMovies = array_map(function ($movie) use ($slugger) {
            $this->saveImage("posters", $movie['posterPath'], $this->imageConfiguration->getUrl('poster_sizes', 5));
            $movie['slug'] = $slugger->slug($movie['title']);
            return $movie;
        }, $this->movieRepository->getMovieCards($user));

        dump(['userMovies' => $userMovies]);
        return $this->render('movie/index.html.twig', [
            'userMovies' => $userMovies,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/show/{userMovieId}', name: 'show', requirements: ['userMovieId' => '\d+'])]
//    #[Route('/show/{id}-{slug}', name: 'show', requirements: ['id' => '\d+', 'slug' => '[a-z0-9-]+'])]
    public function show(Request $request, int $userMovieId): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $locale = $request->getLocale();
        $language = ($user->getPreferredLanguage() ?? $locale) . '-' . ($user->getCountry() ?? ($locale === 'fr' ? 'FR' : 'US'));
        $userMovie = $this->userMovieRepository->find($userMovieId);
        $movie = json_decode($this->tmdbService->getMovie($userMovie->getMovie()->getTmdbId(), $language, ['videos,images,credits,recommendations,watch/providers,release_dates']), true);

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
                'userMovie' => $userMovie,
            ]
        );
        return $this->render('movie/show.html.twig', [
            'userMovie' => $userMovie,
            'movie' => $movie,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/tmdb/{id}', name: 'tmdb', requirements: ['id' => '\d+'])]
//    #[Route('/tmdb/{id}-{slug}', name: 'tmdb', requirements: ['id' => '\d+', 'slug' => '[a-z0-9-]+'])]
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

    #[IsGranted('ROLE_USER')]
    #[Route('/add/{id}', name: 'add', requirements: ['id' => '\d+'])]
    public function add(Request $request, int $id): Response
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

    #[Route('/image/config', name: 'image_config')]
    public function getImageConfig(): Response
    {
        return $this->json([
            'ok' => true,
            'body' => ['poster_url' => $this->imageConfiguration->getUrl('poster_sizes', 0)],
        ]);
    }

    public function getCredits(array &$movie): void
    {
        $movie['credits']['cast'] = array_map(function ($people) {
            $people['profile_path'] = $this->imageConfiguration->getUrl('profile_sizes', 2) . $people['profile_path'];
            return $people;
        }, $movie['credits']['cast']);
        $movie['credits']['crew'] = array_map(function ($people) {
            $people['profile_path'] = $this->imageConfiguration->getUrl('profile_sizes', 2) . $people['profile_path'];
            return $people;
        }, $movie['credits']['crew']);
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

    public function getReleaseDates(array &$movie): void
    {
        $releaseDates = array_filter($movie['release_dates']['results'], function ($rd) {
            return $rd['iso_3166_1'] === 'FR';
        });
        $releaseDates = array_values($releaseDates);
        if (count($releaseDates)) {
            $movie['release_dates'] = $releaseDates[0]['release_dates'];
        } else {
            $movie['release_dates'] = [];
        }
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
