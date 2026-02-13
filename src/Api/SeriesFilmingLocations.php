<?php

namespace App\Api;

use App\Entity\User;
use App\Repository\SeriesLocalizedNameRepository;
use App\Repository\SeriesRepository;
use App\Repository\SettingsRepository;
use App\Service\SeriesService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/** @method User|null getUser() */
#[Route('/api/series', name: 'api_series_')]
class SeriesFilmingLocations extends AbstractController
{
    public function __construct(
        private readonly SeriesLocalizedNameRepository $seriesLocalizedNameRepository,
        private readonly SeriesRepository              $seriesRepository,
        private readonly SeriesService                 $seriesService,
        private readonly SettingsRepository            $settingsRepository,
    )
    {
    }

    #[Route('/get/filming/locations/{locale}/{id}', name: 'get_filming_locations', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function getLocations(string $locale, int $id): JsonResponse
    {
        if (!$id) {
            return new JsonResponse(['error' => 'No series ID provided'], 400);
        }
        $series = $this->seriesRepository->findOneBy(['tmdbId' => $id]);
        if (!$series) {
            return new JsonResponse(['error' => 'Series not found'], 404);
        }
        $sln = $this->seriesLocalizedNameRepository->findOneBy(['series' => $series, 'locale' => $locale]);
        $filmingLocationsWithBounds = $this->seriesService->getFilmingLocations($series, $sln);
        $mapSettings = $this->settingsRepository->findOneBy(['name' => 'mapbox']);
        $mapSettingsData = $mapSettings ? $mapSettings->getData() : ['styles' => []];
        $block = $this->render('_blocks/map/_map_container.html.twig', ['type' => 'series', 'styleSettings' => $mapSettingsData['styles']]);

        $data = json_encode([
            'locations' => $filmingLocationsWithBounds['filmingLocations'],
            'bounds' => $filmingLocationsWithBounds['bounds'],
            'emptyLocation' => $filmingLocationsWithBounds['emptyLocation'],
            'fieldList' => ['series-id', 'tmdb-id', 'crud-type', 'crud-id', 'title', 'location', 'season-number', 'episode-number', 'description', 'latitude', 'longitude', 'radius', "source-name", "source-url"],
            "locationImagePath" => "/images/map",
            "poiImagePath" => "/images/poi",
        ]);

        return new JsonResponse([
            'mapBlock' => $block->getContent(),
            'data' => $data,
        ]);
    }
}