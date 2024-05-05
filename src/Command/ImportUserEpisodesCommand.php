<?php

namespace App\Command;

use App\Entity\Series;
use App\Entity\UserEpisode;
use App\Entity\UserSeries;
use App\Repository\SeriesRepository;
use App\Repository\UserEpisodeRepository;
use App\Repository\UserRepository;
use App\Repository\UserSeriesRepository;
use App\Service\DateService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import:user:episodes',
    description: 'Import user\'s episodes from a json file',
)]
class ImportUserEpisodesCommand extends Command
{
    public function __construct(
        private readonly DateService            $dateService,
        private readonly EntityManagerInterface $entityManager,
        private readonly SeriesRepository       $seriesRepository,
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
            ->addArgument('json', InputArgument::REQUIRED, 'Json file with user\'s episodes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $userId = $input->getOption('user');

        if (!$userId) {
            $userId = $io->ask('User\'s Id');
            if (!$userId) {
                $io->error('User\'s Id is required');
                return Command::FAILURE;
            }
        }
        $user = $this->userRepository->find($userId);
        $io->writeln('Importing episodes for user ' . $user->getUsername());

        $json = $input->getArgument('json');
        if (!file_exists($json)) {
            $io->error('File not found');
            return Command::FAILURE;
        }

        $now = $this->dateService->newDateImmutable('now', 'Europe/Paris');
        $io->writeln('Import Command started at ' . $now->format('Y-m-d H:i:s'));
        $t0 = microtime(true);

        $jsonContent = file_get_contents($json);
        $import = json_decode($jsonContent, true);

        $seriesArr = $import['series'];
        $userSeriesArr = $import['userSeries'];
        $io->writeln('Importing ' . count($seriesArr) . ' series');

        //
        // Importing series
        //
        foreach ($seriesArr as $serie) {
            $io->writeln('Importing series ' . $serie['name']);
            // {
            //      "tmdbId": 125910,
            //      "name": "Young Royals",
            //      "posterPath": "\/uGGAoM8ojRHOXj16n6xWiccKr34.jpg",
            //      "originalName": "Young Royals",
            //      "slug": "Young-Royals",
            //      "overview": "Le prince Wilhelm s'adapte \u00e0 la vie \u00e0 Hillerska, son nouveau pensionnat prestigieux, mais suivre son c\u0153ur s'av\u00e8re plus compliqu\u00e9 que pr\u00e9vu.",
            //      "backdropPath": "\/1A2Wh7Vs1YCmDNeRhBP2eP9ZAJj.jpg",
            //      "firstDateAir": {
            //          "date": "2021-07-01 00:00:00.000000",
            //          "timezone_type": 3,
            //          "timezone": "UTC"
            //      },
            //      "createdAt": {
            //          "date": "2022-08-21 16:37:59.000000",
            //          "timezone_type": 3,
            //          "timezone": "UTC"
            //      },
            //      "updatedAt": {
            //          "date": "2024-01-25 07:38:21.000000",
            //          "timezone_type": 3,
            //          "timezone": "UTC"
            //      },
            //      "visitNumber": 0,
            //      "seriesLocalizedNames": []
            //  }
            $series = $this->seriesRepository->findOneBy(['tmdbId' => $serie['tmdbId']]);
            $index = 0;
            if (!$series) {
                $io->writeln('Creating series ' . $serie['name']);
                $series = new Series();
                $series->setTmdbId($serie['tmdbId']);
                $series->setName($serie['name']);
                $series->setPosterPath($serie['posterPath']);
                $series->setOriginalName($serie['originalName']);
                $series->setSlug($serie['slug']);
                $series->setOverview($serie['overview']);
                $series->setBackdropPath($serie['backdropPath']);
                $series->setFirstDateAir($serie['firstDateAir'] ? $this->dateService->newDateImmutable($serie['firstDateAir']['date'], $user->getTimezone() ?? 'Europe/Paris'): null);
                $series->setCreatedAt($this->dateService->newDateImmutable($serie['createdAt']['date'], $user->getTimezone() ?? 'Europe/Paris'));
                $series->setUpdatedAt($this->dateService->newDateImmutable($serie['updatedAt']['date'], $user->getTimezone() ?? 'Europe/Paris'));
                $series->setVisitNumber($serie['visitNumber']);
                $this->entityManager->persist($series);
                if ($index++ % 10 == 0)
                    $this->entityManager->flush();
            }
        }
        $this->entityManager->flush();
        $io->newLine();

        //
        // Importing user series & user episodes
        //
        foreach ($userSeriesArr as $userSerie) {
            $io->writeln('Importing user series ' . $userSerie['seriesName']);
            //"userSeries": [
            //        {
            //            "user": 2,
            //            "seriesName": "Young Royals",
            //            "tmdbId": 125910,
            //            "addedAt": {
            //                "date": "2022-12-12 21:26:09.000000",
            //                "timezone_type": 3,
            //                "timezone": "UTC"
            //            },
            //            "lastWatchAt": {
            //                "date": "2024-03-11 17:08:46.000000",
            //                "timezone_type": 3,
            //                "timezone": "UTC"
            //            },
            //            "lastSeason": 3,
            //            "lastEpisode": 1,
            //            "viewedEpisodes": 13,
            //            "progress": 72.22222222222223,
            //            "favorite": false,
            //            "rating": 0,
            //            "userEpisodes": [
            //                {
            //                    "episodeId": 2962277,
            //                    "seasonNumber": 1,
            //                    "episodeNumber": 1,
            //                    "watchAt": {
            //                        "date": "2022-12-13 11:46:50.000000",
            //                        "timezone_type": 3,
            //                        "timezone": "UTC"
            //                    },
            //                    "providerId": 8,
            //                    "deviceId": 5,
            //                    "vote": 0,
            //                    "quickWatchDay": false,
            //                    "quickWatchWeek": false
            //                },
            //                [...]
            //            ]
            //        },
            //        [...]
            //    ]
            $series = $this->seriesRepository->findOneBy(['tmdbId' => $userSerie['tmdbId']]);
            if (!$series) {
                $io->error('Series not found');
//                return Command::FAILURE;
                continue;
            }
            $dbUserSeries = $this->userSeriesRepository->findOneBy(['user' => $user, 'series' => $series]);
            $newEntity = false;
            if (!$dbUserSeries) {
                $io->writeln('Creating user series ' . $series->getName());
                $dbUserSeries = new UserSeries($user, $series, $this->dateService->newDateImmutable($userSerie['addedAt']['date'], $user->getTimezone() ?? 'Europe/Paris'));
                $newEntity = true;
            }
            $dbUserSeries->setLastWatchAt($userSerie['lastWatchAt'] ? $this->dateService->newDateImmutable($userSerie['lastWatchAt']['date'], $user->getTimezone() ?? 'Europe/Paris') : null);
            $dbUserSeries->setLastSeason($userSerie['lastSeason']);
            $dbUserSeries->setLastEpisode($userSerie['lastEpisode']);
            $dbUserSeries->setViewedEpisodes($userSerie['viewedEpisodes']);
            $dbUserSeries->setProgress($userSerie['progress']);
            $dbUserSeries->setFavorite($userSerie['favorite']);
            $dbUserSeries->setRating($userSerie['rating']);
            $this->entityManager->persist($dbUserSeries);
            $this->entityManager->flush();

            if ($newEntity)
                $dbUserSeries = $this->userSeriesRepository->findOneBy(['user' => $user, 'series' => $series]);

            $index = 0;
            foreach ($userSerie['userEpisodes'] as $userEpisode) {
                $dbUserEpisode = $this->userEpisodeRepository->findOneBy(['user' => $user, 'series' => $dbUserSeries, 'episodeId' => $userEpisode['episodeId']]);
                if (!$dbUserEpisode) {
                    $io->writeln('Creating user episode ' . $userEpisode['episodeId']);
                    $dbUserEpisode = new UserEpisode(
                        $dbUserSeries,
                        $userEpisode['episodeId'],
                        $userEpisode['seasonNumber'],
                        $userEpisode['episodeNumber'],
                        $this->dateService->newDateImmutable($userEpisode['watchAt']['date'], $user->getTimezone() ?? 'Europe/Paris')
                    );
                }
                $dbUserEpisode->setSeasonNumber($userEpisode['seasonNumber']);
                $dbUserEpisode->setEpisodeNumber($userEpisode['episodeNumber']);
                $dbUserEpisode->setWatchAt($this->dateService->newDateImmutable($userEpisode['watchAt']['date'], $user->getTimezone() ?? 'Europe/Paris'));
                $dbUserEpisode->setProviderId($userEpisode['providerId']);
                $dbUserEpisode->setDeviceId($userEpisode['deviceId']);
                $dbUserEpisode->setVote($userEpisode['vote']);
                $dbUserEpisode->setQuickWatchDay($userEpisode['quickWatchDay']);
                $dbUserEpisode->setQuickWatchWeek($userEpisode['quickWatchWeek']);
                $this->entityManager->persist($dbUserEpisode);
                if ($index++ % 10 == 0)
                    $this->entityManager->flush();
            }
            $this->entityManager->flush();
        }

        $io->newLine();
        $now = $this->dateService->newDateImmutable('now', 'Europe/Paris');
        $io->writeln('Import Command ended at ' . $now->format('Y-m-d H:i:s'));
        $t1 = microtime(true);
        $io->writeln('Import Command took ' . ($t1 - $t0) . ' seconds');

        return Command::SUCCESS;
    }
}
