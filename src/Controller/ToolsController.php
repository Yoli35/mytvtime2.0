<?php

namespace App\Controller;

use App\Service\BackgroundService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/tools', name: 'app_tools_')]
final class ToolsController extends AbstractController
{
    public function __construct(
        private readonly BackgroundService $backgroundService,
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
}
