<?php

namespace App\Controller;

use App\Entity\Album;
use App\Entity\Photo;
use App\Entity\User;
use App\Repository\AlbumRepository;
use App\Repository\CountryRepository;
use App\Repository\PhotoRepository;
use App\Repository\SettingsRepository;
use App\Service\DateService;
use App\Service\ImageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/** @method User|null getUser() */
#[Route('/{_locale}/album', name: 'app_album_', requirements: ['_locale' => 'en|fr|ko'])]
final class AlbumController extends AbstractController
{
    public function __construct(
        private readonly AlbumRepository    $albumRepository,
        private readonly CountryRepository  $countryRepository,
        private readonly DateService        $dateService,
        private readonly ImageService       $imageService,
        private readonly PhotoRepository    $photoRepository,
        private readonly SettingsRepository $settingsRepository,
    )
    {
    }

    #[Route('/', name: 'index')]
    public function index(): Response
    {
        $user = $this->getUser();
        $albums = $this->albumRepository->findBy(['user' => $user,], ['createdAt' => 'DESC']);

        foreach ($albums as $album) {
            $photos = $this->photoRepository->findBy(['album' => $album], ['createdAt' => 'ASC']);
            $dates = array_map(function ($photo) {
                return $photo->getDate()->format('Y-m-d H:i:s');
            }, $photos);
            if (count($dates) === 0) {
                continue; // Skip albums with no photos
            }
            // Set the date range for the album
            $range = [
                'min' => min($dates),
                'max' => max($dates),
            ];
            $album->setDateRange($range);
        }

        return $this->render('album/index.html.twig', [
            'albums' => $albums,
            'pagination' => '',
        ]);
    }

    #[Route('/show/{id}', name: 'show', requirements: ['id' => '\d+'])]
    public function show(Album $album): Response
    {

        $editAlbumFormData = [
            'hiddenFields' => [
                ['item' => 'hidden', 'name' => 'crud-type', 'value' => 'edit'],
                ['item' => 'hidden', 'name' => 'crud-id', 'value' => $album->getId()],
            ],
            'rows' => [
                [
                    ['item' => 'input', 'name' => 'name', 'label' => 'Name', 'type' => 'text', 'value' => $album->getName(), 'required' => true],
                ],
                [
                    ['item' => 'textarea', 'name' => 'description', 'label' => 'Description', 'value' => $album->getDescription(), 'required' => false],
                ],
            ],
        ];
        return $this->render('album/show.html.twig', [
            'album' => $album,
            'albumArray' => $this->toArray($album),
            'mapSettings' => $this->settingsRepository->findOneBy(['name' => 'mapbox']),
            'emptyPhoto' => $this->newPhoto($album),
            'addPhotoFormData' => $editAlbumFormData,
            'fieldList' => ['album-id', 'crud-type', 'crud-id', 'caption', 'date', 'latitude', 'longitude'],
            'previousAlbum' => null,
            'nextAlbum' => null,
            'dbUserAlbums' => [],
        ]);
    }

    #[Route('/modify/{id}', name: 'modify', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function modify(Request $request, Album $album): Response
    {
        $messages = [];

        $data = $request->request->all();
        if (empty($data)) {
            $messages[] = 'Aucune donnée reçue';
            return $this->json([
                'ok' => false,
                'messages' => $messages,
            ]);
        }

        $name = $data['name'];
        $description = $data['description'];

        if ($name != $album->getName() || $description !== $album->getDescription()) {
            $album->update($name, $description);
            $this->albumRepository->save($album, true);
            $messages[] = 'Album modifié';
        } else {
            $messages[] = 'Aucune modification apportée à l\'album';
        }

        return $this->json([
            'ok' => true,
            'messages' => $messages,
        ]);
    }

    #[Route('/add/{id}', name: 'add_photos', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function add(Request $request, Album $album): Response
    {
        $messages = [];

        $files = $request->files->all();
        if (empty($files)) {
            $messages[] = 'Album mis à jour, aucune photo ajoutée';
            return $this->json([
                'ok' => false,
                'messages' => $messages,
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
        dump($imageFiles);

        $now = $this->dateService->getNowImmutable('UTC');


        /******************************************************************************
         * Images ajoutées depuis des fichiers locaux (type : UploadedFile)           *
         ******************************************************************************/
        $n = 0;
        foreach ($imageFiles as $file) {
            $result = $this->imageService->photoToWebp($file);
            if ($result) {
                $imagePath = $result['path']; // original image path
                $isHighRes = $result['1080p'];
                $isMediumRes = $result['720p'];
                $isLowRes = $result['576p'];
                if ($imagePath && $isHighRes && $isMediumRes && $isLowRes) {
                    $photo = new Photo(
                        user: $this->getUser(),
                        album: $album,
                        caption: '',
                        image_path: $imagePath,
                        createdAt: $now,
                        updatedAt: $now,
                        date: $now,
                        latitude: null,
                        longitude: null
                    );
                    $this->photoRepository->save($photo, true);
                    $n++;
                } else {
                    $messages[] = 'Erreur lors de l\'ajout de la photo : ' . $file->getClientOriginalName();
                }
            }
        }
        if ($n) {
            $messages[] = $n . ($n > 1 ? ' photos ajoutées' : ' photo ajoutée');
        }

        return $this->json([
            'ok' => true,
            'messages' => $messages,
        ]);
    }

    private function toArray(Album $album): array
    {
        $photos = $this->photoRepository->findBy(['album' => $album], ['createdAt' => 'ASC']);

        $array = [
            'id' => $album->getId(),
            'user_id' => $album->getUser()->getId(),
            'name' => $album->getName(),
            'description' => $album->getDescription(),
            'created_at_string' => $album->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at_string' => $album->getUpdatedAt()->format('Y-m-d H:i:s'),
            'photos' => [],
        ];

        foreach ($photos as $photo) {
            $arr = [
                'id' => $photo->getId(),
                'user_id' => $photo->getUser()->getId(),
                'album_id' => $photo->getAlbum()->getId(),
                'caption' => $photo->getCaption(),
                'image_path' => $photo->getImagePath(),
                'created_at_string' => $photo->getCreatedAt()->format('Y-m-d H:i:s'),
                'updated_at_string' => $photo->getUpdatedAt()->format('Y-m-d H:i:s'),
                'date_string' => $photo->getDate()->format('Y-m-d H:i:s'),
                'latitude' => $photo->getLatitude(),
                'longitude' => $photo->getLongitude(),
            ];
            $array['photos'][] = $arr;
        }
        $photos = $array['photos'];
        $photosWithLocation = array_filter($photos, function ($photo) {
            return $photo['latitude'] !== null && $photo['longitude'] !== null;
        });
        if (count($photosWithLocation) === 0) {
            $countryCode = $this->getUser()->getCountry() ?? 'FR';
            $countryBounds = $this->countryRepository->findOneBy(['code' => $countryCode]);
            $bounds = $countryBounds ? $countryBounds->getBounds() : [[2.5, 49.5], [1.5, 48.5]];
            $array['bounds'] = $bounds;
            return $array;
        }
        $minLat = min(array_column($photos, 'latitude'));
        $maxLat = max(array_column($photos, 'latitude'));
        $minLng = min(array_column($photos, 'longitude'));
        $maxLng = max(array_column($photos, 'longitude'));
        $bounds = [[$maxLng + .1, $maxLat + .1], [$minLng - .1, $minLat - .1]];
        $array['bounds'] = $bounds;

        return $array;
    }

    private function newPhoto(Album $album): array
    {
        $now = $this->dateService->getNowImmutable('UTC');
        $emptyPhoto = new Photo(
            user: $this->getUser(),
            album: $album,
            caption: '',
            image_path: '',
            createdAt: $now,
            updatedAt: $now,
            date: $now,
            latitude: null,
            longitude: null
        );
        return $emptyPhoto->toArray();
    }
}
