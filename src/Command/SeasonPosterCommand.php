<?php

namespace App\Command;

use App\Repository\SeriesRepository;
use App\Service\DateService;
use App\Service\ImageConfiguration;
use App\Service\SeasonService;
use App\Service\TMDBService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:season:poster',
    description: 'Update season poster for series',
)]
class SeasonPosterCommand
{
    private SymfonyStyle $io;
    private float $t0;

    public function __construct(
        private readonly DateService                       $dateService,
        private readonly ImageConfiguration                $imageConfiguration,
        private readonly SeasonService                     $seasonService,
        private readonly SeriesRepository                  $seriesRepository,
        private readonly TMDBService                       $tmdbService,
    )
    {
    }

    public function __invoke(SymfonyStyle $io, #[Option(shortcut: 's')] ?int $seriesId = null, #[Option(shortcut: 'o')] ?int $offset = null, #[Option(shortcut: 'y')] int $yearOnly = 0, #[Option(shortcut: 'f')] bool $force = false): int
    {
        $this->io = $io;

        if (!$seriesId) {
            $allSeries = $this->seriesRepository->findAll();
        } else {
            $allSeries = $this->seriesRepository->findBy(['id' => $seriesId]);
        }

        $this->commandStart();
        $posterUrl = $this->imageConfiguration->getUrl('poster_sizes', 5);
        $n = 0;

        foreach ($allSeries as $series) {
            if ($offset && $series->getId() < $offset) {
                continue;
            }
            $firstAirDate = $series->getFirstAirDate();
            $year = intval($firstAirDate?->format('Y'));
            if ($yearOnly > 0) {
                if ($year && $year != $yearOnly ) {
                    continue;
                }
            }
            $this->io->writeln(sprintf('Series: %s (%d)', $series->getName(), $series->getId()));
            $n++;
            $seriesId = $series->getId();
            $tvId = $series->getTmdbId();
            $tv = json_decode($this->tmdbService->getTv($tvId, 'en-US'), true);
            if (key_exists('error', $tv)) {
                $this->io->error(sprintf('Error fetching TV data for series %s (%d): %s', $series->getName(), $series->getId(), $tv['error']));
                continue;
            }
            $this->seasonService->posters($tv['seasons'], $seriesId, $tvId, $posterUrl);
        }

        $this->io->newLine(2);
        $this->io->writeln(sprintf('Series checked: %d / %d', $n, count($allSeries)));

        $this->commandEnd();

        return Command::SUCCESS;
    }

    public function commandStart(): void
    {
        $now = $this->dateService->newDateImmutable('now', 'Europe/Paris');
        $this->io->newLine(2);
        $this->io->title('📺 Season poster Command');
        $this->io->writeln('Command started at ' . $now->format('Y-m-d H:i:s'));
        $this->t0 = microtime(true);
    }

    public function commandEnd(): void
    {
        $t1 = microtime(true);
        $now = $this->dateService->newDateImmutable('now', 'Europe/Paris');
        $this->io->newLine(2);
        $this->io->writeln('Command ended at ' . $now->format('Y-m-d H:i:s'));
        $this->io->writeln(sprintf('Execution time: %.2f seconds', ($t1 - $this->t0)));
        $this->io->newLine(2);
    }
}
