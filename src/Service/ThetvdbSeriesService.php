<?php

namespace App\Service;

readonly class ThetvdbSeriesService
{
    public function __construct(
        private ThetvdbService $thetvdbService,
    )
    {
    }

    public function getTvdbEpisodes(int $tvdbId, int $seasonNumber): array
    {
        // test the tv db api
        $tvdbEpisodeArr = [];
        if ($tvdbId) {
            $result = json_decode($this->thetvdbService->seriesExtended($tvdbId), true);
            if (!$result) {
                return [];
            }
            /*dump($result);*/
            if (isset($result['data']['artworks'])) {
                $backdrop = array_find($result['data'], fn($artwork) => $artwork['type'] == 3);
            } else {
                $backdrop = null;
            }
            foreach ($result['data']['seasons'] as $tvdbSeason) {
                if ($tvdbSeason['number'] != $seasonNumber || $tvdbSeason['type']['type'] !== 'official') continue;
                $seasonExtendedResult = json_decode($this->thetvdbService->seasonExtended($tvdbSeason['id']), true);
                /*dump($seasonExtendedResult);*/
                /*$seasonTranslationsResult = json_decode($this->thetvdbService->seasonTranslations($tvdbSeason['id'], 'en'), true);*/
                /*dump($seasonTranslationsResult);*/
                /*dump($seasonExtendedResult);*/
                foreach ($seasonExtendedResult['data']['episodes'] as $episode) {
                    /*$tvdbEpisode = json_decode($this->thetvdbService->episode($episode['id']), true);
                    $tvdbEpisodeExtended = json_decode($this->thetvdbService->episodeExtended($episode['id']), true);
                    dump([
                        'ep' =>$tvdbEpisode,
                        'ep ext' =>$tvdbEpisodeExtended
                    ]);*/
                    $tvdbEpisodeArr[] = [
                        'episode' => sprintf('S%02dE%02d - %s', $episode['seasonNumber'], $episode['number'], $episode['name']),
                        'aired' => $episode['aired'],
                        'episodeId' => $episode['id'],
                        'episodeOverview' => $episode['overview'],
                        'image' => $episode['image'] ?: $backdrop['image'] ?? $result['data']['image'] ?? null,
                    ];
                }
            }
        }
        /*dump(['tvdbEpisodeArr' => $tvdbEpisodeArr]);*/
        return $tvdbEpisodeArr;
    }

    /*public function dumpTvdb($tvdbId): void
    {
        // test the tv db api
        if ($tvdbId) {
            $result = json_decode($this->thetvdbService->series($tvdbId), true);
            dump(['series' => $result]);
            $result = json_decode($this->thetvdbService->seriesExtended($tvdbId), true);
            dump(['series extended' => $result]);
            foreach ($result['data']['seasons'] as $season) {
                $seasonExtendedResult = json_decode($this->thetvdbService->seasonExtended($season['id']), true);
                dump(['season extended' => $seasonExtendedResult]);
                foreach ($seasonExtendedResult['data']['episodes'] as $episode) {
                    dump([
                        'episode' => sprintf('S%02dE%02d - %s', $episode['seasonNumber'], $episode['number'], $episode['name']),
                        'aired' => $episode['aired'],
                        'episodeId' => $episode['id'],
                        'episodeOverview' => $episode['overview'],
                    ]);
                }
            }
        }
    }*/
}