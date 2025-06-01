<?php

namespace App\Command;

use App\Entity\Network;
use App\Repository\NetworkRepository;
use App\Repository\SeriesRepository;
use App\Service\DateService;
use App\Service\TMDBService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:series:network',
    description: 'Update series network infos for all series or a specific one',
)]
class SeriesNetworkCommand
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
    }

    public function __invoke(SymfonyStyle $io, #[Option(shortcut: 's')] ?int $seriesId = null): int
    {
        $this->io = $io;

        if (!$seriesId) {
            $allSeries = $this->seriesRepository->findAll();
        } else {
            $allSeries = $this->seriesRepository->findBy(['id' => $seriesId]);
        }

        $this->commandStart();

        $count = 0;
        $networkCount = 0;
        $networkUpdatedCount = 0;
        $now = $this->dateService->newDateImmutable('now', 'Europe/Paris');
        $lastMonth = $now->modify('-1 month');
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

            $seriesNetworks = $series->getNetworks()->toArray();
            foreach ($tv['networks'] as $network) {
                $networkId = $network['id'];
                $dbNetwork = $this->networkRepository->findOneBy(['networkId' => $networkId]);
                if (!$dbNetwork) {
                    $this->io->write('    New network: ' . $network['name']);
                    $dbNetwork = new Network(
                        $network['logo_path'],
                        $network['name'],
                        $networkId,
                        $network['origin_country'],
                        $now
                    );
                    $this->networkRepository->save($dbNetwork);
                    $this->io->writeln(' added (' . $dbNetwork->getId() . ' / ' . $dbNetwork->getNetworkId() . ')');
                    $networkCount++;
                    if ($networkCount % 10 == 0)
                    {
                        $this->entityManager->flush();
                    }
                } else {
                    $this->io->writeln('    Network already exists: ' . $network['name'] . ' (' . $dbNetwork->getId() . ' / ' . $dbNetwork->getNetworkId() . ')');
                    $updated = false;
                    if (!$dbNetwork->getUpdatedAt() || $dbNetwork->getUpdatedAt() < $lastMonth) {
                        $dbNetwork->setUpdatedAt($now);

                        if ($dbNetwork->getName() !== $network['name']) {
                            $this->io->writeln('    🟠 Update network name from ' . $dbNetwork->getName() . ' to ' . $network['name']);
                            $dbNetwork->setName($network['name']);
                            $updated = true;
                        }
                        if ($dbNetwork->getLogoPath() !== $network['logo_path']) {
                            $this->io->writeln('    🟠 Update network logo from ' . $dbNetwork->getLogoPath() . ' to ' . $network['logo_path']);
                            $dbNetwork->setLogoPath($network['logo_path']);
                            $updated = true;
                        }
                        if ($dbNetwork->getOriginCountry() !== $network['origin_country']) {
                            $this->io->writeln('    🟠 Update network origin country from ' . $dbNetwork->getOriginCountry() . ' to ' . $network['origin_country']);
                            $dbNetwork->setOriginCountry($network['origin_country']);
                            $updated = true;
                        }
                        if ($updated) {
                            $this->networkRepository->save($dbNetwork);
                            $networkUpdatedCount++;
                        }
                        if ($networkUpdatedCount % 10 == 0)
                        {
                            $this->entityManager->flush();
                        }
                    }
                }
                if (!$this->isInNetworkSeries($dbNetwork, $seriesNetworks)) {
                    $this->io->writeln('    Add network to series');
                    $series->addNetwork($dbNetwork);
                    $this->seriesRepository->save($series);
                    $count++;
                    if ($count % 10 == 0)
                    {
                        $this->entityManager->flush();
                    }
                }
            }
        }
        $this->entityManager->flush();

        $this->commandEnd($count, $networkCount);

        return Command::SUCCESS;
    }

    public function isInNetworkSeries(Network $network, array $seriesNetworks): bool
    {
        return array_any($seriesNetworks, fn($seriesNetwork) => $seriesNetwork->getId() === $network->getId());
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
