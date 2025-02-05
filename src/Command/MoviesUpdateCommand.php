<?php

namespace App\Command;

use App\Entity\Movie;
use App\Entity\MovieCollection;
use App\Entity\MovieImage;
use App\Repository\MovieCollectionRepository;
use App\Repository\MovieImageRepository;
use App\Repository\MovieRepository;
use App\Service\DateService;
use App\Service\ImageConfiguration;
use App\Service\ImageService;
use App\Service\TMDBService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:movie:update',
    description: 'Update movie infos for all movie or a specific one',
)]
class MoviesUpdateCommand extends Command
{
    private SymfonyStyle $io;
    private float $t0;
    private string $root;
    /**
     * @var array|int[]
     */
    private array $sizes;

    public function __construct(
        private readonly DateService               $dateService,
        private readonly EntityManagerInterface    $entityManager,
        private readonly ImageConfiguration        $imageConfiguration,
        private readonly ImageService              $imageService,
        private readonly MovieCollectionRepository $movieCollectionRepository,
        private readonly MovieImageRepository      $movieImageRepository,
        private readonly MovieRepository           $movieRepository,
        private readonly TMDBService               $tmdbService,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('movie', 'm', InputOption::VALUE_REQUIRED, 'Movie ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $movieId = $input->getOption('movie');

        // Demander la langue souhaitée (par défaut fr)
        // Demander la région souhaitée (par défaut FR)
        // Demander la timezone souhaitée (par défaut Europe/Paris)
        $locale = $this->io->ask('Language', 'fr');
        $region = $this->io->ask('Region', 'FR');
        $timezone = $this->io->ask('Timezone', 'Europe/Paris');
        $language = $locale . '-' . $region;

        $this->root = $this->imageService->getProjectDir() . '/public';
        $this->sizes = ['backdrop' => 3, 'logo' => 5, 'poster' => 5];

        if (!$movieId) {
            $allMovies = $this->movieRepository->findAll();
        } else {
            $allMovies = $this->movieRepository->findBy(['id' => $movieId]);
        }
        $movieCount = min(10000, count($allMovies));
        $startIndex = 1450;
        $now = $this->dateService->newDateImmutable('now', $timezone);

        $this->commandStart();

        $count = 0;
        /** @var Movie $movie */
//        foreach ($allMovies as $movie) {
        for ($index = $startIndex; $index < $movieCount; $index++) {
            $this->io->newLine();
            $movie = $allMovies[$index];

            $title = $movie->getTitle();
            $this->io->writeln(sprintf('Movie (%d/%d): %s', $movie->getId(), $movie->getTmdbId(), $movie->getTitle()));
            $localizedName = $movie->getMovieLocalizedName('fr');
            if ($localizedName) {
                $this->io->writeln('Localized name: ' . $localizedName->getName());
                $title = $localizedName->getName();
            }

            /*if ($movieCount > 1) {
                $lastUpdate = $movie->getUpdatedAt();
                if ($lastUpdate) {
                    $diff = $now->diff($lastUpdate);
                    if ($diff->days < 7) {
                        $this->io->writeln('[' . $title . '] ' . 'Movie updated less than a week ago, skipping');
                        continue;
                    }
                }
            }*/
            $m = json_decode($this->tmdbService->getMovie($movie->getTmdbId(), $language), true);

            if ($m === null) {
                $this->io->error('Error while fetching TV show');
                continue;
            }

            $updated = $this->checkMovieImage($title, $m, $movie, 'backdrop');
            $updated = $this->checkMovieImage($title, $m, $movie, 'poster') || $updated;

            $updated = $this->checkBelongsToCollection($title, $m, $movie) || $updated;

            $updated = $this->checkMovieInfos($title, $m, $movie) || $updated;

            if ($updated) {
                $movie->setUpdatedAt($now);
                $this->movieRepository->save($movie);
            }

            $count++;
            if ($count % 10 === 0) {
                $this->entityManager->flush();
            }
        }
        $this->entityManager->flush();

        $this->commandEnd($count);

        return Command::SUCCESS;
    }

    public function checkMovieImage(string $title, array $tmdbMovie, Movie $movie, string $imageType): bool
    {
        $movieImages = array_filter($movie->getMovieImages()->toArray(), fn($i) => $i->getType() === $imageType);

        $updated = false;
        $tmdbImage = $tmdbMovie[$imageType . '_path'];
        $getter = 'get' . ucfirst($imageType) . 'Path';
        $setter = 'set' . ucfirst($imageType) . 'Path';
        $image = $movie->$getter();
        $this->io->writeln('[' . $title . '] ' . 'Current / actual poster: ' . $image . ' / ' . $tmdbImage);
        if ($tmdbImage !== $image) {
            $this->io->writeln('[' . $title . '] ' . 'Updating ' . $imageType . ' image');
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
                $this->io->writeln('[' . $title . '] ' . 'Adding ' . $imageType . ' image: ' . $i);
                $movieImage = new MovieImage($movie, $imageType, $i);
                $movie->addMovieImage($movieImage);
                $this->movieImageRepository->save($movieImage);
                $updated = true;
            }
            if (!$this->fileExists($this->root . '/movies/' . $imageType . 's' . $i)) {
                $this->io->writeln('[' . $title . '] ' . ucfirst($imageType) . ' image does not exist');
                $url = $this->imageConfiguration->getUrl('poster_sizes', $this->sizes[$imageType]);
                if ($this->imageService->saveImage($imageType . 's', $i, $url, '/movies/')) {
                    $this->io->writeln('[' . $title . '] ' . ucfirst($imageType) . ' image saved: ' . $i);
                }
            }
        }
        return $updated;
    }

    public function checkBelongsToCollection(string $title, array $tmdbMovie, Movie $movie): bool
    {
        $updated = false;
        $tmdbCollection = $tmdbMovie['belongs_to_collection'];
        if ($tmdbCollection) {
            $tmdbCollectionId = $tmdbCollection['id'];
            $this->io->write('[' . $title . '] ' . 'Updating collection');
            $dbCollection = $this->movieCollectionRepository->findOneBy(['tmdbId' => $tmdbCollectionId]);
            if (!$dbCollection) {
                $this->io->writeln(' by creating new collection "' . $tmdbCollection['name'] . '"');
                $dbCollection = new MovieCollection();
                $save = true;
            } else {
                $this->io->writeln(' "' . $tmdbCollection['name'] . '"');
                $save = false;
            }
            $dbCollection->setTmdbId($tmdbCollectionId);
            if ($dbCollection->getName() !== $tmdbCollection['name']) {
                $this->io->writeln('[' . $title . '] ' . 'Updating collection name');
                $save = true;
            }
            $dbCollection->setName($tmdbCollection['name']);
            if ($dbCollection->getPosterPath() !== $tmdbCollection['poster_path']) {
                $this->io->writeln('[' . $title . '] ' . 'Updating collection poster');
                $save = true;
            }
            $dbCollection->setPosterPath($tmdbCollection['poster_path']);
            if ($dbCollection->getBackdropPath() !== $tmdbCollection['backdrop_path']) {
                $this->io->writeln('[' . $title . '] ' . 'Updating collection backdrop');
                $save = true;
            }
            $dbCollection->setBackdropPath($tmdbCollection['backdrop_path']);
            $this->movieCollectionRepository->save($dbCollection, $save);
            $collection = $movie->getCollection();
            $collectionId = $collection?->getTmdbId() ?? 'null';
            $this->io->writeln('[' . $title . '] ' . 'Current / actual collection: ' . $collectionId . ' / ' . $tmdbCollectionId);
            if ($tmdbCollectionId !== $collectionId) {
                $movie->setCollection($dbCollection);
                $updated = true;
            }
        } else {
            $this->io->writeln('[' . $title . '] ' . 'No collection');
            $collection = $movie->getCollection();
            if ($collection) {
                $this->io->writeln('[' . $title . '] ' . 'Removing collection');
                $movie->setCollection(null);
                $updated = true;
            }
        }
        return $updated;
    }

    public function checkMovieInfos(string $title, array $tmdbMovie, Movie $movie): bool
    {
        $updated = false;

        // checking "origin_country", "original_language", "original_title", "overview", "release_date",
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
            $this->io->writeln('[' . $title . '] ' . $field . ': ' . $dbValue . ' / ' . $tmdbValue);
            if ($tmdbValue !== $dbValue) {
                $this->io->writeln('[' . $title . '] ' . 'Updating ' . $field);
                if ($field == 'release_date') {
                    $tmdbValue = $this->dateService->newDateFromUTC($tmdbValue, true);
                }
                $movie->$setter($tmdbValue);
                $updated = true;
            }
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

    public function commandStart(): void
    {
        $now = $this->dateService->newDateImmutable('now', 'Europe/Paris');
        $this->io->newLine(2);
        $this->io->title('Movies update Command');
        $this->io->writeln('Movies update Command started at ' . $now->format('Y-m-d H:i:s'));
        $this->t0 = microtime(true);
    }

    public function commandEnd(int $count): void
    {
        $this->io->newLine(2);
        $this->io->writeln(sprintf('Movies updated: %d', $count));
        $t1 = microtime(true);
        $now = $this->dateService->newDateImmutable('now', 'Europe/Paris');
        $this->io->newLine(2);
        $this->io->writeln('Movies update Command ended at ' . $now->format('Y-m-d H:i:s'));
        $this->io->writeln(sprintf('Execution time: %.2f seconds', ($t1 - $this->t0)));
        $this->io->newLine(2);
    }
}
