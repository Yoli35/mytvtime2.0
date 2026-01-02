<?php

namespace App\Api;

use App\Repository\FilmingLocationImageRepository;
use App\Repository\FilmingLocationRepository;
use Closure;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\DependencyInjection\Attribute\AutowireMethodOf;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/api/season', name: 'api_season_')]
readonly class ApiSeasonFilmingLocation
{
    public function __construct(
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure                        $json,
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure                        $renderView,
        private FilmingLocationRepository      $filmingLocationRepository,
        private FilmingLocationImageRepository $filmingLocationImageRepository,
    )
    {
    }

    #[Route('/filming/location/{id}', name: 'filming_location', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function add(Request $request, int $id): JsonResponse
    {
        $inputBag = $request->getPayload();
        $seasonNumber = $inputBag->get('seasonNumber');
        $flArr = [];

        $fl = $this->filmingLocationRepository->findBy([
            'tmdbId' => $id,
            'seasonNumber' => $seasonNumber,
        ]);

        foreach ($fl as $l) {
            $location = $l->toArray();
            $li = $this->filmingLocationImageRepository->findBy(['filmingLocation' => $l]);
            $images = array_map(fn($i) => $i->getPath(), $li);
            $location['images'] = $images;
            $flArr[$location['episode_number']]['locations'][] = $location;
        }
        foreach ($flArr as $e => $location) {
            $flArr[$e]['episode_number'] = $e;
            $flArr[$e]['block'] = ($this->renderView)('_blocks/series/_season_filming_locations.html.twig', [
                'locations' => $location['locations'],
            ]);
        }

        return ($this->json)([
            'ok' => true,
            'results' => array_values($flArr),
        ]);
    }
}