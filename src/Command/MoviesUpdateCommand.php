<?php

namespace App\Command;

use App\Entity\Movie;
use App\Repository\MovieRepository;
use App\Service\DateService;
use App\Service\MovieService;
use App\Service\TMDBService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:movie:update',
    description: 'Update movie infos for all movie or a specific one',
)]
class MoviesUpdateCommand extends Command
{
    private SymfonyStyle $io;
    private float $t0;

    public function __construct(
        private readonly DateService               $dateService,
        private readonly EntityManagerInterface    $entityManager,
        private readonly MovieRepository           $movieRepository,
        private readonly MovieService              $movieService,
        private readonly TMDBService               $tmdbService,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('movie', 'm', InputOption::VALUE_REQUIRED, 'Movie ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $movieId = $input->getOption('movie');

        $locale = $this->io->ask('Language', 'fr');
        $region = $this->io->ask('Region', 'FR');
        $timezone = $this->io->ask('Timezone', 'Europe/Paris');
        $language = $locale . '-' . $region;

        if (!$movieId) {
            $allMovies = $this->movieRepository->findAll();
        } else {
            $allMovies = $this->movieRepository->findBy(['id' => $movieId]);
        }
        $movieCount = min(10000, count($allMovies));
        $startIndex = 1450;

        $this->commandStart();

        $count = 0;
        /** @var Movie $movie */
//        foreach ($allMovies as $movie) {
        for ($index = $startIndex; $index < $movieCount; $index++) {
            $this->io->newLine();
            $movie = $allMovies[$index];

            $title = $movie->getTitle();
            $this->io->writeln(sprintf('Movie (%d/%d): %s', $movie->getId(), $movie->getTmdbId(), $movie->getTitle()));
            $localizedName = $movie->getMovieLocalizedName('fr');
            if ($localizedName) {
                $this->io->writeln('Localized name: ' . $localizedName->getName());
                $title = $localizedName->getName();
            }

            /*if ($movieCount > 1) {
                $lastUpdate = $movie->getUpdatedAt();
                if ($lastUpdate) {
                    $diff = $now->diff($lastUpdate);
                    if ($diff->days < 7) {
                        $this->io->writeln('[' . $title . '] ' . 'Movie updated less than a week ago, skipping');
                        continue;
                    }
                }
            }*/
            $m = json_decode($this->tmdbService->getMovie($movie->getTmdbId(), $language), true);

            if ($m === null) {
                $this->io->error('Error while fetching TV show');
                continue;
            }

            $messages = [];
            $updated = $this->movieService->checkMovieImage($title, $m, $movie, 'backdrop', true);
            $messages = array_merge($messages, $this->movieService->getMessages());
            $updated = $this->movieService->checkMovieImage($title, $m, $movie, 'poster', true) || $updated;
            $messages = array_merge($messages, $this->movieService->getMessages());

            $updated = $this->movieService->checkMovieCollection($title, $m, $movie, true) || $updated;
            $messages = array_merge($messages, $this->movieService->getMessages());

            $updated = $this->movieService->checkMovieInfos($title, $m, $movie, true) || $updated;
            $messages = array_merge($messages, $this->movieService->getMessages());

            $this->writeMessages($messages);

            if ($updated) {
                $now = $this->dateService->newDateImmutable('now', $timezone);
                $movie->setUpdatedAt($now);
                $this->movieRepository->save($movie);
            }

            $count++;
            if ($count % 10 === 0) {
                $this->entityManager->flush();
            }
        }
        $this->entityManager->flush();

        $this->commandEnd($count);

        return Command::SUCCESS;
    }

    public function writeMessages(array $messages): void
    {
        foreach ($messages as $message) {
            $this->io->writeln($message);
        }
    }

    public function commandStart(): void
    {
        $now = $this->dateService->newDateImmutable('now', 'Europe/Paris');
        $this->io->newLine(2);
        $this->io->title('Movies update Command');
        $this->io->writeln('Movies update Command started at ' . $now->format('Y-m-d H:i:s'));
        $this->t0 = microtime(true);
    }

    public function commandEnd(int $count): void
    {
        $this->io->newLine(2);
        $this->io->writeln(sprintf('Movies updated: %d', $count));
        $t1 = microtime(true);
        $now = $this->dateService->newDateImmutable('now', 'Europe/Paris');
        $this->io->newLine(2);
        $this->io->writeln('Movies update Command ended at ' . $now->format('Y-m-d H:i:s'));
        $this->io->writeln(sprintf('Execution time: %.2f seconds', ($t1 - $this->t0)));
        $this->io->newLine(2);
    }
}
