<?php

namespace App\Api;

use App\Entity\FilmingLocation;
use App\Entity\User;
use App\Repository\FilmingLocationRepository;
use App\Repository\SettingsRepository;
use App\Service\DateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/** @method User|null getUser() */
#[Route('/api/series', name: 'api_series_')]
class SeriesFilmingLocations extends AbstractController
{
    public function __construct(
        private readonly DateService               $dateService,
        private readonly FilmingLocationRepository $filmingLocationRepository,
        private readonly SettingsRepository        $settingsRepository,
    )
    {
    }

    #[Route('/get/filming/locations/{id}', name: 'get_filming_locations', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function getLocations(int $id): JsonResponse
    {
        if (!$id) {
            return new JsonResponse(['error' => 'No series ID provided'], 400);
        }
        $filmingLocationsWithBounds = $this->getFilmingLocations($id);
        $mapSettings = $this->settingsRepository->findOneBy(['name' => 'mapbox']);
        $mapSettingsData = $mapSettings ? $mapSettings->getData() : ['styles' => []];
        $block = $this->render('_blocks/map/_map-container.html.twig', ['type' => 'series', 'styleSettings' => $mapSettingsData['styles']]);

        return new JsonResponse([
            'mapBlock' => $block->getContent(),
            'locations' => $filmingLocationsWithBounds['filmingLocations'],
            'locationsBounds' => $filmingLocationsWithBounds['bounds'],
            'emptyLocation' => $filmingLocationsWithBounds['emptyLocation'],
            'fieldList' => ['series-id', 'tmdb-id', 'crud-type', 'crud-id', 'title', 'location', 'season-number', 'episode-number', 'description', 'latitude', 'longitude', 'radius', "source-name", "source-url"],
            "locationImagePath" => "/images/map",
            "poiImagePath" => "/images/poi",
        ]);
    }

    private function getFilmingLocations(int $id): array
    {
        $tmdbId = $id;
        $filmingLocations = $this->filmingLocationRepository->locations($tmdbId);
        $emptyLocation = $this->newLocation($id);
        if (count($filmingLocations) == 0) {
            return [
                'filmingLocations' => [],
                'emptyLocation' => $emptyLocation,
                'bounds' => []
            ];
        }
        $filmingLocationIds = array_column($filmingLocations, 'id');
        $filmingLocationImages = $this->filmingLocationRepository->locationImages($filmingLocationIds);
        $flImages = [];
        foreach ($filmingLocationImages as $image) {
            $flImages[$image['filming_location_id']][] = $image;
        }
        foreach ($filmingLocations as &$location) {
            $location['filmingLocationImages'] = $flImages[$location['id']] ?? [];
        }
        // Bounding box → center
        if (count($filmingLocations) == 1) {
            $loc = $filmingLocations[0];
            $bounds = [[$loc['longitude'] + .1, $loc['latitude'] + .1], [$loc['longitude'] - .1, $loc['latitude'] - .1]];
        } else {
            $minLat = min(array_column($filmingLocations, 'latitude'));
            $maxLat = max(array_column($filmingLocations, 'latitude'));
            $minLng = min(array_column($filmingLocations, 'longitude'));
            $maxLng = max(array_column($filmingLocations, 'longitude'));
            $bounds = [[$maxLng + .1, $maxLat + .1], [$minLng - .1, $minLat - .1]];
        }

        return [
            'filmingLocations' => $filmingLocations,
            'emptyLocation' => $emptyLocation,
            'bounds' => $bounds
        ];
    }

    private function newLocation(int $id): array
    {
        $uuid = Uuid::v4()->toString();
        $now = $this->dateService->getNowImmutable('UTC');
        $tmdbId = $id;
        $emptyLocation = new FilmingLocation($uuid, $tmdbId, "", "", "", 0, 0, null, 0, 0, "", "", $now, true);
        $emptyLocation->setOriginCountry(["FR"]);
        return $emptyLocation->toArray();
    }
}