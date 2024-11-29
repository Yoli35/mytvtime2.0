<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\FilmingLocationImageRepository;
use App\Repository\FilmingLocationRepository;
use App\Repository\SeriesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\UX\Map\InfoWindow;
use Symfony\UX\Map\Map;
use Symfony\UX\Map\Marker;
use Symfony\UX\Map\Point;

#[Route('/{_locale}/map', name: 'app_map_', requirements: ['_locale' => 'fr|en|kr'])]
class MapController extends AbstractController
{
    public function __construct(
        private readonly FilmingLocationImageRepository $filmingLocationImageRepository,
        private readonly FilmingLocationRepository      $filmingLocationRepository,
        private readonly SeriesRepository               $seriesRepository,
    )
    {
    }

    #[Route('/index', name: 'index')]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $locale = $request->getLocale();
        $map = (new Map())
            ->center(new Point(46.903354, 1.888334))
            ->zoom(2)
            ->fitBoundsToMarkers();

        $seriesLocations = $this->seriesRepository->seriesLocations($user, $locale);

        $filmingLocations = $this->filmingLocationRepository->findBy(['isSeries' => true]);
        $filmingLocationIds = array_map(fn($filmingLocation) => $filmingLocation->getId(), $filmingLocations);
        $filmingLocationImages = $this->filmingLocationImageRepository->findBy(['filmingLocation' => $filmingLocationIds]);

        dump([
            'seriesLocations' => $seriesLocations,
            'filmingLocations' => $filmingLocations,
            'filmingLocationIds' => $filmingLocationIds,
            'filmingLocationImages' => $filmingLocationImages,
        ]);

        $filmingLocationCount = $this->filmingLocationRepository->count();
        $filmingLocationImageCount = $this->filmingLocationImageRepository->count();

        foreach ($seriesLocations as $seriesLocation) {
            foreach ($seriesLocation['locations'] as $location) {
                $infoWindow = new InfoWindow('<strong>' . $seriesLocation['name'] . '</strong> - ' . $location['description'], '<img src="' . $location['image'] . '" alt="' . $location['description'] . '" style="height: auto; width: 100%">');
                $map->addMarker(new Marker(new Point($location['latitude'], $location['longitude']), "***" . $seriesLocation['name'] . "***", $infoWindow, ['id' => $seriesLocation['id'], 'tmdbId' => $seriesLocation['tmdbId']]));
            }
        }

        return $this->render('map/index.html.twig', [
            'map' => $map,
            'seriesLocations' => $seriesLocations,
            'seriesCount' => count($seriesLocations),
            'filmingLocationCount' => $filmingLocationCount,
            'filmingLocationImageCount' => $filmingLocationImageCount,
        ]);
    }
}
