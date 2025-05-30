<?php

namespace App\Command;

use App\Repository\SeriesRepository;
use App\Repository\UserEpisodeRepository;
use App\Repository\UserRepository;
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
    name: 'app:series:binge-watched',
    description: 'Verify if a series has been binge-watched by a user',
)]
class SeriesHasBeenBingeWatchedCommand extends Command
{
    private SymfonyStyle $io;
    private float $t0;

    public function __construct(
        private readonly DateService            $dateService,
        private readonly EntityManagerInterface $entityManager,
        private readonly SeriesRepository       $seriesRepository,
        private readonly TMDBService            $tmdbService,
        private readonly UserRepository         $userRepository,
        private readonly UserEpisodeRepository  $userEpisodeRepository,
        private readonly UserSeriesRepository   $userSeriesRepository,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('user', 'u', InputOption::VALUE_REQUIRED, 'User ID')
            ->addOption('series', 's', InputOption::VALUE_REQUIRED, 'Series ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $userId = $input->getOption('user');
        $seriesId = $input->getOption('series');

        if (!$userId) {
            $userId = $this->io->ask('User\'s Id');
            if (!$userId) {
                $this->io->error('User\'s Id is required');
                return Command::FAILURE;
            }
        }

        if (!$seriesId) {
            $seriesId = $this->io->ask('Series\'s Id');
            if (!$seriesId) {
                $this->io->error('Series\'s Id is required');
                return Command::FAILURE;
            }
        }

        $this->commandStart();

        $user = $this->userRepository->find($userId);
        $series = $this->seriesRepository->find($seriesId);
        $this->io->writeln(sprintf('User (%d): %s', $user->getId(), $user->getUsername()));
        $this->io->writeln(sprintf('Series (%d): %s', $seriesId, $series->getName()));
        $localizedName = $series->getLocalizedName($user->getPreferredLanguage() ?? 'fr');
        if ($localizedName) {
            $this->io->writeln('Localized name: ' . $localizedName->getName());
        }
        $tv = json_decode($this->tmdbService->getTv($series->getTmdbId(), 'en-US'), true);

        $userSeries = $this->userSeriesRepository->findOneBy(['user' => $user, 'series' => $series]);
        $userSeriesId = $userSeries->getId();
        $userEpisodes = $this->userEpisodeRepository->findBy(['user' => $user], ['id' => 'DESC']);

        $previousUserEpisode = null;
        $previousSeasonNumber = 0;
        $episodeCount = 0;
        $interruptions = 0;
        $otherSeriesEpisodes = 0;
        foreach ($userEpisodes as $userEpisode) {
            $currentUserSeriesId = $userEpisode->getUserSeries()->getId();
            if (!$previousUserEpisode) {
                if ($currentUserSeriesId == $userSeriesId) {
                    $previousUserEpisode = $userEpisode;
                    $previousSeasonNumber = $userEpisode->getSeasonNumber();
                    $episodeCount++;
                }
                continue;
            }

            if ($currentUserSeriesId != $userSeriesId
                || $previousUserEpisode->getSeasonNumber() != $userEpisode->getSeasonNumber()) {
                $interruptions++;
                $otherSeriesEpisodes++;
                // We leave a margin of two episodes of another series
                if ($otherSeriesEpisodes > 2) {
                    break;
                }
                continue;
            } else {
                if ($interruptions > 0) {
                    $this->io->warning('Interruption detected before episode ' . $previousUserEpisode->getEpisodeNumber());
                    $interruptions = 0;
                    $otherSeriesEpisodes = 0;
                }
            }
            $previousUserEpisode = $userEpisode;
            $previousSeasonNumber = $userEpisode->getSeasonNumber();
            $episodeCount++;
        }
        $tvSeason = $this->getTvSeason($tv, $previousSeasonNumber);

        $theoreticalEpisodeCount = $tvSeason['episode_count'] ??0;

        if ($episodeCount == $theoreticalEpisodeCount) {
            $this->io->success('All episodes seen in one go! Binge watched series!');
            $userSeries->setBinge(true);
            $this->entityManager->persist($userSeries);
            $this->entityManager->flush();
        }
        if ($episodeCount < $theoreticalEpisodeCount) {
            $missingEpisodes = $theoreticalEpisodeCount - $episodeCount;
            $this->io->warning('Not all episodes have been watched');
            $this->io->warning($missingEpisodes . ' ' . ($missingEpisodes > 1 ? 'episodes' : 'episode') . ' left');
            $userSeries->setBinge(false);
            $this->entityManager->persist($userSeries);
            $this->entityManager->flush();
        }

        $this->commandEnd();

        return Command::SUCCESS;
    }

    public function commandStart(): void
    {
        $now = $this->dateService->newDateImmutable('now', 'Europe/Paris');
        $this->io->newLine(2);
        $this->io->title('Binge Command');
        $this->io->writeln('Binge Command started at ' . $now->format('Y-m-d H:i:s'));
        $this->t0 = microtime(true);
    }

    public function commandEnd(): void
    {
        $t1 = microtime(true);
        $now = $this->dateService->newDateImmutable('now', 'Europe/Paris');
        $this->io->writeln('Binge Command ended at ' . $now->format('Y-m-d H:i:s'));
        $this->io->writeln(sprintf('Execution time: %.2f seconds', ($t1 - $this->t0)));
        $this->io->newLine(2);
    }

    public function getTvSeason($tv, $seasonNumber): ?array
    {
        foreach ($tv['seasons'] as $season) {
            if ($season['season_number'] == $seasonNumber) {
                return $season;
            }
        }
        return null;
    }
}
