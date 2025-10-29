<?php

namespace App\Api;

use App\Entity\Movie;
use App\Entity\MovieLocalizedName;
use App\Entity\User;
use App\Entity\UserMovie;
use App\Repository\MovieLocalizedNameRepository;
use App\Repository\MovieRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\String\Slugger\AsciiSlugger;

/** @method User|null getUser() */
#[Route('/api/movie/name', name: 'api_movie_name_')]
class ApiMovieName extends AbstractController
{
    public function __construct(
        private readonly MovieLocalizedNameRepository $movieLocalizedNameRepository,
        private readonly MovieRepository $movieRepository,
    )
    {}

    #[Route('/add/{id}', name: 'add', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function add(Request $request, UserMovie $userMovie): Response
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

    #[Route('/remove/{id}', name: 'remove', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function remove(Request $request, Movie $movie): Response
    {
        $data = json_decode($request->getContent(), true);
        $locale = $data['locale'];
//        $movie = $this->movieRepository->findOneBy(['id' => $id]);

        $localizedName = $movie->getMovieLocalizedName($locale);
        if ($localizedName) {
            $movie->removeMovieLocalizedName($localizedName);
            $this->movieRepository->save($movie, true);
            $this->movieLocalizedNameRepository->remove($localizedName);
        }

        return $this->json([
            'ok' => true,
        ]);
    }
}