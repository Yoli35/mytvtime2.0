<?php

namespace App\Api;

use App\Repository\UserMovieRepository;
use App\Repository\UserSeriesRepository;
use App\Service\ImageConfiguration;
use App\Service\TMDBService;
use Closure;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\DependencyInjection\Attribute\AutowireMethodOf;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/api/search', name: 'api_search_')]
readonly class ApiSearch
{
    public function __construct(
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure              $json,
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure              $generateUrl,
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure              $getUser,
        private ImageConfiguration   $imageConfiguration,
        private TMDBService          $tmdbService,
        private TranslatorInterface  $translator,
        private UserMovieRepository  $userMovieRepository,
        private UserSeriesRepository $userSeriesRepository,
    )
    {
    }

    #[Route('/multi', name: 'multi', methods: ['POST'])]
    public function multi(Request $request): Response
    {
        $user = ($this->getUser)();
        $locale = 'en-US';
        $data = json_decode($request->getContent(), true);
        $query = $data['query'];

        if ($query === 'init') {
            $results = [];
        } else {
            $multi = json_decode($this->tmdbService->searchMulti(1, $query, $locale), true);
            $tvIds = array_map(function ($result) {
                return $result['id'];
            }, array_filter($multi['results'], function ($result) {
                return $result['media_type'] == 'tv';
            }));
            $movieIds = array_map(function ($result) {
                return $result['id'];
            }, array_filter($multi['results'], function ($result) {
                return $result['media_type'] == 'movie';
            }));

            $usIds = array_column($this->userSeriesRepository->userSeriesByTMDBIds($user, $tvIds), 'id');
            $umIds = array_column($this->userMovieRepository->userMoviesByTMDBIds($user, $movieIds), 'id');

            $results = array_map(function ($result) use ($usIds, $umIds) {
                if (key_exists('media_type', $result)) {
                    if ($result['media_type'] == 'tv') {
                        $result['add_this'] = !in_array($result['id'], $usIds);
                        $result['add_this_link'] = ($this->generateUrl)('app_series_add', ['id' => $result['id']]);
                    }
                    if ($result['media_type'] == 'movie') {
                        $result['add_this'] = !in_array($result['id'], $umIds);
                        $result['add_this_link'] = ($this->generateUrl)('app_movie_add', ['id' => $result['id']]);
                    }
                }
                return $result;
            }, $multi['results']);
        }

        return ($this->json)([
            'ok' => true,
            'results' => $results,
            'posterUrl' => $this->imageConfiguration->getUrl('poster_sizes', 3),
            'profileUrl' => $this->imageConfiguration->getUrl('profile_sizes', 3),
        ]);
    }

    #[Route('/db/movie', name: 'fetch_search_movie', methods: ['POST'])]
    public function dbmovie(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);
        $query = $data['query'];

        $movies = array_map(function ($movie) {
            $movie['display_title'] = $movie['localized_name'] ?? $movie['title'] . ' - ' . ($movie['release_date'] ? substr($movie['release_date'], 0, 4) : $this->translator->trans('No date')) . ($movie['original_title'] && $movie['original_title'] !== $movie['title'] ? " - ({$movie['original_title']})" : '');
            return $movie;
        }, $this->userMovieRepository->searchMoviesByTitle(($this->getUser)(), $query));

        return ($this->json)([
            'ok' => true,
            'results' => $movies,
        ]);
    }

    #[Route('/tmdb/movie', name: 'tmdb_movie', methods: ['POST'])]
    public function movie(Request $request): Response
    {
        $user = ($this->getUser)();
        $data = json_decode($request->getContent(), true);
        $query = $data['query'];

        $searchString = "query=$query&include_adult=false&page=1";
        $movies = json_decode($this->tmdbService->searchMovie($searchString), true);

        $ids = array_column($movies['results'], 'id');
        $umIds = array_column($this->userMovieRepository->userMoviesByTMDBIds($user, $ids), 'id');

        $movies = array_map(function ($movie) use ($umIds) {
            $movie['add_this'] = !in_array($movie['id'], $umIds);
            $movie['add_this_link'] = ($this->generateUrl)('app_movie_add', ['id' => $movie['id']]);
            return $movie;
        }, $movies['results']);

        return ($this->json)([
            'ok' => true,
            'results' => $movies,
        ]);
    }

    #[Route('/tmdb/movie/{id}', name: 'tmdb_movie_id', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function movieById(int $id): Response
    {
        $movie = json_decode($this->tmdbService->getMovie($id, 'en-US', ['translations']), true);

        return ($this->json)([
            'ok' => true,
            'results' => [$movie],
        ]);
    }

    #[Route('/db/tv', name: 'db_tv', methods: ['POST'])]
    public function dbtv(Request $request): Response
    {
        $user = ($this->getUser)();
        $locale = $user->getPreferredLanguage() ?? $request->getLocale();
        $data = json_decode($request->getContent(), true);
        $query = $data['query'];
        $series = $this->userSeriesRepository->searchSeries($user, $query, $locale);

        return ($this->json)([
            'ok' => true,
            'results' => $series,
        ]);
    }

    #[Route('/tmdb/tv', name: 'tmdb_tv', methods: ['POST'])]
    public function tv(Request $request): Response
    {
        $user = ($this->getUser)();
        $data = json_decode($request->getContent(), true);
        $query = $data['query'];

        $searchString = "query=$query&include_adult=false&page=1";
        $series = json_decode($this->tmdbService->searchTv($searchString), true);

        $ids = array_column($series['results'], 'id');
        $usIds = array_column($this->userSeriesRepository->userSeriesByTMDBIds($user, $ids), 'id');

        $series = array_map(function ($serie) use ($usIds) {
            $serie['add_this'] = !in_array($serie['id'], $usIds);
            $serie['add_this_link'] = ($this->generateUrl)('app_series_add', ['id' => $serie['id']]);
            return $serie;
        }, $series['results']);

        return ($this->json)([
            'ok' => true,
            'results' => $series,
        ]);
    }

    #[Route('/tmdb/tv/{id}', name: 'tmdb_tv_id', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function seriesById(int $id): Response
    {
        $series = json_decode($this->tmdbService->getTv($id, 'en-US', ['translations']), true);

        return ($this->json)([
            'ok' => true,
            'results' => [$series],
        ]);
    }

    #[Route('/people', name: 'people', methods: ['POST'])]
    public function people(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);
        $query = $data['query'];
        $searchString = "query=$query&include_adult=false&page=1";
        $people = json_decode($this->tmdbService->searchPerson($searchString), true);

        return ($this->json)([
            'ok' => true,
            'results' => $people['results'],
        ]);
    }
}