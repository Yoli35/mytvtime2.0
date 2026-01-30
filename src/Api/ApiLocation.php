<?php

namespace App\Api;

use App\Entity\FilmingLocation;
use App\Entity\FilmingLocationImage;
use App\Entity\Series;
use App\Repository\FilmingLocationImageRepository;
use App\Repository\FilmingLocationRepository;
use App\Service\DateService;
use App\Service\ImageService;
use Closure;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\DependencyInjection\Attribute\AutowireMethodOf;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route('/api/location', name: 'api_location_')]
readonly class ApiLocation
{
    public function __construct(
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure                        $json,
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure                        $getParameter,
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure                        $getUser,
        private DateService                    $dateService,
        private FilmingLocationImageRepository $filmingLocationImageRepository,
        private FilmingLocationRepository      $filmingLocationRepository,
        private ImageService                   $imageService,
    )
    {
    }

    #[Route('/add/{id}', name: 'add', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function add(Request $request, Series $series): Response
    {
        $messages = [];

        $data = $request->request->all();
        $files = $request->files->all();
        if (empty($data) && empty($files)) {
            return ($this->json)([
                'ok' => false,
                'message' => 'No data',
            ]);
        }

        $imageFiles = [];
        foreach ($files as $key => $file) {
            if ($file instanceof UploadedFile) {
                // Est-ce qu'il s'agit d'une image ?
                $mimeType = $file->getMimeType();
                if (str_starts_with($mimeType, 'image')) {
                    $imageFiles[$key] = $file;
                }
            }
        }
        if ($data['location'] == 'test') {
            // "image-url" => "blob:https://localhost:8000/71698467-714e-4b2e-b6b3-a285619ea272"
            $testUrl = $data['image-url'];
            if (str_starts_with($testUrl, 'blob')) {
                $this->imageService->blobToWebp2($data['image-url-blob'], $data['title'], $data['location'], 100);
            }

            return ($this->json)([
                'ok' => true,
                'testMode' => true,
                'message' => 'Test location',
            ]);
        }
        $data = array_filter($data, fn($key) => $key != "google-map-url", ARRAY_FILTER_USE_KEY);

        $crudType = $data['crud-type'];
        $new = $crudType === 'create';
        $crudId = $data['crud-id'];
        $now = $this->now();

        if (!$new)
            $filmingLocation = $this->filmingLocationRepository->findOneBy(['id' => $crudId]);
        else
            $filmingLocation = null;
//        $seriesId = $data['series-id'];

        $title = $data['title'];
        $location = $data['location'];
        $description = $data['description'];
        $data['latitude'] = str_replace(',', '.', $data['latitude']);
        $data['longitude'] = str_replace(',', '.', $data['longitude']);
        $data['radius'] = str_replace(',', '.', $data['radius']);
        $episodeNumber = intval($data['episode-number']);
        $seasonNumber = intval($data['season-number']);
        $latitude = $data['latitude'] = floatval($data['latitude']);
        $longitude = $data['longitude'] = floatval($data['longitude']);
        $radius = $data['radius'] = floatval($data['radius']);
        $sourceName = $data['source-name'] ?? '';
        $sourceUrl = $data['source-url'] ?? '';

        if ($crudType === 'create') {// Toutes les images
            $images = array_filter($data, fn($key) => str_contains($key, 'image-url'), ARRAY_FILTER_USE_KEY);
        } else { // Images supplémentaires
            $images = array_filter($data, fn($key) => str_contains($key, 'image-url-'), ARRAY_FILTER_USE_KEY);
        }
        $images = array_filter($images, fn($image) => $image != '' and $image != "undefined");
        // TODO: Vérifier le code suivant
        $firstImageIndex = 1;
        if ($filmingLocation) {
            // Récupérer les images existantes et les compter
            $existingAdditionalImages = $this->filmingLocationImageRepository->findBy(['filmingLocation' => $filmingLocation]);
            $firstImageIndex += count($existingAdditionalImages);
        }
        // Fin du code à vérifier

        if (!$filmingLocation) {
            $uuid = $data['uuid'] = Uuid::v4()->toString();
            $tmdbId = $data['tmdb-id'];
            $filmingLocation = new FilmingLocation($uuid, $tmdbId, $title, $location, $description, $latitude, $longitude, $radius, $seasonNumber, $episodeNumber, $sourceName, $sourceUrl, $now, true);
            $filmingLocation->setOriginCountry($series->getOriginCountry());
        } else {
            $filmingLocation->update($title, $location, $description, $latitude, $longitude, $radius, $seasonNumber, $episodeNumber, $sourceName, $sourceUrl, $now);
        }
        $this->filmingLocationRepository->save($filmingLocation, true);

        $n = $firstImageIndex;
        /****************************************************************************************
         * En mode dev, on peut ajouter des FilmingLocationImage sans passer par le             *
         * téléversement : "~/some picture.webp"                                                *
         * SINON :                                                                              *
         * Images ajoutées avec Url (https://website/some-pisture.png)                          *
         * ou par glisser-déposer ("blob:https://website/71698467-714e-4b2e-b6b3-a285619ea272") *
         ****************************************************************************************/
        foreach ($images as $name => $imageUrl) {
            if (str_starts_with($imageUrl, '~/')) {
                $image = str_replace('~/', '/', $imageUrl);
            } else {
                if (str_starts_with('blob:', $imageUrl)) {
//                    $this->blobs[$name . '-blob'] = $data[$name . '-blob'];
                    $image = $this->imageService->blobToWebp2($data[$name . '-blob'], $data['title'], $data['location'], $n);
                } else {
                    $image = $this->imageService->urlToWebp($imageUrl, $title, $location, $n);
                }
            }
            if ($image) {
                $filmingLocationImage = new FilmingLocationImage($filmingLocation, $image, $now);
                $this->filmingLocationImageRepository->save($filmingLocationImage, true);

                if ($crudType === 'create' && $n == 1) {
                    $filmingLocation->setStill($filmingLocationImage);
                    $this->filmingLocationRepository->save($filmingLocation, true);
                }
                $n++;
            }
        }

        /******************************************************************************
         * Images ajoutées depuis des fichiers locaux (type : UploadedFile)           *
         ******************************************************************************/
        foreach ($imageFiles as $key => $file) {
            $image = $this->imageService->fileToWebp($file, $title, $location, $n, '/public/images/map/', $seasonNumber, $episodeNumber);
            if ($image) {
                $filmingLocationImage = new FilmingLocationImage($filmingLocation, $image, $now);
                $this->filmingLocationImageRepository->save($filmingLocationImage, true);

                if ($key === 'image-file') { // la vignette
                    $filmingLocation->setStill($filmingLocationImage);
                    $this->filmingLocationRepository->save($filmingLocation, true);
                }
                $n++;
            }
        }
        if ($n > $firstImageIndex) {
            $addedImageCount = $n - $firstImageIndex;
            $messages[] = $addedImageCount . $addedImageCount > 1 ? ' images ajoutées' : ' image ajoutée';
        }

        return ($this->json)([
            'ok' => true,
            'messages' => $messages,
        ]);
    }

    #[Route('/delete', name: 'delete', requirements: ['id' => Requirement::DIGITS], methods: ['DELETE'])]
    public function delete(Request $request): Response
    {
        $data = $request->request->all();
        $id = $data['id'] ?? null;
        if (!$id) {
            return ($this->json)([
                'ok' => false,
                'success' => false,
                'message' => 'Invalid ID',
            ]);
        }
        $filmingLocation = $this->filmingLocationRepository->findOneBy(['id' => intval($id)]);
        if (!$filmingLocation) {
            return ($this->json)([
                'ok' => false,
                'success' => false,
                'message' => 'Location not found',
            ]);
        }
        $filmingLocation->setStill(null);
        $this->filmingLocationRepository->save($filmingLocation, true);
        $filmingLocationImages = $filmingLocation->getFilmingLocationImages();
        foreach ($filmingLocationImages as $filmingLocationImage) {
            $path = $filmingLocationImage->getPath();

            $filmingLocation->removeFilmingLocationImage($filmingLocationImage);
            $this->filmingLocationImageRepository->remove($filmingLocationImage);

            $filePath = ($this->getParameter)('kernel.project_dir') . '/public/images/map/' . $path;
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        $this->filmingLocationImageRepository->flush();
        $this->filmingLocationRepository->remove($filmingLocation, true);

        return ($this->json)([
            'ok' => true,
            'success' => true,
        ]);
    }

    public function now(): DateTimeImmutable
    {
        $user = ($this->getUser)();
        $timezone = $user ? $user->getTimezone() : 'Europe/Paris';
        return $this->dateService->newDateImmutable('now', $timezone);
    }
}