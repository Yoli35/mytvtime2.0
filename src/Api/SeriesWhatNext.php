<?php

namespace App\Api;

use App\Entity\User;
use App\Repository\UserSeriesRepository;
use App\Service\ImageConfiguration;
use App\Service\TMDBService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\AsciiSlugger;

/** @method User|null getUser() */
#[Route('/api/series', name: 'api_series_')]
class SeriesWhatNext extends AbstractController
{
    public function __construct(
        private readonly ImageConfiguration   $imageConfiguration,
        private readonly TMDBService          $tmdbService,
        private readonly UserSeriesRepository $userSeriesRepository,
    )
    {
    }

    #[Route('/what/next', name: 'what_next', methods: ['GET'])]
    public function next(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $filters = ['page' => 1, 'perPage' => 20, 'sort' => 'finalAirDate', 'order' => 'DESC', 'network' => 'all'];
        $localisation = ['language' => 'fr_FR', 'country' => 'FR', 'timezone' => 'Europe/Paris', 'locale' => 'fr'];
        $userSeries = $this->userSeriesRepository->getAllSeries(
            $user,
            $localisation,
            $filters,
            true
        );
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

        if (count($blocks) < 20) {
            $missingCount = 20 - count($blocks);
            /*$data = json_decode($request->getContent(), true);*/
            $tmdbId = $request->query->get('id');//$data['id'];
            $language = $request->query->get('language');//$data['language'] ?? 'fr';
            $similarSeries = json_decode($this->tmdbService->getTvSimilar($tmdbId, $language), true);
            if (!$similarSeries || !isset($similarSeries['results']) || count($similarSeries['results']) === 0) {
                $similarSeries = json_decode($this->tmdbService->getTvSimilar($tmdbId), true);
            }
            if (!$similarSeries || !isset($similarSeries['results']) || count($similarSeries['results']) === 0) {
                return new JsonResponse([
                    'blocks' => $blocks,
                ]);
            }
            $posterUrl = $this->imageConfiguration->getUrl('poster_sizes', 5);
            $similarSeries = array_map(function ($s) use ($posterUrl) {
                $s['poster_path'] = $s['poster_path'] ? $posterUrl . $s['poster_path'] : null;
                $s['tmdb'] = true;
                $s['slug'] = new AsciiSlugger()->slug($s['name']);
                return $s;
            }, $similarSeries['results']);
            $similarSeries = array_slice($similarSeries, 0, $missingCount);
            foreach ($similarSeries as $series) {
                $blocks[] = $this->renderView('_blocks/series/_card.html.twig', [
                    'series' => $series,
                ]);
            }
        }

        return new JsonResponse([
            'blocks' => $blocks,
        ]);
    }
}