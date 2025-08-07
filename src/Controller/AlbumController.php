<?php

namespace App\Controller;

use App\Entity\Album;
use App\Entity\Photo;
use App\Entity\Settings;
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
use Symfony\Contracts\Translation\TranslatorInterface;

/** @method User|null getUser() */
#[Route('/{_locale}/album', name: 'app_album_', requirements: ['_locale' => 'en|fr|ko'])]
final class AlbumController extends AbstractController
{
    public function __construct(
        private readonly AlbumRepository     $albumRepository,
        private readonly CountryRepository   $countryRepository,
        private readonly DateService         $dateService,
        private readonly ImageService        $imageService,
        private readonly PhotoRepository     $photoRepository,
        private readonly SettingsRepository  $settingsRepository,
        private readonly TranslatorInterface $translator
    )
    {
    }

    #[Route('/', name: 'index')]
    public function index(Request $request): Response
    {
        $user = $this->getUser();
        $albums = $this->albumRepository->findBy(['user' => $user,], ['createdAt' => 'DESC']);

        $newAlbumName = $request->query->get('new-album');
        if (strlen($newAlbumName) > 0) {
            $now = $this->dateService->getNowImmutable('UTC');
            $album = new Album($user, $newAlbumName, $now);
            $this->albumRepository->save($album, true);
            return $this->redirectToRoute('app_album_show', ['id' => $album->getId()]);
        }

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
        $this->dateRangeString($albums);

        return $this->render('album/index.html.twig', [
            'albums' => $albums,
            'pagination' => '',
        ]);
    }

    #[Route('/show/{id}', name: 'show', requirements: ['id' => '\d+'])]
    public function show(Album $album): Response
    {
        $next = $this->albumRepository->getNextAlbumId($album);
        $nextAlbum = $next ? $this->albumRepository->findOneBy(['id' => $next['id']]) : null;
        $previous = $this->albumRepository->getPreviousAlbumId($album);
        $previousAlbum = $previous ? $this->albumRepository->findOneBy(['id' => $previous['id']]) : null;

        $user = $this->getUser();
        $settings = $this->settingsRepository->findOneBy(['user' => $user, 'name' => 'album']);
        if (!$settings) {
            $settings = new Settings($user, 'album', ['layout' => 'grid', 'photosPerPage' => 20]);
            $this->settingsRepository->save($settings, true);
        }

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
        $albumArr = $this->toArray($album);
        $imagePaths = array_map(function ($photo) {
            return $photo['image_path'];
        }, $albumArr['photos']);

        return $this->render('album/show.html.twig', [
            'album' => $album,
            'albumArray' => $albumArr,
            'imagePaths' => $imagePaths,
            'settings' => $settings->getData(),
            'mapSettings' => $this->settingsRepository->findOneBy(['name' => 'mapbox']),
            'emptyPhoto' => $this->newPhoto($album),
            'addPhotoFormData' => $editAlbumFormData,
            'photoFieldList' => ['album-id', 'photo-id', 'caption', 'date', 'latitude', 'longitude'],
            'previousAlbum' => $previousAlbum,
            'nextAlbum' => $nextAlbum,
            'srcsetPaths' => ['lowRes' => '/albums/576p', 'mediumRes' => '/albums/720p', 'highRes' => '/albums/1080p', 'original' => '/albums/original'],
        ]);
    }

    #[Route('/settings/{id}', name: 'settings', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function settings(Request $request, Album $album): Response
    {
        // {
        //     "layout": "list",
        //     "photosPerPage": 20
        // }
        $data = json_decode($request->getContent(), true);
//        dump($data);
        $settings = $this->settingsRepository->findOneBy(['user' => $album->getUser(), 'name' => 'album']);

        $layout = $data['layout'] ?? 'grid';
        $photosPerPage = $data['photosPerPage'] ?? 20;
        if (!$settings) {
            $settings = new Settings($album->getUser(), 'album', ['layout' => $layout, 'photosPerPage' => $photosPerPage]);
        } else {
            $settings->setData(['layout' => $layout, 'photosPerPage' => $photosPerPage]);
        }
        $this->settingsRepository->save($settings, true);

        $messages = ['Settings updated successfully.'];
        return $this->json([
            'ok' => true,
            'messages' => $messages,
            'settings' => [
                'layout' => $settings->getData()['layout'],
                'photosPerPage' => $settings->getData()['photosPerPage'],
            ],
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

    #[Route('/photo/edit', name: 'modify', methods: ['POST'])]
    public function edit(Request $request): Response
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

        $photo = $this->photoRepository->findOneBy(['id' => $data['photo-id']]);
        if (!$photo) {
            $messages[] = 'Photo non trouvée';
            return $this->json([
                'ok' => false,
                'messages' => $messages,
            ]);
        }
        $caption = $data['caption'] ?? '';
        $date = $data['date'] ?? null;
        $latitude = $data['latitude'] ?? null;
        $longitude = $data['longitude'] ?? null;
        if ($date) {
            $date = $this->dateService->newDateImmutable($date, 'UTC');
        } else {
            $date = $photo->getDate();
        }
        $photo->setCaption($caption);
        $photo->setDate($date);
        $photo->setLatitude($latitude);
        $photo->setLongitude($longitude);
        $photo->setUpdatedAt($this->dateService->getNowImmutable('UTC'));
        $this->photoRepository->save($photo, true);

        $dateString = ucfirst($this->dateService->formatDateRelativeLong($data['date'], 'UTC', $request->getLocale()));
        $data['date_string'] = $dateString;
        $data['image_path'] = $photo->getImagePath();
        $data['id'] = $photo->getId();

        $messages[] = 'Photo modifiée : ' . $photo->getImagePath();

        return $this->json([
            'ok' => true,
            'messages' => $messages,
            'photo' => $data
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

        $now = $this->dateService->getNowImmutable('UTC');


        /******************************************************************************
         * Images ajoutées depuis des fichiers locaux (type : UploadedFile)           *
         ******************************************************************************/
        $n = 0;
        $imagePaths = [];
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
                    $album->setUpdatedAt($now);
                    $this->albumRepository->save($album, true);
                    $imagePaths[] = $imagePath;
                    $messages[] = 'Photo ajoutée : ' . $file->getClientOriginalName();
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
            'image_paths' => $imagePaths,
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
        $minLat = min(array_column($photosWithLocation, 'latitude'));
        $maxLat = max(array_column($photosWithLocation, 'latitude'));
        $minLng = min(array_column($photosWithLocation, 'longitude'));
        $maxLng = max(array_column($photosWithLocation, 'longitude'));
        $bounds = [[$maxLng + .01, $maxLat + .01], [$minLng - .01, $minLat - .01]];
        $array['bounds'] = $bounds;

        return $array;
    }

    private function dateRangeString($albums): void
    {
        /** @var Album $album */
        foreach ($albums as $album) {
            $dateRange = $album->getDateRange() ?? null;
            if (empty($dateRange)) {
                $string = $this->translator->trans('No date range');
                $album->setDateRangeString($string);
                continue;
            }

            $minDate = $this->dateService->newDateImmutable($dateRange['min'], 'UTC'); //new \DateTimeImmutable($dateRange['min']);
            $maxDate = $this->dateService->newDateImmutable($dateRange['max'], 'UTC'); //new \DateTimeImmutable($dateRange['max']);
            $maxYear = $maxDate->format('Y');
            $minYear = $minDate->format('Y');
            $minMouth = $minDate->format('m');
            $maxMonth = $maxDate->format('m');
            $minDay = $minDate->format('d');
            $maxDay = $maxDate->format('d');
            $F1 = strtolower($this->translator->trans($minDate->format('F')));
            $F2 = strtolower($this->translator->trans($maxDate->format('F')));
            if ($minYear === $maxYear) {
                if ($minMouth === $maxMonth) {
                    if ($minDay === $maxDay) {
                        $string = $minDate->format('j \F1 Y');
                    } else {
                        $string = $minDate->format('j') . ' - ' . $maxDate->format('j \F1 Y');
                    }
                } else {
                    $string = $minDate->format('j \F1') . ' - ' . $maxDate->format('j \F2 Y');
                }
            } else {
                $string = $minDate->format('j \F1 Y') . ' - ' . $maxDate->format('j \F2 Y');
            }
            $string = str_replace('F1', $F1, $string);
            $string = str_replace('F2', $F2, $string);
            $album->setDateRangeString($string);
        }
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
