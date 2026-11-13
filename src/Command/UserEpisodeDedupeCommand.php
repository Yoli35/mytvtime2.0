<?php

namespace App\Command;

use App\Entity\UserEpisode;
use App\Entity\UserSeries;
use App\Repository\UserEpisodeRepository;
use App\Repository\UserSeriesRepository;
use App\Service\TMDBService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Supprime les doublons de UserEpisode laissés par les remplacements d'épisodes chez TMDB
 * (épisodes recréés avec un nouvel id peu avant la diffusion d'une saison annoncée).
 *
 * Pour chaque groupe (série de l'utilisateur, saison, numéro d'épisode) en double :
 * - on garde l'épisode vu s'il y en a un, sinon celui dont l'id existe chez TMDB ;
 * - l'épisode conservé reçoit l'id TMDB valide ;
 * - les autres sont supprimés, après redirection de l'épisode suivant / dernier épisode vu.
 * Les cas ambigus (plusieurs épisodes vus, revisionnages, aucun id TMDB valide…) sont signalés, pas traités.
 *
 * Par défaut, simulation : ajouter "--apply" pour modifier la base.
 */
#[AsCommand(
    name: 'app:user-episode:dedupe',
    description: 'Remove duplicate user episodes left by TMDB episode replacements (dry run unless --apply)',
)]
class UserEpisodeDedupeCommand
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TMDBService            $tmdbService,
        private readonly UserEpisodeRepository  $userEpisodeRepository,
        private readonly UserSeriesRepository   $userSeriesRepository,
    )
    {
    }

    public function __invoke(
        SymfonyStyle                    $io,
        #[Option('User series id', shortcut: 'u')] ?int $userSeriesId = null,
        #[Option('Apply changes (dry run otherwise)', shortcut: 'a')] bool $apply = false,
    ): int
    {
        $io->title('🧹 User episode duplicates' . ($apply ? '' : ' (dry run)'));

        $groups = $this->findDuplicateGroups($userSeriesId);
        if (!count($groups)) {
            $io->success('No duplicates found');
            return Command::SUCCESS;
        }

        $tmdbSeasonIds = []; // cache [tmdbId-season] => int[]
        $fixed = $skipped = $deleted = 0;
        $touchedUserSeries = [];

        foreach ($groups as $group) {
            $label = sprintf('User series %d - %s S%02dE%02d', $group['user_series_id'], $group['name'], $group['season_number'], $group['episode_number']);

            $key = $group['tmdb_id'] . '-' . $group['season_number'];
            if (!array_key_exists($key, $tmdbSeasonIds)) {
                $tvSeason = json_decode($this->tmdbService->getTvSeason($group['tmdb_id'], $group['season_number'], 'en-US'), true);
                $tmdbSeasonIds[$key] = isset($tvSeason['episodes']) ? array_column($tvSeason['episodes'], 'id') : null;
            }
            $validIds = $tmdbSeasonIds[$key];
            if ($validIds === null) {
                $io->writeln("⚠️  $label: TMDB season not found, skipped");
                $skipped++;
                continue;
            }

            /** @var UserSeries $userSeries */
            $userSeries = $this->userSeriesRepository->find($group['user_series_id']);
            /** @var UserEpisode[] $userEpisodes */
            $userEpisodes = $this->userEpisodeRepository->findBy(
                ['userSeries' => $userSeries, 'seasonNumber' => $group['season_number'], 'episodeNumber' => $group['episode_number'], 'previousOccurrence' => null],
                ['id' => 'ASC']
            );

            $valid = array_values(array_filter($userEpisodes, fn(UserEpisode $ue) => in_array($ue->getEpisodeId(), $validIds)));
            $watched = array_values(array_filter($userEpisodes, fn(UserEpisode $ue) => $ue->getWatchedAt() !== null));
            $validIdSet = array_unique(array_map(fn(UserEpisode $ue) => $ue->getEpisodeId(), $valid));

            if (!count($valid)) {
                $io->writeln("⚠️  $label: no episode id matches TMDB, skipped");
                $skipped++;
                continue;
            }
            if (count($valid) == count($userEpisodes)) {
                $io->writeln("⚠️  $label: all ids exist on TMDB (" . implode(', ', array_map(fn($ue) => $ue->getEpisodeId(), $userEpisodes)) . "), skipped");
                $skipped++;
                continue;
            }
            if (count($validIdSet) > 1) {
                $io->writeln("⚠️  $label: several valid TMDB ids, skipped");
                $skipped++;
                continue;
            }
            if (count($watched) > 1) {
                $io->writeln("⚠️  $label: several watched episodes, skipped (manual check)");
                $skipped++;
                continue;
            }

            $validId = $validIdSet[0];
            $validAirDate = $valid[0]->getAirDate();
            $keeper = $watched[0] ?? $valid[0];
            $toDelete = array_values(array_filter($userEpisodes, fn(UserEpisode $ue) => $ue !== $keeper));

            $hasOccurrences = array_any($toDelete, fn(UserEpisode $ue) => $this->userEpisodeRepository->count(['previousOccurrence' => $ue]) > 0);
            if ($hasOccurrences) {
                $io->writeln("⚠️  $label: an episode to delete has rewatches, skipped (manual check)");
                $skipped++;
                continue;
            }

            $io->writeln(sprintf('🔧 %s: keep #%d (%d%s), delete %s',
                $label,
                $keeper->getId(),
                $keeper->getEpisodeId(),
                $keeper->getEpisodeId() != $validId ? ' → ' . $validId : '',
                implode(', ', array_map(fn(UserEpisode $ue) => '#' . $ue->getId() . ' (' . $ue->getEpisodeId() . ')', $toDelete))
            ));
            $fixed++;
            $deleted += count($toDelete);

            if (!$apply) {
                continue;
            }

            // Redirection de l'épisode suivant / dernier épisode vu vers l'épisode conservé
            if (in_array($userSeries->getNextUserEpisode(), $toDelete, true)) {
                $userSeries->setNextUserEpisode($keeper);
            }
            if (in_array($userSeries->getLastUserEpisode(), $toDelete, true)) {
                $userSeries->setLastUserEpisode($keeper);
            }
            foreach ($toDelete as $ue) {
                $this->em->remove($ue);
            }
            $this->em->flush();

            if ($keeper->getEpisodeId() != $validId) {
                $this->userEpisodeRepository->replaceEpisodeId($userSeries, $keeper->getEpisodeId(), $validId, $validAirDate ?? $keeper->getAirDate());
                $this->em->flush();
            }
            $touchedUserSeries[$userSeries->getId()] = $userSeries;
        }

        foreach ($touchedUserSeries as $userSeries) {
            $next = $this->userEpisodeRepository->updateNextUserEpisodeIfNeeded($userSeries);
            if ($next) {
                $io->writeln(sprintf('🎯 User series %d: next episode → S%02dE%02d', $userSeries->getId(), $next->getSeasonNumber(), $next->getEpisodeNumber()));
            }
        }

        $io->newLine();
        $io->writeln(sprintf('Groups: %d - fixed: %d - skipped: %d - episodes deleted: %d', count($groups), $fixed, $skipped, $deleted));
        if (!$apply && $fixed) {
            $io->note('Dry run: nothing was changed. Run again with --apply to fix.');
        }

        return Command::SUCCESS;
    }

    private function findDuplicateGroups(?int $userSeriesId): array
    {
        $sql = <<<SQL
            SELECT ue.user_series_id, ue.season_number, ue.episode_number, s.tmdb_id, s.name
            FROM user_episode ue
                INNER JOIN user_series us ON us.id = ue.user_series_id
                INNER JOIN series s ON s.id = us.series_id
            WHERE ue.previous_occurrence_id IS NULL
            SQL;
        $params = [];
        if ($userSeriesId) {
            $sql .= ' AND ue.user_series_id = :userSeriesId';
            $params['userSeriesId'] = $userSeriesId;
        }
        $sql .= ' GROUP BY ue.user_series_id, ue.season_number, ue.episode_number, s.tmdb_id, s.name HAVING COUNT(*) > 1';
        $sql .= ' ORDER BY ue.user_series_id, ue.season_number, ue.episode_number';

        return $this->em->getConnection()->fetchAllAssociative($sql, $params);
    }
}
