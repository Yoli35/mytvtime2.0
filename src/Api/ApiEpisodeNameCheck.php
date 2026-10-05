<?php

namespace App\Api;

use App\Entity\Settings;
use App\Entity\User;
use App\Repository\SettingsRepository;
use App\Service\DateService;
use App\Service\TMDBService;
use Closure;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\DependencyInjection\Attribute\AutowireMethodOf;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/tv/time', name: 'api_tv_time_')]
readonly class ApiEpisodeNameCheck
{
    public function __construct(
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure            $getUser,
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure            $json,
        private DateService        $dateService,
        private SettingsRepository $settingsRepository,
        private TMDBService        $tmdbService,
    )
    {
    }

    #[Route('/episode/check', name: 'episode_check', methods: ['POST'])]
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

        $data = $this->getSettings($user);
        $lastUpdates = $data['last_checks'] ?? [];

        $now = $this->now($user);
        $tmdbCalls = 0;
        $updates = [];
        $messages = [];

        $t0 = microtime(true);
        foreach ($episodeData as $ep) {
            $seriesId = $ep['id'];
            $episodeId = $ep['episodeId'];
            $seasonNumber = $ep['seasonNumber'];
            $episodeNumber = $ep['episodeNumber'];

            $lastUpdate = array_find($lastUpdates, fn($update) => $update['id'] === $episodeId);
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
                    'episode_id' => $episodeId,
                    'name' => sprintf('%s S%02dE%02d', $ep['name'], $seasonNumber, $episodeNumber),
                    'still' => null,
                    'poster' => $ep['poster_path'],
                    'content' => ['status' => 'skip', 'reason' => '*** Updated less than 24 hours ago ***'],
                ];
                continue;
            }
            $episode = json_decode($this->tmdbService->getTvEpisode($seriesId, $seasonNumber, $episodeNumber, $locale, ['translations']), true);
            $tmdbCalls++;

            if ($episode == null || isset($episode['error'])) {
                $updates[] = [
                    'episode_id' => $episodeId,
                    'name' => sprintf('%s S%02dE%02d', $ep['name'], $seasonNumber, $episodeNumber),
                    'still' => null,
                    'poster' => $ep['poster_path'],
                    'content' => ['status' => 'error', 'reason' => '*** Episode not found ***'],
                ];
                continue;
            }

            $translations = $episode['translations']['translations'];
//            $translations = array_filter($translations, function ($translation) {
//                return ($translation['iso_639_1'] == 'en' || $translation['iso_639_1'] == 'fr') && strlen($translation['data']['name']) > 0;
//            });

            $updates[] = [
                'episode_id' => $episodeId,
                'name' => sprintf('%s S%02dE%02d', $ep['name'], $seasonNumber, $episodeNumber),
                'still' => $episode['still_path'],
                'poster' => $ep['poster_path'],
                'content' => [
                    'status' => 'success',
                    'name' => $episode['name'] . ($ep['esn'] ? ' - ' . $ep['esn'] : ''),
                    'languages' => array_column($translations, 'iso_639_1'),
                    'translations' => array_column($translations, 'data.name'),
                    'runtime' => $episode['runtime'],
                ],
            ];

            $t1 = microtime(true);
            $interval = $t1 - $t0;
            if ($interval > 25) {
                break;
            }
        }
        /*$this->seriesRepository->flush();*/

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
            $settings = new Settings($user, 'tv time episode name check', ["last_checks" => []]);
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