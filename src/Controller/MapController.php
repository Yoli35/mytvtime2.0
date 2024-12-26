<?php

namespace App\Controller;

use App\Repository\FilmingLocationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/{_locale}/map', name: 'app_map_', requirements: ['_locale' => 'fr|en|kr'])]
class MapController extends AbstractController
{
    public function __construct(
        private readonly FilmingLocationRepository $filmingLocationRepository,
    )
    {
    }

    #[Route('/index', name: 'index')]
    public function index(Request $request): Response
    {
        $locations = $this->getAllFilmingLocations();

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

        if ($request->getLocale() === 'fr') {
            uasort($countries, function ($a, $b) {
                $a = str_replace('É', 'E', $a);
                $b = str_replace('É', 'E', $b);
                return strcasecmp($a, $b);
            });
        }

        // "fl" => array:90 [▼
        //    236900 => array:2 [▼
        //      "locations" => array:5 [▶]
        //      "country" => array:1 [▼
        //        0 => "TH"
        //      ]
        //    ]
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
            'countryLatLngs' => $countryLatLngs,
            'countryLocationIds' => $countryLocationIds,
            'countries' => $countries,
//            'filmingLocations' => $locations['filmingLocations'],
//            'filmingLocationCount' => $locations['filmingLocationCount'],
//            'filmingLocationImagesCount' => $locations['filmingLocationImageCount'],
        ]);

        return $this->render('map/index.html.twig', [
            'fl' => $fl,
            'countries' => $countries,
            'countryLatLngs' => $countryLatLngs,
            'countryLocationIds' => $countryLocationIds,
            'seriesCount' => count($fl),
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
            'filmingLocations' => $filmingLocations,
            'filmingLocationCount' => count($filmingLocations),
            'filmingLocationImageCount' => count($filmingLocationImages),];
    }
}
