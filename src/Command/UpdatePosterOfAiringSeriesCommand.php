<?php

namespace App\Command;

use App\Repository\SeriesRepository;
use App\Repository\SettingsRepository;
use App\Service\DateService;
use App\Service\ImageConfiguration;
use App\Service\ImageService;
use App\Service\SeriesService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
//use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
//use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:update:poster',
    description: 'Check if airing series have a new poster and update it if needed',
)]
class UpdatePosterOfAiringSeriesCommand extends Command
{
    public function __construct(
        private readonly DateService $dateService,
        private readonly ImageConfiguration $imageConfiguration,
        private readonly ImageService $imageService,
        private readonly SeriesRepository $seriesRepository,
        private readonly SeriesService $seriesService,
        private readonly SettingsRepository $settingsRepository,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
//        $this
//            ->addArgument('arg1', InputArgument::OPTIONAL, 'Argument description')
//            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description')
//        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
//        $arg1 = $input->getArgument('arg1');
//
//        if ($arg1) {
//            $io->note(sprintf('You passed an argument: %s', $arg1));
//        }
//
//        if ($input->getOption('option1')) {
//            // ...
//        }
        $settingsArr = $this->settingsRepository->findBy(['name' => 'schedule_menu_settings']);
        if (empty($settingsArr)) {
            $io->error('Settings not found for schedule_menu_settings, assuming default values');
            $startDay = 2;
        } else {
            $startDay = 0;
            foreach ($settingsArr as $settings) {
                $start = intval($settings->getData()['start']);
                $startDay = min($start, $startDay);
            }
        }
        $startDate = $this->dateService->getNow('UTC', true)->modify($startDay . ' days')->format('Y-m-d');
        $io->note('Start date: ' . $startDate);

        $seriesArr = $this->seriesRepository->getAiringSeries($startDate);

        $posterUrl = $this->imageConfiguration->getUrl('poster_sizes', 5);
        $n = 0;
        $u = 0;
        foreach ($seriesArr as $series) {
            $io->writeln('Series: ' . ($series['localized_name'] ?: $series['name']));
            $tv = $this->seriesService->getTvMini($series['tmdb_id']);
            if (!$tv) {
                $io->warning('TMDB TV show not found for series: ' . $series['name']);
                continue;
            }
            if ($tv['poster_path'] != $series['poster_path']) {
                $io->writeln('    → Updating poster');
                $this->imageService->saveImage("posters", $tv['poster_path'], $posterUrl);

                $dbSeries = $this->seriesRepository->find($series['id']);
                if ($dbSeries) {
                    $dbSeries->setPosterPath($tv['poster_path']);
                    $this->seriesRepository->save($dbSeries);
                    $u++;
                }
            }
            $n++;
        }
        if ($u) {
            $this->seriesRepository->flush();
            $io->note('Updated posters for ' . $u . ' series');
        }

        $io->note('Series to update: ' . $n);

        $io->success('You have a new command! Now make it your own! Pass --help to see your options.');

        return Command::SUCCESS;
    }
}
