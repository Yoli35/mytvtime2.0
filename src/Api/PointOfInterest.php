<?php

namespace App\Api;

use App\Repository\PointOfInterestImageRepository;
use App\Repository\PointOfInterestRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
//use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/pois', name: 'api_pois_')]
class PointOfInterest extends AbstractController
{
    public function __construct(
        private readonly PointOfInterestRepository $pointOfInterestRepository,
        private readonly PointOfInterestImageRepository $pointOfInterestImageRepository,
    )
    {
    }

    #[Route('/get', name: 'get', methods: ['GET'])]
    public function list(/*Request $request*/): JsonResponse
    {
        $pois = $this->getALlPointsOfInterest();

        return new JsonResponse([
            'pois' => $pois,
        ]);
    }

    private function getALlPointsOfInterest(): array
    {
        $pointsOfInterest = $this->pointOfInterestRepository->allPointsOfInterest();
        $pointOfInterestIds = array_column($pointsOfInterest, 'id');
        $pointOfInterestImages = $this->pointOfInterestImageRepository->poiImages($pointOfInterestIds);

        $poiImages = [];
        foreach ($pointOfInterestImages as $image) {
            $poiImages[$image['point_of_interest_id']][] = $image;
        }
        foreach ($pointsOfInterest as &$poi) {
            $poi['poiImages'] = $poiImages[$poi['id']] ?? [];
        }
        return [
            'list' => $pointsOfInterest,
            'count' => $this->pointOfInterestRepository->count(),
            'imageCount' => $this->pointOfInterestImageRepository->count(),
        ];
    }
}