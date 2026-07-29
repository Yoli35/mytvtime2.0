<?php

namespace App\Command;

use App\Repository\SeriesBroadcastScheduleRepository;
use App\Repository\UserSeriesRepository;
use App\Repository\WatchProviderRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:user-series:schedule',
    description: 'Add a short description for your command',
)]
class UserSeriesScheduleCommand extends Command
{
    public function __construct(
        private readonly WatchProviderRepository           $providerRepository,
        private readonly SeriesBroadcastScheduleRepository $seriesBroadcastScheduleRepository,
        private readonly UserSeriesRepository              $userSeriesRepository
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        /*$this
            ->addArgument('arg1', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description')
        ;*/
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /*$arg1 = $input->getArgument('arg1');

        if ($arg1) {
            $io->note(sprintf('You passed an argument: %s', $arg1));
        }

        if ($input->getOption('option1')) {
            // ...
        }*/
        $sbsArr = $this->seriesBroadcastScheduleRepository->findAll();
        $n = 0;
        foreach ($sbsArr as $sbs) {
            $series = $sbs->getSeries();
            $localizedName = $series->getLocalizedName('fr');
            $providerId = $sbs->getProviderId();
            if ($providerId) {
                $provider = $this->providerRepository->findOneBy(['providerId' => $providerId]);
                $providerName = $provider->getProviderName() ?? 'Invalid provider id';
            } else {
                $providerName = 'No provider';
            }
            $io->writeln(($localizedName ?: $series->getName()) . " / " . $providerName);

            $userSeriesArr = $this->userSeriesRepository->findBy(['series' => $series]);
            foreach ($userSeriesArr as $userSeries) {
                $io->write("    " . $userSeries->getUser()->getUsername() .": ");
                if (!$userSeries->getScheduleUsed()) {
                    $userSeries->setScheduleUsed($sbs);
                    $this->userSeriesRepository->save($userSeries);
                    $io->writeln('Schedule added 🔵');
                } else {
                    $io->writeln('Schedule already added 🟢');
                }
            }
            if ($n % 10 == 9) {
                $this->userSeriesRepository->flush();
            }
            $io->writeln('-------------------------------------------------------------------');
//            if (++$n == 10) break;
            ++$n;
        }
        if ($n % 10 != 0) {
            $this->userSeriesRepository->flush();
        }

        $io->success('You have a new command! Now make it your own! Pass --help to see your options.');

        return Command::SUCCESS;
    }
}
