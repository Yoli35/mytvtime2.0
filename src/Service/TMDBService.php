<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

class TMDBService
{
    private HttpClientInterface $client;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    public function imageConfiguration(): ?string
    {
        $request = "https://api.themoviedb.org/3/configuration";
        return $this->getResults($request);
    }

    public function searchMulti($page, $query, $locale): ?string
    {
        $request = "https://api.themoviedb.org/3/search/multi?language=$locale&page=$page&query=$query&include_adult=false";
        return $this->getResults($request);
    }

    public function searchMovie(string $searchString): ?string
    {
        $request = "https://api.themoviedb.org/3/search/movie?$searchString";
        return $this->getResults($request);
    }

    public function searchPerson(string $searchString): ?string
    {
        $request = "https://api.themoviedb.org/3/search/person?$searchString";
        return $this->getResults($request);
    }

    public function searchTv(string $searchString): ?string
    {
        $request = "https://api.themoviedb.org/3/search/tv?$searchString";
        return $this->getResults($request);
    }

    public function getTv(int $showId, string $locale, ?array $details = null): ?string
    {
        $request = "https://api.themoviedb.org/3/tv/$showId?language=$locale";
        if ($details) {
            $request .= "&append_to_response=" . implode(',', $details);
        }
        return $this->getResults($request);
    }

    public function getAllTvImages(int $showId): ?string
    {
        $request = "https://api.themoviedb.org/3/tv/$showId/images";
        return $this->getResults($request);
    }

    public function getTvLists(int $showId): ?string
    {
        $request = "https://api.themoviedb.org/3/tv/$showId/lists";
        return $this->getResults($request);
    }

    public function getList(int $listId, int $page = 1, string $language = 'fr-FR'): ?string
    {
        $request = "https://api.themoviedb.org/4/list/$listId?language=$language&page=$page";
        return $this->getResults($request);
    }

    public function getFilterTv(string $filterString): ?string
    {
        $request = "https://api.themoviedb.org/3/discover/tv?$filterString";
        return $this->getResults($request);
    }

    public function getFilterMovie(string $filterString): ?string
    {
        $request = "https://api.themoviedb.org/3/discover/movie?$filterString";
        return $this->getResults($request);
    }

    public function getTvWatchProviderList(string $language = 'fr-FR', ?string $region = null): ?string
    {
        $request = "https://api.themoviedb.org/3/watch/providers/tv?language=$language";
        if ($region) {
            $request .= "&watch_region=$region";
        }
        return $this->getResults($request);
    }

    public function getTvSimilar(int $tvId, string $language = 'en-US'): ?string
    {
        $request = "https://api.themoviedb.org/3/tv/$tvId/similar?language=$language";
        return $this->getResults($request);
    }

    public function getSeries(string $kind, int $page, string $locale, ?string $timezone = null): ?string
    {
        $request = "https://api.themoviedb.org/3/tv/$kind?language=$locale&page=$page";
        if ($timezone) {
            $request .= "&timezone=$timezone";
        }
        return $this->getResults($request);
    }

    public function getTvSeason(int $tvId, int $seasonNumber, string $locale, ?array $details = null): ?string
    {
        $request = "https://api.themoviedb.org/3/tv/$tvId/season/$seasonNumber?language=$locale";
        if ($details) {
            $request .= "&append_to_response=" . implode(',', $details);
        }
        return $this->getResults($request);
    }

    public function getTvSeasonChanges(int $seasonId, string $endDate, string $startDate): ?string
    {
        $request = "https://api.themoviedb.org/3/tv/season/$seasonId/changes?end_date=$endDate&page=1&start_date=$startDate";
        return $this->getResults($request);
    }

    public function getTvEpisode(int $tvId, int $seasonNumber, int $episodeNumber, string $locale, ?array $details = null): ?string
    {
        $request = "https://api.themoviedb.org/3/tv/$tvId/season/$seasonNumber/episode/$episodeNumber&language=$locale";
        if ($details) {
            $request .= '&append_to_response=' . implode(',', $details);
        }
        return $this->getResults($request);
    }

    public function getTvKeywords(int $tvId): ?string
    {
        $request = "https://api.themoviedb.org/3/tv/$tvId/keywords";
        return $this->getResults($request);
    }

    // Uncomment if external IDs are needed in season page
//    public function getTvExternalIds(int $tvId): ?string
//    {
//        $request = "https://api.themoviedb.org/3/tv/$tvId/external_ids";
//        return $this->getResults($request);
//    }

    public function getNetworkDetails(int $networkId): ?string
    {
        $request = "https://api.themoviedb.org/3/network/$networkId";
        return $this->getResults($request);
    }

    public function getPopularPeople(string $locale, int $page = 1): ?string
    {
        $request = "https://api.themoviedb.org/3/person/popular?language=$locale&page=$page";
        return $this->getResults($request);
    }

    public function getPerson(int $id, string $locale, ?string $append = null): ?string
    {
        $request = "https://api.themoviedb.org/3/person/$id?language=$locale";
        if ($append) {
            $request .= "&append_to_response=" . $append;
        }
        return $this->getResults($request);
    }

    public function getMovie(int $movieId, string $locale, ?array $details = null): ?string
    {
        // with_release_type values:
        // 1 = Premiere,
        // 2 = Theatrical (limited),
        // 3 = Theatrical,
        // 4 = Digital,
        // 5 = Physical,
        // 6 = TV
        $request = "https://api.themoviedb.org/3/movie/$movieId?language=$locale";
        if ($details) {
            $request .= "&append_to_response=" . implode(',', $details);
        }
        return $this->getResults($request);
    }

    public function getMovieKeywords(int $movieId): ?string
    {
        $request = "https://api.themoviedb.org/3/movie/$movieId/keywords";
        return $this->getResults($request);
    }

    public function getMovieCollection(int $collectionId): ?string
    {
        $request = "https://api.themoviedb.org/3/collection/$collectionId";
        return $this->getResults($request);
    }

    public function getMovieImages(int $movieId, ?string $language = null): ?string
    {
        $request = "https://api.themoviedb.org/3/movie/$movieId/images";
        if ($language) $request .= "?language=$language";
        return $this->getResults($request);
    }

    public function getMovieExtras(int $movieID, string $extra, string $params): ?string
    {
        $request = "https://api.themoviedb.org/3/movie/$movieID/$extra?$params";
        return $this->getResults($request);
    }

    public function getSeriesExtras(int $seriesID, string $extra, string $params): ?string
    {
        $request = "https://api.themoviedb.org/3/tv/$seriesID/$extra?$params";
        return $this->getResults($request);
    }

    public function getResults(string $request): string
    {
        try {
            $response = $this->client->request(
                'GET',
                $request,
                [
                    'headers' =>
                        [
                            'Authorization' => 'Bearer ' . $this->getBearer(),
                            'accept' => 'application/json',
                        ],
                ]
            );
            try {
                return $response->getContent();
            } catch (Throwable $e) {
                return json_encode(['error' => 'Response error: ' . $e->getMessage() . ' - code: ' . $e->getCode()]);
            }
        } catch (Throwable $e) {
            return json_encode(['error' => 'Request error: ' . $e->getMessage() . ' - code: ' . $e->getCode()]);
        }
    }

    public function getBearer(): string
    {
        return $_ENV['TMDB_BEARER'];
    }
}
