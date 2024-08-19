<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\SeriesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\UX\Map\InfoWindow;
use Symfony\UX\Map\Map;
use Symfony\UX\Map\Marker;
use Symfony\UX\Map\Point;

#[Route('/{_locale}/map', name: 'app_map_', requirements: ['_locale' => 'fr|en'])]
class MapController extends AbstractController
{
    public function __construct(
        private readonly SeriesRepository $seriesRepository,
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
        dump($seriesLocations);
        foreach ($seriesLocations as $seriesLocation) {
            foreach ($seriesLocation['locations'] as $location) {
                dump($location);
                $map->addMarker(new Marker(new Point($location['latitude'], $location['longitude']), $seriesLocation['name'], new InfoWindow('<strong>' . $seriesLocation['name'] . '</strong> - ' . $location['description'], '<img src="' . $location['image'] . '" alt="' . $location['description'] . '" style="height: auto; width: 100%">')));
            }
        }

        return $this->render('map/index.html.twig', [
            'map' => $map,
        ]);
    }
}
