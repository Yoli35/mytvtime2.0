<?php

namespace App\Command;

use App\Entity\EpisodeSubstituteName;
use App\Repository\SeriesRepository;
use App\Repository\UserEpisodeRepository;
use App\Repository\UserRepository;
use App\Repository\UserSeriesRepository;
use App\Service\DateService;
use App\Service\TMDBService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import:episode-names',
    description: 'Import substitute episode names',
)]
class importSubstituteEpisodeNames extends Command
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
            ->addArgument('json', InputArgument::REQUIRED, 'Json file with substitute episode names');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $json = $input->getArgument('json');
        if (!file_exists($json)) {
            $this->io->error('File not found');
            return Command::FAILURE;
        }
        $this->commandStart();

        $data = json_decode(file_get_contents($json), true);
        $newNames = 0;
        foreach ($data['substituteNames'] as $substituteName) {
            $this->io->writeln('Episode ' . $substituteName['tmdb_episode_id'] . ' - ' . $substituteName['substitute_name']);
            $episodeSubstituteName = new EpisodeSubstituteName($substituteName['tmdb_episode_id'], $substituteName['substitute_name']);
            $this->entityManager->persist($episodeSubstituteName);
            $newNames++;
            if ($newNames % 100 == 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();
            }
        }
        $this->entityManager->flush();
        $this->entityManager->clear();

        $this->commandEnd();

        return Command::SUCCESS;
    }

    public function commandStart(): void
    {
        $now = $this->dateService->newDateImmutable('now', 'Europe/Paris');
        $this->io->newLine(2);
        $this->io->title('substitute names import Command');
        $this->io->writeln('Import Command started at ' . $now->format('Y-m-d H:i:s'));
        $this->t0 = microtime(true);
    }

    public function commandEnd(): void
    {
        $t1 = microtime(true);
        $now = $this->dateService->newDateImmutable('now', 'Europe/Paris');
        $this->io->writeln('Import Command ended at ' . $now->format('Y-m-d H:i:s'));
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
