<?php

namespace App\Api;

use App\Entity\Settings;
use App\Entity\User;
use App\Repository\SettingsRepository;
use App\Repository\UserEpisodeRepository;
use App\Repository\UserListRepository;
use App\Repository\UserPinnedSeriesRepository;
use App\Service\ImageConfiguration;
use App\Service\ProviderService;
use App\Service\SeriesSchedule;
use Closure;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\DependencyInjection\Attribute\AutowireMethodOf;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Contracts\Translation\TranslatorInterface;

/** @method User|null getUser() */
#[Route('/api/main/menu', name: 'api_main_menu_')]
readonly class ApiMainMenu
{
    public function __construct(
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure                    $json,
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure                    $generateUrl,
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure                    $getUser,
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure                    $renderView,
        private ImageConfiguration         $imageConfiguration,
        private ProviderService            $providerService,
        private UserPinnedSeriesRepository $userPinnedSeriesRepository,
        private SeriesSchedule             $seriesSchedule,
        private TranslatorInterface        $translator,
        private SettingsRepository         $settingsRepository,
        private UserEpisodeRepository      $userEpisodeRepository,
        private UserListRepository         $userListRepository,
    )
    {
    }

    #[Route('/update', name: 'update', methods: ['POST'])]
    public function update(#[CurrentUser] User $user, Request $request): Response
    {
        $inputBag = $request->getPayload();
        $lastViewedEpisodeId = $inputBag->getInt('lastViewedEpisodeId', -1);
        $actualLastViewedEpisodeId = $this->userEpisodeRepository->lastViewedEpisodeId($user);

        $seriesId = $inputBag->getInt('seriesId', -1);
        $episodeId = $inputBag->getInt('episodeId', -1);

        $pinnedSeriesBlockExists = $inputBag->getInt('pinnedSeriesBlockExists');
        $seriesInProgressBlockExists = $inputBag->getInt('seriesInProgressBlockExists');

        if ($lastViewedEpisodeId === $actualLastViewedEpisodeId) {
            return ($this->json)([
                'ok' => true,
                'update' => false,
            ]);
        }

        $settings = $this->settingsRepository->findOneBy(['user' => $user, 'name' => 'app_schedule_menu_settings']);
        if (!$settings) {
            $settings = new Settings($user, 'app_schedule_menu_settings', ['start' => "-2", 'end' => "2", 'default_start' => "-2", 'default_end' => "2", 'link_to' => 'series', 'default_link_to' => 'series']);
            $this->settingsRepository->save($settings, true);
        }
        $startDay = (int)$settings->getData()['start'];
        $endDay = (int)$settings->getData()['end'];
        $linkTo = $settings->getData()['link_to'];
        $start = $startDay . " day";
        $end = $endDay . " day";
        $locale = $inputBag->getAlpha("locale", "fr");

        $interval = $this->seriesSchedule->getSchedule($user, $start, $end, $locale);
        $blockInterval = ($this->renderView)('_blocks/main_menu/_schedule_menu.html.twig', [
            'interval' => $interval,
            'relativeDays' => $this->relativeDayStrings(),
            'startDay' => $startDay,
            'endDay' => $endDay,
            'seriesId' => $seriesId,
            'episodeId' => $episodeId,
            'linkTo' => $linkTo,
        ]);
        if ($seriesInProgressBlockExists) {
            $blockInProgress = "";
        } else {
            $inProgress = $this->inProgressSeries($user, $locale);
            $blockInProgress = ($this->renderView)('_blocks/main_menu/_in_progress.html.twig', [
                'inProgress' => $inProgress,
            ]);
        }
        if ($pinnedSeriesBlockExists) {
            $blockPinned = "";
        } else {
            $pinnedSeries = $this->pinnedSeries($user, $locale);
            $blockPinned = ($this->renderView)('_blocks/main_menu/_pinned_series.html.twig', [
                'pinnedSeries' => $pinnedSeries,
            ]);
        }

        return ($this->json)([
            'ok' => true,
            'update' => true,
            'blockInterval' => $blockInterval,
            'blockInProgress' => $blockInProgress,
            'blockPinned' => $blockPinned,
        ]);
    }

    public function relativeDayStrings(): array
    {
        return [
            -7 => $this->translator->trans("Seven days ago"),
            -6 => $this->translator->trans("Six days ago"),
            -5 => $this->translator->trans("Five days ago"),
            -4 => $this->translator->trans("Four days ago"),
            -3 => $this->translator->trans("Three days ago"),
            -2 => $this->translator->trans("Two days ago"),
            -1 => $this->translator->trans("Yesterday"),
            0 => $this->translator->trans("Today"),
            1 => $this->translator->trans("Tomorrow"),
            2 => $this->translator->trans("In two days"),
            3 => $this->translator->trans("In three days"),
            4 => $this->translator->trans("In four days"),
            5 => $this->translator->trans("In five days"),
            6 => $this->translator->trans("In six days"),
            7 => $this->translator->trans("In seven days"),
        ];
    }

    public function inProgressSeries(User $user, string $locale = 'fr'): array
    {
        $arr = $this->userEpisodeRepository->inProgressSeriesForTwig($user, $locale);
        $inProgress = $arr[0] ?? [];
        $ok = $inProgress['id'] ?? null;

        if (!$ok) {
            return [
                'ok' => null,
            ];
        }
        $id = $inProgress['id'];
        return [
            'ok' => true,
            'id' => $id,
            'name' => $inProgress['name'],
            'slug' => $inProgress['slug'],
            'posterPath' => $inProgress['posterPath'] ? '/series/posters' . $inProgress['posterPath'] : null,
            'episodeId' => $inProgress['episodeId'],
            'episodeCount' => $inProgress['seasonEpisodeCount'],
            'seasonNumber' => $inProgress['nextEpisodeSeason'],
            'nextEpisode' => $inProgress['nextEpisodeNumber'],
            'progress' => 100 * $inProgress['seasonViewedEpisodeCount'] / $inProgress['seasonEpisodeCount'],
        ];
    }

    public function pinnedSeries(User $user, string $locale): array
    {
        $posterUrl = $this->imageConfiguration->getUrl('poster_sizes', 5);
        $logoUrl = $this->imageConfiguration->getUrl('logo_sizes', 2);
        $arr = array_map(function ($series) use ($logoUrl, $posterUrl) {
            $series['posterPath'] = $series['posterPath'] ? $posterUrl . $series['posterPath'] : null;
            $series['providerLogoPath'] = $this->providerService->getProviderLogoFullPath($series['providerLogoPath'], $logoUrl);
            return $series;
        }, $this->userPinnedSeriesRepository->getPinnedSeriesByUser($user, $locale));
        $pinnedSeriesArr = [];
        foreach ($arr as $item) {
            $index = $item['id'];
            if (!key_exists($index, $pinnedSeriesArr)) {
                $pinnedSeriesArr[$index] = $item;
                $pinnedSeriesArr[$index]['providers'] = [];
            }
            $provider['providerLogoPath'] = $item['providerLogoPath'];
            $provider['providerName'] = $item['providerName'];
            $pinnedSeriesArr[$index]['providers'][] = $provider;
        }
        return $pinnedSeriesArr;
    }

    #[Route('/suggestions', name: 'suggestions', methods: ['GET'])]
    public function suggestions(Request $request): Response
    {
        $user = ($this->getUser)();
        if (!$user) {
            return ($this->json)([
                'ok' => true,
                'suggestions' => [],
            ]);
        }

        $list = $this->userListRepository->findOneBy(['user' => $user, 'mainMenu' => true]);
        $locale = $request->query->getAlpha("locale", "fr");
        $suggestions = array_map(function ($s) use ($locale) {
            return [
//                'href' => '/' . $locale . '/show/series/' . $s['id'] . '-' . ($s['localized_slug'] ?: $s['slug']),
                'href' => ($this->generateUrl)('app_tv_series', ['id' => $s['id'], 'slug' => $s['localized_slug'] ?: $s['slug'], 'locale' => $locale]),
                'id' => $s['id'],
                'name' => ($s['localized_name'] ?: $s['name']) . '(' . $s['air_year'] . ')',
                'poster_path' => $s['poster_path'],
            ];
        }, $this->userListRepository->getListContent($user, 1, $locale));

        return ($this->json)([
            'ok' => true,
            'suggestions' => $suggestions,
            'label' => $list->getName() . ' (' . count($suggestions) . ')',
        ]);
    }
}