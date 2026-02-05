<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\FilmingLocationImageRepository;
use App\Repository\FilmingLocationRepository;
use App\Repository\UserListRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class MapService
{
    public function __construct(
        private FilmingLocationImageRepository $filmingLocationImageRepository,
        private FilmingLocationRepository $filmingLocationRepository,
    )
    {
    }

    public function get(string $order, int $page = 1, int $perPage = 50): array
    {
        $filmingLocations = $this->filmingLocationRepository->allLocations($order, $page, $perPage);

        return $this->locationsWithImages($filmingLocations);
    }

    public function lastest(int $id): array
    {
        $filmingLocations = $this->filmingLocationRepository->findLatestLocations($id);

        return $this->locationsWithImages($filmingLocations);
    }

    public function lastId(): int
    {
        return $this->filmingLocationRepository->lastId();
    }

    private function locationsWithImages($filmingLocations): array
    {
        $filmingLocationIds = array_column($filmingLocations, 'id');

        // Bounding box → center
        $minLat = min(array_column($filmingLocations, 'latitude'));
        $maxLat = max(array_column($filmingLocations, 'latitude'));
        $minLng = min(array_column($filmingLocations, 'longitude'));
        $maxLng = max(array_column($filmingLocations, 'longitude'));
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