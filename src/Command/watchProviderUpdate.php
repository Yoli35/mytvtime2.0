<?php

namespace App\Command;

use App\Entity\WatchProvider;
use App\Repository\WatchProviderRepository;
use App\Service\DateService;
use App\Service\TMDBService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:watch-provider:update',
    description: 'Update Tv provider for series',
)]
class watchProviderUpdate extends Command
{
    private SymfonyStyle $io;
    private float $t0;

    public function __construct(
        private readonly DateService             $dateService,
        private readonly TMDBService             $tmdbService,
        private readonly WatchProviderRepository $watchProviderRepository,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $tvProviders = json_decode($this->tmdbService->getTvWatchProviderList(), true);
        $tvProviderIds = array_column($tvProviders['results'], 'provider_id');

        $this->commandStart();

        $newCount = 0;
        $updatedCount = 0;
        $modifiedProviderCountries = [];
        $now = $this->dateService->newDateImmutable('now', 'Europe/Paris');
        $lastMonth = $now->modify('-1 month');

        foreach ($tvProviders['results'] as $tvProvider) {
            $this->io->write(sprintf('Provider (%d): %s', $tvProvider['provider_id'], $tvProvider['provider_name']));

            $watchProvider = $this->watchProviderRepository->findOneBy(['providerId' => $tvProvider['provider_id']]);

            if ($watchProvider === null) {
                $watchProvider = new WatchProvider($tvProvider['provider_id'], $tvProvider['provider_name'], $tvProvider['logo_path'], $tvProvider['display_priority'], $tvProvider['display_priorities'], $now, false);
                $this->io->writeln(' - 🟢 New');
                $newCount++;
            } else {
                if ($watchProvider->getUpdatedAt() && $watchProvider->getUpdatedAt() > $lastMonth) {
                    $this->io->writeln(' - 🔵 Already updated');
                    continue;
                }
                $watchProvider->setProviderName($tvProvider['provider_name']);
                $watchProvider->setLogoPath($tvProvider['logo_path']);
                $watchProvider->setDisplayPriority($tvProvider['display_priority']);
                $tvCountries = array_keys($tvProvider['display_priorities']);
                $dbCountries = array_keys($watchProvider->getDisplayPriorities());
                // Differences between old ($dbCountries) and new ($tvCountries) countries
                $diff = array_diff($dbCountries, $tvCountries);
                if (!empty($diff)) {
                    $this->io->write(' - Countries removed: ' . implode(', ', $diff));
                    $modifiedProviderCountries[] = sprintf('Provider (%d): %s - Countries removed: %s', $tvProvider['provider_id'], $tvProvider['provider_name'], implode(', ', $diff));
                }
                $watchProvider->setDisplayPriorities($tvProvider['display_priorities']);
                $watchProvider->setUpdatedAt($now);
                $watchProvider->setRemoved(false);
                $this->io->writeln(' - 🟠 Updated');
                $updatedCount++;
            }
            $this->watchProviderRepository->save($watchProvider);
            if (($newCount + $updatedCount) % 10 === 0) {
                $this->watchProviderRepository->flush();
            }
        }
        $this->watchProviderRepository->flush();

        $dbProviderIds = array_column($this->watchProviderRepository->providerIds(), "id");
        foreach ($dbProviderIds as $dbProviderId) {
            if (!in_array($dbProviderId, $tvProviderIds)) {
                $this->io->writeln(sprintf('Provider (%d) removed', $dbProviderId));
                $watchProvider = $this->watchProviderRepository->findOneBy(['providerId' => $dbProviderId]);
                if ($watchProvider) {
                    $watchProvider->setUpdatedAt($now);
                    $watchProvider->setRemoved(true);
                    $this->watchProviderRepository->save($watchProvider);
                }
            }
        }
        $this->watchProviderRepository->flush();

        $this->commandEnd($newCount, $updatedCount, $modifiedProviderCountries);

        return Command::SUCCESS;
    }

    public function commandStart(): void
    {
        $now = $this->dateService->newDateImmutable('now', 'Europe/Paris');
        $this->io->newLine(2);
        $this->io->title('Tv providers update Command');
        $this->io->writeln('Tv providers update Command started at ' . $now->format('Y-m-d H:i:s'));
        $this->t0 = microtime(true);
    }

    public function commandEnd(int $newCount, int $updatedCount, array $modifiedProviderCountries): void
    {
        $this->io->newLine(2);
        $this->io->writeln(sprintf('Providers added: %d', $newCount));
        $this->io->writeln(sprintf('Providers updated: %d', $updatedCount));
        foreach ($modifiedProviderCountries as $modifiedProviderCountry) {
            $this->io->writeln($modifiedProviderCountry);
        }

        $t1 = microtime(true);
        $now = $this->dateService->newDateImmutable('now', 'Europe/Paris');
        $this->io->writeln('Tv providers update Command ended at ' . $now->format('Y-m-d H:i:s'));
        $this->io->writeln(sprintf('Execution time: %.2f seconds', ($t1 - $this->t0)));
        $this->io->newLine(2);
    }
}
