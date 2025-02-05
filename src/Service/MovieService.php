<?php

namespace App\Service;

use App\Entity\Movie;
use App\Entity\MovieCollection;
use App\Entity\MovieImage;
use App\Repository\MovieCollectionRepository;
use App\Repository\MovieImageRepository;

class MovieService
{
    private string $root;
    private array $sizes;
    private array $messages = [];

    public function __construct(
        private readonly DateService               $dateService,
        private readonly ImageConfiguration        $imageConfiguration,
        private readonly ImageService              $imageService,
        private readonly MovieCollectionRepository $movieCollectionRepository,
        private readonly MovieImageRepository      $movieImageRepository,
    )
    {
        $this->root = $this->imageService->getProjectDir() . '/public';
        $this->sizes = ['backdrop' => 3, 'logo' => 5, 'poster' => 5];
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

    public function checkMovieInfos(string $title, array $tmdbMovie, Movie $movie, bool $isCommand = false): bool
    {
        $updated = false;

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