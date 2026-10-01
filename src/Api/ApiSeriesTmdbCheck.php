<?php

namespace App\Api;

use App\Entity\User;
use App\Repository\SeriesRepository;
use App\Service\DateService;
use App\Service\KeywordService;
use App\Service\SeriesService;
use App\Service\TMDBService;
use Closure;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\DependencyInjection\Attribute\AutowireMethodOf;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/series', name: 'api_series_')]
readonly class ApiSeriesTmdbCheck
{
    public function __construct(
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure          $getUser,
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure          $json,
        private DateService      $dateService,
        private SeriesRepository $seriesRepository,
        private TMDBService      $tmdbService,
        private KeywordService   $keywordService,
        private SeriesService    $seriesService,
    )
    {
    }

    #[Route('/tmdb/check', name: 'tmdb_check', methods: ['POST'])]
    public function tmdbCheck(Request $request): Response
    {
        $user = ($this->getUser)();
        $locale = $user->getPreferredLanguage() ?? $request->getLocale();
        $data = json_decode($request->getContent(), true);
        $tmdbIds = $data['tmdbIds'];

        $dbSeries = $this->seriesRepository->findBy(['tmdbId' => $tmdbIds]);
        $dbSeriesCount = count($dbSeries);
        $seriesIds = array_map(fn($series) => $series->getId(), $dbSeries);
        $localizedNameArr = $this->seriesRepository->getLocalizedNames($seriesIds, $locale);
        $localizedNames = [];
        foreach ($localizedNameArr as $ln) {
            $localizedNames[$ln['series_id']] = $ln['name'];
        }

        $now = $this->now($user);
        $tmdbCalls = 0;
        $updates = [];
        $messages = [];

        $t0 = microtime(true);
        foreach ($dbSeries as $series) {
            $lastUpdate = $series->getUpdatedAt();
            $interval = $now->diff($lastUpdate);

            if ($interval->days < 1) {
                $updates[] = [
                    'id' => $series->getId(),
                    'name' => $series->getName(),
                    'localized_name' => $localizedNames[$series->getId()] ?? null,
                    'poster_path' => $series->getPosterPath(),
                    'updates' => [], // '*** Updated less than 24 hours ago ***'
                ];
                continue;
            }
            $tv = json_decode($this->tmdbService->getTv($series->getTmdbId(), $locale, ['images', 'keywords']), true);
            $tmdbCalls++;
            if ($tv == null || isset($tv['error'])) {
                $updates[] = [
                    'id' => $series->getId(),
                    'name' => $series->getName(),
                    'localized_name' => $localizedNames[$series->getId()] ?? null,
                    'poster_path' => $series->getPosterPath(),
                    'updates' => ['*** Series not found ***'],
                ];
                $series->setUpdatedAt($now);
                $this->seriesRepository->save($series);
                continue;
            }
            $messages = array_merge($messages, $this->keywordService->saveKeywords($tv['keywords']['results'], 'api'));
            $updateSeries = $this->seriesService->updateSeries($series, $tv, []);
            $update = $updateSeries->getUpdates();
            $updates[] = [
                'id' => $series->getId(),
                'name' => $series->getName(),
                'localized_name' => $localizedNames[$series->getId()] ?? null,
                'poster_path' => $series->getPosterPath(),
                'updates' => $update];
            $series->setUpdatedAt($now);
            $this->seriesRepository->save($series);

            $t1 = microtime(true);
            $interval = $t1 - $t0;
            if ($interval > 25) {
                break;
            }
        }
        $this->seriesRepository->flush();

        return ($this->json)([
            'ok' => true,
            'updates' => $updates,
            'messages' => $messages,
            'dbSeriesCount' => $dbSeriesCount,
            'tmdbCalls' => $tmdbCalls,
        ]);
    }

    public function now(User $user): DateTimeImmutable
    {
        return $this->dateService->newDateImmutable('now', $user->getTimezone() ?? 'Europe/Paris');
    }
}