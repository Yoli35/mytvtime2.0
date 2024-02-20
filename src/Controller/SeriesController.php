<?php

namespace App\Controller;

use App\Repository\SeriesRepository;
use App\Service\ImageConfiguration;
use App\Service\TMDBService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\AsciiSlugger;

class SeriesController extends AbstractController
{
    public function __construct(
        private readonly ImageConfiguration $imageConfiguration,
        private readonly SeriesRepository   $seriesRepository,
        private readonly TMDBService        $tmdbService,
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

    #[Route('/series/tmdb/{id}-{slug}', name: 'app_series_tmdb', requirements: ['id' => '\d+'])]
    public function tmdb(Request $request, $id, $slug): Response
    {
        $tv = json_decode($this->tmdbService->getTv($id, $request->getLocale(), ["images", "videos", "credits", "watch/providers", "content/ratings", "keywords"]), true);

        $this->checkTmdbSlug($tv, $slug);

        $this->saveImage("posters", $tv['poster_path'], $this->imageConfiguration->getUrl('poster_sizes', 5));
        $this->saveImage("backdrops", $tv['backdrop_path'], $this->imageConfiguration->getUrl('backdrop_sizes', 3));

        $tv['credits'] = $this->castAndCrew($tv);

        $tv['watch/providers'] = $this->getWatchProviders($tv, 'FR');

//        dump([
//            'tv' => $tv,
//        ]);
        return $this->render('series/tmdb.html.twig', [
            'tv' => $tv,
        ]);
    }

    #[Route('/series/{id}-{slug}', name: 'app_series_show', requirements: ['id' => '\d+'])]
    public function show(Request $request, $id, $slug): Response
    {
        $series = $this->seriesRepository->findOneBy(['id' => $id]);
        $series->setVisitNumber($series->getVisitNumber() + 1);
        $this->seriesRepository->save($series, true);

        $this->checkSlug($series, $slug);

        $this->saveImage("posters", $series->getPosterPath(), $this->imageConfiguration->getUrl('poster_sizes', 5));
        $this->saveImage("backdrops", $series->getBackdropPath(), $this->imageConfiguration->getUrl('backdrop_sizes', 3));

        $tv = json_decode($this->tmdbService->getTv($series->getTmdbId(), $request->getLocale(), ["images", "videos", "credits", "watch/providers", "content/ratings", "keywords"]), true);
        $tv['credits'] = $this->castAndCrew($tv);

        $tv['watch/providers'] = $this->getWatchProviders($tv, 'FR');

//        dump([
//            'series' => $series,
//            'tv' => $tv,
//        ]);
        return $this->render('series/show.html.twig', [
            'series' => $series,
            'tv' => $tv,
        ]);
    }

    public function checkSlug($series, $slug): bool|Response
    {
        if ($series->getSlug() !== $slug) {
            return $this->redirectToRoute('app_series_show', [
                'id' => $series->getId(),
                'slug' => $series->getSlug(),
            ], 301);
        }
        return true;
    }

    public function checkTmdbSlug($series, $slug): bool|Response
    {
        $slugger = new AsciiSlugger();
        $realSlug = $slugger->slug($series['name'])->lower()->toString();
        if ($realSlug !== $slug) {
            return $this->redirectToRoute('app_series_tmdb', [
                'id' => $series['id'],
                'slug' => $realSlug,
            ], 301);
        }
        return true;
    }

    public function castAndCrew($tv):array
    {
        $tv['credits']['cast'] = array_map(function ($cast) {
            $cast['profile_path'] = $cast['profile_path'] ? $this->imageConfiguration->getCompleteUrl($cast['profile_path'], 'profile_sizes', 2) : null; // w185
            return $cast;
        }, $tv['credits']['cast']);

        $tv['credits']['crew'] = array_map(function ($crew) {
            $crew['profile_path'] = $crew['profile_path'] ? $this->imageConfiguration->getCompleteUrl($crew['profile_path'], 'profile_sizes', 2) : null; // w185
            return $crew;
        }, $tv['credits']['crew']);

        usort($tv['credits']['crew'], function ($a, $b) {
            return !$a['profile_path'] <=> !$b['profile_path'];
        });
        return $tv['credits'];
    }

    public function getWatchProviders($tv, $country): array
    {
        $watchProviders = [];
        if (isset($tv['watch/providers']['results'][$country])) {
            $watchProviders = $tv['watch/providers']['results'][$country];
        }
        $flatrate = $watchProviders['flatrate'] ?? [];
        $rent = $watchProviders['rent'] ?? [];
        $buy = $watchProviders['buy'] ?? [];

        $flatrate = array_map(function ($wp) {
            return [
                'provider_id' => $wp['provider_id'],
                'provider_name' => $wp['provider_name'],
                'logo_path' => $wp['logo_path'] ? $this->imageConfiguration->getCompleteUrl($wp['logo_path'], 'logo_sizes', 2) : null, // w45
            ];
        }, $flatrate);
        $rent = array_map(function ($wp) {
            return [
                'provider_id' => $wp['provider_id'],
                'provider_name' => $wp['provider_name'],
                'logo_path' => $wp['logo_path'] ? $this->imageConfiguration->getCompleteUrl($wp['logo_path'], 'logo_sizes', 2) : null, // w45
            ];
        }, $rent);
        $buy = array_map(function ($wp) {
            return [
                'provider_id' => $wp['provider_id'],
                'provider_name' => $wp['provider_name'],
                'logo_path' => $wp['logo_path'] ? $this->imageConfiguration->getCompleteUrl($wp['logo_path'], 'logo_sizes', 2) : null, // w45
            ];
        }, $buy);
        return [
            'flatrate' => $flatrate,
            'rent' => $rent,
            'buy' => $buy,
        ];
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
