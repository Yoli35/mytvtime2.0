<?php

namespace App\Command;

use App\Controller\SeriesController;
use App\Repository\SeriesImageRepository;
use App\Repository\SeriesRepository;
use App\Service\DateService;
use App\Service\ImageConfiguration;
use App\Service\ImageService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:images:reload',
    description: 'Reload images (posters, backdrops, logos) from TMDB',
)]
class reloadImages extends Command
{
    private SymfonyStyle $io;
    private float $t0;

    public function __construct(
        private readonly DateService           $dateService,
        private readonly ImageConfiguration    $imageConfiguration,
        private readonly ImageService          $imageService,
        private readonly SeriesController      $seriesController,
        private readonly SeriesImageRepository $seriesImageRepository,
        private readonly SeriesRepository      $seriesRepository,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $this->commandStart();

        $seriesAll = $this->seriesRepository->findBy([], ['id' => 'DESC'], 10000, 0);

        $root = $this->seriesController->getProjectDir() . '/public';
        $this->io->writeln('Project directory: ' . $root);
        $this->io->newLine();

        $seriesArr = [];
        $dbImageCount = [
            'posters' => 0,
            'backdrops' => 0,
            'logos' => 0,
            'total' => 0,
            'saved posters' => 0,
            'saved backdrops' => 0,
            'saved logos' => 0,
            'saved total' => 0,
            'removed posters' => 0,
            'removed backdrops' => 0,
            'removed logos' => 0,
            'removed total' => 0,
        ];
        $sizes = ['backdrops' => 3, 'logos' => 5, 'posters' => 5];

        foreach ($seriesAll as $series) {
            $seriesId = $series->getId();
            $seriesName = $series->getName();
            $localizedNames = $series->getLocalizedName('fr');
            if ($localizedNames) {
                $seriesName = $seriesName . ' - ' . $localizedNames->getName();
            }
            $tmdbId = $series->getTmdbId();

            $this->io->writeln(sprintf('Series %d: %s', $seriesId, $seriesName));
            $seriesArr[$seriesId] = ['id' => $seriesId, 'tmdbId' => $tmdbId, 'name' => $seriesName, 'backdrop' => [], 'poster' => [], 'logo' => []];

            $seriesImages = $series->getSeriesImages();

            foreach ($seriesImages as $seriesImage) {
                $type = $seriesImage->getType();
                $types = $seriesImage->getType() . 's';
                $imagePath = $seriesImage->getImagePath();
                $seriesArr[$seriesId][$type][] = $imagePath;
                $dbImageCount[$types]++;
                $dbImageCount['total']++;
                $localFileExists = $this->fileExists($root . '/series/' . $types . $imagePath);
                $this->io->writeln(sprintf('  %s: %s → %s', $type, $imagePath, $localFileExists ? 'OK' : 'KO'));
                if (!$localFileExists) {
                    $imageConfigType = $type . '_sizes';
                    $url = $this->imageConfiguration->getUrl($imageConfigType, $sizes[$types]);
                    $this->imageService->saveImage($types, $imagePath, $url);
                    $this->io->writeln(sprintf('  %s: %s → %s', $type, $url . $imagePath, '/series/' . $types . $imagePath));
                    $localFileExists = $this->fileExists($root . '/series/' . $types . $imagePath);
                    if ($localFileExists) {
                        $this->io->success(sprintf('  %s: %s → OK', $type, $imagePath));
                        $dbImageCount['saved ' . $types]++;
                        $dbImageCount['saved total']++;
                    } else {
                        $this->seriesImageRepository->remove($seriesImage);
                        $this->io->warning(sprintf('  %s: %s → reference removed', $type, $imagePath));
                        $dbImageCount['removed ' . $types]++;
                        $dbImageCount['removed total']++;
                    }
                }
            }
            $this->io->newLine();
        }

        $this->commandEnd($dbImageCount);

        return Command::SUCCESS;
    }

    public function fileExists(string $path): bool
    {
        return file_exists($path) && is_file($path);
    }

    public function commandStart(): void
    {
        $now = $this->dateService->newDateImmutable('now', 'Europe/Paris');
        $this->io->newLine(2);
        $this->io->title('Images reload Command');
        $this->io->writeln('Images reload Command started at ' . $now->format('Y-m-d H:i:s'));
        $this->t0 = microtime(true);
    }

    public function commandEnd(array $images): void
    {
        $this->io->newLine(2);
        $this->io->writeln(sprintf('Images checked: %d', $images['total']));
        $this->io->writeln(sprintf('Images added: %d', $images['saved total']));
        $this->io->writeln(sprintf('Images removed: %d', $images['removed total']));
        $this->io->newLine();

        $this->io->writeln(sprintf('Posters: %d → %d', $images['posters'], $images['saved posters']));
        $this->io->writeln(sprintf('Backdrops: %d → %d', $images['backdrops'], $images['saved backdrops']));
        $this->io->writeln(sprintf('Logos: %d → %d', $images['logos'], $images['saved logos']));
        $this->io->newLine();

        $this->io->writeln(sprintf('Posters: %d', $images['removed posters']));
        $this->io->writeln(sprintf('Backdrops: %d', $images['removed backdrops']));
        $this->io->writeln(sprintf('Logos: %d', $images['removed logos']));
        $this->io->newLine();

        $t1 = microtime(true);
        $now = $this->dateService->newDateImmutable('now', 'Europe/Paris');
        $this->io->writeln('Images reload Command ended at ' . $now->format('Y-m-d H:i:s'));
        $this->io->writeln(sprintf('Execution time: %.2f seconds', ($t1 - $this->t0)));
        $this->io->newLine(2);
    }
}
