<?php

namespace App\Command;

use App\Entity\EpisodeNotification;
use App\Entity\SeriesLocalizedName;
use App\Entity\User;
use App\Entity\UserEpisode;
use App\Entity\UserEpisodeNotification;
use App\Entity\UserSeries;
use App\Repository\EpisodeNotificationRepository;

use App\Repository\SeriesRepository;
use App\Repository\UserEpisodeNotificationRepository;
use App\Repository\UserEpisodeRepository;
use App\Repository\UserSeriesRepository;
use App\Service\DateService;
use App\Service\TMDBService;
use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:episode:air-date',
    description: 'Update user episode air date for all user series or a specific one',
)]
class EpisodeAirDateCommand
{
    private SymfonyStyle $io;
    private float $t0;
    private bool $list = false;

    private const string SERIES_DATE = 'series date';
    private const string SERIES_STATUS = 'series status';
    private const string EPISODE_NEW = 'episode';
    private const string EPISODE_DATE = 'episode date';

    public function __construct(
        private readonly DateService                       $dateService,
        private readonly EpisodeNotificationRepository     $episodeNotificationRepository,
        private readonly SeriesRepository                  $seriesRepository,
        private readonly UserEpisodeNotificationRepository $userEpisodeNotificationRepository,
        private readonly UserEpisodeRepository             $userEpisodeRepository,
        private readonly UserSeriesRepository              $userSeriesRepository,
        private readonly TMDBService                       $tmdbService,
    )
    {
    }

    public function __invoke(SymfonyStyle $io, #[Option(shortcut: 's')] ?int $seriesId = null, #[Option(shortcut: 'l')] bool $list = false, #[Option(shortcut: 'f')] bool $force = false): int
    {
        $endedSeriesStatus = ['Ended', 'Canceled'];

        $this->io = $io;
        $this->list = $list;

        if (!$seriesId) {
            $allUserSeries = $this->userSeriesRepository->findAll();
        } else {
            $allUserSeries = $this->userSeriesRepository->findBy(['id' => $seriesId]);
            $force = true;
        }

        $this->commandStart();

        $episodeCount = 0;
        $newEpisodeCount = 0;
        $newSeriesDateCount = 0;
        $totalEpisodeUpdates = 0;
        $notifications = [];

        foreach ($allUserSeries as $userSeries) {
            $series = $userSeries->getSeries();
            $seriesId = $series->getId();
            $user = $userSeries->getUser();
            $language = $user->getPreferredLanguage() ?? "fr" . "-" . $user->getCountry() ?? "FR";
            $episodeUpdates = 0;
            $seriesNewEpisodeCount = 0;

            $name = $series->getName();
            $localizedName = $series->getLocalizedName('fr');
            if ($localizedName) {
                $name .= ' - ' . $localizedName->getName();
            }
            $line = sprintf('User %s - Series (%d): %s', $user->getUsername(), $seriesId, $name);
            if ($force) {
                $line .= ' 🔨 Force update';
            } else {
                if ($userSeries->getProgress() == 100) {
                    $line .= ' 👍🏻 Series already updated';
                    $this->io->writeln($line);
                    continue;
                }
                if ($series->getStatus() && in_array($series->getStatus(), $endedSeriesStatus)) {
                    $line .= ' 🔒 Serie is finished';
                    $this->io->writeln($line);
                    continue;
                }
            }
            $this->io->write($line);

            $tv = json_decode($this->tmdbService->getTv($series->getTmdbId(), $language), true);
            if ($tv === null) {
                $this->io->writeln(' 🚫📺 Error while fetching TV show');
                continue;
            }
            if ($tv['first_air_date']) {
                $airDate = $this->dateService->newDateImmutable($tv['first_air_date'], 'UTC');
                if ($series->getFirstAirDate() != $airDate) {
                    if (!$this->list) {
                        $series->setFirstAirDate($airDate);
                        $this->seriesRepository->save($series, true);
                    }
                    $notifications[] = $this->newNotification(self::SERIES_DATE, $userSeries, null, $localizedName, $airDate);
                    $newSeriesDateCount++;
                }
            }

            if ($tv['status'] != $series->getStatus()) {
                $series->setStatus($tv['status']);
                $this->seriesRepository->save($series, true);
                $notifications[] = $this->newNotification(self::SERIES_STATUS, $userSeries, null, $localizedName, null);
                $newSeriesDateCount++;
            }

            if (!$series->getNumberOfEpisode()) {
                $series->setNumberOfEpisode($tv['number_of_episodes']);
                $series->setNumberOfSeason($tv['number_of_seasons']);
                $this->seriesRepository->save($series, true);
            }

            $userEpisodes = $this->userEpisodeRepository->findBy(['userSeries' => $userSeries], ['seasonNumber' => 'ASC', 'episodeNumber' => 'ASC']);
            $result = $this->adjustNextEpisodeToWatch($userSeries, $userEpisodes);
            if ($result) {
                $notifications[] = $name . $result;
            }
            $firstUnseenEpisode = $this->findFirstNotWatchedEpisode($userEpisodes);
            $startingSeason = $firstUnseenEpisode ? $firstUnseenEpisode->getSeasonNumber() : 1;
            foreach ($tv['seasons'] as $season) {
                if (!$force && $season['season_number'] > 0 && $season['season_number'] < $startingSeason) {
                    continue;
                }
                $seasonNumber = $season['season_number'];
                $tvSeason = json_decode($this->tmdbService->getTvSeason($series->getTmdbId(), $seasonNumber, $language), true);
                $episodes = $tvSeason ? $tvSeason['episodes'] : [];
                if (!count($episodes)) {
                    $this->io->writeln(' 🚫 No episodes for season ' . $seasonNumber);
                    continue;
                }
                foreach ($episodes as $episode) {
                    $episodeId = $episode['id'];
                    $episodeNumber = $episode['episode_number'];
                    /** @var DateTimeImmutable|null $airDate */
                    $airDate = $episode['air_date'] ? $this->dateService->newDateImmutable($episode['air_date'], 'UTC') : null;

                    $userEpisode = $this->getUserEpisodeById($userEpisodes, $episodeId);

                    if (!$userEpisode) {
                        $userEpisode = new UserEpisode($userSeries, $episodeId, $seasonNumber, $episodeNumber, null);
                        $this->userEpisodeRepository->save($userEpisode);
                        $seriesNewEpisodeCount++;

                        $notifications[] = $this->newNotification(self::EPISODE_NEW, $userSeries, $userEpisode, $localizedName, $airDate);
                    }
                    if ($userEpisode->getAirDate() != $airDate) {
                        if (!$this->list) {
                            $userEpisode->setAirDate($airDate);
                            $this->userEpisodeRepository->save($userEpisode);
                        }
                        $notifications[] = $this->newNotification(self::EPISODE_DATE, $userSeries, $userEpisode, $localizedName, $airDate);
                        $episodeUpdates++;
                    }
                    $episodeCount++;
                }
            }
            $writeln = false;
            if ($episodeUpdates) {
                $this->episodeNotificationRepository->flush();
                $this->userEpisodeNotificationRepository->flush();
                $this->io->writeln(' 🟠 ' . $episodeUpdates . ' episodes updated');
                $writeln = true;
            }
            if ($seriesNewEpisodeCount) {
                $this->io->writeln(' 🟢 ' . $seriesNewEpisodeCount . ' new episodes');
                $writeln = true;
            }
            if (!$writeln) {
                $this->io->writeln(' ✅');
            }

            $totalEpisodeUpdates += $episodeUpdates;

            $newEpisodeCount += $seriesNewEpisodeCount;
        }

        $this->io->newLine(2);
        $this->io->writeln('Notifications:');
        foreach ($notifications as $notification) {
            $this->io->writeln($notification);
        }

        $this->io->newLine(2);
        $this->io->writeln(sprintf('Series checked: %d', count($allUserSeries)));
        $this->io->writeln(sprintf('New series air date: %d', $newSeriesDateCount));
        $this->io->writeln(sprintf('Episodes checked: %d', $episodeCount));
        $this->io->writeln(sprintf('New episodes: %d', $newEpisodeCount));
        $this->io->writeln(sprintf('Total episodes updated: %d', $totalEpisodeUpdates));

        $this->commandEnd();

        return Command::SUCCESS;
    }

    public function newNotification(string $type, UserSeries $userSeries, ?UserEpisode $userEpisode, ?SeriesLocalizedName $localizedName, ?DateTimeImmutable $airDate): string
    {
        $seasonNumber = $userEpisode ? $userEpisode->getSeasonNumber() : 0;
        $episodeNumber = $userEpisode ? $userEpisode->getEpisodeNumber() : 0;
        $episodeId = $userEpisode ? $userEpisode->getEpisodeId() : 0;

        $series = $userSeries->getSeries();
        $user = $userSeries->getUser();

        $notification = sprintf('New %s: %s', $type, $series->getName());
        if ($localizedName) {
            $notification .= ' - ' . $localizedName->getName();
        }
        if ($seasonNumber && $episodeNumber) {
            $notification .= sprintf(' - S%02dE%02d', $seasonNumber, $episodeNumber);
        }
        $notification .= ' - ' . ($airDate ? $airDate->format('d/m/Y') : 'Unknown');

        if (!$this->list) {
            $newRecord = new EpisodeNotification($episodeId, $notification);
            $this->episodeNotificationRepository->save($newRecord, true);
            $this->addUserNotification($user, $newRecord);
        }

        return $notification;
    }

    public function addUserNotification(User $user, EpisodeNotification $notification): void
    {
        $userEpisodeNotification = new UserEpisodeNotification($user, $notification);
        $this->userEpisodeNotificationRepository->save($userEpisodeNotification, true);
    }

    public function getUserEpisodeById($userEpisodes, $episodeId): mixed
    {
        /*array_filter($userEpisodes, function ($userEpisode) use ($episodeId) {
            return $userEpisode->getEpisodeId() == $episodeId;
        });*/
        return array_find($userEpisodes, fn($userEpisode) => $userEpisode->getEpisodeId() == $episodeId);
    }

    public function findFirstNotWatchedEpisode($userEpisodes): mixed
    {
        return array_find($userEpisodes, fn($userEpisode) => $userEpisode->getWatchAt() === null);
    }

    /**
     * @param UserSeries $userSeries
     * @param UserEpisode [] $userEpisodes
     * @return string|null
     */
    private function adjustNextEpisodeToWatch(UserSeries $userSeries, array $userEpisodes): ?string
    {
        if ($userSeries->getNextUserEpisode() === null) {
            $userEpisodes = array_filter($userEpisodes, function ($ue) {
                return $ue->getWatchAt() === null && $ue->getPreviousOccurrence() === null;
            });
            usort($userEpisodes, function ($a, $b) {
                if ($a->getSeasonNumber() == $b->getSeasonNumber()) {
                    return $a->getEpisodeNumber() <=> $b->getEpisodeNumber();
                }
                return $a->getSeasonNumber() <=> $b->getSeasonNumber();
            });
            if (count($userEpisodes) > 0) {
                $ep = $userEpisodes[0];
                $userSeries->setNextUserEpisode($ep);
                $this->userSeriesRepository->save($userSeries, true);
                $userName = $userSeries->getUser()->getUsername();
                $nextEpisodeString = sprintf(' - 🎉→ Next episode to watch for user %s: S%02dE%02d', $userName, $ep->getSeasonNumber(), $ep->getEpisodeNumber());
                $this->io->write($nextEpisodeString);
                return $nextEpisodeString;
            }
        }
        return null;
    }

    public function commandStart(): void
    {
        $now = $this->dateService->newDateImmutable('now', 'Europe/Paris');
        $this->io->newLine(2);
        $this->io->title('📺 Tv Episode air date Command');
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
