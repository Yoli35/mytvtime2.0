<?php

namespace App\Api;

use App\Entity\Settings;
use App\Entity\User;
use App\Repository\FilmingLocationRepository;
use App\Repository\SettingsRepository;
use App\Repository\UserEpisodeRepository;
use App\Repository\UserListRepository;
use App\Service\MapService;
use App\Service\SeriesSchedule;
use Closure;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\DependencyInjection\Attribute\AutowireMethodOf;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** @method User|null getUser() */
#[Route('/api/map', name: 'api_map_')]
readonly class ApiMap
{
    public function __construct(
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure               $json,
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure               $renderView,
        private MapService            $mapService,
    )
    {
    }

    #[Route('/update', name: 'update', methods: ['POST'])]
    public function update(Request $request): Response
    {
        $inputBag = $request->getPayload();
        $lastId = $inputBag->getInt('lastId', -1);

        if ($lastId === $this->mapService->lastId()) {
            return ($this->json)([
                'ok' => true,
                'update' => false,
            ]);
        }

        $latest = $this->mapService->lastest($lastId);
        if (empty($latest)) {
            return ($this->json)([
                'ok' => true,
                'update' => false,
            ]);
        }

        $blocks = [];
        foreach ($latest['filmingLocations'] as $location) {
            $blocks[] = ($this->renderView)('_blocks/series/_map_location.html.twig', ['loc' => $location]);
        }

        return ($this->json)([
            'ok' => true,
            'update' => true,
            'locations' => $latest,
            'blocks' => $blocks,
        ]);
    }
}