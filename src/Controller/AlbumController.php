<?php

namespace App\Controller;

use App\Entity\Album;
use App\Entity\Photo;
use App\Entity\User;
use App\Repository\AlbumRepository;
use App\Repository\PhotoRepository;
use App\Repository\SettingsRepository;
use App\Service\DateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** @method User|null getUser() */
#[Route('/{_locale}/album', name: 'app_album_', requirements: ['_locale' => 'en|fr|ko'])]
final class AlbumController extends AbstractController
{
    public function __construct(
        private readonly AlbumRepository    $albumRepository,
        private readonly DateService        $dateService,
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
            $range = [
                'min' => min($dates),
                'max' => max($dates),
            ];
            $album->setDateRange($range);
        }
        dump($albums);

        return $this->render('album/index.html.twig', [
            'albums' => $albums,
            'pagination' => '',
        ]);
    }

    #[Route('/show/{id}', name: 'show', requirements: ['id' => '\d+'])]
    public function show(Album $album): Response
    {

        $addPhotoFormData = [
            'hiddenFields' => [
                ['item' => 'hidden', 'name' => 'album-id', 'value' => $album->getId()],
                ['item' => 'hidden', 'name' => 'crud-type', 'value' => 'create'],
                ['item' => 'hidden', 'name' => 'crud-id', 'value' => 0],
            ],
            'rows' => [
                [
                    ['item' => 'input', 'name' => 'caption', 'label' => 'Caption', 'type' => 'text', 'required' => true],
                ],
                [
                    ['item' => 'input', 'name' => 'date', 'label' => 'Date', 'type' => 'datetime-local', 'required' => true],
                ],
            ],
        ];
        return $this->render('album/show.html.twig', [
            'album' => $album,
            'albumArray' => $this->toArray($album),
            'mapSettings' => $this->settingsRepository->findOneBy(['name' => 'mapbox']),
            'emptyPhoto' => $this->newPhoto($album),
            'addPhotoFormData' => $addPhotoFormData,
            'fieldList' => ['album-id', 'crud-type', 'crud-id', 'caption', 'date', 'latitude', 'longitude'],
            'previousAlbum' => null,
            'nextAlbum' => null,
            'dbUserAlbums' => [],
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
