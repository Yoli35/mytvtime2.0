<?php

namespace App\Command;

use App\Entity\SeriesBroadcastSchedule;
use App\Repository\SeriesBroadcastScheduleRepository;
use App\Service\DateService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:schedule:days-of-week',
    description: 'Update scheduled days of week for series and episodes',
)]
class scheduleDaysOfWeekCommand
{
    private SymfonyStyle $io;
    private float $t0;

    public function __construct(
        private readonly DateService                       $dateService,
        private readonly SeriesBroadcastScheduleRepository $seriesBroadcastScheduleRepository,
    )
    {
    }

    public function __invoke(SymfonyStyle $io, #[Option(shortcut: 's')] ?int $seriesId = null): int
    {
        $this->io = $io;
        $schedules = $this->seriesBroadcastScheduleRepository->findAll();

        foreach ($schedules as $schedule) {
            if (!$schedule instanceof SeriesBroadcastSchedule) {
                continue;
            }

            $this->io->writeln(sprintf('Processing schedule for series: %s', $schedule->getSeries()->getName()));

            $days = $schedule->getDaysOfWeek();
            // [4] → [0,0,0,0,1,0,0] (Thursday)
            // [0,1] → [1,1,0,0,0,0,0] (Sunday, Monday)
            $daysOfWeek = array_fill(0, 7, 0); // Initialize an array with 7 zeros
            foreach ($days as $day) {
                if (is_numeric($day) && $day >= 0 && $day <= 6) {
                    $daysOfWeek[$day] = 1; // Set the corresponding index to 1
                } else {
                    $this->io->error(sprintf('Invalid day of week: %s', $day));
                }
            }
            $this->io->writeln(sprintf('Days of week for series %s: [%s] → [%s]', $schedule->getSeries()->getName(), implode(',', $days), implode(',', $daysOfWeek)));
            $schedule->setDaysOfWeek($daysOfWeek);
            $this->seriesBroadcastScheduleRepository->save($schedule);
        }

        $this->commandStart();

        $this->commandEnd();

        return Command::SUCCESS;
    }

    public
    function commandStart(): void
    {
        $now = $this->dateService->newDateImmutable('now', 'Europe/Paris');
        $this->io->newLine(2);
        $this->io->title('📺 Schedule days of Command');
        $this->io->writeln('Command started at ' . $now->format('Y-m-d H:i:s'));
        $this->t0 = microtime(true);
    }

    public
    function commandEnd(): void
    {
        $t1 = microtime(true);
        $now = $this->dateService->newDateImmutable('now', 'Europe/Paris');
        $this->io->newLine(2);
        $this->io->writeln('Command ended at ' . $now->format('Y-m-d H:i:s'));
        $this->io->writeln(sprintf('Execution time: %.2f seconds', ($t1 - $this->t0)));
        $this->io->newLine(2);
    }
}
