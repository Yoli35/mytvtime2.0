<?php

namespace App\Command;

use App\Controller\SeriesController;
use App\Entity\FilmingLocation;
use App\Entity\FilmingLocationImage;
use App\Repository\FilmingLocationImageRepository;
use App\Repository\FilmingLocationRepository;
use App\Repository\SeriesRepository;
use App\Service\DateService;
use App\Service\ImageService;
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
    description: 'Set createdAt & updatedAt fields in filming_location and filming_location_image tables',
)]
class SeriesFilmingLocationCommand extends Command
{
    private SymfonyStyle $io;
    private float $t0;

    public function __construct(
        private readonly DateService                    $dateService,
        private readonly EntityManagerInterface         $entityManager,
        private readonly FilmingLocationImageRepository $filmingLocationImageRepository,
        private readonly FilmingLocationRepository      $filmingLocationRepository,
        private readonly ImageService                   $imageService,
        private readonly SeriesController               $seriesController,
        private readonly SeriesRepository               $seriesRepository,
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
        $rootDir = $this->seriesController->getProjectDir() . '/public';
        $imagePath = $rootDir . '/images/map';

        foreach ($allSeries as $series) {

            $tmdbId = $series->getTmdbId();
            $filmingLocations = $this->seriesController->getFilmingLocations($tmdbId);

            if (empty($filmingLocations)) {
                continue;
            }
            $this->io->write(sprintf('Series (%d): %s', $series->getId(), $series->getName()));
            $localizedName = $series->getLocalizedName('fr');
            if ($localizedName) {
                $this->io->writeln(' - ' . $localizedName->getName());
            } else {
                $this->io->newLine();
            }

            foreach ($filmingLocations as $filmingLocation) {
                if ($filmingLocation['created_at']) {
                    continue;
                }

                foreach ($filmingLocation['filmingLocationImages'] as &$image) {
                    $path = $imagePath . $image['path'];
                    if (file_exists($path)) {
                        $image['date'] = date("Y-m-d H:i:s.", filemtime($path));

                        $fliDB = $this->filmingLocationImageRepository->findOneBy(['id' => $image['id']]);
                        if ($fliDB) {
                            $fliDB->setCreatedAt($this->dateService->newDateImmutable($image['date'], 'Europe/Paris'));
                            $this->filmingLocationImageRepository->save($fliDB);
                        }
                    }
                }
                // trier les images par date
                usort($filmingLocation['filmingLocationImages'], function ($a, $b) {
                    return $a['date'] <=> $b['date'];
                });
                $firstDate = $filmingLocation['filmingLocationImages'][0]['date'];
                $lastDate = $filmingLocation['filmingLocationImages'][count($filmingLocation['filmingLocationImages']) - 1]['date'];

                $flDb = $this->filmingLocationRepository->findOneBy(['id' => $filmingLocation['id']]);
                if ($flDb) {
                    $flDb->setCreatedAt($this->dateService->newDateImmutable($firstDate, 'Europe/Paris'));
                    $flDb->setUpdatedAt($this->dateService->newDateImmutable($lastDate, 'Europe/Paris'));
                    $this->filmingLocationRepository->save($flDb);
                }
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

    public function commandStart(): void
    {
        $now = $this->dateService->newDateImmutable('now', 'Europe/Paris');
        $this->io->newLine(2);
        $this->io->title('Series origin country update Command');
        $this->io->writeln('Series origin country update Command started at ' . $now->format('Y-m-d H:i:s'));
        $this->io->newLine();

        $this->t0 = microtime(true);
    }

    public function commandEnd(int $count): void
    {
        $this->io->newLine();
        $this->io->writeln(sprintf('Series updated: %d', $count));

        $t1 = microtime(true);
        $now = $this->dateService->newDateImmutable('now', 'Europe/Paris');
        $this->io->writeln('Series origin country update Command ended at ' . $now->format('Y-m-d H:i:s'));
        $this->io->writeln(sprintf('Execution time: %.2f seconds', ($t1 - $this->t0)));
        $this->io->newLine(2);
    }
}
