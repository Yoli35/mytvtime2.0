<?php

namespace App\Controller;

use App\Repository\SeriesRepository;
use App\Service\ImageConfiguration;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SeriesController extends AbstractController
{
    public function __construct(
        private readonly ImageConfiguration $imageConfiguration,
        private readonly SeriesRepository   $seriesRepository,
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

        $series = $series->toArray();
        $this->saveImage("posters", $series['posterPath'], $this->imageConfiguration->getUrl('poster_sizes', 5));
        $this->saveImage("backdrops", $series['backdropPath'], $this->imageConfiguration->getUrl('backdrop_sizes', 3));
//        $series['posterPath'] = $this->imageConfiguration->getCompleteUrl($series['posterPath'], 'poster_sizes', 5);
//        $series['backdropPath'] = $this->imageConfiguration->getCompleteUrl($series['backdropPath'], 'backdrop_sizes', 3);

        dump($series, $request->getLocale());
        return $this->render('series/show.html.twig', [
            'series' => $series,
        ]);
    }

    public function saveImage($type, $imagePath, $imageUrl): void
    {
        if (!$imagePath) return;
        $root = $this->getParameter('kernel.project_dir');
        $this->saveImageFromUrl(
            $imageUrl . $imagePath,
            $root . "/public/series/" . $type . $imagePath
        );
    }

    public function saveImageFromUrl($imageUrl, $localeFile): bool
    {
        if (!file_exists($localeFile)) {

            // Vérifier si l'URL de l'image est valide
            if (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                // Récupérer le contenu de l'image à partir de l'URL
                $imageContent = file_get_contents($imageUrl);

                // Ouvrir un fichier en mode écriture binaire
                $file = fopen($localeFile, 'wb');

                // Écrire le contenu de l'image dans le fichier
                fwrite($file, $imageContent);

                // Fermer le fichier
                fclose($file);

                return true;
            } else {
                return false;
            }
        }
        return true;
    }
}
