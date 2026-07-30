<?php

namespace App\Command;

use App\Entity\UserSeason;
use App\Repository\SeriesBroadcastScheduleRepository;
use App\Repository\UserSeasonRepository;
use App\Repository\UserSeriesRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:user-season:sync',
    description: 'Sync user series season',
)]
class UserSeasonSyncCommand extends Command
{
    public function __construct(
        private readonly UserSeasonRepository              $userSeasonRepository,
        private readonly SeriesBroadcastScheduleRepository $sbsRepository,
        private readonly UserSeriesRepository              $userSeriesRepository
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $userSeriesArr = $this->userSeriesRepository->findAll();
        $n = 0;
        foreach ($userSeriesArr as $us) {
            if ($us->getId() < 1410) continue;
            $series = $us->getSeries();
            $sln = $series->getLocalizedName('fr');
            $io->writeln($series->getId() . ' / ' . $us->getId() . ' / ' . ($sln ? $sln->getName() : $series->getName()));
            $ues = $us->getUserEpisodes();
            $ueBySeason = [];
            foreach ($ues as $ue) {
                $ueBySeason[$ue->getSeasonNumber()][] = $ue/*->getEpisodeId()*/
                ;
            }
            foreach ($ueBySeason as $seasonNumber => $ues) {
                $userSeason = $us->getUserSeasonsBySeasonNumber($seasonNumber);
                if (!$userSeason) {
                    $userSeason = new UserSeason($us, $seasonNumber);
                    foreach ($ues as $ue) {
                        $userSeason->addUserEpisode($ue);
                    }
                    // Look for series broadcast schedules (by series id and season number)
                    $seriesBroadcastSchedules = $this->sbsRepository->findBy(['series' => $series, 'seasonNumber' => $seasonNumber]);
                    foreach ($seriesBroadcastSchedules as $seriesBroadcastSchedule) {
                        $userSeason->addBroadcastSchedule($seriesBroadcastSchedule);
                        $io->writeln('    Adding broadcast schedule for ' . (string)$seriesBroadcastSchedule);
                    }
                    $this->userSeasonRepository->save($userSeason);
                }
            }
            $sbsArr = $this->sbsRepository->findBy(['series' => $series]);
            $io->writeln('    Season count: ' . count($ueBySeason));
            $io->writeln('    Sbs count: ' . count($sbsArr));

            if ($n % 10 == 9) {
                $this->userSeasonRepository->flush();
                $io->writeln('Last flush count: ' . $n + 1);
            }
            $io->writeln('-------------------------------------------------------------------');
            ++$n;
            if ($n == 200) break;
        }
        if ($n % 10 != 0) {
            $this->userSeasonRepository->flush();
            $io->writeln('Last flush count: ' . $n);
        }

        $io->success('You have a new command! Now make it your own! Pass --help to see your options.');

        return Command::SUCCESS;
    }
}
