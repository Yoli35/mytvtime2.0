<?php

namespace App\Api;

use App\Entity\Settings;
use App\Entity\User;
use App\Repository\SettingsRepository;
use App\Repository\UserEpisodeRepository;
use App\Repository\UserListRepository;
use App\Service\SeriesSchedule;
use Closure;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\DependencyInjection\Attribute\AutowireMethodOf;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** @method User|null getUser() */
#[Route('/api/main/menu', name: 'api_main_menu_')]
readonly class ApiMainMenu
{
    public function __construct(
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure               $json,
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure               $getUser,
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure               $renderView,
        private SeriesSchedule        $seriesSchedule,
        private SettingsRepository    $settingsRepository,
        private UserEpisodeRepository $userEpisodeRepository,
        private UserListRepository    $userListRepository,
    )
    {
    }

    #[Route('/update', name: 'update', methods: ['POST'])]
    public function update(Request $request): Response
    {
        $inputBag = $request->getPayload();
        $user = ($this->getUser)();
        if (!$user) {
            return ($this->json)([
                'ok' => true,
                'update' => false,
            ]);
        }
        $lastViewedEpisodeId = $inputBag->getInt('lastViewedEpisodeId', -1);
        $actualLastViewedEpisodeId = $this->userEpisodeRepository->lastViewedEpisodeId($user);

        if ($lastViewedEpisodeId === $actualLastViewedEpisodeId) {
            return ($this->json)([
                'ok' => true,
                'update' => false,
            ]);
        }

        $settings = $this->settingsRepository->findOneBy(['user' => $user, 'name' => 'schedule_range']);
        if (!$settings) {
            $settings = new Settings($user, 'schedule_range', ['start' => "-2", 'end' => "2", 'default_start' => "-2", 'default_end' => "2"]);
            $this->settingsRepository->save($settings, true);
        }
        $startDay = (int)$settings->getData()['start'];
        $endDay = (int)$settings->getData()['end'];
        $start = $startDay . " day";;
        $end = $endDay . " day";
        $locale = $inputBag->getAlpha("locale", "fr");

        $interval = $this->seriesSchedule->getSchedule($user, $start, $end, $locale);

        return ($this->json)([
            'ok' => true,
            'update' => true,
            'block' => ($this->renderView)('_blocks/_schedule_menu.html.twig', [
                'data' => [
                    'interval' => $interval,
                    'startDay' => $startDay,
                    'endDay' => $endDay,
                ]
            ]),
        ]);
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
                'href' => '/' . $locale . '/series/show/' . $s['id'] . '-' . ($s['localized_slug'] ?: $s['slug']),
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