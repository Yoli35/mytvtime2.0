<?php

namespace App\Controller;

use App\Repository\SeriesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SeriesController extends AbstractController
{
    public function __construct(
        private readonly SeriesRepository $seriesRepository,
    )
    {
    }
    #[Route('/series', name: 'app_series')]
    public function index(): Response
    {
        return $this->render('series/index.html.twig', [
            'controller_name' => 'SeriesController',
        ]);
    }

    #[Route('/series/{id}-{slug}', name: 'app_series_show', requirements: ['id' => '\d+'])]
    public function show(Request $request, $id, $slug): Response
    {
        $series = $this->seriesRepository->findOneBy(['id' => $id]);
        $series->setVisitNumber($series->getVisitNumber() + 1);
        $this->seriesRepository->save($series, true);

        if ($series->getSlug() !== $slug) {
            return $this->redirectToRoute('app_series_show', [
                'id' => $series->getId(),
                'slug' => $series->getSlug(),
            ], 301);
        }

        return $this->render('series/show.html.twig', [
            'series' => $series,
        ]);
    }
}
