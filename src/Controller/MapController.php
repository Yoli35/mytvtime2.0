<?php

namespace App\Controller;

use App\DTO\MapDTO;
use App\Form\MapType;
use App\Repository\CountryRepository;
use App\Repository\FilmingLocationImageRepository;
use App\Repository\FilmingLocationRepository;
use App\Repository\SettingsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/{_locale}/map', name: 'app_map_', requirements: ['_locale' => 'fr|en|ko'])]
class MapController extends AbstractController
{
    public function __construct(
        private readonly FilmingLocationRepository      $filmingLocationRepository,
        private readonly FilmingLocationImageRepository $filmingLocationImageRepository,
        private readonly CountryRepository              $countryRepository,
        private readonly SettingsRepository             $settingsRepository,
    )
    {
    }

    #[Route('/index', name: 'index')]
    public function index(Request $request): Response
    {
        $settings = $this->settingsRepository->findOneBy(['name' => 'mapbox']);

        $locations = $this->getAllFilmingLocations('title');
        dump($locations);

        $fl = [];
        $countries = [];
        $countryLatLngs = [];
        $countryLocationIds = [];
        foreach ($locations['filmingLocations'] as $location) {
            $fl[$location['tmdb_id']]['locations'][] = $location;
            if (!isset($fl[$location['tmdb_id']]['country'])) {
                $fl[$location['tmdb_id']]['country'] = [];
            }
            $fl[$location['tmdb_id']]['country'] = array_merge($fl[$location['tmdb_id']]['country'], json_decode($location['origin_country'], true));
        }

        foreach ($fl as $tmdbId => $data) {
            $fl[$tmdbId]['country'] = array_unique($data['country']);
            foreach ($fl[$tmdbId]['country'] as $country) {
                if (!key_exists($country, $countries)) {
                    $countries[$country] = Countries::getName($country);
                }
            }
        }

        $bb = array_map(function ($c) {
            $c->setDisplayName(Countries::getName($c->getCode()));
            return $c;
        }, $this->countryRepository->findBy([], ['code' => 'ASC']));

        if ($request->getLocale() === 'fr') {
            uasort($countries, function ($a, $b) {
                $a = str_replace('É', 'E', $a);
                $b = str_replace('É', 'E', $b);
                return strcasecmp($a, $b);
            });
            uasort($bb, function ($a, $b) {
                $a = str_replace('É', 'E', $a->getDisplayName());
                $b = str_replace('É', 'E', $b->getDisplayName());
                return strcasecmp($a, $b);
            });
        }

        foreach ($fl as $tmdbId => $data) {
            foreach ($data['country'] as $country) {
                foreach ($data['locations'] as $location) {
                    $countryLatLngs[$country][] = [
                        'lat' => $location['latitude'],
                        'lng' => $location['longitude'],
                    ];
                    $countryLocationIds[$country][] = $location['tmdb_id'];
                }
            }
        }

        dump([
            'fl' => $fl,
//            'countryLatLngs' => $countryLatLngs,
//            'countryLocationIds' => $countryLocationIds,
//            'countries' => $countries,
//            'countryBoundingBoxes' => $bb,
            'filmingLocations' => $locations['filmingLocations'],
//            'filmingLocationCount' => $locations['filmingLocationCount'],
//            'filmingLocationImagesCount' => $locations['filmingLocationImageCount'],
        ]);

        return $this->render('map/index.html.twig', [
            'fl' => $fl,
            'countries' => $countries,
            'countryLatLngs' => $countryLatLngs,
            'countryLocationIds' => $countryLocationIds,
            'countryBoundingBoxes' => $bb,
            'seriesCount' => count($fl),
            'locations' => $locations['filmingLocations'],
            'filmingLocationCount' => $locations['filmingLocationCount'],
            'filmingLocationImageCount' => $locations['filmingLocationImageCount'],
            'leaflet' => false,
            'mapbox' => true,
            'settings' => $settings,
        ]);
    }

    #[Route('/last-locations', name: 'last_locations')]
    public function lastLocations(Request $request): Response
    {
        $type = $request->get('type', 'creation');
        $settings = $this->settingsRepository->findOneBy(['name' => 'mapbox']);

        $mapDTO = new MapDTO($type, 1, $perPage = 20);
        $form = $this->createForm(MapType::class, $mapDTO);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
        } else {
            $data = $mapDTO;
        }
        $type = $data->getType();
        $page = $data->getPage();
        $perPage = $data->getPerPage();

        $locations = $this->getAllFilmingLocations($type, $page, $perPage);

        dump([
            'locations' => $locations['filmingLocations'],
            'filmingLocationCount' => $locations['filmingLocationCount'],
            'filmingLocationImageCount' => $locations['filmingLocationImageCount'],
            'seriesCount' => $this->filmingLocationRepository->seriesCount(),
            'bounds' => $locations['bounds'],
        ]);

        return $this->render('map/last-creations.html.twig', [
            'form' => $form->createView(),
            'locations' => $locations['filmingLocations'],
            'filmingLocationCount' => $locations['filmingLocationCount'],
            'filmingLocationImageCount' => $locations['filmingLocationImageCount'],
            'seriesCount' => $this->filmingLocationRepository->seriesCount(),
            'bounds' => $locations['bounds'],
            'type' => $type,
            'page' => $page,
            'pages' => ceil($locations['filmingLocationCount'] / $perPage),
            'settings' => $settings,
        ]);
    }

    public function getAllFilmingLocations(string $order, int $page = 1, int $perPage = 50): array
    {
        $filmingLocations = $this->filmingLocationRepository->allLocations($order, $page, $perPage);
        $filmingLocationIds = array_column($filmingLocations, 'id');

        // Bounding box → center
        $minLat = min(array_column($filmingLocations, 'latitude')) - .5;
        $maxLat = max(array_column($filmingLocations, 'latitude')) + .5;
        $minLng = min(array_column($filmingLocations, 'longitude')) - .5;
        $maxLng = max(array_column($filmingLocations, 'longitude')) + .5;
        $bounds = [[$maxLng, $maxLat], [$minLng, $minLat]];

        $filmingLocationImages = $this->filmingLocationRepository->locationImages($filmingLocationIds);
        $flImages = [];
        foreach ($filmingLocationImages as $image) {
            $flImages[$image['filming_location_id']][] = $image;
        }
        foreach ($filmingLocations as &$location) {
            $location['filmingLocationImages'] = $flImages[$location['id']] ?? [];
        }

        return [
            'filmingLocations' => $filmingLocations,
            'filmingLocationCount' => $this->filmingLocationRepository->count(),//count($filmingLocations),
            'filmingLocationImageCount' => $this->filmingLocationImageRepository->count(),//count($filmingLocationImages),
            'bounds' => $bounds,
        ];
    }
}
