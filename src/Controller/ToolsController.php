<?php

namespace App\Controller;

use App\Service\BackgroundService;
use App\Service\SeriesService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/tools', name: 'app_tools_')]
final class ToolsController extends AbstractController
{
    public function __construct(
        private readonly BackgroundService $backgroundService,
        private readonly SeriesService $seriesService,
    )
    {
    }

    #[Route('/index', name: 'index')]
    public function index(): Response
    {
        return $this->render('tools/index.html.twig', [
            'background' => $this->backgroundService->getRandomBackgroundImage(),
        ]);
    }

    #[Route('/css/menu', name: 'css_menu')]
    public function cssMenu(): Response
    {
        return $this->render('tools/css_menu.html.twig', [
            'background' => $this->backgroundService->getRandomBackgroundImage(),
        ]);
    }

    #[Route('/css/radiant', name: 'css_radiant')]
    public function cssRadiant(): Response
    {
        return $this->render('tools/css_radiant.html.twig', [
            'background' => $this->backgroundService->getRandomBackgroundImage(),
        ]);
    }

    #[Route('/css/mail', name: 'css_mail')]
    public function cssMail(Request $request): Response
    {
        $id = $request->query->get('id', 1);
        $appUrl = $request->server->get('DEFAULT_URI');
        $addition = $this->seriesService->getSeries($id, 'fr');

        $series = $addition['series'];
        $blurredPoster = $addition['blurredPoster'];
        $localizedName = $addition['localizedName']?->getName();
        $localizedOverview = $addition['localizedOverview']?->getOverview();

        return $this->render('tools/css_mail.html.twig', [
            'background' => $this->backgroundService->getRandomBackgroundImage(),
            'title' => 'New addition',
            'user' => [
                'id' => 1,
                'username' => 'iboy',
                'email' => 'iboy35@gmail.com'
            ],
            'series' => [
                'name' => $series->getName(),
                'localizedName' => $localizedName,
                'firstAirDate' => $series->getFirstAirDate(),
                'overview' => $series->getOverview(),
                'localizedOverview' => $localizedOverview,
                'backgroundUrl' => $appUrl . '/series/posters_blurred' . $blurredPoster,
                'posterUrl' => $appUrl . '/series/posters' . $series->getPosterPath(),
                'posterAlt' => 'Shinji: Double Shuffle',
                'backdropUrl' => $appUrl . '/series/backdrops' . $series->getBackdropPath(),
                'backdropAlt' => 'Shinji: Double Shuffle',
            ],
        ]);
    }
}
