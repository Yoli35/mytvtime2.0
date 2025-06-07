<?php

namespace App\Command;

use App\Entity\Series;
use App\Repository\SeriesRepository;
use App\Service\DateService;
use App\Service\TMDBService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Twig\Extra\Intl\IntlExtension;

#[AsCommand(
    name: 'app:series:original:language',
    description: 'Update series origin language info for all series',
)]
class SeriesOriginalLanguageCommand
{
    private SymfonyStyle $io;
    private float $t0;

    public function __construct(
        private readonly DateService      $dateService,
        private readonly SeriesRepository $seriesRepository,
        private readonly TMDBService      $tmdbService,
    )
    {
    }

    public function __invoke(SymfonyStyle $io, #[Option(shortcut: 's')] ?int $seriesId = null): int
    {
        $this->io = $io;
        $series = $this->seriesRepository->findAll();
        $languages = (new IntlExtension)->getLanguageNames('fr');
        $count = 0;

        $this->commandStart();

        foreach ($series as $s) {
            if (!$s instanceof Series) {
                continue;
            }

            $this->io->write(sprintf('%d -  %s: ', $s->getId(), $s->getName()));
            if ($s->getOriginalLanguage()) {
                $this->io->writeln(sprintf('🟢 Original language already set: %s', $languages[$s->getOriginalLanguage()] ?? $s->getOriginalLanguage()));
                continue;
            }

            $tv = json_decode($this->tmdbService->getTv($s->getTmdbId(), 'en-US'), true);
            if ($tv === null) {
                $io->newLine();
                $this->io->error(sprintf('🔴 TV data not found for TMDB ID %d', $s->getTmdbId()));
                continue;
            }
            $s->setOriginalLanguage($tv['original_language']);
            $this->seriesRepository->save($s);

            $io->writeln(sprintf('🟠 Updated original language: %s', $languages[$tv['original_language']] ?? $tv['original_language']));

            if ($count++ % 10 === 0) {
                $this->seriesRepository->flush();
            }
        }
        if ($count > 0) {
            $this->seriesRepository->flush();
            $this->io->success(sprintf('Updated %d series with original language.', $count));
        } else {
            $this->io->warning('No series found to update.');
        }

        $this->commandEnd();

        return Command::SUCCESS;
    }

    public
    function commandStart(): void
    {
        $now = $this->dateService->newDateImmutable('now', 'Europe/Paris');
        $this->io->newLine(2);
        $this->io->title('📺 Series original language Command');
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
