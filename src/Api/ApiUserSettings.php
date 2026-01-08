<?php

namespace App\Api;

use App\Repository\SettingsRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/user/settings', name: 'api_user_settings_', methods: ['GET'])]
class ApiUserSettings extends AbstractController
{
    public function __construct(
        private readonly UserRepository     $userRepository,
        private readonly SettingsRepository $settingsRepository,
    )
    {
    }

    #[Route('/network/list', name: 'networks', methods: ['GET'])]
    public function networks(Request $request): Response
    {
//        $data = $request->getContent();
        $userId = 1;//json_decode($data, true)['userId'];
        $networks = $this->userRepository->getUserNetworkIds($userId);
        $networks = array_column($networks, "network_id");
        return $this->json([
            'ok' => true,
            'networks' => $networks,
        ]);
    }

    #[Route('/provider/list', name: 'providers', methods: ['GET'])]
    public function providers(Request $request): Response
    {
//        $data = $request->getContent();
        $userId = 1;//json_decode($data, true)['userId'];
        $providers = $this->userRepository->getUserProviderIds($userId);
        $providers = array_column($providers, "provider_id");
        return $this->json([
            'ok' => true,
            'providers' => $providers,
        ]);
    }

    #[Route('/series/advanced/search', name: 'series_advanced_search', methods: ['POST'])]
    public function advancedSearchDisplaySettings(Request $request): JsonResponse
    {
        if ($request->isMethod('POST')) {
            $payload = $request->getPayload()->all();
            $settingsId = $payload['id'];
            $settingsData = $payload['data'];
            foreach ($settingsData as $key => $value) {
                $settingsData[$key] = (bool)$value;
            }
            $displaySettings = $this->settingsRepository->find($settingsId);
            if (!$displaySettings) {
                return new JsonResponse(['ok' => false, 'message' => 'Settings not found'], 404);
            }
            $displaySettings->setData($settingsData);
            $this->settingsRepository->save($displaySettings, true);
        }
        return new JsonResponse(['ok' => true]);
    }
}