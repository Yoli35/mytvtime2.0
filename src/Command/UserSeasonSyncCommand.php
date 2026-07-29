<?php

namespace App\Command;

use App\Entity\UserSeason;
use App\Repository\SeriesBroadcastScheduleRepository;
use App\Repository\UserEpisodeRepository;
use App\Repository\UserSeasonRepository;
use App\Repository\UserSeriesRepository;
use App\Repository\WatchProviderRepository;
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
        private readonly UserEpisodeRepository             $userEpisodeRepository,
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
            $uss = $us->getUserSeasons();
            $ues = $us->getUserEpisodes();
            $ueBySeason = [];
            foreach ($ues as $ue) {
                $ueBySeason[$ue->getSeasonNumber()][] = $ue/*->getEpisodeId()*/;
            }
            foreach ($ueBySeason as $seasonNumber => $ues) {
                $userSeason = $us->getUserSeasonsBySeasonNumber($seasonNumber);
                if (!$userSeason) {
                    $userSeason = new UserSeason($us, $seasonNumber);
                    foreach ($ues as $ue) {
                        $userSeason->addUserEpisode($ue);
                    }
                    $this->userSeasonRepository->save($userSeason);
                }
            }
            $sbsArr = $this->sbsRepository->findBy(['series' => $us->getSeries()]);
            $io->writeln($us->getSeries()->getName() . ': season count: ' . count($ueBySeason) . ' - sbs count: ' . count($sbsArr));
            foreach ($sbsArr as $sbs) {
                $io->writeln('    '.(string) $sbs);
            }
            if ($n % 10 == 9) {
                $this->userSeriesRepository->flush();
            }
            $io->writeln('-------------------------------------------------------------------');
            if (++$n == 2) break;
            /*++$n;*/
        }
        if ($n % 10 != 0) {
            $this->userSeriesRepository->flush();
        }

        $io->success('You have a new command! Now make it your own! Pass --help to see your options.');

        return Command::SUCCESS;
    }
}
