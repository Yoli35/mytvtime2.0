<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\MovieRepository;
use App\Repository\UserMovieRepository;
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
        private readonly ImageConfiguration  $imageConfiguration,
        private readonly MovieRepository     $movieRepository,
        private readonly TMDBService         $tmdbService,
        private readonly UserMovieRepository $userMovieRepository,
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

        $userMovies = array_map(function ($movie) use ($slugger){
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
    #[Route('/show/{id}-{slug}', name: 'show', requirements: ['id' => '\d+', 'slug' => '[a-z0-9-]+'])]
    public function show(Request $request, int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $locale = $request->getLocale();
        $language = $user->getPreferredLanguage() ?? $locale . '-' . $user->getCountry() ?? $locale === 'fr' ? 'FR' : 'US';
        $userMovie = $this->userMovieRepository->find($id);
        $movie = $this->tmdbService->getMovie($userMovie->getMovieId(), $language);

        return $this->render('movie/show.html.twig', [
            'userMovie' => $userMovie,
            'movie' => $movie,
        ]);
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
