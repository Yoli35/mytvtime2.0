<?php

namespace App\Api;

use App\Entity\Series;
use App\Repository\SeriesRepository;
use App\Service\DateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/series', name: 'api_series_')]
class SeriesUpdates extends AbstractController
{
    public function __construct(
        private readonly DateService      $dateService,
        private readonly SeriesRepository $seriesRepository,
    )
    {
        // Inject dependencies if needed
    }

    #[Route('/batch/update', name: 'update_series', methods: ['POST'])]
    public function recentSeries(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $ids = $data['ids'] ?? [];
        $lastId = -1;
        $status = 'success';
        $message = '';
        $results = [];
        $now = $this->dateService->getNow('Europe/Paris', true);

        foreach ($ids as $item) {
            $series = $this->seriesRepository->find($item['id']);
            if ($series) {
                $lastUpdate = $series->getUpdatedAt();
                $check = false;
                $diff = $now->diff($lastUpdate);
                if ($diff->days > 1) {
                    $check = true; // More than 1 day since last update
                }
                $results[] = [
                    'check' => $check,
                    'id' => $series->getId(),
                    'name' => $series->getName(),
                    'localizedName' => $series->getLocalizedName('fr')?->getName() ?? '',
                    'lastUpdate' => ucfirst($this->dateService->formatDateRelativeLong($lastUpdate->format('Y-m-d H:i:s'), "Europe/Paris", "fr")),
                ];
                $lastId = max($lastId, $series->getId());
            } else {
                $status = 'error';
                $message = 'Series not found. Id: ' . $item['id'];
                break; // Stop processing if any series is not found
            }
        }

        return new JsonResponse([
            'lastId' => $lastId,
            'message' => $message,
            'status' => $status,
            'results' => $results,
        ]);
    }
}