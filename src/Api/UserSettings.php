<?php

namespace App\Api;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class UserSettings extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
    )
    {
    }

    #[Route('/api/user/network/list', name: 'api_user_networks_', methods: ['GET'])]
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

    #[Route('/api/user/provider/list', name: 'api_user_providers_', methods: ['GET'])]
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
}