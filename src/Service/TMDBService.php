<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

class TMDBService
{
    // Clé d'API (v3 auth)
    //      f7e3c5fe794d565b471334c9c5ecaf96
    // Jeton d'accès en lecture à l'API (v4 auth)
    //      eyJhbGciOiJIUzI1NiJ9.eyJhdWQiOiJmN2UzYzVmZTc5NGQ1NjViNDcxMzM0YzljNWVjYWY5NiIsInN1YiI6IjYyMDJiZjg2ZTM4YmQ4MDA5MWVjOWIzOSIsInNjb3BlcyI6WyJhcGlfcmVhZCJdLCJ2ZXJzaW9uIjoxfQ.9-8i4TOkKXtPZE_nkXk1ZvAlbDYgAdtcrCR6R8Dv3Wg

    private HttpClientInterface $client;
    private string $api_key = "f7e3c5fe794d565b471334c9c5ecaf96";

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    public function justWatchPage($tvId): ?string
    {
        try {
            $response = $this->client->request(
                'GET',
                'https://www.themoviedb.org/tv/' . $tvId . '/watch'
            );
            try {
                return $response->getContent();
            } catch (Throwable) {
                return "";
            }
        } catch (Throwable) {
            return "";
        }
    }

    public function imageConfiguration(): ?string
    {
        try {
            $response = $this->client->request(
                'GET',
                'https://api.themoviedb.org/3/configuration?api_key=' . $this->api_key
            );
            try {
                return $response->getContent();
            } catch (Throwable) {
                return "Image Configuration not found";
            }
        } catch (Throwable) {
            return "Image Configuration not found";
        }
    }

    public function trending($mediaType, $timeWindow, $locale): ?string
    {
        try {
            $response = $this->client->request(
                'GET',
                'https://api.themoviedb.org/3/trending/' . $mediaType . '/' . $timeWindow . '?api_key=' . $this->api_key . '&language=' . $locale,
            );
            try {
                return $response->getContent();
            } catch (Throwable) {
                return "";
            }
        } catch (Throwable) {
            return "";
        }
    }

    public function multiSearch($page, $query, $locale): ?string
    {
        try {
            $response = $this->client->request(
                'GET',
                'https://api.themoviedb.org/3/search/multi?api_key=' . $this->api_key . '&language=' . $locale . '&page=' . $page . '&query=' . $query . '&include_adult=false'
            );

            try {
                return $response->getContent();
            } catch (Throwable) {
                return "";
            }
        } catch (Throwable) {
            return "";
        }
    }

    public function getTv(int $showId, string $locale, array $details = null): ?string
    {
        $request = 'https://api.themoviedb.org/3/tv/' . $showId . '?api_key=' . $this->api_key . '&language=' . $locale;
        if ($details) {
            $request .= '&append_to_response=' . implode(',', $details);
        }
        try {
            $response = $this->client->request(
                'GET',
                $request,
            );
            try {
                return $response->getContent();
            } catch (Throwable) {
                return "";
            }
        } catch (Throwable) {
            return "";
        }
    }

    public function getTvImages(int $showId, bool $addEnglish): ?string
    {
        try {
            $response = $this->client->request(
                'GET',
                'https://api.themoviedb.org/3/tv/' . $showId . '/images?api_key=' . $this->api_key .'&include_image_language=fr' . ($addEnglish ? ',en' : ''),
            );
            try {
                return $response->getContent();
            } catch (Throwable) {
                return "";
            }
        } catch (Throwable) {
            return "";
        }
    }

    public function getAllTvImages(int $showId): ?string
    {
        try {
            $response = $this->client->request(
                'GET',
                'https://api.themoviedb.org/3/tv/' . $showId . '/images?api_key=' . $this->api_key,
            );
            try {
                return $response->getContent();
            } catch (Throwable) {
                return "";
            }
        } catch (Throwable) {
            return "";
        }
    }

    public function getTvLists(int $showId): ?string
    {
        $request = 'https://api.themoviedb.org/3/tv/' . $showId . '/lists?api_key=' . $this->api_key;
        try {
            $response = $this->client->request(
                'GET',
                $request,
            );
            try {
                return $response->getContent();
            } catch (Throwable) {
                return "";
            }
        } catch (Throwable) {
            return "";
        }
    }

    public function getList(int $listId, int $page = 1, string $language = 'fr-FR'): ?string
    {
        $request = "https://api.themoviedb.org/4/list/$listId?api_key=$this->api_key&language=$language&page=$page";
        try {
            $response = $this->client->request(
                'GET',
                $request,
            );
            try {
                return $response->getContent();
            } catch (Throwable) {
                return "";
            }
        } catch (Throwable) {
            return "";
        }
    }

    public function getFilterTv(string $filterString): ?string
    {
        try {
            $response = $this->client->request(
                'GET',
                'https://api.themoviedb.org/3/discover/tv?api_key=' . $this->api_key . $filterString,
            );
            try {
                return $response->getContent();
            } catch (Throwable $exception) {
                return 'Response : ' . $exception->getMessage() . ' - code : ' . $exception->getCode();
            }
        } catch (Throwable $exception) {
            return 'Request : ' . $exception->getMessage() . ' - code : ' . $exception->getCode();
        }
    }

    public function getFilterMovie(string $filterString): ?string
    {
        try {
            $response = $this->client->request(
                'GET',
                'https://api.themoviedb.org/3/discover/movie?api_key=' . $this->api_key . $filterString,
            );
            try {
                return $response->getContent();
            } catch (Throwable $exception) {
                return 'Response : ' . $exception->getMessage() . ' - code : ' . $exception->getCode();
            }
        } catch (Throwable $exception) {
            return 'Request : ' . $exception->getMessage() . ' - code : ' . $exception->getCode();
        }
    }

    public function searchTv(string $searchString): ?string
    {
        try {
            $response = $this->client->request(
                'GET',
                'https://api.themoviedb.org/3/search/tv?api_key=' . $this->api_key . $searchString,
            );
            try {
                return $response->getContent();
            } catch (Throwable) {
                return "";
            }
        } catch (Throwable) {
            return "";
        }
    }

    public function getTvWatchProviderList($language = 'fr-FR', $region = null): ?string
    {
        try {
            $response = $this->client->request(
                'GET',
                'https://api.themoviedb.org/3/watch/providers/tv?language=' . $language . ($region ? '&watch_region=' . $region : '') . '&api_key=' . $this->api_key,
            );
            try {
                return $response->getContent();
            } catch (Throwable) {
                return "";
            }
        } catch (Throwable) {
            return "";
        }
    }

    public function getMovieWatchProviderList($language = 'fr-FR', $region = null): ?string
    {
        try {
            $response = $this->client->request(
                'GET',
                'https://api.themoviedb.org/3/watch/providers/movie?language=' . $language . ($region ? '&watch_region=' . $region : '') . '&api_key=' . $this->api_key,
            );
            try {
                return $response->getContent();
            } catch (Throwable) {
                return "";
            }
        } catch (Throwable) {
            return "";
        }
    }

    public function getTvSimilar($tvId): ?string
    {
        try {
            $response = $this->client->request(
                'GET',
                'https://api.themoviedb.org/3/tv/' . $tvId . '/similar?api_key=' . $this->api_key,
            );
            try {
                return $response->getContent();
            } catch (Throwable) {
                return "";
            }
        } catch (Throwable) {
            return "";
        }
    }

    public function searchPerson($name, $locale): ?string
    {
        try {
            $response = $this->client->request(
                'GET',
                'https://api.themoviedb.org/3/search/person?api_key=' . $this->api_key . '&language=' . $locale . '&query=' . $name . '&include_adult=false',
            );
            try {
                return $response->getContent();
            } catch (Throwable) {
                return "";
            }
        } catch (Throwable) {
            return "";
        }
    }

//    public function getLatest($locale): ?string
//    {
//        try {
//            $response = $this->client->request(
//                'GET',
//                'https://api.themoviedb.org/3/tv/latest?api_key=' . $this->api_key . '&language=' . $locale,
//            );
//            try {
//                return $response->getContent();
//            } catch (Throwable) {
//                return "";
//            }
//        } catch (Throwable) {
//            return "";
//        }
//    }

    public function getSeries($kind, $page, $locale, $timezone = null): ?string
    {
        try {
            $response = $this->client->request(
                'GET',
                'https://api.themoviedb.org/3/tv/' . $kind . '?api_key=' . $this->api_key . '&language=' . $locale . '&page=' . $page . ($timezone ? '&timezone=' . $timezone : ''),
            );
            try {
                return $response->getContent();
            } catch (Throwable) {
                return "";
            }
        } catch (Throwable) {
            return "";
        }
    }

    public function getTvSeason(int $tvId, int $seasonNumber, string $locale, array $details = null): ?string
    {
        $request = 'https://api.themoviedb.org/3/tv/' . $tvId . '/season/' . $seasonNumber . '?api_key=' . $this->api_key . '&language=' . $locale;
        if ($details) {
            $request .= '&append_to_response=' . implode(',', $details);
        }
        try {
            $response = $this->client->request(
                'GET',
                $request,
            );
            try {
                return $response->getContent();
            } catch (Throwable) {
                return "";
            }
        } catch (Throwable) {
            return "";
        }
    }

    public function getTvEpisode($tvId, $seasonNumber, $episodeNumber, $locale, $details = null): ?string
    {
        $request = 'https://api.themoviedb.org/3/tv/' . $tvId . '/season/' . $seasonNumber . '/episode/' . $episodeNumber . '?api_key=' . $this->api_key . '&language=' . $locale;
        if ($details) {
            $request .= '&append_to_response=' . implode(',', $details);
        }
        try {
            $response = $this->client->request(
                'GET',
                $request,
            );
            try {
                return $response->getContent();
            } catch (Throwable) {
                return "";
            }
        } catch (Throwable) {
            return "";
        }
    }

    public function getTvKeywords($tvId): ?string
    {
        $request = "https://api.themoviedb.org/3/tv/$tvId/keywords?api_key=$this->api_key";
        try {
            $response = $this->client->request(
                'GET',
                $request,
            );
            try {
                return $response->getContent();
            } catch (Throwable) {
                return "";
            }
        } catch (Throwable) {
            return "";
        }
    }

    public function getTvEpisodeCredits($tvId, $seasonNumber, $episodeNumber, $locale): ?string
    {
        $request = 'https://api.themoviedb.org/3/tv/' . $tvId . '/season/' . $seasonNumber . '/episode/' . $episodeNumber . '/credits?api_key=' . $this->api_key . '&language=' . $locale;
        try {
            $response = $this->client->request(
                'GET',
                $request,
            );
            try {
                return $response->getContent();
            } catch (Throwable) {
                return "";
            }
        } catch (Throwable) {
            return "";
        }
    }

    public function getMovieReleaseDates($movieId): ?string
    {
        $noReleaseDates = json_encode(["id" => $movieId, "results" => []]);
        try {
            $response = $this->client->request(
                'GET',
                'https://api.themoviedb.org/3/movie/' . $movieId . '/release_dates?api_key=' . $this->api_key,
            );
            try {
                return $response->getContent();
            } catch (Throwable) {
                return $noReleaseDates;
            }
        } catch (Throwable) {
            return $noReleaseDates;
        }
    }

    public function getCountries($locale = 'fr', $country = 'FR'): ?string
    {
        $noCountries = json_encode([]);
        try {
            $response = $this->client->request(
                'GET',
                'https://api.themoviedb.org/3/configuration/countries?api_key=' . $this->api_key . '&language=' . $locale . ($country ? ('-' . $country) : ''),
            );
            try {
                return $response->getContent();
            } catch (Throwable) {
                return $noCountries;
            }
        } catch (Throwable) {
            return $noCountries;
        }
    }

    public function getPopularPeople($locale, $page = 1): ?string
    {
        $noPopularPeople = json_encode([]);
        try {
            $response = $this->client->request(
                'GET',
                'https://api.themoviedb.org/3/person/popular?api_key=' . $this->api_key . '&language=' . $locale . '&page=' . $page,
            );
            try {
                return $response->getContent();
            } catch (Throwable) {
                return $noPopularPeople;
            }
        } catch (Throwable) {
            return $noPopularPeople;
        }
    }

    public function getPerson($id, $locale, $append = ""): ?string
    {
        $noOne = json_encode([]);
        try {
            if (strlen($append)) {
                $response = $this->client->request(
                    'GET',
                    'https://api.themoviedb.org/3/person/' . $id . '?api_key=' . $this->api_key . '&language=' . $locale . '&append_to_response=' . $append
                );
            } else {
                $response = $this->client->request(
                    'GET',
                    'https://api.themoviedb.org/3/person/' . $id . '?api_key=' . $this->api_key . '&language=' . $locale
                );
            }
            try {
                return $response->getContent();
            } catch (Throwable) {
                return $noOne;
            }
        } catch (Throwable) {
            return $noOne;
        }
    }

    public function getPersonCredits($id, $locale): ?string
    {
        $noCredits = json_encode([]);
        try {
            $response = $this->client->request(
                'GET',
                'https://api.themoviedb.org/3/person/' . $id . '/combined_credits?api_key=' . $this->api_key . '&language=' . $locale
            );
            try {
                return $response->getContent();
            } catch (Throwable) {
                return $noCredits;
            }
        } catch (Throwable) {
            return $noCredits;
        }
    }

    public function availableRegions(): ?string
    {
        $noRegions = json_encode([]);
        try {
            $response = $this->client->request(
                'GET',
                'https://api.themoviedb.org/3/watch/providers/regions?api_key=' . $this->api_key
            );
            try {
                return $response->getContent();
            } catch (Throwable) {
                return $noRegions;
            }
        } catch (Throwable) {
            return $noRegions;
        }
    }

    public function getMovie($movieId, $locale, $details = null): ?string
    {
        // with_release_type values: 1 = Premiere, 2 = Theatrical (limited), 3 = Theatrical, 4 = Digital, 5 = Physical, 6 = TV
        $request = 'https://api.themoviedb.org/3/movie/' . $movieId . '?api_key=' . $this->api_key . '&language=' . $locale;
        if ($details) {
            $request .= '&append_to_response=' . implode(',', $details);
        }

        try {
            $response = $this->client->request(
                'GET',
                $request,
            );
            try {
                return $response->getContent();
            } catch (Throwable) {
                return "";
            }
        } catch (Throwable) {
            return "";
        }
    }

    public function getMovieKeywords($movieId): ?string
    {
        $request = "https://api.themoviedb.org/3/movie/$movieId/keywords?api_key=$this->api_key";
        try {
            $response = $this->client->request(
                'GET',
                $request,
            );
            try {
                return $response->getContent();
            } catch (Throwable) {
                return "";
            }
        } catch (Throwable) {
            return "";
        }
    }

    public function getMovieCollection($collectionId): ?string
    {
        $request = "https://api.themoviedb.org/3/collection/$collectionId?api_key=$this->api_key";
        try {
            $response = $this->client->request(
                'GET',
                $request,
            );
            try {
                return $response->getContent();
            } catch (Throwable) {
                return "";
            }
        } catch (Throwable) {
            return "";
        }
    }

    public function getBearer()
    {
        return $_ENV['TMDB_BEARER'];
    }
}
