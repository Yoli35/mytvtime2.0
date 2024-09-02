<?php

namespace App\Command;

use App\Entity\Network;
use App\Entity\Series;
use App\Repository\NetworkRepository;
use App\Repository\SeriesRepository;
use App\Service\DateService;
use App\Service\TMDBService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:series:network',
    description: 'Update series network infos for all series or a specific one',
)]
class SeriesNetworkCommand extends Command
{
    private SymfonyStyle $io;
    private float $t0;

    public function __construct(
        private readonly DateService            $dateService,
        private readonly EntityManagerInterface $entityManager,
        private readonly SeriesRepository       $seriesRepository,
        private readonly NetworkRepository      $networkRepository,
        private readonly TMDBService            $tmdbService,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('series', 's', InputOption::VALUE_REQUIRED, 'Series ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $seriesId = $input->getOption('series');

        if (!$seriesId) {
            $allSeries = $this->seriesRepository->findAll();
        } else {
            $allSeries = $this->seriesRepository->findBy(['id' => $seriesId]);
        }

        $this->commandStart();

        $count = 0;
        $networkCount = 0;
        foreach ($allSeries as $series) {

            $this->io->writeln(sprintf('Series (%d): %s', $series->getId(), $series->getName()));
            $localizedName = $series->getLocalizedName('fr');
            if ($localizedName) {
                $this->io->writeln('Localized name: ' . $localizedName->getName());
            }
            $tv = json_decode($this->tmdbService->getTv($series->getTmdbId(), 'en-US'), true);

            if ($tv === null) {
                $this->io->error('Error while fetching TV show');
                continue;
            }

            foreach ($tv['networks'] as $network) {
                $networkId = $network['id'];
                $dbNetwork = $this->entityManager->getRepository(Network::class)->findOneBy(['networkId' => $networkId]);
                if (!$dbNetwork) {
                    $this->io->write('    New network: ' . $network['name']);
                    $dbNetwork = new Network(
                        $network['logo_path'],
                        $network['name'],
                        $networkId,
                        $network['origin_country']
                    );
                    $this->networkRepository->save($dbNetwork);
                    $this->io->writeln(' added (' . $dbNetwork->getId() . ')');
                    $networkCount++;
                } else {
                    $this->io->writeln('    Network already exists: ' . $network['name'] . ' (' . $dbNetwork->getId() . ')');
                }
                if (!$this->isInNetworkSeries($dbNetwork, $series)) {
                    $this->io->writeln('    Add network to series');
                    $series->addNetwork($dbNetwork);
                    $this->seriesRepository->save($series);
                    $count++;
                }
            }
        }
        $this->entityManager->flush();

        $this->commandEnd($count, $networkCount);

        return Command::SUCCESS;
    }

    public function isInNetworkSeries(Network $network, Series $series): bool
    {
        $seriesNetworks = $series->getNetworks();
        foreach ($seriesNetworks as $seriesNetwork) {
            if ($seriesNetwork->getId() === $network->getId()) {
                return true;
            }
        }
        return false;
    }

    public function commandStart(): void
    {
        $now = $this->dateService->newDateImmutable('now', 'Europe/Paris');
        $this->io->newLine(2);
        $this->io->title('Series network update Command');
        $this->io->writeln('Series network update Command started at ' . $now->format('Y-m-d H:i:s'));
        $this->t0 = microtime(true);
    }

    public function commandEnd(int $count, int $networkCount): void
    {
        $this->io->writeln(sprintf('Series updated: %d', $count));
        $this->io->writeln(sprintf('New network added: %d', $networkCount));

        $t1 = microtime(true);
        $now = $this->dateService->newDateImmutable('now', 'Europe/Paris');
        $this->io->writeln('Series network update Command ended at ' . $now->format('Y-m-d H:i:s'));
        $this->io->writeln(sprintf('Execution time: %.2f seconds', ($t1 - $this->t0)));
        $this->io->newLine(2);
    }
}
