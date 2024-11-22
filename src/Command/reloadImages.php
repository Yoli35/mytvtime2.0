<?php

namespace App\Command;

use App\Entity\Network;
use App\Entity\Series;
use App\Entity\WatchProvider;
use App\Repository\NetworkRepository;
use App\Repository\SeriesImageRepository;
use App\Repository\SeriesRepository;
use App\Repository\WatchProviderRepository;
use App\Service\DateService;
use App\Service\TMDBService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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
        private readonly SeriesRepository      $seriesRepository,
        private readonly SeriesImageRepository $seriesImageRepository,
        private readonly TMDBService           $tmdbService,
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

        $seriesAll = $this->seriesRepository->findBy([], ['id' => 'DESC'], 10, 0);

        $seriesArr = [];
        $tmdbs = [];
        $count = 0;
        $dbImageCount = ['poster' => 0, 'backdrop' => 0, 'logo' => 0, 'total' => 0];
        $tmdbsImageCount = ['poster' => 0, 'backdrop' => 0, 'logo' => 0, 'total' => 0];

//        $projectDirectory = $this->getParameter('kernel.project_dir');

        foreach ($seriesAll as $series) {
            $seriesId = $series->getId();
            $seriesName = $series->getName();
            $localizedNames = $series->getLocalizedName('fr');
            if ($localizedNames) {
                $seriesName = $seriesName . ' - ' . $localizedNames->getName();
            }
            $tmdbId = $series->getTmdbId();

            $this->io->writeln(sprintf('Series %d: %s', $seriesId, $seriesName));
            $count++;
            $seriesArr[$seriesId] = ['id' => $seriesId, 'tmdbId' => $tmdbId, 'name' => $seriesName, 'backdrop' => [], 'poster' => [], 'logo' => []];

            $seriesImages = $series->getSeriesImages();

            foreach ($seriesImages as $seriesImage) {
                $type = $seriesImage->getType();
                $imagePath = $seriesImage->getImagePath();
                $seriesArr[$seriesId][$type][] = $imagePath;
                $dbImageCount[$type]++;
                $dbImageCount['total']++;
                $this->io->writeln(sprintf('  %s: %s → %s', $type, $imagePath, $this->fileExists('/series/' . $type . $imagePath) ? 'OK' : 'KO'));
            }
            $this->io->newLine();

            $addEnglish = $localizedNames != null;

            $tmdb = json_decode($this->tmdbService->getTvImages($tmdbId, $addEnglish), true);
            $tmdbs[$seriesId] = ['id' => $tmdbId, 'name' => $seriesName, 'language' => $addEnglish ? 'fr,en' : 'fr', 'backdrops' => [], 'posters' => [], 'logos' => []];
            if (!$tmdb) {
                $this->io->error('TMDB error');
                continue;
            }
            foreach ($tmdb['posters'] as $poster) {
                $tmdbs[$seriesId]['posters'][] = $poster['file_path'];
            }
            foreach ($tmdb['backdrops'] as $backdrop) {
                $tmdbs[$seriesId]['backdrops'][] = $backdrop['file_path'];
            }
            foreach ($tmdb['logos'] as $logo) {
                $tmdbs[$seriesId]['logos'][] = $logo['file_path'];
            }
            $tmdbsImageCount['poster'] += count($tmdbs[$seriesId]['posters']);
            $tmdbsImageCount['backdrop'] += count($tmdbs[$seriesId]['backdrops']);
            $tmdbsImageCount['logo'] += count($tmdbs[$seriesId]['logos']);
            $tmdbsImageCount['total'] += count($tmdbs[$seriesId]['posters']) + count($tmdbs[$seriesId]['backdrops']) + count($tmdbs[$seriesId]['logos']);
        }
        /*dump([
            'count' => $count,
            'dbImageCount' => $dbImageCount,
            'tmdbsImageCount' => $tmdbsImageCount,
            'seriesArr' => $seriesArr,
            'tmdbs' => $tmdbs,
        ]);*/
        /*foreach ($seriesIds as $seriesId) {
            $s = $series[$seriesId];
            $t = $tmdbs[$seriesId];
            dump([
                'id' => $seriesId,
                'series' => $s,
                'tmdbs' => $t,
            ]);
        }*/

        $newCount = 0;
        $deletedCount = 0;

        $this->commandEnd($newCount, $deletedCount);

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

    public function commandEnd(int $newCount, int $deletedCount): void
    {
        $this->io->newLine(2);
        $this->io->writeln(sprintf('Images added: %d', $newCount));
        $this->io->writeln(sprintf('Image references deleted: %d', $deletedCount));

        $t1 = microtime(true);
        $now = $this->dateService->newDateImmutable('now', 'Europe/Paris');
        $this->io->writeln('Images reload Command ended at ' . $now->format('Y-m-d H:i:s'));
        $this->io->writeln(sprintf('Execution time: %.2f seconds', ($t1 - $this->t0)));
        $this->io->newLine(2);
    }
}
