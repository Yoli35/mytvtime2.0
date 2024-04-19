<?php

namespace App\Command;

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

#[AsCommand(
    name: 'app:series:update',
    description: 'Update series infos for all series or a specific one',
)]
class SeriesUpdateCommand extends Command
{
    private SymfonyStyle $io;
    private float $t0;

    public function __construct(
        private readonly DateService            $dateService,
        private readonly EntityManagerInterface $entityManager,
        private readonly SeriesRepository       $seriesRepository,
        private readonly TMDBService            $tmdbService,
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
        $endedSeriesStatus = ['Ended', 'Canceled'];
        $this->io = new SymfonyStyle($input, $output);

        $seriesId = $input->getOption('series');

        if (!$seriesId) {
            $allSeries = $this->seriesRepository->findAll();
        } else {
            $allSeries = $this->seriesRepository->findBy(['id' => $seriesId]);
        }

        $this->commandStart();

        $count = 0;
        foreach ($allSeries as $series) {

            $this->io->writeln(sprintf('Series (%d): %s', $series->getId(), $series->getName()));
            $localizedName = $series->getLocalizedName('fr');
            if ($localizedName) {
                $this->io->writeln('Localized name: ' . $localizedName->getName());
            }
            if (in_array($series->getStatus(), $endedSeriesStatus)) {
                $this->io->writeln('Serie is ended or canceled, skipping seasons update');
                continue;
            }
            $tv = json_decode($this->tmdbService->getTv($series->getTmdbId(), 'en-US'), true);

            if ($tv === null) {
                $this->io->error('Error while fetching TV show');
                continue;
            }

            $series->setStatus($tv['status']);
            if ($tv['next_episode_to_air'] && $tv['next_episode_to_air']['air_date']) {
                $date = $this->dateService->newDateImmutable($tv['next_episode_to_air']['air_date'], 'Europe/Paris');
            }
            else
                $date = null;
            $series->setNextEpisodeAirDate($date);

            $this->entityManager->persist($series);
            if ($count++ % 10 == 0) {
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
        $this->io->title('Series update Command');
        $this->io->writeln('Series update Command started at ' . $now->format('Y-m-d H:i:s'));
        $this->t0 = microtime(true);
    }

    public function commandEnd(int $count): void
    {
        $this->io->writeln(sprintf('Series updated: %d', $count));
        $t1 = microtime(true);
        $now = $this->dateService->newDateImmutable('now', 'Europe/Paris');
        $this->io->writeln('Series update Command ended at ' . $now->format('Y-m-d H:i:s'));
        $this->io->writeln(sprintf('Execution time: %.2f seconds', ($t1 - $this->t0)));
        $this->io->newLine(2);
    }
}
