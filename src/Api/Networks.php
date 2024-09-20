<?php

namespace App\Api;

use App\Repository\NetworkRepository;
use App\Service\TMDBService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/network', name: 'api_network_')]
class Networks extends AbstractController
{
    public function __construct(
        private readonly NetworkRepository $networkRepository,
        private readonly TMDBService       $tmdbService,
    )
    {
        // Inject dependencies if needed
    }

    #[Route('/list', name: 'list', methods: ['GET'])]
    public function list(): Response
    {
        $list = $this->networkRepository->getNetworkList();
        return $this->json([
            'ok' => true,
            'list' => $list,
        ]);
    }

    #[Route('/content', name: 'content', methods: ['GET'])]
    public function content(Request $request): Response
    {
        $networkId = $request->query->get('id');
        $filterString = "include_adult=false&include_null_first_air_dates=false&language=fr-FR&page=1&sort_by=first_air_date.desc&with_networks=$networkId";
        $list = $this->tmdbService->getFilterTv($filterString);
        return $this->json([
            'ok' => true,
            'list' => $list,
        ]);
    }
}