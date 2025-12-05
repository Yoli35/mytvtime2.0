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
#[Route('/api/settings/schedule-range', name: 'api_settings_schedule_range_')]
readonly class ScheduleRange
{
    public function __construct(
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure               $json,
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure               $getUser,
        private SettingsRepository $settingsRepository,
    )
    {
    }

    #[Route('/read', name: 'read', methods: ['GET'])]
    public function read(Request $request): Response
    {
        $user = ($this->getUser)();
        if (!$user) {
            return ($this->json)([
                'ok' => true,
                'read' => false,
            ]);
        }

        $type = $request->query->getAlpha('t', 'values');

        $settings = $this->settingsRepository->findOneBy(['user' => $user, 'name' => 'schedule_range']);
        if (!$settings) {
            $settings = new Settings($user, 'schedule_range', ['start' => "-2", 'end' => "2", 'default_start' => "-2", 'default_end' => "2"]);
            $this->settingsRepository->save($settings, true);
        }
        if ($type === 'default') {
            $start = $settings->getData()['default_start'] ?? "-2";
            $end = $settings->getData()['default_end'] ?? "2";

            return ($this->json)([
                'ok' => true,
                'read' => true,
                'start' => $start,
                'end' => $end,
            ]);
        }
        $start = $settings->getData()['start'] ?? "-2";
        $end = $settings->getData()['end'] ?? "2";

        return ($this->json)([
            'ok' => true,
            'read' => true,
            'start' => $start,
            'end' => $end,
        ]);
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

        $settings = $this->settingsRepository->findOneBy(['user' => $user, 'name' => 'schedule_range']);
        $start = $inputBag->get("start", "-2");
        $end = $inputBag->get("end", "2");

        $data = $settings->getData();
        $data['start'] = $start;
        $data['end'] = $end;
        $settings->setData($data);
        $this->settingsRepository->save($settings, true);

        return ($this->json)([
            'ok' => true,
            'update' => true,
        ]);
    }

}