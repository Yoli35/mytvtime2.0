<?php

namespace App\Command;

use App\Repository\UserSeriesRepository;
use App\Service\UserEpisodeCheckService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Vérifie les épisodes des séries des utilisateurs (épisodes manquants, trous, ids TMDB incohérents…)
 * et les corrige d'après TMDB. Toute la logique est dans UserEpisodeCheckService (réutilisable côté admin).
 *
 * Par défaut, simulation : ajouter "--apply" pour modifier la base.
 *
 * Exemples :
 *   bin/console app:user-episode:check -u 80            # une série d'un utilisateur
 *   bin/console app:user-episode:check -s 80 --deep     # tous les utilisateurs de la série, toutes les saisons
 *   bin/console app:user-episode:check --user 1 --apply # toutes les séries d'un utilisateur
 *   bin/console app:user-episode:check --added "1 week" --date 2026-11-01   # séries ajoutées dans la semaine précédant le 1er novembre
 *   bin/console app:user-episode:check --offset 200 --limit 100             # 3e lot de 100 séries
 */
#[AsCommand(
    name: 'app:user-episode:check',
    description: 'Check user episodes against TMDB (missing episodes, gaps, wrong ids) and fix them (dry run unless --apply)',
)]
class UserEpisodeCheckCommand
{
    public function __construct(
        private readonly EntityManagerInterface  $em,
        private readonly UserEpisodeCheckService $checkService,
        private readonly UserSeriesRepository    $userSeriesRepository,
    )
    {
    }

    public function __invoke(
        SymfonyStyle                                                                            $io,
        #[Option('User series id', shortcut: 'u')] ?int                                          $userSeriesId = null,
        #[Option('Series id (all users following it)', shortcut: 's')] ?int                     $seriesId = null,
        #[Option('User id (all their series)')] ?int                                             $user = null,
        #[Option('Compare every season with TMDB, even without detected anomaly', shortcut: 'd')] bool $deep = false,
        #[Option('Apply changes (dry run otherwise)', shortcut: 'a')] bool                       $apply = false,
        #[Option('Only show series with issues', shortcut: 'i')] bool                            $issuesOnly = false,
        #[Option('Only show series with watched episodes that no longer exist on TMDB', shortcut: 'w')] bool $watchedMissing = false,
        #[Option('Series added during this period, e.g. "1 day", "1 week", "3 months", "1 year"')] ?string $added = null,
        #[Option('Reference date for --added (Y-m-d, today by default)')] ?string                $date = null,
        #[Option('Skip the first n user series (ordered by id)')] int                            $offset = 0,
        #[Option('Check at most n user series')] ?int                                            $limit = null,
    ): int
    {
        $io->title('🔎 User episode check' . ($apply ? '' : ' (dry run)') . ($deep ? ' - deep' : ''));

        $filters = ['userSeriesId' => $userSeriesId, 'seriesId' => $seriesId, 'userId' => $user, 'period' => $added, 'date' => $date];
        try {
            $total = $this->checkService->countUserSeries($filters);
            $list = $this->checkService->getUserSeriesList($filters, $offset, $limit);
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());
            return Command::INVALID;
        }
        if ($added) {
            [$from, $to] = $this->checkService->getAddedPeriod($added, $date);
            $io->writeln(sprintf('Added between %s and %s', $from->format('Y-m-d'), $to->modify('-1 day')->format('Y-m-d')));
        }
        if ($offset || $limit) {
            $io->writeln(sprintf('Batch: %d to %d of %d', min($offset + 1, $total), $offset + count($list), $total));
        }
        if (!count($list)) {
            $io->warning('No user series found');
            return Command::FAILURE;
        }

        $totals = ['ok' => 0, 'issues' => 0, 'fixed' => 0, 'error' => 0];
        $counts = ['updated' => 0, 'created' => 0, 'deleted' => 0, 'skipped' => 0, 'watchedNotOnTmdb' => 0];
        $watchedMissingSeries = 0;
        $tmdbCalls = 0;
        $icons = ['info' => 'ℹ️ ', 'warning' => '⚠️ ', 'fix' => '🔧', 'error' => '🚫'];

        foreach ($list as $row) {
            $userSeries = $this->userSeriesRepository->find($row['id']);
            $result = $this->checkService->checkUserSeries($userSeries, $apply, $deep);

            $totals[$result['status']]++;
            $tmdbCalls += $result['tmdbCalls'];
            foreach ($counts as $key => $value) {
                $counts[$key] += $result['counts'][$key];
            }

            $hasWatchedMissing = $result['counts']['watchedNotOnTmdb'] > 0;
            $watchedMissingSeries += $hasWatchedMissing ? 1 : 0;
            if ($watchedMissing ? $hasWatchedMissing : (!$issuesOnly || $result['status'] !== 'ok')) {
                $io->writeln(sprintf('<info>%s</info> (series %d, user series %d, user %d) → %s',
                    $row['name'], $row['series_id'], $row['id'], $row['user_id'], $result['status']));
                foreach ($result['messages'] as $message) {
                    if ($message['code'] === UserEpisodeCheckService::CODE_WATCHED_NOT_ON_TMDB) {
                        $io->writeln('    👁️  <comment>' . $message['text'] . '</comment>');
                    } else {
                        $io->writeln('    ' . $icons[$message['level']] . ' ' . $message['text']);
                    }
                }
            }

            // Mémoire limitée chez IONOS : on repart d'un gestionnaire d'entités vide à chaque série
            $this->em->clear();
        }

        $io->newLine();
        $io->writeln(sprintf('User series: %d - ok: %d - with issues: %d - fixed: %d - errors: %d',
            count($list), $totals['ok'], $totals['issues'], $totals['fixed'], $totals['error']));
        $io->writeln(sprintf('Episodes %s: %d - %s: %d - %s: %d - skipped: %d - TMDB calls: %d',
            $apply ? 'updated' : 'to update', $counts['updated'],
            $apply ? 'created' : 'to create', $counts['created'],
            $apply ? 'deleted' : 'to delete', $counts['deleted'],
            $counts['skipped'], $tmdbCalls));
        if ($counts['watchedNotOnTmdb']) {
            $io->writeln(sprintf('<comment>👁️  Watched episodes no longer on TMDB: %d in %d series (left as is)</comment>', $counts['watchedNotOnTmdb'], $watchedMissingSeries));
        }
        if (!$apply && $totals['issues']) {
            $io->note('Dry run: nothing was changed. Run again with --apply to fix.');
        }

        return Command::SUCCESS;
    }
}
