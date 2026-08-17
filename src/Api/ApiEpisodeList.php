<?php

namespace App\Api;

use App\Entity\User;
use App\Repository\UserEpisodeRepository;
use App\Repository\UserSeasonRepository;
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
        private Closure               $renderView,
        private DateService           $dateService,
        private ImageConfiguration    $imageConfiguration,
        private TMDBService           $tmdbService,
        private UserEpisodeRepository $userEpisodeRepository,
        private UserSeasonRepository  $userSeasonRepository,
    )
    {
    }

    #[Route('/get', name: 'get', methods: ['POST'])]
    public function get(Request $request): JsonResponse
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

        $block = ($this->renderView)('_blocks/episode/_list.html.twig', ['seasonNumber' => $seasonNumber, 'episodes' => $episodes, 'watchLinks' => $watchLinks]);

        return new JsonResponse(['view' => $block]);
    }

    #[Route('/toggle', name: 'toggle', methods: ['POST'])]
    public function toggle(#[CurrentUser] User $user, Request $request): JsonResponse
    {
        $content = json_decode($request->getContent(), true);
        $episodeData = $content['episodeData'];
        $providerId = $content['providerId'];

        $now = $this->now($user);
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
                    $ue->setWatchAt($now);
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

    private function now(User $user): DateTimeImmutable
    {
        $timezone = $user->getTimezone() ?? 'Europe/Paris';
        return $this->dateService->newDateImmutable('now', $timezone);
    }
}