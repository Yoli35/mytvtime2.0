<?php

namespace App\Api;

use App\Entity\EpisodeLocalizedOverview;
use App\Entity\EpisodeStill;
use App\Entity\EpisodeSubstituteName;
use App\Entity\User;
use App\Entity\UserEpisode;
use App\Entity\UserSeries;
use App\Repository\EpisodeLocalizedOverviewRepository;
use App\Repository\EpisodeStillRepository;
use App\Repository\EpisodeSubstituteNameRepository;
use App\Repository\SeriesBroadcastDateRepository;
use App\Repository\SeriesRepository;
use App\Repository\SettingsRepository;
use App\Repository\UserEpisodeRepository;
use App\Repository\UserSeriesRepository;
use App\Service\DateService;
use App\Service\ImageService;
use App\Service\TMDBService;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Contracts\Translation\TranslatorInterface;

/** @method User|null getUser() */
#[Route('/api/episode', name: 'api_episode_')]
class ApiSeriesEpisode extends AbstractController
{
    public function __construct(
        private readonly DateService                        $dateService,
        private readonly EpisodeSubstituteNameRepository    $episodeSubstituteNameRepository,
        private readonly EpisodeLocalizedOverviewRepository $episodeLocalizedOverviewRepository,
        private readonly EpisodeStillRepository             $episodeStillRepository,
        private readonly ImageService                       $imageService,
        private readonly SeriesBroadcastDateRepository      $seriesBroadcastDateRepository,
        private readonly SeriesRepository                   $seriesRepository,
        private readonly SettingsRepository                 $settingsRepository,
        private readonly TmdbService                        $tmdbService,
        private readonly TranslatorInterface                $translator,
        private readonly UserEpisodeRepository              $userEpisodeRepository,
        private readonly UserSeriesRepository               $userSeriesRepository,
    )
    {
    }

    #[Route('/add/{id}', name: 'add', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function add(Request $request, int $id): Response
    {
        $inputBag = $request->getPayload();

        $showId = $inputBag->get('showId');
        $lastEpisode = $inputBag->get('lastEpisode') == "1";
        $seasonNumber = $inputBag->get('seasonNumber');
        $episodeNumber = $inputBag->get('episodeNumber');
        $ueId = $inputBag->get('ueId');
        $new = false;
        $bestProviderIds = [];

        $messages = [];

        $user = $this->getUser();
        $series = $this->seriesRepository->findOneBy(['tmdbId' => $showId]);
        $userSeries = $this->userSeriesRepository->findOneBy(['user' => $user, 'series' => $series]);
        $userSeriesEpisodes = $this->userEpisodeRepository->findBy(['userSeries' => $userSeries], ['seasonNumber' => 'ASC', 'episodeNumber' => 'ASC']);
        $userEpisode = $this->userEpisodeRepository->findOneBy(['id' => $ueId]);
        $userEpisodes = $this->userEpisodeRepository->findBy(['userSeries' => $userSeries, 'episodeId' => $id], ['id' => 'ASC']);

        $now = $this->now();
        if ($userEpisode->getWatchAt()) { // Si l'épisode a déjà été vu
            $userEpisode = new UserEpisode($userSeries, $id, $seasonNumber, $episodeNumber, $now);
            $userEpisode->setPreviousOccurrence($userEpisodes[count($userEpisodes) - 1]);
            $new = true;
        } else {
            $userEpisode->setWatchAt($now);
        }

        $firstViewedUserEpisode = $userEpisodes[0];
        $airDate = $firstViewedUserEpisode->getAirDate();
        if (!$airDate) {
            $tmdbEpisode = json_decode($this->tmdbService->getTvEpisode($showId, $seasonNumber, $episodeNumber, 'en-US'), true);
            $airDate = $tmdbEpisode['air_date'] ? $this->dateService->newDateImmutable($tmdbEpisode['air_date'], 'Europe/Paris', true) : null;
            $firstViewedUserEpisode->setAirDate($airDate);
            $this->userEpisodeRepository->save($firstViewedUserEpisode, true);
        }

        if ($new) {
            $userEpisode->setAirDate($airDate);
            $lastViewedUserEpisode = $userEpisodes[count($userEpisodes) - 1];
            $userEpisode->setProviderId($lastViewedUserEpisode->getProviderId());
            $userEpisode->setDeviceId($lastViewedUserEpisode->getDeviceId());
        } else {
            if ($airDate) {
                $diff = $now->diff($airDate);
                $quickWatchDay = $diff->days < 1;
                $quickWatchWeek = $diff->days < 7;
                $userEpisode->setQuickWatchDay($quickWatchDay);
                $userEpisode->setQuickWatchWeek($quickWatchWeek);
                if ($quickWatchWeek) {
                    if ($quickWatchDay) {
                        $messages[] = $this->translator->trans('Quick day watch badge unlocked');
                    } else {
                        $messages[] = $this->translator->trans('Quick week watch badge unlocked');
                    }
                }
            }

            if ($episodeNumber > 1) {
                $previousUserEpisode = $this->userEpisodeRepository->findOneBy(['userSeries' => $userSeries, 'seasonNumber' => $seasonNumber, 'episodeNumber' => $episodeNumber - 1]);
                $userEpisode->setProviderId($previousUserEpisode->getProviderId());
                $userEpisode->setDeviceId($previousUserEpisode->getDeviceId());
            } else {
                if ($seasonNumber > 1) {
                    $previousUserEpisode = $this->userEpisodeRepository->findOneBy(['userSeries' => $userSeries, 'seasonNumber' => $seasonNumber - 1], ['episodeNumber' => 'DESC']);
                    $userEpisode->setProviderId($previousUserEpisode->getProviderId());
                    $userEpisode->setDeviceId($previousUserEpisode->getDeviceId());
                }
            }

            if ($episodeNumber == 1 && $seasonNumber == 1) {
                $watchLinks = $series->getSeriesWatchLinks()->toArray();
                if (count($watchLinks) == 1) {
                    $userEpisode->setProviderId($watchLinks[0]->getProviderId());
                }
                $bestProviderIds = array_map(function ($watchLink) {
                    return $watchLink->getProviderId();
                }, $watchLinks);
            }

            // Si on regarde 3 épisodes en moins d'un jour, on considère que c'est un marathon
            if (!$userSeries->getMarathoner() && $episodeNumber >= 3) {
                $episodes = $this->userEpisodeRepository->findBy(['userSeries' => $userSeries, 'seasonNumber' => $seasonNumber], ['watchAt' => 'DESC'], 3);
                if ($episodes[0]->getEpisodeNumber() - $episodes[1]->getEpisodeNumber() == 1 && $episodes[1]->getEpisodeNumber() - $episodes[2]->getEpisodeNumber() == 1) {
                    $firstViewAt = $episodes[0]->getWatchAt();
                    $lastViewAt = $episodes[2]->getWatchAt();
                    $diff = $lastViewAt->diff($firstViewAt);
                    if ($diff->days < 1) {
                        $userSeries->setMarathoner(true);
                        $messages[] = $this->translator->trans('Marathoner badge unlocked');
                    }
                }
            }

            // Si on regarde le dernier épisode de la saison (hors épisodes spéciaux : $seasonNumber > 0)
            // et que l'on n'a pas regardé autre chose entre temps, on considère que c'est un binge
            if ($lastEpisode && $seasonNumber) {
                $isBinge = $this->isBinge($userSeries, $seasonNumber, $episodeNumber);
                $userSeries->setBinge($isBinge);
                if ($isBinge) {
                    $messages[] = $this->translator->trans('Binge watcher badge unlocked');
                }
            }
        }

        $this->userEpisodeRepository->save($userEpisode, true);

        if ($seasonNumber) {
            $userSeries->setLastWatchAt($now);
            $userSeries->setLastEpisode($episodeNumber);
            $userSeries->setLastSeason($seasonNumber);
            $userSeries->setLastUserEpisode($userEpisode);
            $nextUserEpisode = array_find($userSeriesEpisodes, function ($ue) use ($seasonNumber) {
                return ($ue->getSeasonNumber() >= $seasonNumber && $ue->getWatchAt() == null);
            });
            $userSeries->setNextUserEpisode($nextUserEpisode);

            if (!$new) {
                $userSeries->setViewedEpisodes($userSeries->getViewedEpisodes() + 1);
                $userSeries->setProgress(round(100 * $userSeries->getViewedEpisodes() / $series->getNumberOfEpisode(), 2));
            }
            $this->userSeriesRepository->save($userSeries, true);
        }

        $sbd = $this->seriesBroadcastDateRepository->findOneBy(['episodeId' => $id]);
        $airDate = $sbd ? $sbd->getDate() : $userEpisode->getAirDate();
        $ue = $this->userEpisodeRepository->getUserEpisodeDB($userEpisode->getId(), $user->getPreferredLanguage() ?? $request->getLocale());
        if ($ue['custom_date']) {
            $cd = $this->dateService->newDateImmutable($ue['custom_date'], 'Europe/Paris');
            $ue['custom_date'] = $cd->format('Y-m-d H:i O');
        }
        if ($ue['air_at']) {
            // 10:00:00 → 10:00
            $ue['air_at'] = $this->dateService->newDateImmutable($ue['air_at'], 'Europe/Paris');
            $ue['air_at'] = $ue['air_at']->format('H:i');
        }
        $ue['watch_at_db'] = $ue['watch_at'];
        if ($ue['watch_at']) {
            $ue['watch_at'] = $this->dateService->newDateImmutable($ue['watch_at'], 'UTC');
        }
        $arr = $this->userEpisodeRepository->getUserEpisodeViews($user->getId(), $id);
        $ues = array_map(function ($ue) {
            $ue['watch_at_db'] = $ue['watch_at'];
            $ue['watch_at'] = $this->dateService->newDateImmutable($ue['watch_at'], 'UTC');
            return $ue;
        }, $arr);

        $airDateBlock = $this->renderView('_blocks/series/_episode_air_date.html.twig', [
            'episode' => ['id' => $id, 'air_date' => $airDate->format('Y-m-d')],
            'ue' => $ue, //['watch_at' => $userEpisode->getWatchAt()->format('Y-m-d')],
            'ues' => $ues,
        ]);

        return $this->json([
            'ok' => true,
            'airDateBlock' => $airDateBlock,
            'new' => $new,
            'views' => $this->translator->trans('Watched %time% times', ['%time%' => count($ues)]),
            'series_progress' => $userSeries->getProgress(),
            'season_progress' => $this->userEpisodeRepository->seasonProgress($userSeries, $seasonNumber),
            'messages' => $messages,
            'deviceId' => $userEpisode->getDeviceId() ?? 0,
            'providerId' => $userEpisode->getProviderId() ?? 0,
            'bestProviderIds' => $bestProviderIds,
        ]);
    }

    #[Route('/touch/{id}', name: 'touch_episode', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function touch(Request $request, UserEpisode $userEpisode): Response
    {
        $data = json_decode($request->getContent(), true);

        if (key_exists('date', $data) && $data['date']) {
            $now = $this->date($data['date']);
        } else {
            $now = $this->now();
        }
        $seasonNumber = $userEpisode->getEpisodeNumber();
        $episodeNumber = $userEpisode->getSeasonNumber();
        $userSeries = $userEpisode->getUserSeries();

        $userEpisode->setWatchAt($now);

        $airDate = $userEpisode->getAirDate();

        $diff = $now->diff($airDate);
        $userEpisode->setQuickWatchDay($diff->days < 1);
        $userEpisode->setQuickWatchWeek($diff->days < 7);

        $this->userEpisodeRepository->save($userEpisode, true);

        $ue = $this->userEpisodeRepository->getUserEpisodeDB($userEpisode->getId(), $userEpisode->getUserSeries()->getUser()->getPreferredLanguage() ?? $request->getLocale());
        $ue['watch_at_db'] = $ue['watch_at'];
        $ue['watch_at'] = $this->dateService->newDateImmutable($ue['watch_at'], 'UTC');

        if ($seasonNumber) {
            $userSeries->setLastWatchAt($now);
            $userSeries->setLastEpisode($episodeNumber);
            $userSeries->setLastSeason($seasonNumber);
            $this->userSeriesRepository->save($userSeries, true);
        }
        $svg = '<svg viewBox="0 0 576 512" height="18px" width="18px" aria-hidden="true"><path fill="currentColor" d="M288 32c-80.8 0-145.5 36.8-192.6 80.6C48.6 156 17.3 208 2.5 243.7c-3.3 7.9-3.3 16.7 0 24.6C17.3 304 48.6 356 95.4 399.4C142.5 443.2 207.2 480 288 480s145.5-36.8 192.6-80.6c46.8-43.5 78.1-95.4 93-131.1c3.3-7.9 3.3-16.7 0-24.6c-14.9-35.7-46.2-87.7-93-131.1C433.5 68.8 368.8 32 288 32M144 256a144 144 0 1 1 288 0a144 144 0 1 1-288 0m144-64c0 35.3-28.7 64-64 64c-7.1 0-13.9-1.2-20.3-3.3c-5.5-1.8-11.9 1.6-11.7 7.4c.3 6.9 1.3 13.8 3.2 20.7c13.7 51.2 66.4 81.6 117.6 67.9s81.6-66.4 67.9-117.6c-11.1-41.5-47.8-69.4-88.6-71.1c-5.8-.2-9.2 6.1-7.4 11.7c2.1 6.4 3.3 13.2 3.3 20.3"></path></svg>';
        $viewedAt = $this->translator->trans('Today') . ', ' . $now->format('H:i');

        $watchedAtBlock = $this->renderView('_blocks/series/_watched_at.html.twig', [
            'episode' => ['id' => $userEpisode->getEpisodeId()],
            'e' => $ue,
        ]);

        return $this->json([
            'ok' => true,
            'viewedAt' => $svg . ' ' . $viewedAt,
            'dataViewedAt' => $now->format('Y-m-d H:i:s'),
            'watchedAtBlock' => $watchedAtBlock,
        ]);
    }

    #[Route('/remove', name: 'remove', methods: ['POST'])]
    public function remove(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);
        $showId = $data['showId'];
        $userEpisodeId = $data['userEpisodeId'];
        $seasonNumber = $data['seasonNumber'];
        $episodeNumber = $data['episodeNumber'];
        $locale = $request->getLocale();

        $user = $this->getUser();
        $series = $this->seriesRepository->findOneBy(['tmdbId' => $showId]);
        $userSeries = $this->userSeriesRepository->findOneBy(['user' => $user, 'series' => $series]);

        $userEpisode = $this->userEpisodeRepository->findOneBy(['id' => $userEpisodeId], ['watchAt' => 'DESC']);
        if ($userEpisode) {
            if ($userEpisode->getPreviousOccurrence()) {
                // on met à jour la "user" séries avec l'épisode précédemment vu
                $lastWatchedEpisode = $this->userEpisodeRepository->findOneBy(['userSeries' => $userSeries, 'previousOccurrence' => null], ['watchAt' => 'DESC']);
                $userSeries->setLastUserEpisode($lastWatchedEpisode);
                $userSeries->setLastWatchAt($lastWatchedEpisode->getWatchAt());
                $userSeries->setLastEpisode($lastWatchedEpisode->getEpisodeNumber());
                $userSeries->setLastSeason($lastWatchedEpisode->getSeasonNumber());
                $this->userSeriesRepository->save($userSeries, true);
                $this->userEpisodeRepository->remove($userEpisode);
                return $this->json([
                    'ok' => true,
                    'progress' => $userSeries->getProgress(),
                ]);
            }

            $userEpisode->setWatchAt(null);
            $userEpisode->setProviderId(null);
            $userEpisode->setDeviceId(null);
            $userEpisode->setVote(null);
            $userEpisode->setQuickWatchDay(false);
            $userEpisode->setQuickWatchWeek(false);
            $this->userEpisodeRepository->save($userEpisode, true);
        }

        if ($episodeNumber > 1 && $seasonNumber > 0) {
            for ($j = $seasonNumber; $j > 0; $j--) {
                for ($i = $episodeNumber - 1; $i > 0; $i--) {
                    $episode = $this->userEpisodeRepository->findOneBy(['userSeries' => $userSeries, 'seasonNumber' => $j, 'episodeNumber' => $i]);
                    if ($episode && $episode->getWatchAt()) {
                        $userSeries->setLastEpisode($episode->getEpisodeNumber());
                        $userSeries->setLastSeason($episode->getSeasonNumber());
                        $userSeries->setLastWatchAt($episode->getWatchAt());
                        $viewedEpisodes = $userSeries->getViewedEpisodes();
                        $tv = json_decode($this->tmdbService->getTv($showId, $locale), true);
                        $numberOfEpisode = $tv['number_of_episodes'];
                        $userSeries->setViewedEpisodes($viewedEpisodes - 1);
                        $userSeries->setProgress(($viewedEpisodes - 1) / $numberOfEpisode * 100);
                        $userSeries->setBinge(false);
                        $this->userSeriesRepository->save($userSeries, true);
                        return $this->json([
                            'ok' => true,
                            'progress' => $this->userEpisodeRepository->seasonProgress($userSeries, $seasonNumber),
                        ]);
                    }
                }
            }
        }
        // on a supprimé le premier épisode de la première saison ou on n'a pas trouvé d'épisode précédemment vu
        if ($seasonNumber == 1 && $episodeNumber == 1) {
            $userSeries->setLastEpisode(null);
            $userSeries->setLastSeason(null);
            $userSeries->setLastWatchAt(null);
            $userSeries->setViewedEpisodes(0);
            $userSeries->setProgress(0);
        }
        $this->userSeriesRepository->save($userSeries, true);
        return $this->json([
            'ok' => true,
            'progress' => $this->userEpisodeRepository->seasonProgress($userSeries, $seasonNumber),
        ]);
    }

    #[Route('/provider/{id}', name: 'provider', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function provider(Request $request, UserEpisode $userEpisode): JsonResponse
    {
        if ($request->isMethod('POST')) {
            $providerId = $request->getPayload()->get('providerId');
            $userEpisode->setProviderId($providerId == -1 ? null : $providerId);
            $this->userEpisodeRepository->save($userEpisode, true);
        }
        return new JsonResponse(['ok' => true]);
    }

    #[Route('/device/{id}', name: 'device', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function device(Request $request, UserEpisode $userEpisode): JsonResponse
    {
        if ($request->isMethod('POST')) {
            $deviceId = $request->getPayload()->get('deviceId');
            $userEpisode->setDeviceId($deviceId == -1 ? null : $deviceId);
            $this->userEpisodeRepository->save($userEpisode, true);
        }
        return new JsonResponse(['ok' => true]);
    }

    #[Route('/vote/{id}', name: 'vote', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function vote(Request $request, UserEpisode $userEpisode): JsonResponse
    {
        if ($request->isMethod('POST')) {
            $vote = $request->getPayload()->get('vote');
            $userEpisode->setVote($vote == -1 ? null : $vote);
            $this->userEpisodeRepository->save($userEpisode, true);
        }
        return new JsonResponse(['ok' => true]);
    }

    #[Route('/height/{userSeriesId}', name: 'height', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function height(Request $request, int $userSeriesId): Response
    {
        $user = $this->getUser();
        $episodeSizeSettings = $this->settingsRepository->findOneBy(['user' => $user, 'name' => 'episode_div_size_' . $userSeriesId]);
        $settings = $episodeSizeSettings->getData();

        $data = json_decode($request->getContent(), true);
        $settings['height'] = $data['height'];
        $settings['aspect-ratio'] = $data['aspectRatio'];

        $episodeSizeSettings->setData($settings);
        $this->settingsRepository->save($episodeSizeSettings, true);

        return $this->json([
            'ok' => true,
        ]);
    }

    #[Route('/update/info/{id}', name: 'update_info', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function update(Request $request, int $id): Response
    {
        $data = json_decode($request->getContent(), true);
        $content = $data['content'];
        $type = $data['type'];

        if ($type === 'name') {
            $esn = $this->episodeSubstituteNameRepository->findOneBy(['episodeId' => $id]);
            if ($esn) {
                $esn->setName($content);
            } else {
                $esn = new EpisodeSubstituteName($id, $content);
            }
            $this->episodeSubstituteNameRepository->save($esn, true);
        }
        if ($type === 'overview') {
            $elo = $this->episodeLocalizedOverviewRepository->findOneBy(['episodeId' => $id]);
            if ($elo) {
                $elo->setOverview($content);
            } else {
                $elo = new EpisodeLocalizedOverview($id, $content, $request->getLocale());
            }
            $this->episodeLocalizedOverviewRepository->save($elo, true);
        }

        return $this->json([
            'ok' => true,
        ]);
    }

    #[Route('/update/infos', name: 'update_infos', methods: ['POST'])]
    public function updates(Request $request): JsonResponse
    {
        $createdTitleCount = 0;
        $createdOverviewCount = 0;
        $updatedTitleCount = 0;
        $updatedOverviewCount = 0;

        if ($request->isMethod('POST')) {
            $payload = $request->getPayload();

            foreach ($payload as $key => $value) {
                list($type, $episodeId) = explode('-', $key);
                $content = $value;

                if ($content === "") {
                    continue;
                }
                if ($type === 'title' && !str_contains($content, 'pisode')) {
                    $esn = $this->episodeSubstituteNameRepository->findOneBy(['episodeId' => $episodeId]);
                    if ($esn) {
                        if ($content === $esn->getName()) {
                            continue;
                        }
                        $esn->setName($content);
                        $updatedTitleCount++;
                    } else {
                        $esn = new EpisodeSubstituteName($episodeId, $content);
                        $createdTitleCount++;
                    }
                    $this->episodeSubstituteNameRepository->save($esn, true);
                }
                if ($type === 'overview') {
                    $elo = $this->episodeLocalizedOverviewRepository->findOneBy(['episodeId' => $episodeId]);
                    if ($elo) {
                        if ($content === $elo->getOverview()) {
                            continue;
                        }
                        $elo->setOverview($content);
                        $updatedOverviewCount++;
                    } else {
                        $elo = new EpisodeLocalizedOverview($episodeId, $content, $request->getLocale());
                        $createdOverviewCount++;
                    }
                    $this->episodeLocalizedOverviewRepository->save($elo, true);
                }
            }
        }
        $message = 'Titles and overviews updated.<br>';
        if ($createdTitleCount) {
            $message .= 'Created titles: ' . $createdTitleCount . '<br>';
        }
        if ($createdOverviewCount) {
            $message .= 'Created overviews: ' . $createdOverviewCount . '<br>';
        }
        if ($updatedTitleCount) {
            $message .= 'Updated titles: ' . $updatedTitleCount . '<br>';
        }
        if ($updatedOverviewCount) {
            $message .= 'Updated overviews: ' . $updatedOverviewCount;
        }
        $this->addFlash('success', $message);
        return new JsonResponse([
            'ok' => true,
            'createdTitleCount' => $createdTitleCount,
            'createdOverviewCount' => $createdOverviewCount,
            'updatedTitleCount' => $updatedTitleCount,
            'updatedOverviewCount' => $updatedOverviewCount,
        ]);
    }

    #[Route('/still/{id}', name: 'still', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function still(Request $request, int $id): Response
    {
        /** @var UploadedFile $uploadedFile */
        $uploadedFile = $request->files->get('file');
        $seriesName = $request->get('name');
        $seasonNumber = $request->get('seasonNumber');
        $episodeNumber = $request->get('episodeNumber');

        $basename = $uploadedFile->getClientOriginalName();
        $projectDir = $this->getParameter('kernel.project_dir');
        $imageTempPath = $projectDir . '/public/images/temp/';
        $tempName = $imageTempPath . $basename;
        $stillPath = $projectDir . '/public/series/stills/' . $basename . '.webp';

        // Ajout d'un suffixe si le fichier existe déjà
        $copyCount = 0;
        while (file_exists($stillPath)) {
            $stillPath = $projectDir . '/public/series/stills/' . $basename . '-' . ++$copyCount . '.webp';
        }

        try {
            $uploadedFile->move($imageTempPath, $basename);
        } catch (FileException $e) {
            return $this->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ]);
        }
        $copy = false;

        try {
            $webp = $this->imageService->webpImage($seriesName . ' - ' . sprintf("S%02dE%02d", $seasonNumber, $episodeNumber), $tempName, $stillPath, 90);
            if ($webp) {
                if ($copyCount) $basename .= '-' . $copyCount;
                $imagePath = '/' . $basename . '.webp';
            } else {
                $imagePath = null;
            }
        } catch (FileException $e) {
            return $this->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ]);
        }

        if ($imagePath) {
            $episodeStill = new EpisodeStill($id, $imagePath);
            $this->episodeStillRepository->save($episodeStill, true);
            $copy = true;
        }

        return $this->json([
            'ok' => $copy,
            'image' => $imagePath,
        ]);
    }

    private function isBinge(UserSeries $userSeries, int $seasonNumber, int $numberOfEpisode): bool
    {
        $isBinge = false;

        $lastEpisodeDb = $this->userEpisodeRepository->findOneBy(['userSeries' => $userSeries, 'seasonNumber' => $seasonNumber], ['watchAt' => 'DESC']);
        $lastWatchDate = $lastEpisodeDb->getWatchAt();
        $firstEpisodeDb = $this->userEpisodeRepository->findOneBy(['userSeries' => $userSeries, 'seasonNumber' => $seasonNumber, 'episodeNumber' => 1]);
        $firstWatchDate = $firstEpisodeDb->getWatchAt();
        $userSeriesId = $userSeries->getId();
        $userId = $userSeries->getUser()->getId();
        $userEpisodes = $this->userEpisodeRepository->getEpisodeListBetweenDates($userId, $firstWatchDate, $lastWatchDate);
        $episodeCount = count($userEpisodes);
        /*dump([
            'first EpisodeDb' => $firstEpisodeDb,
            'last EpisodeDb' => $lastEpisodeDb,
            'first WatchDate' => $firstWatchDate,
            'last WatchDate' => $lastWatchDate,
            'episode Count' => $episodeCount,
            'user episodes' => $userEpisodes,
        ]);*/
        if ($episodeCount == $numberOfEpisode) {
            return true;
        }
        $previousUserEpisode = null;
        $episodeCount = 0;
        $interruptions = 0;
        $otherSeriesEpisodes = 0;
        // userEpisode
        //  "episode_number" => 3
        //  "season_number" => 1
        //  "user_series_id" => 826
        foreach ($userEpisodes as $userEpisode) {
            $currentUserSeriesId = $userEpisode['user_series_id'];
            if (!$previousUserEpisode) {
                if ($currentUserSeriesId == $userSeriesId) {
                    $previousUserEpisode = $userEpisode;
                    $episodeCount++;
                }
                continue;
            }

            if ($currentUserSeriesId != $userSeriesId
                || $previousUserEpisode['season_number'] != $userEpisode['season_number']) {
                $interruptions++;
                $otherSeriesEpisodes++;
                // We leave a margin of two episodes of another series
                if ($otherSeriesEpisodes > 2) {
                    break;
                }
                continue;
            } else {
                if ($interruptions > 0) {
                    $interruptions = 0;
                    $otherSeriesEpisodes = 0;
                }
            }
            $previousUserEpisode = $userEpisode;
            $episodeCount++;
        }
        if ($episodeCount == $numberOfEpisode) {
            $isBinge = true;
        }
        return $isBinge;
    }

    private function now(): DateTimeImmutable
    {
        $user = $this->getUser();
        $timezone = $user ? $user->getTimezone() : 'Europe/Paris';
        return $this->dateService->newDateImmutable('now', $timezone);
    }

    private function date(string $dateString): DateTimeImmutable
    {
        $user = $this->getUser();
        $timezone = $user ? $user->getTimezone() : 'Europe/Paris';
        return $this->dateService->newDateImmutable($dateString, $timezone);
    }
}