<?php

namespace App\Api;

use App\Entity\Settings;
use App\Entity\User;
use App\Repository\SeriesRepository;
use App\Repository\SettingsRepository;
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

#[Route('/api/episode', name: 'api_episode_')]
readonly class ApiEpisodeNameCheck
{
    public function __construct(
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure            $getUser,
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure            $json,
        private DateService        $dateService,
        private SeriesRepository   $seriesRepository,
        private SettingsRepository $settingsRepository,
        private TMDBService        $tmdbService,
    )
    {
    }

    #[Route('/tmdb/check', name: 'tmdb_check', methods: ['POST'])]
    public function tmdbCheck(Request $request): Response
    {
        $user = ($this->getUser)();
        $locale = $user->getPreferredLanguage() ?? $request->getLocale();

        if ($locale === 'ko') {
            return ($this->json)([
                'ok' => true,
                'updates' => [],
                'messages' => ['Episode name check skipped'],
                'count' => 0,
            ]);
        }

        $data = json_decode($request->getContent(), true);
        $episodeData = $data['episodeData'];

        $lastUpdates = $this->getSettings($user);
        $now = $this->now($user);
        $tmdbCalls = 0;
        $updates = [];
        $messages = [];

        $t0 = microtime(true);
        foreach ($episodeData as $ep) {
            $seriesId = $ep['id'];
            $seasonNumber = $ep['seasonNumber'];
            $episodeNumber = $ep['episodeNumber'];

            $lastUpdate = array_find($lastUpdates, fn($update) => $update['id'] === $seriesId && $update['season'] === $seasonNumber && $update['episode'] === $episodeNumber);
            if ($lastUpdate) {
                $lastUpdate = $this->date($user, $lastUpdate['updatedAt']);
                $interval = $now->diff($lastUpdate);
                $skip = ($interval->days < 1);
            } else {
                $skip = false;
                // Ajouter
            }

            if ($skip) {
                $updates[] = [
                    'id' => $seriesId,
                    'name' => sprintf('S%02dE%02d', $seasonNumber, $episodeNumber),
                    'updates' => [], // '*** Updated less than 24 hours ago ***'
                ];
                continue;
            }
            $episode = json_decode($this->tmdbService->getTvEpisode($seriesId, $seasonNumber, $episodeNumber, $locale, ['translations']), true);
            $tmdbCalls++;
            if ($episode == null || isset($episode['error'])) {
                $updates[] = [
                    'id' => $seriesId,
                    'name' => sprintf('S%02dE%02d', $seasonNumber, $episodeNumber),
                    'updates' => ['*** Episode not found ***'],
                ];
                continue;
            }
            // {
            //  "air_date": "2026-10-01",
            //  "crew": [],
            //  "episode_number": 6,
            //  "episode_type": "standard",
            //  "guest_stars": [],
            //  "name": "Episode 6",
            //  "overview": "",
            //  "id": 7787987,
            //  "production_code": "",
            //  "runtime": null,
            //  "season_number": 1,
            //  "still_path": null,
            //  "vote_average": 0,
            //  "vote_count": 0,
            //  "translations": {
            //    "translations": [
            //      {
            //        "iso_3166_1": "CN",
            //        "iso_639_1": "zh",
            //        "name": "普通话",
            //        "english_name": "Mandarin",
            //        "data": {
            //          "name": "",
            //          "overview": ""
            //        }
            //      },
            //      {
            //        "iso_3166_1": "US",
            //        "iso_639_1": "en",
            //        "name": "English",
            //        "english_name": "English",
            //        "data": {
            //          "name": "",
            //          "overview": ""
            //        }
            //      },
            //      {
            //        "iso_3166_1": "BR",
            //        "iso_639_1": "pt",
            //        "name": "Português",
            //        "english_name": "Portuguese",
            //        "data": {
            //          "name": "",
            //          "overview": ""
            //        }
            //      },
            //      {
            //        "iso_3166_1": "TH",
            //        "iso_639_1": "th",
            //        "name": "ภาษาไทย",
            //        "english_name": "Thai",
            //        "data": {
            //          "name": "ตอนที่ 6",
            //          "overview": ""
            //        }
            //      }
            //    ]
            //  }
            //}
            // TODO: Retourner l'identifiant TMDB de l'épisode pour retrouver la carte correspondante (data-episode-id)

            $updates[] = [
                'id' => $seriesId,
                'name' => sprintf('S%02dE%02d', $seasonNumber, $episodeNumber),
                'updates' => ['*** Episode not found ***'],
            ];

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
            'count' => $tmdbCalls,
        ]);
    }

    private function getSettings(User $user): array
    {
        $settings = $this->settingsRepository->findOneBy(['user' => $user, 'name' => 'tv time episode name check']);
        if (!$settings) {
            $settings = new Settings();
            $settings->setUser($user);
            $settings->setName('tv time episode name check');
            $settings->setData([]);
            $this->settingsRepository->save($settings, true);
        }
        return $settings->getData();
    }

    public function now(User $user): DateTimeImmutable
    {
        return $this->dateService->newDateImmutable('now', $user->getTimezone() ?? 'Europe/Paris');
    }

    public function date(User $user, string $date): DateTimeImmutable
    {
        return $this->dateService->newDateImmutable($date, $user->getTimezone() ?? 'Europe/Paris');
    }
}