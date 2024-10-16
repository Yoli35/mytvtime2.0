<?php

namespace App\Command;

use App\Controller\SeriesController;
use App\Entity\FilmingLocation;
use App\Entity\FilmingLocationImage;
use App\Repository\FilmingLocationRepository;
use App\Repository\SeriesRepository;
use App\Service\DateService;
use App\Service\TMDBService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'app:series:filming-location',
    description: 'Move series locations field to filming_location and filming_location_image tables',
)]
class SeriesFilmingLocationCommand extends Command
{
    private SymfonyStyle $io;
    private float $t0;

    public function __construct(
        private readonly DateService               $dateService,
        private readonly EntityManagerInterface    $entityManager,
        private readonly FilmingLocationRepository $filmingLocationRepository,
        private readonly SeriesController          $seriesController,
        private readonly SeriesRepository          $seriesRepository,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('series', 's', InputOption::VALUE_REQUIRED, 'Series ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $seriesId = $input->getOption('series');

        if (!$seriesId) {
            $allSeries = $this->seriesRepository->findAll();
        } else {
            $allSeries = $this->seriesRepository->findBy(['id' => $seriesId]);
        }

        $this->commandStart();

        $count = 0;
        // Root directory
        $rootDir = $this->seriesController->getRootDir() . '/public';
        foreach ($allSeries as $series) {

            $this->io->writeln(sprintf('Series (%d): %s', $series->getId(), $series->getName()));
            $localizedName = $series->getLocalizedName('fr');
            if ($localizedName) {
                $this->io->writeln('Localized name: ' . $localizedName->getName());
            }

            $locations = $series->getLocations();
            if (!$locations) {
                $this->io->writeln('No locations found');
                continue;
            }

            $locations = $locations['locations'];

            for ($i = 0; $i < count($locations); $i++) {
                $loc = $this->filmingLocationRepository->findOneBy(['uuid' => $locations[$i]['uuid']]);
                if ($loc) {
                    $this->io->writeln('Location already exists');
                    continue;
                }
                $location = $locations[$i];
                $additionalImages = $location['additional_images'] ?? [];
                $description = $location['description'];
                $images = [];
                $images[] = $location['image'];
                $images = array_merge($images, $additionalImages);
                $latitude = $location['latitude'];
                $longitude = $location['longitude'];
                $title = $location['location'];
                $uuid = $location['uuid'];
                $this->io->writeln($title);

                $filmingLocation = new FilmingLocation($uuid, $series->getTmdbId(), $title, $description, $latitude, $longitude, true);
                $this->entityManager->persist($filmingLocation);

                foreach ($images as $image) {// https://blscene.com/wp-content/uploads/2024/05/Official-Trailer-My-Love-Mix-Up-เขียนรักด้วยยางลบ-0002.webp
                    if (str_contains($image, '/images/map')) {
                        $image = str_replace('/images/map', '', $image);
                    } else {
                        // copy image to /images/map
                        // https://someurl.com/image.jpg -> /images/map/image.jpg
                        $basename = basename($image);
                        $destination = $rootDir . '/images/map/' . $basename;
                        $copied = $this->seriesController->saveImageFromUrl($image, $destination);
                        if ($copied) {
                            $this->io->writeln('Image [ ' . $image . ' ] copied to ' . $destination);
                        } else {
                            $this->io->error('Image [ ' . $image . ' ] not copied');
                        }
                        $image = '/' . $basename;
                    }
                    $filmingLocationImage = new FilmingLocationImage($filmingLocation, $image);
                    $this->entityManager->persist($filmingLocationImage);
                }
            }
            $series->setLocations(['locations' => $locations]);
//            $this->entityManager->persist($series);

            $count++;
            if ($count % 10 === 0) {
                $this->entityManager->flush();
            }
        }
        $this->entityManager->flush();

        $this->commandEnd($count);

        return Command::SUCCESS;
    }

    public function commandStart(): void
    {
        $now = $this->dateService->newDateImmutable('now', 'Europe/Paris');
        $this->io->newLine(2);
        $this->io->title('Series origin country update Command');
        $this->io->writeln('Series origin country update Command started at ' . $now->format('Y-m-d H:i:s'));
        $this->t0 = microtime(true);
    }

    public function commandEnd(int $count): void
    {
        $this->io->writeln(sprintf('Series updated: %d', $count));

        $t1 = microtime(true);
        $now = $this->dateService->newDateImmutable('now', 'Europe/Paris');
        $this->io->writeln('Series origin country update Command ended at ' . $now->format('Y-m-d H:i:s'));
        $this->io->writeln(sprintf('Execution time: %.2f seconds', ($t1 - $this->t0)));
        $this->io->newLine(2);
    }
}
