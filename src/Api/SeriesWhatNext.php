<?php

namespace App\Api;

use App\Entity\User;
use App\Repository\UserSeriesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/** @method User|null getUser() */
#[Route('/api/series', name: 'api_series_')]
class SeriesWhatNext extends AbstractController
{
    public function __construct(
        private readonly UserSeriesRepository $userSeriesRepository,
    )
    {
    }

    #[Route('/what/next', name: 'what_next', methods: ['GET'])]
    public function whatNext(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $filters = ['page' => 1, 'perPage' => 20, 'sort' => 'finalAirDate', 'order' => 'DESC', 'network' => 'all'];
        $localisation = ['language' => 'fr_FR', 'country' => 'FR', 'timezone' => 'Europe/Paris', 'locale' => 'fr'];
        $userSeries = $this->userSeriesRepository->getAllSeries(
            $user,
            $localisation,
            $filters);
        $userSeries = array_map(function ($series) {
            $series['poster_path'] = $series['poster_path'] ? '/series/posters' . $series['poster_path'] : null;
            return $series;
        }, $userSeries);
        $blocks = [];
        foreach ($userSeries as $series) {
            $blocks[] = $this->renderView('_blocks/series/_card.html.twig', [
                'series' => $series,
                "allFiltered" => true,
                "sort" => $filters['sort'],
            ]);
        }
        dump($blocks);
        return new JsonResponse([
            'blocks' => $blocks,
        ]);
    }
}