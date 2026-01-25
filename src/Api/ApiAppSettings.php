<?php

namespace App\Api;

use App\Entity\Settings;
use App\Entity\User;
use App\Repository\SettingsRepository;
use Closure;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\DependencyInjection\Attribute\AutowireMethodOf;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** @method User|null getUser() */
#[Route('/api/settings', name: 'api_settings_')]
readonly class ApiAppSettings
{
    public function __construct(
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure            $json,
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure            $getUser,
        private SettingsRepository $settingsRepository,
    )
    {
    }

    #[Route('/accent-color/read', name: 'accent_color_read', methods: ['GET'])]
    public function ACRead(): Response
    {
        $user = ($this->getUser)();
        if (!$user) {
            return ($this->json)([
                'ok' => true,
                'read' => false,
            ]);
        }

        $settings = $this->settingsRepository->findOneBy(['user' => $user, 'name' => 'accent_color']);
        if (!$settings) {
            $settings = new Settings($user, 'accent_color', ['accent_color' => "#E0861F", 'default_color' => "#E0861F"]);
            $this->settingsRepository->save($settings, true);
        }
        $accentColor = $settings->getData()['accent_color'] ?? "#E0861F";
        $defaultColor = $settings->getData()['default_color'] ?? "#E0861F";

        return ($this->json)([
            'ok' => true,
            'read' => true,
            'value' => $accentColor,
            'default' => $defaultColor,
        ]);
    }

    #[Route('/accent-color/update', name: 'accent_color_update', methods: ['POST'])]
    public function ACUpdate(Request $request): Response
    {
        $inputBag = $request->getPayload();
        $user = ($this->getUser)();
        if (!$user) {
            return ($this->json)([
                'ok' => true,
                'update' => false,
            ]);
        }

        $settings = $this->settingsRepository->findOneBy(['user' => $user, 'name' => 'accent_color']);
        $accentColor = $inputBag->get("accentColor");

        $data = $settings->getData();
        $data['accent_color'] = $accentColor;
        $settings->setData($data);
        $this->settingsRepository->save($settings, true);

        return ($this->json)([
            'ok' => true,
            'update' => true,
        ]);
    }

    #[Route('/schedule-range/read', name: 'schedule_range_read', methods: ['GET'])]
    public function SRRead(Request $request): Response
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

    #[Route('/schedule-range/update', name: 'schedule_range_update', methods: ['POST'])]
    public function SRUpdate(Request $request): Response
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

    #[Route('/what/next/read', name: 'what_next_read', methods: ['GET'])]
    public function WNRead(Request $request): Response
    {
        $user = ($this->getUser)();
        if (!$user) {
            return ($this->json)([
                'ok' => true,
                'read' => false,
            ]);
        }

        $type = $request->query->getAlpha('t', 'values');

        $settingsEntity = $this->settingsRepository->findOneBy(['user' => $user, 'name' => 'seriesWhatNext']);
        $settings = $settingsEntity->getData();

        if ($type === 'default') {
            $settings = [
                'limit' => $settings['default_limit'],
                'sort' => $settings['default_sort'],
                'order' => $settings['default_order'],
                'link_to' => $settings['default_link_to']
            ];
            $settingsEntity->setData($settings);
            $this->settingsRepository->save($settingsEntity, true);

            return ($this->json)([
                'ok' => true,
                'read' => true,
                'limit' => $settings['default_limit'],
                'sort' => $settings['default_sort'],
                'order' => $settings['default_order'],
                'link_to' => $settings['default_link_to']
            ]);
        }
        return ($this->json)([
            'ok' => true,
            'read' => true,
            'limit' => $settings['limit'],
            'sort' => $settings['sort'],
            'order' => $settings['order'],
            'link_to' => $settings['link_to'],
        ]);
    }

    #[Route('/what/next/update', name: 'what_next_update', methods: ['POST'])]
    public function WNUpdate(Request $request): Response
    {
        $inputBag = $request->getPayload();

        $user = ($this->getUser)();
        if (!$user) {
            return ($this->json)([
                'ok' => true,
                'update' => false,
            ]);
        }

        $settingsEntity = $this->settingsRepository->findOneBy(['user' => $user, 'name' => 'seriesWhatNext']);
        $settings = $settingsEntity->getData();

        $limit = intval($inputBag->get("limit", $settings['default_limit']));
        $sort = $inputBag->get("sort", $settings['default_sort']);
        $order = $inputBag->get("order", $settings['default_order']);
        $linkTO = $inputBag->get("link_to", $settings['default_link_to']);

        $settings['limit'] = $limit;
        $settings['sort'] = $sort;
        $settings['order'] = $order;
        $settings['link_to'] = $linkTO;

        $settingsEntity->setData($settings);
        $this->settingsRepository->save($settingsEntity, true);
        return ($this->json)([
            'ok' => true,
            'update' => true,
            'limit' => $settings['limit'],
            'sort' => $settings['sort'],
            'order' => $settings['order'],
            'link_to' => $settings['link_to'],
        ]);
    }
}