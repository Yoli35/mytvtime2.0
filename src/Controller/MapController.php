<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\FilmingLocationRepository;
use App\Repository\SeriesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/{_locale}/map', name: 'app_map_', requirements: ['_locale' => 'fr|en|kr'])]
class MapController extends AbstractController
{
    public function __construct(
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

        $seriesLocations = $this->seriesRepository->seriesLocations($user, $locale);

        $locations = $this->getAllFilmingLocations();

        dump([
            'seriesLocations' => $seriesLocations,
            'filmingLocations' => $locations['filmingLocations'],
            'filmingLocationCount' => $locations['filmingLocationCount'],
            'filmingLocationImagesCount' => $locations['filmingLocationImageCount'],
        ]);

        return $this->render('map/index.html.twig', [
            'seriesLocations' => $seriesLocations,
            'seriesCount' => count($seriesLocations),
            'locations' => $locations['filmingLocations'],
            'filmingLocationCount' => $locations['filmingLocationCount'],
            'filmingLocationImageCount' => $locations['filmingLocationImageCount'],
        ]);
    }

    public function getAllFilmingLocations(): array
    {
        $filmingLocations = $this->filmingLocationRepository->allLocations();
        $filmingLocationIds = array_column($filmingLocations, 'id');

        $filmingLocationImages = $this->filmingLocationRepository->locationImages($filmingLocationIds);
        $flImages = [];
        foreach ($filmingLocationImages as $image) {
            $flImages[$image['filming_location_id']][] = $image;
        }
        foreach ($filmingLocations as &$location) {
            $location['filmingLocationImages'] = $flImages[$location['id']] ?? [];
        }

        return [
            'filmingLocations'=>$filmingLocations,
            'filmingLocationCount' => count($filmingLocations),
            'filmingLocationImageCount' => count($filmingLocationImages),];
    }
}
