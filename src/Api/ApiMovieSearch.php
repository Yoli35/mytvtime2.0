<?php

namespace App\Api;

use App\Service\ImageConfiguration;
use App\Service\TMDBService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/movie/search', name: 'api_movie_search_')]
class ApiMovieSearch extends AbstractController
{
    public function __construct(
        private readonly ImageConfiguration $imageConfiguration,
        private readonly TMDBService $tmdbService,
    )
    {
    }

    #[Route('/advanced', name: 'advanced', methods: ['POST'])]
    public function get(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);
        dump($data);
        // * "cast" => [▶]
        // * "castSeparator" => ","
        // * "crew" => [▶]
        // * "crewSeparator" => ","
        // * "genreSeparator" => ","
        // * "genres" => [▶]
        // * "keywordSeparator" => ","
        // * "keywords" => [▶]
        // * "originCountry" => "US"
        // * "originLanguage" => "en"
        // * "region" => "FR"
        // * "releaseYear" => "2025"
        // * "releaseYearAfter" => "2024"
        // * "releaseYearBefore" => "2026"
        // * "sort": "primary_release_date.desc"
        $page = 1;
        $cast = count($data['cast']) ? implode($data['castSeparator'], $data['cast']) : null;
        $crew = count($data['crew']) ? implode($data['crewSeparator'], $data['crew']) : null;
        $genres = count($data['genres']) ? implode($data['genreSeparator'], $data['genres']) : null;
        $keywords = count($data['keywords']) ? implode($data['keywordSeparator'], $data['keywords']) : null;
        $originCountry = $data['originCountry'];
        $originLanguage = $data['originLanguage'];
        $language = $request->getLocale();
        $country = $data['region'];
        $date = strlen($data['releaseYear']) ? $data['releaseYear'] : null;
        $startDate = strlen($data['releaseYearAfter']) ? $data['releaseYearAfter'] : null;
        $endDate = strlen($data['releaseYearBefore']) ? $data['releaseYearBefore'] : null;
        $sort = $data['sort'];

        $filterString = "include_adult=false&include_video=false&language=$language&page=$page&sort_by=$sort";
        if ($cast) $filterString .= "&with_cast=$cast";
        if ($crew) $filterString .= "&with_crew=$crew";
        if ($genres) $filterString .= "&with_genres=$genres";
        if ($keywords) $filterString .= "&with_keywords=$keywords";
        if ($originCountry) $filterString .= "&with_origin_country=$originCountry";
        if ($originLanguage) $filterString .= "&with_original_language=$originLanguage";
        if ($country) $filterString .= "&watch_region=$country";
        if ($date) $filterString .= "&primary_release_year=$date";
        if ($startDate) $filterString .= "&primary_release_date.gte=$startDate";
        if ($endDate) $filterString .= "&primary_release_date.lte=$endDate";
        dump($filterString);
        // ?include_adult=false&include_video=false&language=en-US&page=1&sort_by=primary_release_date.desc&with_cast=933238%2C117642%2C1190668'

        $results = json_decode($this->tmdbService->getFilterMovie($filterString), true);
        dump($results);

        $posterUrl = $this->imageConfiguration->getUrl('poster_sizes', 5);
        dump($posterUrl);

        return $this->json([
            'ok' => true,
            'results' => $results,
            'posterUrl' => $posterUrl,
        ]);
    }
}

// certification                     string      use in conjunction with region
// certification.gte                 string      use in conjunction with region
// certification.lte                 string      use in conjunction with region
// certification_country             string      use in conjunction with the certification, certification.gte and certification.lte filters
// include_adult                     boolean     Defaults to false
// include_video                     boolean     Defaults to false
// language                          string      Defaults to en-US
// page                              int32       Defaults to 1
// primary_release_year           *  int32
// primary_release_date.gte       *  date
// primary_release_date.lte       *  date
// region                         *  string
// release_date.gte                  date
// release_date.lte                  date
// sort_by                           string      enum (original_title.asc,original_title.desc,popularity.asc,popularity.desc,revenue.asc,revenue.desc,
//                                               primary_release_date.asc,title.asc,title.desc,primary_release_date.desc,vote_average.asc,vote_average.desc,
//                                               vote_count.asc,vote_count.desc)
//                                               Defaults to popularity.desc
// vote_average.gte                  float
// vote_average.lte                  float
// vote_count.gte                    float
// vote_count.lte                    float
// watch_region                      string
//                                   use in conjunction with with_watch_monetization_types or with_watch_providers
// with_cast                      *  string can be a comma (AND) or pipe (OR) separated query
// with_companies                    string can be a comma (AND) or pipe (OR) separated query
// with_crew                      *  string can be a comma (AND) or pipe (OR) separated query
// with_genres                    *  string can be a comma (AND) or pipe (OR) separated query
// with_keywords                  *  string can be a comma (AND) or pipe (OR) separated query
// with_origin_country            *  string
// with_original_language         *  string
// with_people                       string can be a comma (AND) or pipe (OR) separated query
// with_release_type                 int32
//                                   possible values are: [1, 2, 3, 4, 5, 6] can be a comma (AND) or pipe (OR) separated query, can be used in conjunction with region
// with_runtime.gte                  int32
// with_runtime.lte                  int32
// with_watch_monetization_types     string
//                                   possible values are: [flatrate, free, ads, rent, buy] use in conjunction with watch_region, can be a comma (AND) or pipe (OR) separated query
// with_watch_providers              string use in conjunction with watch_region, can be a comma (AND) or pipe (OR) separated query
// without_companies                 string
// without_genres                    string
// without_keywords                  string
// without_watch_providers           string
// year                              int32