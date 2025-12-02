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
#[Route('/api/settings/accent-color', name: 'api_settings_accent_color_')]
readonly class AccentColor
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

}