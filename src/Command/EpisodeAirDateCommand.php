<?php

namespace App\Command;

use App\Entity\UserEpisode;
use App\Repository\SeriesRepository;
use App\Repository\UserEpisodeRepository;
use App\Repository\UserSeriesRepository;
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
    name: 'app:episode:air-date',
    description: 'Update user episode air date for all series or a specific one',
)]
class EpisodeAirDateCommand extends Command
{
    private SymfonyStyle $io;
    private float $t0;

    public function __construct(
        private readonly DateService            $dateService,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserEpisodeRepository  $userEpisodeRepository,
        private readonly UserSeriesRepository   $userSeriesRepository,
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
            $allUserSeries = $this->userSeriesRepository->findAll();
        } else {
            $allUserSeries = $this->userSeriesRepository->findBy(['id' => $seriesId]);
        }

        $this->commandStart();

        $count = 0;
        $episodeCount = 0;
        $newEpisodeCount = 0;
        $endedSeriesStatus = ['Ended', 'Canceled'];

        foreach ($allUserSeries as $userSeries) {
            $series = $userSeries->getSeries();
            $user = $userSeries->getUser();
            $language = $user->getPreferredLanguage() ?? "fr" . "-" . $user->getCountry() ?? "FR";
            $episodeUpdates = 0;

            $line = sprintf('User %s - Series (%d): %s', $user->getUsername(), $series->getId(), $series->getName());
            $localizedName = $series->getLocalizedName('fr');
            if ($localizedName) {
                $line .= ' - Localized name: ' . $localizedName->getName();
            }
            if ($userSeries->getProgress() == 100) {
                $line .= 'Series already updated';//' - Serie is finished';
                $this->io->writeln($line);
                continue;
            }
            if ($series->getStatus() && in_array($series->getStatus(), $endedSeriesStatus)) {
                $line .= ' - Serie is finished';
                $this->io->writeln($line);
                continue;
            }
            $this->io->writeln($line);

            $tv = json_decode($this->tmdbService->getTv($series->getTmdbId(), $language), true);
            if ($tv === null) {
                $this->io->error('Error while fetching TV show');
                continue;
            }
            $userEpisodes = $this->userEpisodeRepository->findBy(['userSeries' => $userSeries]);
            foreach ($tv['seasons'] as $season) {
                $seasonNumber = $season['season_number'];
                $tvSeason = json_decode($this->tmdbService->getTvSeason($series->getTmdbId(), $seasonNumber, $language), true);
                $episodes = $tvSeason['episodes'];
                if (!count($episodes)) {
                    $this->io->error('No episodes for season ' . $seasonNumber);
                    continue;
                }
                foreach ($episodes as $episode) {
                    $episodeId = $episode['id'];
                    $episodeNumber = $episode['episode_number'];
                    $airDate = $episode['air_date'] ? $this->dateService->newDateImmutable($episode['air_date'], $user->getTimezone() ?? 'Europe/Paris') : null;
                    $userEpisode = $this->getUserEpisode($userEpisodes, $seasonNumber, $episodeNumber);
                    if (!$userEpisode) {
                        $userEpisode = new UserEpisode($user, $userSeries, $episodeId, $seasonNumber, $episodeNumber, null);
                        $newEpisodeCount++;
                    }
                    if ($userEpisode->getAirDate() != $airDate) {
                        $userEpisode->setAirDate($airDate);
                        $this->userEpisodeRepository->save($userEpisode);
                        $episodeUpdates++;
                    }
                    $episodeCount++;
                }
            }
            if ($episodeUpdates > 0) {
                $this->entityManager->flush();
            }
        }

        $this->io->writeln(sprintf('Episodes: %d', $episodeCount));
        $this->io->writeln(sprintf('New episodes: %d', $newEpisodeCount));

        $this->commandEnd();

        return Command::SUCCESS;
    }

    public function getUserEpisode($userEpisodes, $seasonNUmber, $episodeNumber): mixed
    {
        foreach ($userEpisodes as $userEpisode) {
            if ($userEpisode->getSeasonNumber() == $seasonNUmber && $userEpisode->getEpisodeNumber() == $episodeNumber) {
                return $userEpisode;
            }
        }
        return null;
    }

    public function commandStart(): void
    {
        $now = $this->dateService->newDateImmutable('now', 'Europe/Paris');
        $this->io->newLine(2);
        $this->io->title('Episode air date Command');
        $this->io->writeln('Command started at ' . $now->format('Y-m-d H:i:s'));
        $this->t0 = microtime(true);
    }

    public function commandEnd(): void
    {
        $t1 = microtime(true);
        $now = $this->dateService->newDateImmutable('now', 'Europe/Paris');
        $this->io->writeln('Command ended at ' . $now->format('Y-m-d H:i:s'));
        $this->io->writeln(sprintf('Execution time: %.2f seconds', ($t1 - $this->t0)));
        $this->io->newLine(2);
    }
}
