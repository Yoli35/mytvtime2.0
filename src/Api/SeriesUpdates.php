<?php

namespace App\Api;

use App\Repository\SeriesRepository;
use App\Service\DateService;
use App\Service\ImageConfiguration;
use App\Service\TMDBService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[Route('/api/series', name: 'api_series_')]
class SeriesUpdates extends AbstractController
{
    public function __construct(
        private readonly DateService        $dateService,
        private readonly ImageConfiguration $imageConfiguration,
        private readonly SeriesRepository   $seriesRepository,
        private readonly TMDBService        $tmdbService,
    )
    {
        // Inject dependencies if needed
    }

    #[Route('/batch/update', name: 'update_series', methods: ['POST'])]
    public function seriesBatchUpdate(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $ids = $data['ids'] ?? [];
        $results = [];
        $endedSeriesStatus = ['Ended', 'Canceled'];
        $slugger = new AsciiSlugger();

        foreach ($ids as $item) {
            $now = $this->dateService->getNowImmutable('UTC');

            $updates = [];
            $series = $this->seriesRepository->find($item['id']);
            if ($series) {
                $name = $series->getName() ?: 'Unknown Series';
                $lastUpdate = $series->getUpdatedAt();
                $checkStatus = 'Checking';
                $diff = $now->diff($lastUpdate);

                if ($diff->days < 1) { // More than 1 day since last update
                    $checkStatus = 'Passed';
                }
                if ($series->getStatus() && in_array($series->getStatus(), $endedSeriesStatus)) {
                    $checkStatus = 'Ended';
                }

                if ($checkStatus !== 'Checking') {
                    $results[] = [
                        'check' => $checkStatus,
                        'id' => $series->getId(),
                        'name' => $name,
                        'localizedName' => $series->getLocalizedName('fr')?->getName() ?? '',
                        'updates' => [],
                        'lastUpdate' => ucfirst($this->dateService->formatDateRelativeLong($lastUpdate->format('Y-m-d H:i:s'), "Europe/Paris", "fr")),
                    ];
                    continue;
                }

                $tvFR = json_decode($this->tmdbService->getTv($item['tmdb_id'], 'fr-FR'), true);
                $tvUS = json_decode($this->tmdbService->getTv($item['tmdb_id'], 'en-US'), true);

                if (!$tvFR || !$tvUS) {
                    $checkStatus = 'Not Found';
                    $name .= ' - Series not found on TMDB (' . $item['tmdb_id'] . ')';
                    $this->addFlash('error', 'Series not found on TMDB: ' . $name);

                    $results[] = [
                        'check' => $checkStatus,
                        'id' => $item['id'],
                        'name' => $name,
                        'localizedName' => '',
                        'updates' => [],
                        'lastUpdate' => ucfirst($this->dateService->formatDateRelativeLong($lastUpdate->format('Y-m-d H:i:s'), "Europe/Paris", "fr")),
                    ];
                    continue;
                }

                $firstAirDate = $tvFR['first_air_date'] ?? $tvUS['first_air_date'] ?? null;
                $dbFirstAirDate = $series->getFirstAirDate()?->format("Y-m-d");
                if ($firstAirDate && $dbFirstAirDate !== $firstAirDate) {
                    $updates[] = ['field' => 'first_air_date', 'label' => 'First air date', 'valueBefore' => $series->getFirstAirDate() ? $series->getFirstAirDate()->format('Y-m-d') : 'No date', 'valueAfter' => $firstAirDate];
                    $series->setFirstAirDate($this->dateService->newDateImmutable($firstAirDate, 'Europe/Paris', true));
                }

                $name = $tvFR['name'] ?? $tvUS['name'] ?? null;
                if ($name && $series->getName() !== $name) {
                    $updates[] = ['field' => 'name', 'label' => 'Name', 'valueBefore' => $series->getName(), 'valueAfter' => $name];
                    $series->setName($name);
                    $series->setSlug($slugger->slug($name)->lower()->toString());
                }

                $overview = $tvFR['overview'] ?? $tvUS['overview'] ?? null;
                if ($overview && !$series->getOverview()) {
                    $updates[] = ['field' => 'overview', 'label' => 'Overview', 'valueBefore' => $series->getOverview(), 'valueAfter' => $overview];
                    $series->setOverview($overview);
                }

                $backdropPath = $tvFR['backdrop_path'] ?? $tvUS['backdrop_path'] ?? null;
                if ($backdropPath && $series->getBackdropPath() !== $backdropPath) {
                    $updates[] = ['field' => 'backdrop_path', 'label' => 'Backdrop', 'valueBefore' => $series->getBackdropPath(), 'valueAfter' => $backdropPath];
                    $series->setBackdropPath($backdropPath);
                }

                $posterPath = $tvFR['poster_path'] ?? $tvUS['poster_path'] ?? null;
                if ($posterPath && $series->getPosterPath() !== $posterPath) {
                    $updates[] = ['field' => 'poster_path', 'label' => 'Poster', 'valueBefore' => $series->getPosterPath(), 'valueAfter' => $posterPath];
                    $series->setPosterPath($posterPath);
                }

                $status = $tvFR['status'] ?? $tvUS['status'] ?? null;
                if ($status && $series->getStatus() !== $status) {
                    $updates[] = ['field' => 'status', 'label' => 'Status', 'valueBefore' => $series->getStatus(), 'valueAfter' => $status];
                    $series->setStatus($status);
                }

                $seasonNUmber = $tvFR['number_of_seasons'] ?? $tvUS['number_of_seasons'] ?? null;
                if ($seasonNUmber && $series->getNumberOfSeason() !== $seasonNUmber) {
                    $updates[] = ['field' => 'number_of_seasons', 'label' => 'Number of seasons', 'valueBefore' => $series->getNumberOfSeason(), 'valueAfter' => $seasonNUmber];
                    $series->setNumberOfSeason($seasonNUmber);
                }

                $episodeNumber = $tvFR['number_of_episodes'] ?? $tvUS['number_of_episodes'] ?? null;
                if ($episodeNumber && $series->getNumberOfEpisode() !== $episodeNumber) {
                    $updates[] = ['field' => 'number_of_episodes', 'label' => 'Number of episodes', 'valueBefore' => $series->getNumberOfEpisode(), 'valueAfter' => $episodeNumber];
                    $series->setNumberOfEpisode($episodeNumber);
                }

                $series->setUpdatedAt($now);
                $this->seriesRepository->save($series, true);
                $lastUpdate = $now;

                if (count($updates)) {
                    $checkStatus = 'Updated';
                } else {
                    $checkStatus = 'No changes';
                }

                $results[] = [
                    'check' => $checkStatus,
                    'id' => $series->getId(),
                    'name' => $name,
                    'localizedName' => $series->getLocalizedName('fr')?->getName() ?? '',
                    'updates' => $updates,
                    'lastUpdate' => ucfirst($this->dateService->formatDateRelativeLong($lastUpdate->format('Y-m-d H:i:s'), "Europe/Paris", "fr") . '(' . $diff->days . ' days ago)'),
                ];
            } else {

                $results[] = [
                    'check' => 'Not found',
                    'id' => $item['id'],
                    'name' => 'Unknown in database',
                    'localizedName' => '',
                    'updates' => [],
                    'lastUpdate' => ucfirst($this->dateService->formatDateRelativeLong($now->format('Y-m-d H:i:s'), "Europe/Paris", "fr")),
                ];
            }
        }

        return new JsonResponse([
            'status' => 'success',
            'results' => $results,
        ]);
    }
}