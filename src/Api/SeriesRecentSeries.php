<?php

namespace App\Api;

use App\Controller\HomeController;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[Route('/api/series', name: 'api_series_')]
class SeriesRecentSeries extends AbstractController
{
    public function __construct(
        private readonly HomeController $homeController,
    )
    {
        // Inject dependencies if needed
    }

    #[Route('/recent/series', name: 'recent_series', methods: ['GET'])]
    public function recentSeries(): Response
    {
        $slugger = new AsciiSlugger();
        $country = "FR";
        $timezone = "UTC";
        $language = "fr";
        $seriesSelection = $this->homeController->getSeriesSelection($slugger, $country, $timezone, $language, true);

        return $this->json([
            'ok' => true,
            'list' => $seriesSelection,
        ]);
    }
}