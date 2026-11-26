<?php

namespace App\Controller;

use App\Entity\User;
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
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED');
        $id = $request->query->getInt('id', 1);
        /** @var User $user */
        $user = $this->getUser();
        $locale = $user->getPreferredLanguage() ?: 'fr';
        $addition = $this->seriesService->getSeries($id, $locale);

        $context = $this->seriesService->getNewSeriesMailContext(
            $user,
            $addition['series'],
            $addition['localizedName']?->getName(),
            $addition['localizedOverview']?->getOverview(),
            $request->getSchemeAndHttpHost(),
        );

        return $this->render('tools/css_mail.html.twig', $context + [
            'background' => $this->backgroundService->getRandomBackgroundImage(),
            'around' => $this->seriesService->getSeriesAround($id, $locale),
        ]);
    }
}
