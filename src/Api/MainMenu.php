<?php

namespace App\Api;

use App\Entity\Settings;
use App\Entity\User;
use App\Repository\SettingsRepository;
use App\Repository\UserEpisodeRepository;
use App\Service\SeriesSchedule;
use Closure;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\DependencyInjection\Attribute\AutowireMethodOf;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** @method User|null getUser() */
#[Route('/api/main/menu', name: 'api_main_menu_')]
readonly class MainMenu
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

}