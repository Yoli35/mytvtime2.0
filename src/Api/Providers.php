<?php

namespace App\Api;

use App\Repository\ProviderRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/provider', name: 'api_provider_')]
class Providers extends AbstractController
{
    public function __construct(
        private readonly ProviderRepository $providerRepository,
    )
    {
        // Inject dependencies if needed
    }

    #[Route('/list', name: 'list', methods: ['GET'])]
    public function list(): Response
    {
        $list = $this->providerRepository->getAllProviders();
        return $this->json([
                'ok' => true,
                'list' => $list,
            ]);
    }
}