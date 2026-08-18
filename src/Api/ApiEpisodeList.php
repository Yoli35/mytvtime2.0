<?php

namespace App\Api;

use App\Entity\User;
use App\Repository\SettingsRepository;
use App\Repository\UserEpisodeRepository;
use App\Repository\UserSeasonRepository;
use App\Repository\WatchProviderRepository;
use App\Service\DateService;
use App\Service\ImageConfiguration;
use App\Service\TMDBService;
use Closure;
use DateTime;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\DependencyInjection\Attribute\AutowireMethodOf;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/episode/list', name: 'app_episode_list')]
readonly class ApiEpisodeList
{
    public function __construct(
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure                 $renderView,
        private DateService             $dateService,
        private ImageConfiguration      $imageConfiguration,
        private SettingsRepository      $settingsRepository,
        private TMDBService             $tmdbService,
        private UserEpisodeRepository   $userEpisodeRepository,
        private UserSeasonRepository    $userSeasonRepository,
        private WatchProviderRepository $providerRepository,
    )
    {
    }

    #[Route('/get', name: 'get', methods: ['POST'])]
    public function get(#[CurrentUser] User $user, Request $request): JsonResponse
    {
        $inputBag = $request->getPayload();
        $userSeasonId = $inputBag->getInt('seasonId');

        $userSeason = $this->userSeasonRepository->findOneBy(['id' => $userSeasonId]);
        $userEpisodes = $this->userEpisodeRepository->findBy(['userSeason' => $userSeason, 'previousOccurrence' => null]);

        $seasonNumber = $userSeason->getSeasonNumber();
        $userSeries = $userSeason->getUserSeries();
        $series = $userSeries->getSeries();
        $seriesId = $series->getTmdbId();
        $tvSeason = json_decode($this->tmdbService->getTvSeason($seriesId, $seasonNumber, $request->getLocale()), true);

        $now = new DateTime()->format('Y-m-d');
        $posterUrl = $this->imageConfiguration->getUrl('poster_sizes', 5);
        $stillUrl = $this->imageConfiguration->getUrl('still_sizes', 3);
        $seasonPosterPath = $tvSeason['poster_path'] ? $posterUrl . $tvSeason['poster_path'] : null;
        $episodes = array_map(function ($ue) use ($tvSeason, $seasonPosterPath, $stillUrl, $now) {
            $tvEpisode = array_find($tvSeason['episodes'], fn($e) => $e['episode_number'] === $ue->getEpisodeNumber());
            return [
                'airDate' => $now >= $tvEpisode['air_date'] ? $tvEpisode['air_date'] : null,
                'episodeNumber' => $tvEpisode['episode_number'],
                'name' => $tvEpisode['name'],
                'posterPath' => $tvEpisode['still_path'] ? ($stillUrl . $tvEpisode['still_path']) : $seasonPosterPath,
                'ue' => $ue,
            ];
        }, $userEpisodes);

        $watchLinks = $series->getSeriesWatchLinks()->toArray();
        $providers = $this->getProviders($user->getCountry() ?? "FR", $series->getFirstAirDate()->format('Y'));
        dump($providers);

        $block = ($this->renderView)('_blocks/episode/_list.html.twig', [
            'seasonNumber' => $seasonNumber,
            'episodes' => $episodes,
            'watchLinks' => $watchLinks,
            'providers' => $providers,
        ]);

        return new JsonResponse(['view' => $block]);
    }

    #[Route('/toggle', name: 'toggle', methods: ['POST'])]
    public function toggle(#[CurrentUser] User $user, Request $request): JsonResponse
    {
        $content = json_decode($request->getContent(), true);
        $episodeData = $content['episodeData'];
        $seasonNumber = $content['seasonNumber'];
        $providerId = $content['providerId'];
        $isWatchedAsTheyGo = $content['isWatchedAsTheyGo'];

        $firstEpisode = array_first($episodeData);
        $episodeId = $firstEpisode['episodeId'];
        $watched = $firstEpisode['watched'];
        if (!$watched && $seasonNumber > 1) {
            $userEpisode = $this->userEpisodeRepository->find($episodeId);
            $firstEpisodeNumber = $userEpisode->getEpisodeNumber();
            $userSeries = $userEpisode->getUserSeries();
            // on récupère les user épisodes des saisons précédentes
            $previousSeasonEpisodes = array_filter($this->userEpisodeRepository->findBy(['userSeries' => $userSeries, 'previousOccurrence' => null]), function ($episode) use ($firstEpisodeNumber, $seasonNumber) {
                return $episode->getWatchAt() === null && (($episode->getSeasonNumber() < $seasonNumber) || ($episode->getSeasonNumber() == $seasonNumber && $episode->getEpisodeNumber() < $firstEpisodeNumber));
            });
            $previousSeasonEpisodes = array_map(function ($episode) {
                return ['episodeId' => $episode->getId(), 'episodeNumber' => $episode->getEpisodeNumber(), 'watched' => false];
            }, $previousSeasonEpisodes);
            $episodeData = array_merge($previousSeasonEpisodes, $episodeData);
            dump($episodeData);
        }

        $now = $lastDate = $this->now($user);
        $n = 0;
        $markedAsWatched = 0;
        $markedAsNotWatched = 0;
        $message = 'No episode updated';

        foreach ($episodeData as $episode) {
            $ue = $this->userEpisodeRepository->find($episode['episodeId']);
            if ($ue) {
                if ($episode['watched']) {
                    $ue->setWatchAt(null);
                    $markedAsNotWatched++;
                } else {
                    if ($isWatchedAsTheyGo) {
                        $lastDate = $ue->getAirDate() ?: $lastDate;
                        $ue->setWatchAt($lastDate->setTime(18, 0));
                    } else {
                        $ue->setWatchAt($now);
                    }
                    if ($providerId > 0) {
                        $ue->setProviderId($providerId);
                    } else {
                        $ue->setProviderId(null);
                    }
                    $markedAsWatched++;
                }
                $this->userEpisodeRepository->save($ue);
                $n++;
            }
        }
        if ($markedAsNotWatched > 0) {
            $message = 'Episode marked as not watched: ' . $markedAsNotWatched;
        }
        if ($markedAsWatched > 0) {
            $message = 'Episode marked as watched: ' . $markedAsWatched;
        }
        if ($n > 0) {
            $this->userEpisodeRepository->flush();
            $success = true;
        } else {
            $message = 'No episode updated';
            $success = false;
        }
        return new JsonResponse(['success' => $success, 'message' => $message]);
    }

    private function getProviders(string $country, int $year): array
    {
        $settingsArr = $this->settingsRepository->findBy(['name' => 'local providers']);
        if (empty($settingsArr)) {
            return [];
        }
        foreach ($settingsArr as $settings) {
            $data = $settings->getData();
            if ($data['origin_country'] == $country && $year >= $data['year_gte'] && $year <= $data['year_lte']) {
                $providerIds = $data['provider_ids'];
                return $this->providerRepository->findBy(['id' => $providerIds]);
            }
        }
        return $this->providerRepository->findBy(['country' => $country]);
    }

    private function now(User $user): DateTimeImmutable
    {
        $timezone = $user->getTimezone() ?? 'Europe/Paris';
        return $this->dateService->newDateImmutable('now', $timezone);
    }
}