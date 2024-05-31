<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class SeriesAdminController extends AbstractController
{
    #[IsGranted('ROLE_ADMIN')]
    #[Route('/series/admin', name: 'app_series_admin')]
    public function index(): Response
    {
        return $this->render('series_admin/index.html.twig', [
        ]);
    }
}
