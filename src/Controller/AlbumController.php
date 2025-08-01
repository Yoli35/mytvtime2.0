<?php

namespace App\Controller;

use App\Entity\Album;
use App\Entity\User;
use App\Repository\AlbumRepository;
use App\Repository\FilmingLocationRepository;
use App\Repository\MovieRepository;
use App\Repository\PhotoRepository;
use App\Repository\PointOfInterestCategoryRepository;
use App\Repository\PointOfInterestImageRepository;
use App\Repository\PointOfInterestRepository;
use App\Repository\SeriesRepository;
use App\Repository\SettingsRepository;
use App\Repository\UserRepository;
use App\Repository\VideoCategoryRepository;
use App\Repository\VideoRepository;
use App\Repository\WatchProviderRepository;
use App\Service\DateService;
use App\Service\ImageConfiguration;
use App\Service\ImageService;
use App\Service\TMDBService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/** @method User|null getUser() */
#[Route('/{_locale}/album', name: 'app_album_', requirements: ['_locale' => 'en|fr|ko'])]
final class AlbumController extends AbstractController
{
    public function __construct(
        private readonly AlbumRepository                   $albumRepository,
        private readonly DateService                       $dateService,
        private readonly FilmingLocationRepository         $filmingLocationRepository,
        private readonly ImageConfiguration                $imageConfiguration,
        private readonly ImageService                      $imageService,
        private readonly MapController                     $mapController,
        private readonly MovieRepository                   $movieRepository,
        private readonly PhotoRepository                   $photoRepository,
        private readonly PointOfInterestCategoryRepository $pointOfInterestCategoryRepository,
        private readonly PointOfInterestImageRepository    $pointOfInterestImageRepository,
        private readonly PointOfInterestRepository         $pointOfInterestRepository,
        private readonly SeriesController                  $seriesController,
        private readonly SeriesRepository                  $seriesRepository,
        private readonly SettingsRepository                $settingsRepository,
        private readonly TMDBService                       $tmdbService,
        private readonly TranslatorInterface               $translator,
        private readonly UserRepository                    $userRepository,
        private readonly VideoCategoryRepository           $categoryRepository,
        private readonly VideoController                   $videoController,
        private readonly VideoRepository                   $videoRepository,
        private readonly WatchProviderRepository           $watchProviderRepository
    )
    {
    }

    #[Route('/', name: 'index')]
    public function index(): Response
    {
        $user = $this->getUser();
        $albums = $this->albumRepository->findBy(['user' => $user,], ['createdAt' => 'DESC']);

        $dateArr = array_map(function ($album) use ($user) {
            $photos = $this->photoRepository->findBy(['album' => $album], ['createdAt' => 'ASC']);
            $dates = array_map(function ($photo) {
                return $photo->getDate();
            }, $photos);
            return $dates;
        }, $albums);

        dump($dateArr);

        return $this->render('album/index.html.twig', [
            'albums' => $albums,
            'pagination' => '',
        ]);
    }

    #[Route('/show/{id}', name: 'show', requirements: ['id' => '\d+'])]
    public function show(Album $album): Response
    {
        return $this->render('album/show.html.twig', [
            'album' => $album,
            'previousAlbum' => null,
            'nextAlbum' => null,
            'dbUserAlbums' => [],
        ]);
    }
}
