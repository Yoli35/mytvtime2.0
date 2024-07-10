<?php

namespace App\Command;

use App\Entity\Movie;
use App\Entity\MovieCollection;
use App\Entity\UserMovie;
use App\Repository\MovieCollectionRepository;
use App\Repository\MovieRepository;
use App\Repository\UserMovieRepository;
use App\Repository\UserRepository;
use App\Service\DateService;
use App\Service\TMDBService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import:user:movies',
    description: 'Add a short description for your command',
)]
class ImportUserMoviesCommand extends Command
{
    public function __construct(
        private readonly MovieCollectionRepository $movieCollectionRepository,
        private readonly DateService               $dateService,
        private readonly MovieRepository           $movieRepository,
        private readonly TMDBService               $tmdbService,
        private readonly UserMovieRepository       $userMovieRepository,
        private readonly UserRepository            $userRepository,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('user', 'u', InputOption::VALUE_REQUIRED, 'User ID')
            ->addArgument('json', InputArgument::REQUIRED, 'Json file with user\'s movies');;
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
        $io->writeln('Importing movies for user ' . $user->getUsername());

        $json = $input->getArgument('json');
        if (!file_exists($json)) {
            $io->error('File not found');
            return Command::FAILURE;
        }

        $language = 'fr-FR';
        $count = 0;

        $now = $this->dateService->newDateImmutable('now', 'Europe/Paris');
        $io->writeln('Import Command started at ' . $now->format('Y-m-d H:i:s'));
        $t0 = microtime(true);

        $jsonContent = file_get_contents($json);
        $import = json_decode($jsonContent, true);

        $moviesArr = $import['movies'];
        $io->writeln('Importing ' . count($moviesArr) . ' movies');

        foreach ($moviesArr as $movieArr) {

            if ($count > 1672) {
                $io->newLine();
                $io->writeln($movieArr['title']);

                $tmdbMovie = json_decode($this->tmdbService->getMovie($movieArr['id'], $language), true);
//                dump([
//                    'id' => $movieArr['id'],
//                    'title' => $movieArr['title'],
//                    'tmdbMovie' => $tmdbMovie,
//                ]);
                $movieCollection = null;
                if (key_exists('belongs_to_collection', $tmdbMovie)) {
                    $collection = $tmdbMovie['belongs_to_collection'];
                    if ($collection) {
                        $io->writeln('Collection found: ' . $collection['name'] . ' (' . $collection['id'] . ')');
                        $collectionId = $collection['id'];
                        $movieCollection = $this->movieCollectionRepository->findOneBy(['tmdbId' => $collectionId]);
                        if (!$movieCollection) {
                            $io->writeln('Collection not found in db, creating it');
                            $movieCollection = new MovieCollection();
                            $movieCollection->setTmdbId($collectionId);
                            $movieCollection->setName($collection['name']);
                            $movieCollection->setPosterPath($collection['poster_path']);
                            $this->movieCollectionRepository->save($movieCollection, true);
                            $movieCollection = $this->movieCollectionRepository->findOneBy(['tmdbId' => $collectionId]);
                        }
                    }
                }

                $movie = new Movie($tmdbMovie);
                $movie->setCollection($movieCollection);
                $this->movieRepository->save($movie, true);

                $userMovie = new UserMovie($user, $movie, $this->dateService->newDateImmutable('now', 'Europe/Paris'), $movieArr['favorite'], $movieArr['rating'] ?? 0);
                $this->userMovieRepository->save($userMovie, true);
            }

            $count++;
        }

        $io->newLine();
        $now = $this->dateService->newDateImmutable('now', 'Europe/Paris');
        $io->writeln('Import Command ended at ' . $now->format('Y-m-d H:i:s'));
        $t1 = microtime(true);
        $io->writeln('Import Command took ' . ($t1 - $t0) . ' seconds');

        return Command::SUCCESS;
    }
}
