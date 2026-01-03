<?php

namespace App\Api;

use App\Entity\Settings;
use App\Entity\User;
use App\Repository\SettingsRepository;
use App\Repository\UserSeriesRepository;
use App\Service\ImageConfiguration;
use App\Service\TMDBService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Contracts\Translation\TranslatorInterface;

/** @method User|null getUser() */
#[Route('/api/series', name: 'api_series_')]
class SeriesWhatNext extends AbstractController
{
    public function __construct(
        private readonly ImageConfiguration   $imageConfiguration,
        private readonly TMDBService          $tmdbService,
        private readonly UserSeriesRepository $userSeriesRepository,
        private readonly SettingsRepository   $settingsRepository,
        private readonly TranslatorInterface $translator,
    )
    {
    }

    #[Route('/what/next', name: 'what_next', methods: ['GET'])]
    public function next(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $settings = $this->getSettings($user);

        $filters = ['page' => 1, 'perPage' => $settings['limit'], 'sort' => $settings['sort'], 'order' => $settings['order'], 'network' => 'all'];
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
                'home' => true,
                'allFiltered' => true,
                'sort' => $filters['sort'],
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
                    'home' => true,
                ]);
            }
        }

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

        return new JsonResponse([
            'blocks' => $blocks,
            'sortOption' => $sortOptions[$settings['sort']],
            'orderOption' => $orderOptions[$settings['order']],
            'limitOption' => $settings['limit'],
        ]);
    }

    public function getSettings(User $user): array
    {
        $settings = $this->settingsRepository->findOneBy(['user' => $user, 'name' => 'seriesWhatNext']);
        if (!$settings) {
            $settings = new Settings($user, 'seriesWhatNext', [
                'default_limit' => 20,
                'default_order' => 'DESC',
                'default_sort' => 'lastWatched',
                'limit' => 20,
                'order' => 'DESC',
                'sort' => 'lastWatched',
            ]);
            $this->settingsRepository->save($settings, true);
        }
        return $settings->getData();
    }
}