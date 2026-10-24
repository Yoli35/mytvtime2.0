<?php

namespace App\Service;

use App\Entity\Movie;
use App\Entity\MovieCollection;
use App\Entity\MovieImage;
use App\Entity\User;
use App\Entity\UserMovie;
use App\Repository\MovieCollectionRepository;
use App\Repository\MovieImageRepository;
use App\Repository\MovieRepository;
use App\Repository\SettingsRepository;
use App\Repository\UserMovieRepository;

class MovieService
{
    public const array WATCHLIST_DEFAULT_FILTERS = [
        'order' => 'DESC',
        'page' => 1,
        'limit' => 10,
        'sort' => 'releaseDate',
        'title' => '',
    ];

    private string $root;
    private array $sizes;
    private array $messages = [];

    public function __construct(
        private readonly DateService               $dateService,
        private readonly ImageConfiguration        $imageConfiguration,
        private readonly ImageService              $imageService,
        private readonly MovieCollectionRepository $movieCollectionRepository,
        private readonly MovieImageRepository      $movieImageRepository,
        private readonly MovieRepository           $movieRepository,
        private readonly SettingsRepository        $settingsRepository,
        private readonly TMDBService             $tmdbService,
        private readonly UserMovieRepository       $userMovieRepository,
    )
    {
        $this->root = $this->imageService->getProjectDir() . '/public';
        $this->sizes = ['backdrop' => 3, 'logo' => 5, 'poster' => 5];
    }

    public function getTranslations(array $tv, User $user): ?array
    {
        // "translations": [
        //      {
        //        "iso_3166_1": "US",
        //        "iso_639_1": "en",
        //        "name": "English",
        //        "english_name": "English",
        //        "data": {
        //          "homepage": "",
        //          "overview": "A boy who gets frustrated in love meets another boy who loses all of his memories and they find some ways to start a new life together.",
        //          "runtime": 104,
        //          "tagline": "He will make your night The Night.",
        //          "title": "Red Wine in the Dark Night"
        //        }
        //      },
        //      [...]
        //      {
        //        "iso_3166_1": "FR",
        //        "iso_639_1": "fr",
        //        "name": "Français",
        //        "english_name": "French",
        //        "data": {
        //          "homepage": "",
        //          "overview": "Wine, un jeune homme au physique angélique, rencontre par hasard un garçon de son âge qui se révèle totalement amnésique. Un lien particulier se noue entre eux lorsque Wine s'aperçoit de la nature de l'inconnu : ce dernier est une créature de la nuit, assoiffée de sang et mise au supplice par le manque. Wine finit par le laisser assouvir sa soif sur lui, franchissant du même coup le point de non retour. Autour d'eux gravitent Tee, l'ex petit ami de Wine qui peine à accepter son homosexualité et Boy, un homme d'affaires épris de Wine depuis longtemps qui espère toujours le conquérir.  Entre les trois hommes, Wine est bientôt la cible de nombreux désirs, dont les plus inquiétants ne viennent pas nécessairement du vampire...",
        //          "runtime": 0,
        //          "tagline": "",
        //          "title": "Red Wine in the Dark Night"
        //        }
        //      },
        $country = $user->getCountry() ?? 'FR'; // user iso_3166_1
        $locale = $user->getPreferredLanguage() ?? 'fr'; // user iso_639_1
        $translations = $tv['translations']['translations'];
        $translation = null;

        foreach ($translations as $t) {
            if ($t['iso_3166_1'] == $country && $t['iso_639_1'] == $locale) {
                $translation = $t;
                break;
            }
        }
        if ($translation == null) {
            foreach ($translations as $t) {
                if ($t['iso_3166_1'] == $country) {
                    $translation = $t;
                    break;
                }
            }
        }
        if ($translation == null) {
            foreach ($translations as $t) {
                if ($t['iso_639_1'] == $locale) {
                    $translation = $t;
                    break;
                }
            }
        }
        // get en-Us if null
        if ($translation == null) {
            foreach ($translations as $t) {
                if ($t['iso_3166_1'] == 'US' && $t['iso_639_1'] == 'en') {
                    $translation = $t;
                    break;
                }
            }
        }
        return $translation;
    }

    public function checkMovieImage(string $title, array $tmdbMovie, Movie $movie, string $imageType, bool $isCommand = false): bool
    {
        $movieImages = array_filter($movie->getMovieImages()->toArray(), fn($i) => $i->getType() === $imageType);

        $updated = false;
        $tmdbImage = $tmdbMovie[$imageType . '_path'];
        $getter = 'get' . ucfirst($imageType) . 'Path';
        $setter = 'set' . ucfirst($imageType) . 'Path';
        $image = $movie->$getter();
        if ($isCommand) $this->messages[] = '[' . $title . '] ' . 'Current / actual poster: ' . $image . ' / ' . $tmdbImage;
        if ($tmdbImage !== $image) {
            if ($isCommand) $this->messages[] = '[' . $title . '] ' . 'Updating ' . $imageType . ' image';
            $movie->$setter($tmdbImage);
            $updated = true;
            $imageArr = [$tmdbImage, $image];
        } else {
            $imageArr = [$tmdbImage];
        }
        foreach ($imageArr as $i) {
            if (!$i) {
                continue;
            }
            if (!$this->imageInArray($i, $movieImages)) {
                if ($isCommand) $this->messages[] = '[' . $title . '] ' . 'Adding ' . $imageType . ' image: ' . $i;
                $movieImage = new MovieImage($movie, $imageType, $i);
                $movie->addMovieImage($movieImage);
                $this->movieImageRepository->save($movieImage);
                $updated = true;
            }
            if (!$this->fileExists($this->root . '/movies/' . $imageType . 's' . $i)) {
                if ($isCommand) $this->messages[] = '[' . $title . '] ' . ucfirst($imageType) . ' image does not exist';
                $url = $this->imageConfiguration->getUrl('poster_sizes', $this->sizes[$imageType]);
                if ($this->imageService->saveImage($imageType . 's', $i, $url, '/movies/')) {
                    if ($isCommand) $this->messages[] = '[' . $title . '] ' . ucfirst($imageType) . ' image saved: ' . $i;
                }
            }
        }
        return $updated;
    }

    public function checkMovieCollection(string $title, array $tmdbMovie, Movie $movie, bool $isCommand = false): bool
    {
        $updated = false;
        $tmdbCollection = $tmdbMovie['belongs_to_collection'];
        if ($tmdbCollection) {
            $tmdbCollectionId = $tmdbCollection['id'];
            $this->messages[] = '[' . $title . '] ' . 'Updating collection';
            $dbCollection = $this->movieCollectionRepository->findOneBy(['tmdbId' => $tmdbCollectionId]);
            if (!$dbCollection) {
                if ($isCommand) $this->messages[] = ' by creating new collection "' . $tmdbCollection['name'] . '"';
                $dbCollection = new MovieCollection();
                $save = true;
            } else {
                if ($isCommand) $this->messages[] = ' "' . $tmdbCollection['name'] . '"';
                $save = false;
            }
            $dbCollection->setTmdbId($tmdbCollectionId);
            if ($dbCollection->getName() !== $tmdbCollection['name']) {
                if ($isCommand) $this->messages[] = '[' . $title . '] ' . 'Updating collection name';
                $save = true;
            }
            $dbCollection->setName($tmdbCollection['name']);
            if ($dbCollection->getPosterPath() !== $tmdbCollection['poster_path']) {
                if ($isCommand) $this->messages[] = '[' . $title . '] ' . 'Updating collection poster';
                $save = true;
            }
            $dbCollection->setPosterPath($tmdbCollection['poster_path']);
            if ($dbCollection->getBackdropPath() !== $tmdbCollection['backdrop_path']) {
                if ($isCommand) $this->messages[] = '[' . $title . '] ' . 'Updating collection backdrop';
                $save = true;
            }
            $dbCollection->setBackdropPath($tmdbCollection['backdrop_path']);
            $this->movieCollectionRepository->save($dbCollection, $save);
            $collection = $movie->getCollection();
            $collectionId = $collection?->getTmdbId() ?? 'null';
            if ($isCommand) $this->messages[] = '[' . $title . '] ' . 'Current / actual collection: ' . $collectionId . ' / ' . $tmdbCollectionId;
            if ($tmdbCollectionId !== $collectionId) {
                $movie->setCollection($dbCollection);
                $updated = true;
            }
        } else {
            if ($isCommand) $this->messages[] = '[' . $title . '] ' . 'No collection';
            $collection = $movie->getCollection();
            if ($collection) {
                if ($isCommand) $this->messages[] = '[' . $title . '] ' . 'Removing collection';
                $movie->setCollection(null);
                $updated = true;
            }
        }
        return $updated;
    }

    /**
     * Ajoute un film (TMDB id) à la liste de l'utilisateur.
     * Utilisé par la route app_movie_add et par l'api (ajout depuis les champs de recherche).
     *
     * @return array{userMovie: UserMovie, alreadyAdded: bool}
     */
    public function addMovieToWatchlist(User $user, int $tmdbId): array
    {
        $movie = $this->movieRepository->findOneBy(['tmdbId' => $tmdbId]);
        if ($movie) {
            $userMovie = $this->userMovieRepository->findOneBy(['movie' => $movie, 'user' => $user]);
            if ($userMovie) {
                return ['userMovie' => $userMovie, 'alreadyAdded' => true];
            }
        } else {
            $tmdbMovie = json_decode($this->tmdbService->getMovie($tmdbId, 'fr-FR', ['videos,images,credits,recommendations,watch/providers,release_dates']), true);
            $movie = new Movie($tmdbMovie);
            $movie->setCollection($this->getCollection($tmdbMovie));
            $this->movieRepository->save($movie, true);
        }

        $timezone = $user->getTimezone() ?? 'Europe/Paris';
        $now = $this->dateService->getNowImmutable($timezone);
        $userMovie = new UserMovie($user, $movie, $now);
        $this->userMovieRepository->save($userMovie, true);

        return ['userMovie' => $userMovie, 'alreadyAdded' => false];
    }

    /**
     * Filtres de la page "Mes films" enregistrés par l'utilisateur (ou ceux par défaut)
     */
    public function getWatchlistFilters(User $user): array
    {
        $settings = $this->settingsRepository->findOneBy(['user' => $user, 'name' => 'my movies']);
        return $settings ? $settings->getData() : self::WATCHLIST_DEFAULT_FILTERS;
    }

    /**
     * Cartes des films de la page "Mes films" (cf. _blocks/movie/_watchlist.html.twig)
     */
    public function getWatchlistCards(User $user, array $filters): array
    {
        return array_map(function ($movie) {
            $this->imageService->saveImage("posters", $movie['posterPath'], $this->imageConfiguration->getUrl('poster_sizes', 5), '/movies/');
            return $movie;
        }, $this->movieRepository->getMovieCards($user, $filters));
    }

    public function getCollection(array $tmdbMovie): ?MovieCollection
    {
        $movieCollection = null;
        if (key_exists('belongs_to_collection', $tmdbMovie)) {
            $collection = $tmdbMovie['belongs_to_collection'];
            if ($collection) {
                $collectionId = $collection['id'];
                $movieCollection = $this->movieCollectionRepository->findOneBy(['tmdbId' => $collectionId]);
                if (!$movieCollection) {
                    $movieCollection = new MovieCollection();
                    $movieCollection->setTmdbId($collectionId);
                    $movieCollection->setName($collection['name']);
                    $movieCollection->setPosterPath($collection['poster_path']);
                    $this->movieCollectionRepository->save($movieCollection, true);
                    $movieCollection = $this->movieCollectionRepository->findOneBy(['tmdbId' => $collectionId]);
                }
            }
        }
        return $movieCollection;
    }

    public function checkMovieInfos(string $title, array $tmdbMovie, Movie $movie, string $region, bool $isCommand = false): bool
    {
        $updated = false;
        $tmdbReleaseDate = $this->getReleaseDate($tmdbMovie, $region);
        if ($tmdbReleaseDate) {
            $tmdbMovie['release_date'] = $tmdbReleaseDate;
        }

        // checking "original_language", "original_title", "overview", "release_date",
        //          "runtime", "status", "tagline", "title", "vote_average", "vote_count"
        $fields = ['original_language', 'original_title', 'overview', 'release_date',
            'runtime', 'status', 'tagline', 'title', 'vote_average', 'vote_count'];
        foreach ($fields as $field) {
            // snake case to camel case
            $ccField = lcfirst(str_replace('_', '', ucwords($field, '_')));
            $getter = 'get' . ucfirst($ccField);
            $setter = 'set' . ucfirst($ccField);
            $tmdbValue = $tmdbMovie[$field];
            $dbValue = $movie->$getter();
            if ($field == 'release_date') {
                $dbValue = $dbValue?->format('Y-m-d');
            }
            if ($isCommand) $this->messages[] = '[' . $title . '] ' . $field . ': ' . $dbValue . ' / ' . $tmdbValue;
            if ($tmdbValue !== $dbValue) {
                if ($isCommand) $this->messages[] = '[' . $title . '] ' . 'Updating ' . $field;
                if ($field == 'release_date') {
                    $tmdbValue = $this->dateService->newDateFromUTC($tmdbValue, true);
                }
                $movie->$setter($tmdbValue);
                $updated = true;
            }
        }
        // Checking "origin_country": compare with $movie->getOriginCountry() and update if necessary
        $tmdbCountries = $tmdbMovie['origin_country'];
        $dbCountries = $movie->getOriginCountry();
        if ($isCommand) $this->messages[] = '[' . $title . '] ' . 'Origin country: ' . implode(', ', $dbCountries) . ' / ' . implode(', ', $tmdbCountries);
        if (array_diff($tmdbCountries, $dbCountries)) {
            if ($isCommand) $this->messages[] = '[' . $title . '] ' . 'Updating origin country';
            $movie->setOriginCountry($tmdbCountries);
            $updated = true;
        }

        return $updated;
    }

    public function getReleaseDate(array $tmdbMovie, string $region): ?string
    {
        $release_dates = $tmdbMovie['release_dates'];
        // "release_dates" => array:1 [▼
        //    "results" => array:234 [▼
        //      0 => array:2 [▼
        //        "iso_3166_1" => "AD"
        //        "release_dates" => array:1 "iso_3166_1": "FR",
        //        "release_dates": [
        //            "certification": "",
        //            "descriptors": [],
        //            "iso_639_1": "",
        //            "note": "Netflix",
        //        →   "release_date": "2025-03-14T00:00:00.000Z", ←
        //            "type": 4
        //        ]
        //      ]
        //      ...
        //    ]
        //  ]
        $regionReleaseDate = array_find($release_dates['results'], fn($i) => $i['iso_3166_1'] === $region);

        if ($regionReleaseDate) {
            $releaseDate = substr($regionReleaseDate['release_dates'][0]['release_date'], 0, 10);
            return $releaseDate;
        }
        return null;
    }

    public function imageInArray(string $image, array $images): bool
    {
        return array_any($images, fn($i) => $i->getImagePath() === $image);
    }

    public function fileExists(string $path): bool
    {
        return file_exists($path) && is_file($path);
    }

    public function getMessages(): array
    {
        return $this->messages;
    }
}