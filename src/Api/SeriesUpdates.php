<?php

namespace App\Api;

use App\Entity\Settings;
use App\Entity\UserEpisode;
use App\Entity\UserSeries;
use App\Repository\SeriesRepository;
use App\Repository\SettingsRepository;
use App\Repository\UserEpisodeRepository;
use App\Repository\UserSeriesRepository;
use App\Service\DateService;
use App\Service\ImageConfiguration;
use App\Service\ImageService;
use App\Service\TMDBService;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[Route('/api/series', name: 'api_series_')]
class SeriesUpdates extends AbstractController
{
    public function __construct(
        private readonly DateService           $dateService,
        private readonly ImageConfiguration    $imageConfiguration,
        private readonly ImageService          $imageService,
        private readonly SeriesRepository      $seriesRepository,
        private readonly SettingsRepository    $settingsRepository,
        private readonly TMDBService           $tmdbService,
        private readonly UserEpisodeRepository $userEpisodeRepository,
        private readonly UserSeriesRepository  $userSeriesRepository,
    )
    {
    }

    #[Route('/batch/update', name: 'update_series', methods: ['POST'])]
    public function seriesBatchUpdate(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $ids = $data['ids'];
        $units = $data['units'];
        $blockStart = intval($data['blockStart']);
        $blockEnd = intval($data['blockEnd']);

        $posterUrl = $this->imageConfiguration->getUrl('poster_sizes', 5);
        $backdropUrl = $this->imageConfiguration->getUrl('backdrop_sizes', 3);

        $progress = !$blockStart ? 0 : $blockEnd;
        if (!$progress) {
            $data = $this->getDates($progress);
        }

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
                    $this->imageService->saveImage("backdrops", $backdropPath, $backdropUrl);
                    $updates[] = ['field' => 'backdrop_path', 'label' => 'Backdrop', 'valueBefore' => $series->getBackdropPath(), 'valueAfter' => $backdropPath];
                    $series->setBackdropPath($backdropPath);
                }

                $posterPath = $tvFR['poster_path'] ?? $tvUS['poster_path'] ?? null;
                if ($posterPath && $series->getPosterPath() !== $posterPath) {
                    $this->imageService->saveImage("posters", $posterPath, $posterUrl);
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

                $userSeries = $this->userSeriesRepository->findBy(['series' => $series]);
                foreach ($userSeries as $us) {
                    $seriesNewEpisodeCount = 0;
                    $userEpisodes = $us->getUserEpisodes();
                    if (count($userEpisodes) == $episodeNumber) {
                        continue;
                    }
                    foreach ($tvUS['seasons'] as $season) {
                        $seasonNumber = $season['season_number'] ?? null;
                        if ($seasonNumber === null) {
                            continue;
                        }
                        foreach ($season['episodes'] as $episode) {
                            $episodeNumber = $episode['episode_number'] ?? null;
                            if ($episodeNumber === null) {
                                continue;
                            }
                            // Check if the episode already exists for the user
                            $existingEpisode = $userEpisodes->filter(function ($ue) use ($seasonNumber, $episodeNumber) {
                                return $ue->getSeasonNumber() === $seasonNumber && $ue->getEpisodeNumber() === $episodeNumber;
                            })->first();

                            if (!$existingEpisode) {
                                // Create a new UserEpisode
                                $userEpisode = new UserEpisode($us, $episode['id'], $seasonNumber, $episodeNumber, null);
                                $this->userEpisodeRepository->save($userEpisode);
                                $seriesNewEpisodeCount++;
                            }
                        }
                    }
                    if ($seriesNewEpisodeCount > 0) {
                        $updates[] = [
                            'field' => 'new_episodes',
                            'label' => 'New episodes for user ' . $this->userToHTML($us),
                            'valueBefore' => $episodeNumber,
                            'valueAfter' => $episodeNumber + $seriesNewEpisodeCount
                        ];
                    }
                }

                $lastUpdate = $now;

                if (count($updates)) {
                    $checkStatus = 'Updated';
                    $a = $diff->format('%a');
                    if ($a == 0) {
                        // If less than 1 day, show hours, minutes, seconds, which will never appends
                        // because:
                        //     if ($diff->days < 1) { // More than 1 day since last update
                        //         $checkStatus = 'Passed';
                        //     }
                        $diffString = $diff->format("%H:%I:%S") . ' ago';
                    } elseif ($a == 1) {
                        $diffString = '1 day ago';
                    } else {
                        $diffString = $a . ' days ago';
                    }
                    $since = [
                        'field' => 'since',
                        'label' => 'Previous update',
                        'previous' => $lastUpdate->format('Y-m-d H:i:s'),
                        'since' => $diffString
                    ];
                    // Add the 'since' update to the beginning of the updates array
                    array_unshift($updates, $since);
                } else {
                    $checkStatus = 'No changes';
                }

                $results[] = [
                    'check' => $checkStatus,
                    'id' => $series->getId(),
                    'name' => $name,
                    'localizedName' => $series->getLocalizedName('fr')?->getName() ?? '',
                    'updates' => $updates,
                    'lastUpdate' => ucfirst($this->dateService->formatDateRelativeLong($lastUpdate->format('Y-m-d H:i:s'), "Europe/Paris", "fr")/* . '(' . $diff->days . ' days ago)'*/),
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

        if ($progress) {
            $data = $this->getDates($progress);
        }

        $lastUpdate = $this->dateService->newDateFromTimestamp(($data['end date'] / 1000) ?? 0, "UTC")->format("Y-m-d H:i:s");
        $lastUpdateString = $this->dateService->formatDateRelativeLong($lastUpdate, "Europe/Paris", $request->getLocale());
        $lastDuration = ($data['end date'] - $data['start date']) / 1000;
        $lastDurationString = $this->dateService->getDurationString($lastDuration, $units);

        return new JsonResponse([
            'status' => 'success',
            'results' => $results,
            'progressInfos' => [
                'progress' => $progress,
                'endDate' => $lastUpdateString,
                'duration' => $lastDurationString,
            ]
        ]);
    }

    private function getDates(int $progress): array
    {
        $settings = $this->settingsRepository->findOneBy(['name' => 'series updates']);
        if (!$settings) {
            $settings = new Settings(null, 'series updates', ['start date' => null, 'end date' => null]);
        }

        $date = new DateTimeImmutable();
        $milli = (int)$date->format('Uv');

        if ($progress == 0) {
            $settings->setData(['start date' => $milli, 'end date' => null]);
        } else {
            $settings->setData(['start date' => $settings->getData()['start date'], 'end date' => $milli]);
        }
        $this->settingsRepository->save($settings, true);

        return $settings->getData();
    }

    private function userToHTML(UserSeries $us): string
    {
        $user = $us->getUser();
        $username = htmlspecialchars($user->getUsername() ?: 'Unknown User');
        $userId = $user->getId() ?: 0;
        $avatar = '/images/users/avatars/' . $user->getAvatar() ?: 'default.png';
        return '<div class="user"><div class="avatar"><img src="'.$avatar.'" alt="'.$username.'"></div><div class="name">' . $username . '</div><div class="badge" title="User ID: ' . $userId . '"></div></div>';
    }
}