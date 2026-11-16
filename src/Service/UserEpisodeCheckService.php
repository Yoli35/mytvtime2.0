<?php

namespace App\Service;

use App\Entity\UserEpisode;
use App\Entity\UserSeason;
use App\Entity\UserSeries;
use App\Repository\UserEpisodeRepository;
use App\Repository\UserSeasonRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Vérifie la cohérence des épisodes d'une série de l'utilisateur avec TheMovieDB et, sur demande, les corrige.
 *
 * Détection (sans appel TMDB par saison) :
 * - trous dans la numérotation, numéros ou ids TMDB en double dans une saison ;
 * - nombre d'épisodes d'une saison différent de celui annoncé par TMDB (une seule requête /tv par série) ;
 * - saisons présentes chez TMDB mais absentes chez l'utilisateur (signalées seulement : l'utilisateur
 *   a pu retirer une saison volontairement).
 * Mode "deep" : toutes les saisons sont comparées épisode par épisode, même sans anomalie détectée.
 *
 * Correction d'une saison (le numéro d'épisode fait foi, comme pour les remplacements d'épisodes TMDB) :
 * - épisode dont l'id TMDB ne correspond pas à son numéro → id (et date de diffusion) corrigés,
 *   revisionnages compris, données de visionnage conservées ;
 * - épisode absent → créé (non vu) ;
 * - épisode dont le numéro n'existe plus chez TMDB → supprimé s'il n'a jamais été vu, signalé sinon.
 * - numéro en double (saison renumérotée par TMDB) → l'épisode qui porte le bon id est conservé, les autres
 *   sont supprimés s'ils sont redondants (jamais vus, ou leur id est déjà porté par l'épisode au bon numéro) ;
 *   sinon la saison est ignorée et signalée.
 *
 * Conçu pour être appelé une série à la fois (commande ou outil d'administration en AJAX).
 */
class UserEpisodeCheckService
{
    public const string LEVEL_INFO = 'info';
    public const string LEVEL_WARNING = 'warning';
    public const string LEVEL_FIX = 'fix';
    public const string LEVEL_ERROR = 'error';

    /** Épisode vu qui n'existe plus chez TMDB (souvent : série ancienne, épisodes supprimés par TMDB après l'import) */
    public const string CODE_WATCHED_NOT_ON_TMDB = 'watched_not_on_tmdb';

    /** @var array<int, array|null> Réponses /tv mises en cache par id TMDB (plusieurs utilisateurs peuvent suivre la même série) */
    private array $tvCache = [];

    private array $messages = [];
    private int $tmdbCalls = 0;
    private array $counts = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TMDBService            $tmdbService,
        private readonly UserEpisodeRepository  $userEpisodeRepository,
        private readonly UserSeasonRepository   $userSeasonRepository,
    )
    {
    }

    /**
     * Liste des séries d'utilisateurs à vérifier : [['id' => …, 'series_id' => …, 'tmdb_id' => …, 'name' => …, 'user_id' => …, 'added_at' => …], …]
     * Ordre stable (id de la série de l'utilisateur), pour pouvoir traiter un gros volume par lots (offset / limit).
     *
     * @param array{userSeriesId?: ?int, seriesId?: ?int, userId?: ?int, period?: ?string, date?: ?string} $filters voir buildFilters()
     */
    public function getUserSeriesList(array $filters = [], int $offset = 0, ?int $limit = null): array
    {
        [$where, $params] = $this->buildFilters($filters);
        $sql = "SELECT us.id, s.id AS series_id, s.tmdb_id, IFNULL(sln.name, s.name) AS name, us.user_id, us.added_at
                FROM user_series us
                    INNER JOIN series s ON s.id = us.series_id
                    LEFT JOIN series_localized_name sln ON sln.series_id = s.id AND sln.locale = 'fr'
                WHERE $where
                ORDER BY us.id";
        // Entiers validés : pas de paramètres liés pour LIMIT / OFFSET (non supportés par tous les drivers)
        if ($limit !== null && $limit > 0) {
            $sql .= ' LIMIT ' . $limit . ' OFFSET ' . max(0, $offset);
        } elseif ($offset > 0) {
            $sql .= ' LIMIT 18446744073709551615 OFFSET ' . $offset;
        }

        return $this->em->getConnection()->fetchAllAssociative($sql, $params);
    }

    /**
     * Nombre total de séries d'utilisateurs correspondant aux filtres (sans offset / limit).
     */
    public function countUserSeries(array $filters = []): int
    {
        [$where, $params] = $this->buildFilters($filters);

        return (int)$this->em->getConnection()->fetchOne(
            "SELECT COUNT(*) FROM user_series us INNER JOIN series s ON s.id = us.series_id WHERE $where",
            $params
        );
    }

    /**
     * Bornes [début, fin[ d'une période d'ajout se terminant à la fin de la journée de référence.
     * Ex. : ('1 week', '2026-11-16') → [2026-11-10 00:00, 2026-11-17 00:00[
     *
     * @param string $period "<n> day|week|month|year[s]"
     * @param string|null $date date de référence "Y-m-d" (aujourd'hui par défaut)
     * @return array{DateTimeImmutable, DateTimeImmutable}
     */
    public function getAddedPeriod(string $period, ?string $date = null): array
    {
        if (!preg_match('/^\d+ (day|week|month|year)s?$/', $period)) {
            throw new \InvalidArgumentException(sprintf('Invalid period "%s" (expected "<n> day|week|month|year")', $period));
        }
        $reference = $date ? DateTimeImmutable::createFromFormat('!Y-m-d', $date) : new DateTimeImmutable('today');
        if (!$reference) {
            throw new \InvalidArgumentException(sprintf('Invalid date "%s" (expected Y-m-d)', $date));
        }
        $end = $reference->modify('+1 day');

        return [$end->modify('-' . $period), $end];
    }

    /**
     * Filtres : userSeriesId, seriesId, userId, period (+ date de référence) — voir getAddedPeriod().
     *
     * @return array{string, array}
     */
    private function buildFilters(array $filters): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (!empty($filters['userSeriesId'])) {
            $where[] = 'us.id = :userSeriesId';
            $params['userSeriesId'] = $filters['userSeriesId'];
        }
        if (!empty($filters['seriesId'])) {
            $where[] = 's.id = :seriesId';
            $params['seriesId'] = $filters['seriesId'];
        }
        if (!empty($filters['userId'])) {
            $where[] = 'us.user_id = :userId';
            $params['userId'] = $filters['userId'];
        }
        if (!empty($filters['period'])) {
            [$from, $to] = $this->getAddedPeriod($filters['period'], $filters['date'] ?? null);
            $where[] = 'us.added_at >= :addedFrom AND us.added_at < :addedTo';
            $params['addedFrom'] = $from->format('Y-m-d H:i:s');
            $params['addedTo'] = $to->format('Y-m-d H:i:s');
        }

        return [implode(' AND ', $where), $params];
    }

    /**
     * Vérifie (et corrige si $apply) une série de l'utilisateur.
     *
     * @return array{userSeriesId: int, name: string, status: string, tmdbCalls: int, counts: array, messages: array<array{level: string, text: string, code: ?string}>}
     *     counts : updated, created, deleted, skipped, watchedNotOnTmdb (épisodes vus absents de TMDB, jamais modifiés)
     *     status : 'ok' (rien à signaler), 'issues' (anomalies, rien de modifié), 'fixed' (corrections appliquées), 'error'
     */
    public function checkUserSeries(UserSeries $userSeries, bool $apply = false, bool $deep = false): array
    {
        $this->messages = [];
        $this->tmdbCalls = 0;
        $this->counts = ['updated' => 0, 'created' => 0, 'deleted' => 0, 'skipped' => 0, 'watchedNotOnTmdb' => 0];

        $series = $userSeries->getSeries();
        $result = fn(string $status) => [
            'userSeriesId' => $userSeries->getId(),
            'name' => $series->getName(),
            'status' => $status,
            'tmdbCalls' => $this->tmdbCalls,
            'counts' => $this->counts,
            'messages' => $this->messages,
        ];

        $tv = $this->getTv($series->getTmdbId());
        if (!$tv) {
            $this->message(self::LEVEL_ERROR, 'TMDB series not found');
            return $result('error');
        }

        $issueCount = 0;
        $changes = 0;

        // Nombre d'épisodes / de saisons de la série (hors saison 0 chez TMDB)
        if ($series->getNumberOfEpisode() != ($tv['number_of_episodes'] ?? null) || $series->getNumberOfSeason() != ($tv['number_of_seasons'] ?? null)) {
            $this->message(self::LEVEL_FIX, sprintf('Series: %d episodes / %d seasons → TMDB: %d episodes / %d seasons',
                $series->getNumberOfEpisode(), $series->getNumberOfSeason(), $tv['number_of_episodes'] ?? 0, $tv['number_of_seasons'] ?? 0));
            $issueCount++;
            if ($apply) {
                $series->setNumberOfEpisode($tv['number_of_episodes'] ?? null);
                $series->setNumberOfSeason($tv['number_of_seasons'] ?? null);
                $this->em->flush();
                $changes++;
            }
        }

        $localStats = $this->getLocalSeasonStats($userSeries);
        $tmdbSeasons = [];
        foreach ($tv['seasons'] ?? [] as $s) {
            $tmdbSeasons[$s['season_number']] = $s['episode_count'];
        }

        $seasonsToCheck = [];
        foreach ($tmdbSeasons as $seasonNumber => $episodeCount) {
            $stats = $localStats[$seasonNumber] ?? null;
            if (!$stats) {
                if ($episodeCount > 0) {
                    $this->message(self::LEVEL_INFO, sprintf('Season %d (%d episodes on TMDB) is not in the user series', $seasonNumber, $episodeCount));
                }
                continue;
            }
            $reasons = $this->localAnomalies($stats);
            if ($stats['numbers'] != $episodeCount) {
                $reasons[] = sprintf('%d episodes, %d on TMDB', $stats['numbers'], $episodeCount);
            }
            if (count($reasons)) {
                $this->message(self::LEVEL_WARNING, sprintf('Season %d: %s', $seasonNumber, implode(', ', $reasons)));
                $seasonsToCheck[] = $seasonNumber;
            } elseif ($deep) {
                $seasonsToCheck[] = $seasonNumber;
            }
        }
        foreach ($localStats as $seasonNumber => $stats) {
            if (!array_key_exists($seasonNumber, $tmdbSeasons)) {
                $issueCount++;
                $this->counts['skipped']++;
                if ($stats['watched']) {
                    $this->message(self::LEVEL_WARNING, sprintf('Season %d (%d episodes, %d watched) does not exist on TMDB, left as is',
                        $seasonNumber, $stats['numbers'], $stats['watched']), self::CODE_WATCHED_NOT_ON_TMDB);
                    $this->counts['watchedNotOnTmdb'] += $stats['watched'];
                } else {
                    $this->message(self::LEVEL_WARNING, sprintf('Season %d (%d episodes) does not exist on TMDB, left as is', $seasonNumber, $stats['numbers']));
                }
            }
        }

        foreach ($seasonsToCheck as $seasonNumber) {
            [$seasonIssues, $seasonChanges] = $this->checkSeason($userSeries, $seasonNumber, $apply);
            $issueCount += $seasonIssues;
            $changes += $seasonChanges;
        }

        if ($apply && $changes) {
            $next = $this->userEpisodeRepository->updateNextUserEpisodeIfNeeded($userSeries);
            if ($next) {
                $this->message(self::LEVEL_INFO, sprintf('Next episode → S%02dE%02d', $next->getSeasonNumber(), $next->getEpisodeNumber()));
            }
        }

        if ($issueCount === 0) {
            return $result('ok');
        }
        // "fixed" seulement si quelque chose a vraiment été modifié (pas si tout a été laissé tel quel)
        return $result($apply && $changes ? 'fixed' : 'issues');
    }

    /**
     * Compare une saison de l'utilisateur à TMDB, épisode par épisode.
     *
     * @return array{int, int} [anomalies, modifications appliquées]
     */
    private function checkSeason(UserSeries $userSeries, int $seasonNumber, bool $apply): array
    {
        $tvSeason = json_decode($this->tmdbService->getTvSeason($userSeries->getSeries()->getTmdbId(), $seasonNumber, 'en-US'), true);
        $this->tmdbCalls++;
        if (!$tvSeason || isset($tvSeason['error']) || !isset($tvSeason['episodes'])) {
            $this->message(self::LEVEL_ERROR, sprintf('Season %d: TMDB season not found', $seasonNumber));
            return [1, 0];
        }

        /** @var array<int, array> $tmdbEpisodes indexés par numéro d'épisode */
        $tmdbEpisodes = [];
        foreach ($tvSeason['episodes'] as $episode) {
            if (isset($tmdbEpisodes[$episode['episode_number']])) {
                $this->message(self::LEVEL_ERROR, sprintf('Season %d: duplicate episode number %d on TMDB, skipped', $seasonNumber, $episode['episode_number']));
                $this->counts['skipped']++;
                return [1, 0];
            }
            $tmdbEpisodes[$episode['episode_number']] = $episode;
        }
        $tmdbNumberById = array_column($tvSeason['episodes'], 'episode_number', 'id');
        $finale = $this->getFinaleEpisodeNumber($tvSeason['episodes']);

        /** @var UserEpisode[] $all toutes les occurrences (revisionnages compris) */
        $all = $this->userEpisodeRepository->findBy(['userSeries' => $userSeries, 'seasonNumber' => $seasonNumber], ['id' => 'ASC']);
        /** @var array<int, UserEpisode[]> $byNumber */
        $byNumber = [];
        $originals = [];
        foreach ($all as $ue) {
            $byNumber[$ue->getEpisodeNumber()][] = $ue;
            if ($ue->getPreviousOccurrence() === null) {
                $originals[$ue->getEpisodeNumber()][] = $ue;
            }
        }
        $issues = 0;
        $changes = 0;
        $toDelete = [];

        // 0. Numéros en double (ex. : saison renumérotée par TMDB) : on garde l'épisode qui porte le bon id,
        //    les autres sont supprimés s'ils sont redondants (jamais vus, ou leur id est déjà porté par l'épisode
        //    au bon numéro), sinon la saison est ignorée.
        foreach ($originals as $number => $list) {
            if (count($list) < 2) {
                continue;
            }
            $label = $this->label($seasonNumber, $number);
            $tmdbId = $tmdbEpisodes[$number]['id'] ?? null;
            $keepers = array_values(array_filter($list, fn(UserEpisode $ue) => $ue->getEpisodeId() == $tmdbId));
            $extras = array_values(array_filter($list, fn(UserEpisode $ue) => $ue->getEpisodeId() != $tmdbId));
            $redundant = fn(UserEpisode $ue) => $ue->getWatchedAt() === null
                || array_any($originals[$tmdbNumberById[$ue->getEpisodeId()] ?? -1] ?? [], fn(UserEpisode $o) => $o !== $ue && $o->getEpisodeId() == $ue->getEpisodeId());
            $hasRewatches = fn(UserEpisode $ue) => array_any($all, fn(UserEpisode $o) => $o->getPreviousOccurrence() === $ue);

            if (count($keepers) !== 1 || !array_all($extras, $redundant) || array_any($extras, $hasRewatches)) {
                $this->message(self::LEVEL_WARNING, sprintf('Season %d: duplicate episodes %s (ids %s) cannot be resolved automatically, season skipped (manual check)',
                    $seasonNumber, $label, implode(', ', array_map(fn(UserEpisode $ue) => $ue->getEpisodeId(), $list))));
                $this->counts['skipped']++;
                return [1, 0];
            }
            foreach ($extras as $extra) {
                $this->message(self::LEVEL_FIX, sprintf('%s: duplicate #%d (id %d%s) → deleted, #%d (id %d) kept',
                    $label, $extra->getId(), $extra->getEpisodeId(),
                    isset($tmdbNumberById[$extra->getEpisodeId()]) ? ', already on ' . $this->label($seasonNumber, $tmdbNumberById[$extra->getEpisodeId()]) : '',
                    $keepers[0]->getId(), $tmdbId));
                $issues++;
                $this->counts['deleted']++;
                $toDelete[] = $extra;
                if ($apply) {
                    // Épisode suivant / dernier épisode vu redirigés vers l'épisode conservé
                    if ($userSeries->getNextUserEpisode() === $extra) {
                        $userSeries->setNextUserEpisode($keepers[0]);
                    }
                    if ($userSeries->getLastUserEpisode() === $extra) {
                        $userSeries->setLastUserEpisode($keepers[0]);
                    }
                }
                $byNumber[$number] = array_values(array_filter($byNumber[$number], fn(UserEpisode $o) => $o !== $extra));
            }
            $originals[$number] = $keepers;
        }

        // 1. Épisodes de l'utilisateur : id TMDB correspondant au numéro, ou numéro disparu chez TMDB
        foreach ($originals as $number => [$ue]) {
            $label = $this->label($seasonNumber, $number);
            $tmdbEpisode = $tmdbEpisodes[$number] ?? null;

            if (!$tmdbEpisode) {
                $watched = array_any($byNumber[$number], fn(UserEpisode $o) => $o->getWatchedAt() !== null);
                $issues++;
                if ($watched) {
                    // L'id peut encore exister à un autre numéro (ex. : épisode en deux parties fusionné par TMDB)
                    $idNumber = $tmdbNumberById[$ue->getEpisodeId()] ?? null;
                    $this->message(self::LEVEL_WARNING, sprintf('%s (id %d%s) does not exist on TMDB but was watched on %s, left as is',
                        $label, $ue->getEpisodeId(),
                        $idNumber !== null ? ', now ' . $this->label($seasonNumber, $idNumber) . ' on TMDB' : '',
                        $ue->getWatchedAt()?->format('Y-m-d') ?? '?'), self::CODE_WATCHED_NOT_ON_TMDB);
                    $this->counts['skipped']++;
                    $this->counts['watchedNotOnTmdb']++;
                } else {
                    $this->message(self::LEVEL_FIX, sprintf('%s (id %d) does not exist on TMDB → deleted', $label, $ue->getEpisodeId()));
                    $this->counts['deleted']++;
                    $toDelete = array_merge($toDelete, $byNumber[$number]);
                }
                continue;
            }

            $airDate = $tmdbEpisode['air_date'] ? DateTimeImmutable::createFromFormat('!Y-m-d', $tmdbEpisode['air_date']) ?: null : null;
            if ($ue->getEpisodeId() != $tmdbEpisode['id']) {
                $oldNumber = $tmdbNumberById[$ue->getEpisodeId()] ?? null;
                $this->message(self::LEVEL_FIX, sprintf('%s: TMDB id %d (%s) → %d',
                    $label, $ue->getEpisodeId(),
                    $oldNumber !== null ? 'is ' . $this->label($seasonNumber, $oldNumber) . ' on TMDB' : 'not in this TMDB season',
                    $tmdbEpisode['id']));
                $issues++;
                $this->counts['updated']++;
                if ($apply) {
                    foreach ($byNumber[$number] as $occurrence) {
                        $occurrence->setEpisodeId($tmdbEpisode['id']);
                        $occurrence->setAirDate($airDate);
                    }
                    $changes++;
                }
            } elseif ($ue->getAirDate()?->format('Y-m-d') !== $airDate?->format('Y-m-d')) {
                // Mise à jour discrète de la date de diffusion (pas comptée comme anomalie)
                if ($apply) {
                    foreach ($byNumber[$number] as $occurrence) {
                        $occurrence->setAirDate($airDate);
                    }
                    $changes++;
                }
            }
        }

        // 2. Épisodes TMDB absents chez l'utilisateur
        $userSeason = null;
        foreach ($tmdbEpisodes as $number => $tmdbEpisode) {
            if (isset($originals[$number])) {
                continue;
            }
            $label = $this->label($seasonNumber, $number);
            if ($number > $finale) {
                $this->message(self::LEVEL_INFO, sprintf('%s is after the season finale, ignored', $label));
                continue;
            }
            $issues++;
            $this->counts['created']++;
            $this->message(self::LEVEL_FIX, sprintf('%s (id %d) missing → created', $label, $tmdbEpisode['id']));

            $previousWatched = ($originals[$number - 1][0] ?? null)?->getWatchedAt() !== null;
            $nextWatched = ($originals[$number + 1][0] ?? null)?->getWatchedAt() !== null;
            if ($previousWatched && $nextWatched) {
                $this->message(self::LEVEL_WARNING, sprintf('%s will be created as not watched, but the episodes before and after have been watched', $label));
            }

            if ($apply) {
                $userSeason ??= $this->getUserSeason($userSeries, $seasonNumber);
                $ue = new UserEpisode($userSeries, $tmdbEpisode['id'], $seasonNumber, $number, null);
                $ue->setUserSeason($userSeason);
                $ue->setAirDate($tmdbEpisode['air_date'] ? DateTimeImmutable::createFromFormat('!Y-m-d', $tmdbEpisode['air_date']) ?: null : null);
                $this->em->persist($ue);
                $changes++;
            }
        }

        if ($apply && count($toDelete)) {
            $this->deleteUserEpisodes($userSeries, $toDelete);
            $changes++;
        }
        if ($apply && $changes) {
            $this->em->flush();
        }

        return [$issues, $changes];
    }

    /**
     * Supprime des épisodes (jamais vus) : l'épisode suivant / le dernier épisode vu sont d'abord détachés
     * (clés étrangères), puis les revisionnages avant les originaux (clé previous_occurrence).
     *
     * @param UserEpisode[] $userEpisodes
     */
    private function deleteUserEpisodes(UserSeries $userSeries, array $userEpisodes): void
    {
        if (in_array($userSeries->getNextUserEpisode(), $userEpisodes, true)) {
            $userSeries->setNextUserEpisode(null);
        }
        if (in_array($userSeries->getLastUserEpisode(), $userEpisodes, true)) {
            $userSeries->setLastUserEpisode(null);
        }
        $this->em->flush();

        usort($userEpisodes, fn(UserEpisode $a, UserEpisode $b) => $b->getId() <=> $a->getId());
        foreach ($userEpisodes as $ue) {
            $ue->getUserSeason()?->removeUserEpisode($ue);
            $this->em->remove($ue);
            $this->em->flush();
        }
    }

    private function getUserSeason(UserSeries $userSeries, int $seasonNumber): UserSeason
    {
        $userSeason = $this->userSeasonRepository->findOneBy(['userSeries' => $userSeries, 'seasonNumber' => $seasonNumber]);
        if (!$userSeason) {
            $userSeason = new UserSeason($userSeries, $seasonNumber);
            $this->em->persist($userSeason);
            $this->message(self::LEVEL_INFO, sprintf('User season %d created', $seasonNumber));
        }
        return $userSeason;
    }

    /**
     * Statistiques des épisodes originaux (hors revisionnages) par saison.
     *
     * @return array<int, array{total: int, numbers: int, ids: int, min_n: int, max_n: int, watched: int}>
     */
    private function getLocalSeasonStats(UserSeries $userSeries): array
    {
        $sql = 'SELECT season_number, COUNT(*) AS total, COUNT(DISTINCT episode_number) AS numbers,
                       COUNT(DISTINCT episode_id) AS ids, MIN(episode_number) AS min_n, MAX(episode_number) AS max_n,
                       SUM(CASE WHEN watched_at IS NOT NULL THEN 1 ELSE 0 END) AS watched
                FROM user_episode
                WHERE user_series_id = :id AND previous_occurrence_id IS NULL
                GROUP BY season_number';
        $rows = $this->em->getConnection()->fetchAllAssociative($sql, ['id' => $userSeries->getId()]);

        $stats = [];
        foreach ($rows as $row) {
            $stats[(int)$row['season_number']] = array_map('intval', $row);
        }
        return $stats;
    }

    /**
     * Anomalies visibles sans TMDB : trous, numéros en double, ids en double.
     */
    private function localAnomalies(array $stats): array
    {
        $reasons = [];
        if ($stats['total'] > $stats['numbers']) {
            $reasons[] = 'duplicate episode numbers';
        }
        if ($stats['ids'] < $stats['total']) {
            $reasons[] = 'duplicate TMDB ids';
        }
        if ($stats['min_n'] > 1 || $stats['max_n'] - $stats['min_n'] + 1 > $stats['numbers']) {
            $reasons[] = sprintf('gaps in episode numbers (%d episodes, from %d to %d)', $stats['numbers'], $stats['min_n'], $stats['max_n']);
        }
        return $reasons;
    }

    private function getTv(int $tmdbId): ?array
    {
        if (!array_key_exists($tmdbId, $this->tvCache)) {
            $tv = json_decode($this->tmdbService->getTv($tmdbId, 'en-US'), true);
            $this->tmdbCalls++;
            $this->tvCache[$tmdbId] = ($tv && !isset($tv['error']) && isset($tv['seasons'])) ? $tv : null;
        }
        return $this->tvCache[$tmdbId];
    }

    /**
     * Même règle que SeriesService::getFinaleEpisodeNumber : les épisodes après un épisode "finale" ne sont pas ajoutés.
     */
    private function getFinaleEpisodeNumber(array $episodes): int
    {
        foreach ($episodes as $episode) {
            if (($episode['episode_type'] ?? null) === 'finale') {
                return $episode['episode_number'];
            }
        }
        return PHP_INT_MAX;
    }

    private function label(int $seasonNumber, int $episodeNumber): string
    {
        return sprintf('S%02dE%02d', $seasonNumber, $episodeNumber);
    }

    private function message(string $level, string $text, ?string $code = null): void
    {
        $this->messages[] = ['level' => $level, 'text' => $text, 'code' => $code];
    }
}
