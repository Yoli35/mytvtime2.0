<?php

namespace App\Command;

use App\Entity\Keyword;
use App\Repository\KeywordRepository;
use App\Repository\SeriesRepository;
use App\Service\DateService;
use App\Service\TMDBService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:series:keyword',
    description: 'Update series keyword infos for all series or a specific one',
)]
class SeriesKeywordCommand
{
    private SymfonyStyle $io;
    private float $t0;

    public function __construct(
        private readonly DateService            $dateService,
        private readonly EntityManagerInterface $entityManager,
        private readonly KeywordRepository      $keywordRepository,
        private readonly SeriesRepository       $seriesRepository,
        private readonly TMDBService            $tmdbService,
    )
    {
    }


    public function __invoke(SymfonyStyle $io, #[Option(shortcut: 's')] ?int $seriesId = null, #[Option(shortcut: 'o')] ?int $offset = null): int
    {
        $this->io = $io;

        if (!$seriesId) {
            $allSeries = $this->seriesRepository->findAll();
        } else {
            $allSeries = $this->seriesRepository->findBy(['id' => $seriesId]);
        }

        $this->commandStart();

        $count = 0;
        $keywordCount = 0;
        $now = $this->dateService->newDateImmutable('now', 'Europe/Paris');

        foreach ($allSeries as $series) {
            if ($offset && $series->getId() < $offset) {
                continue;
            }
            $this->io->writeln(sprintf('Series (%d): %s', $series->getId(), $series->getName()));
            $localizedName = $series->getLocalizedName('fr');
            if ($localizedName) {
                $this->io->writeln('Localized name: ' . $localizedName->getName());
            }
            $tv = json_decode($this->tmdbService->getTv($series->getTmdbId(), 'en-US', ['keywords']), true);

            if ($tv === null) {
                $this->io->error('Error while fetching TV show');
                continue;
            }

            if (!key_exists('keywords', $tv))
                continue;

            $keywords = $tv['keywords']['results'];
            foreach ($keywords as $keyword) {
                $keywordId = $keyword['id'];
                $dbKeyword = $this->keywordRepository->findOneBy(['keywordId' => $keywordId]);
                if (!$dbKeyword) {
                    $this->io->write('    New keyword: ' . $keyword['name']);
                    $dbKeyword = new Keyword(
                        $keyword['name'],
                        $keywordId, $now
                    );
                    $this->keywordRepository->save($dbKeyword);
                    $this->io->writeln(' added (' . $dbKeyword->getId() . ' / ' . $dbKeyword->getKeywordId() . ')');
                    $keywordCount++;
                    if ($keywordCount % 10 == 0) {
                        $this->entityManager->flush();
                    }
                } else {
                    $this->io->writeln('    Keyword already exists: ' . $keyword['name'] . ' (' . $dbKeyword->getId() . ' / ' . $dbKeyword->getKeywordId() . ')');
                    $updated = false;
                }
                if (!$this->isInKeywordSeries($dbKeyword, $keywords)) {
                    $this->io->writeln('    Add keyword to series');
                    $series->addKeyword($dbKeyword);
                    $this->seriesRepository->save($series);
                    $count++;
                    if ($count % 10 == 0) {
                        $this->entityManager->flush();
                    }
                }
            }
        }

        $this->entityManager->flush();

        $this->commandEnd($count, $keywordCount);

        return Command::SUCCESS;
    }

    public function isInKeywordSeries(Keyword $keyword, array $seriesKeywords): bool
    {
        return array_any($seriesKeywords, fn($seriesKeyword) => $seriesKeyword['id'] === $keyword->getId());
    }

    public function commandStart(): void
    {
        $now = $this->dateService->newDateImmutable('now', 'Europe/Paris');
        $this->io->newLine(2);
        $this->io->title('Series keyword update Command');
        $this->io->writeln('Series keyword update Command started at ' . $now->format('Y-m-d H:i:s'));
        $this->t0 = microtime(true);
    }

    public function commandEnd(int $count, int $keywordCount): void
    {
        $this->io->writeln(sprintf('Series updated: %d', $count));
        $this->io->writeln(sprintf('New keyword added: %d', $keywordCount));

        $t1 = microtime(true);
        $now = $this->dateService->newDateImmutable('now', 'Europe/Paris');
        $this->io->writeln('Series keyword update Command ended at ' . $now->format('Y-m-d H:i:s'));
        $this->io->writeln(sprintf('Execution time: %.2f seconds', ($t1 - $this->t0)));
        $this->io->newLine(2);
    }
}
