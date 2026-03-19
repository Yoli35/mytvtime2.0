<?php

namespace App\Api;

use App\Entity\User;
use App\Repository\UserSeriesRepository;
use App\Service\ImageConfiguration;
use App\Service\TMDBService;
use App\Service\WhatNextSettingsService;
use Closure;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\DependencyInjection\Attribute\AutowireMethodOf;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Contracts\Translation\TranslatorInterface;

/** @method User|null getUser() */
#[Route('/api/series', name: 'api_series_')]
readonly class ApiSeriesWhatNext
{
    public function __construct(
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure                 $getUser,
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure                 $renderView,
        private ImageConfiguration      $imageConfiguration,
        private TMDBService             $tmdbService,
        private UserSeriesRepository    $userSeriesRepository,
        private TranslatorInterface     $translator,
        private WhatNextSettingsService $whatNextSettingsService,
    )
    {
    }

    #[Route('/what/next', name: 'what_next', methods: ['GET'])]
    public function next(Request $request): JsonResponse
    {
        $user = ($this->getUser)();
        $settings = $this->whatNextSettingsService->getSettings($user);

        $filters = ['page' => 1, 'limit' => $settings['limit'], 'sort' => $settings['sort'], 'order' => $settings['order'], 'network' => 'all'];
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
            $blocks[] = ($this->renderView)('_blocks/series/_card_what_next.html.twig', [
                'series' => $series,
                'link_type' => $settings['link_to']
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
                $blocks[] = ($this->renderView)('_blocks/series/_card_what_next.html.twig', [
                    'series' => $series,
                    'link_type' => 'tmdb'
                ]);
            }
        }
        $optionStrings = $this->optionStrings();

        return new JsonResponse([
            'blocks' => $blocks,
            'sortOption' => $optionStrings['sort'][$settings['sort']],
            'orderOption' => $optionStrings['order'][$settings['order']],
            'limitOption' => $settings['limit'],
            'linkOption' => $settings['link_to'],
        ]);
    }

    private function optionStrings(): array
    {
        $sortOptions = [
            'first_air_date' => $this->translator->trans('First air date'),
            'lastWatched' => $this->translator->trans('Last watched'),
            'episodeAirDate' => $this->translator->trans('Episode air date'),
            'name' => $this->translator->trans('Name'),
            'addedAt' => $this->translator->trans('Date added'),
            'finalAirDate' => $this->translator->trans('Final air date'),
        ];
        $orderOptions = [
            'ASC' => $this->translator->trans('Ascending'),
            'DESC' => $this->translator->trans('Descending'),
        ];
        return ['sort' => $sortOptions, 'order' => $orderOptions];
    }
}