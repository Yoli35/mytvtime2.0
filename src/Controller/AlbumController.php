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
        $albums = $this->albumRepository->findBy(['user' => $user, 'userCreated' => true], ['createdAt' => 'DESC']);

        $newAlbumName = $request->query->get('new-album');
        if (strlen($newAlbumName) > 0) {
            $now = $this->dateService->getNowImmutable('UTC');
            $album = new Album($user, true, $newAlbumName, $now);
            $this->albumRepository->save($album, true);
            return $this->redirectToRoute('app_album_show', ['id' => $album->getId()]);
        }

        foreach ($albums as $album) {
            $photos = $this->photoRepository->findBy(['albums' => $album], ['createdAt' => 'ASC']);
            $dates = array_map(function ($photo) {
                return $photo->getDate()->format('Y-m-d H:i:s');
            }, $photos);
            /*dump(['album' => $album, 'dates' => $dates]);*/
            if (count($dates) === 0) {
                $album->setDateRange([]);
                continue; // Skip albums with no photos
            }
            // Set the date range for the album
            $range = [
                'min' => min($dates),
                'max' => max($dates),
            ];
            $album->setDateRange($range);
        }
        $albumsByDays = $this->albumsByDays();
        dump($albumsByDays);
        $albums = array_merge($albums, $albumsByDays);
        $this->dateRangeString($albums);

        return $this->render('album/index.html.twig', [
            'albums' => $albums,
            'pagination' => '',
        ]);
    }

    #[Route('/show/{id}', name: 'show', requirements: ['id' => '\d+'])]
    public function show(Album $album): Response
    {
        $userCreated = $album->isUserCreated();
        $next = $userCreated ? $this->albumRepository->getNextAlbumId($album) : false;
        $nextAlbum = $next ? $this->albumRepository->findOneBy(['id' => $next['id']]) : null;
        $previous = $userCreated ? $this->albumRepository->getPreviousAlbumId($album) : false;
        $previousAlbum = $previous ? $this->albumRepository->findOneBy(['id' => $previous['id']]) : null;

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
//        $album->setDate('');
        $albumArr = $this->toArray($album);
        $imagePaths = array_map(function ($photo) {
            return $photo['image_path'];
        }, $albumArr['photos']);

        return $this->render('album/show.html.twig', [
            'album' => $album,
            'albumArray' => $albumArr,
            'imagePaths' => $imagePaths,
            'cellClasses' => $this->getCellClasses(count($imagePaths)),
            'settings' => $this->getAlbumsSettings($this->getUser()),
            'mapSettings' => $this->settingsRepository->findOneBy(['name' => 'mapbox']),
            'emptyPhoto' => $this->newPhoto($album),
            'addPhotoFormData' => $editAlbumFormData,
            'photoFieldList' => ['album-id', 'photo-id', 'caption', 'date', 'latitude', 'longitude'],
            'previousAlbum' => $previousAlbum,
            'nextAlbum' => $nextAlbum,
            'srcsetPaths' => ['lowRes' => '/albums/576p', 'mediumRes' => '/albums/720p', 'highRes' => '/albums/1080p', 'original' => '/albums/original'],
        ]);
    }

    /*#[Route('/date/{date}', name: 'date', requirements: ['date' => '\d{4}-\d{2}-\d{2}'])]
    public function date(string $date): Response
    {
        $name = ucfirst($this->dateService->formatDateRelativeMedium($date, 'UTC', $this->getUser()->getPreferredLanguage() ?? 'en'));
        $album = [
            'id' => -1,
            'name' => $name,
            'date' => $date,
        ];

        $editAlbumFormData = [];
        $albumArr = $this->toArray($album, true);
        $imagePaths = array_map(function ($photo) {
            return $photo['image_path'];
        }, $albumArr['photos']);

        return $this->render('album/show.html.twig', [
            'album' => $album,
            'albumArray' => $albumArr,
            'imagePaths' => $imagePaths,
            'cellClasses' => $this->getCellClasses(count($imagePaths)),
            'settings' => $this->getAlbumsSettings($this->getUser()),
            'mapSettings' => $this->settingsRepository->findOneBy(['name' => 'mapbox']),
            'emptyPhoto' => null,
            'addPhotoFormData' => $editAlbumFormData,
            'photoFieldList' => ['album-id', 'photo-id', 'caption', 'date', 'latitude', 'longitude'],
            'previousAlbum' => null,
            'nextAlbum' => null,
            'srcsetPaths' => ['lowRes' => '/albums/576p', 'mediumRes' => '/albums/720p', 'highRes' => '/albums/1080p', 'original' => '/albums/original'],
        ]);
    }*/

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

    #[Route('/photo/edit', name: 'photo_edit', methods: ['POST'])]
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
            if ($file instanceof UploadedFile && $file->getError() === UPLOAD_ERR_OK) {
                // Est-ce qu'il s'agit d'une image ?
                $mimeType = $file->getMimeType();
                if (str_starts_with($mimeType, 'image')) {
                    $imageFiles[$key] = $file;
                }
            } else {
                $messages[] = 'Erreur lors de l\'upload du fichier : ' . ($file instanceof UploadedFile ? $file->getClientOriginalName() : 'Fichier inconnu');
            }
        }

        $now = $this->dateService->getNowImmutable('UTC');

        /******************************************************************************
         * Images ajoutées depuis des fichiers locaux (type : UploadedFile)           *
         ******************************************************************************/
        $n = 0;
        $results = [];
        foreach ($imageFiles as $file) {
            $result = $this->imageService->photoToWebp($file);
            if ($result) {
                $imagePath = $result['path']; // original image path
                $isHighRes = $result['1080p'];
                $isMediumRes = $result['720p'];
                $isLowRes = $result['576p'];
                if ($imagePath && $isHighRes && $isMediumRes && $isLowRes) {
                    $exif = $result['exif'];
                    $photo = new Photo(
                        user: $this->getUser(),
                        album: $album,
                        caption: '',
                        image_path: $imagePath,
                        createdAt: $now,
                        updatedAt: $now,
                        date: $exif['date'] ?? $now,
                        latitude: $exif['latitude'] ?? null,
                        longitude: $exif['longitude'] ?? null
                    );
                    $this->photoRepository->save($photo, true);
                    $album->setUpdatedAt($now);
                    $this->albumRepository->save($album, true);
                    $r = [];
                    $r['image_path'] = $imagePath;
                    $r['id'] = $photo->getId();
                    $r['caption'] = null;
                    $r['created_at'] = $photo->getCreatedAt()->format('Y-m-d H:i:s');
                    $r['created_at_string'] = ucfirst($this->dateService->formatDateRelativeLong($r['created_at'], 'UTC', $request->getLocale()));
                    $r['updated_at'] = $photo->getUpdatedAt()->format('Y-m-d H:i:s');
                    $r['updated_at_string'] = ucfirst($this->dateService->formatDateRelativeLong($r['updated_at'], 'UTC', $request->getLocale()));
                    $r['date'] = $photo->getDate()->format('Y-m-d H:i:s');
                    $r['date_string'] = $r['date'] ? ucfirst($this->dateService->formatDateRelativeLong($r['date'], 'UTC', $request->getLocale())) : '';
                    $r['latitude'] = $photo->getLatitude();
                    $r['longitude'] = $photo->getLongitude();
                    $results[] = $r;
                    $messages[] = 'Photo ajoutée : ' . $file->getClientOriginalName();
                    $n++;
                } else {
                    $messages[] = 'Erreur lors de l\'ajout de la photo : ' . $file->getClientOriginalName();
                }
            }
        }
        if ($n > 1) {
            $messages[] = $n . ' photos ajoutées';
        }

//        dump([
//            'ok' => true,
//            'messages' => $messages,
//            'results' => $results,
//
//        ]);
        return $this->json([
            'ok' => true,
            'messages' => $messages,
            'results' => $results,

        ]);
    }

    private function getAlbumsSettings(User $user): array
    {
        $settings = $this->settingsRepository->findOneBy(['user' => $user, 'name' => 'album']);
        if (!$settings) {
            $settings = new Settings($user, 'album', ['layout' => 'grid', 'photosPerPage' => 20]);
            $this->settingsRepository->save($settings, true);
        }
        return $settings->getData();
    }

    private function getCellClasses($cellCount): array
    {
        $cellClasses = [];
        $lastGridSpan2Pos = $cellCount - 5;
        for ($i = 0, $index = 0; $i < $cellCount; $i++) {
            if ($i < $lastGridSpan2Pos && in_array($i % 27, [0, 10, 20])) {
                $cellClasses[] = "grid-span-2";
                $index += 4;
                continue;
            }

            $cellClasses[] = "";
            $index++;
        }
        $emptyCellCount = 4 - $index % 4;
        if ($emptyCellCount == 1) {
            $cellClasses[$cellCount - 1] = "grid-col-span-2";
        }
        if ($emptyCellCount == 2) {
            $cellClasses[$cellCount - 1] = "grid-col-span-3";
        }
        if ($emptyCellCount == 3) {
            $cellClasses[$cellCount - 1] = "grid-span-4";
        }
//        dump(['cellCount' => $cellCount, 'index' => $index, 'emptyCellCount' => $emptyCellCount, 'cellClasses' => $cellClasses]);
        return $cellClasses;
    }

    private function albumsByDays(): array
    {
        $user = $this->getUser();
        $now = $this->dateService->getNowImmutable('UTC');
        $photos = $this->photoRepository->findBy(['user' => $user], ['date' => 'DESC']);
        if ($photos) {
            $albums = $this->albumRepository->findBy(['userCreated' => false], ['createdAt' => 'DESC']);
            /** @var Photo $photo */
            foreach ($photos as $photo) {
                $date = $photo->getDate();
                $day = $date->format('Y-m-d');
                $album = $albums ? array_find($albums, function ($a) use ($day) {
                    return $a->getName() === $day;
                }) : null;
                $diff = $now->diff($date);
                if ($diff->days > 1 && $diff->days < 7) {
                    $name = $this->translator->trans($date->format('l'));
                } else {
                    $name = ucfirst($this->dateService->formatDateRelativeMedium($day, 'UTC', $this->getUser()->getPreferredLanguage() ?? 'en'));
                }
                if (!$album) {
                    $album = new Album($user, false, $day, $now);
                    $this->albumRepository->save($album, true);
                    $albums[] = $album;
                }
                // Set the date range for the album
                $album->setDateRange([
                    'min' => $day,
                    'max' => $day,
                ]);
                $album->setDescription($name);
                $album->addPhoto($photo);
            }
            $this->albumRepository->flush();
            return array_values($albums);
        }
        return [];
    }

    private function newPhoto(Album $album): array
    {
        $now = $this->dateService->getNowImmutable('UTC');
        return [
            'id' => null,
            'user_id' => $album->getUser()->getId(),
            'album_id' => $album->getId(),
            'caption' => '',
            'image_path' => '',
            'created_at_string' => $now->format('Y-m-d H:i:s'),
            'updated_at_string' => $now->format('Y-m-d H:i:s'),
            'date_string' => $now->format('Y-m-d H:i:s'),
            'latitude' => null,
            'longitude' => null,
        ];
    }

    private function toArray(Album $album): array
    {
        dump($album);
        $array = [
            'id' => $album->getId(),
            'user_id' => $album->getUser()->getId(),
            'user_created' => $album->isUserCreated(),
            'name' => !$album->getName(),
            'description' => $album->getDescription(),
            'date' => $album->getDate(),
            'created_at_string' => $album->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at_string' => $album->getUpdatedAt()->format('Y-m-d H:i:s'),
            'photos' => [],
        ];

        $photos = $album->getPhotos()->toArray();
        dump($photos);
        /** @var Photo $photo */
        foreach ($photos as $photo) {
            $arr = [
                'id' => $photo->getId(),
                'user_id' => $photo->getUser()->getId(),
                'album_id' => $album->getId(),
                'caption' => $photo->getCaption(),
                'image_path' => $photo->getImagePath(),
                'createdAt' => $photo->getCreatedAt()->format('Y-m-d H:i:s'),
                'updatedAt' => $photo->getUpdatedAt()->format('Y-m-d H:i:s'),
                'created_at_string' => $photo->getCreatedAt()->format('Y-m-d H:i:s'),
                'updated_at_string' => $photo->getUpdatedAt()->format('Y-m-d H:i:s'),
                'date' => $photo->getDate()->format('Y-m-d H:i:s'),
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
//            dump($album);
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
}
