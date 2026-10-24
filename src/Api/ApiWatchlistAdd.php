<?php

namespace App\Api;

use App\Entity\User;
use App\Repository\MovieRepository;
use App\Service\MovieService;
use App\Service\SeriesService;
use Closure;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\DependencyInjection\Attribute\AutowireMethodOf;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Ajout d'une série ou d'un film depuis les champs de recherche (appel ajax),
 * sans redirection vers la page du média.
 */
#[IsGranted('ROLE_USER')]
#[Route('/api/watchlist/add', name: 'api_watchlist_add_')]
readonly class ApiWatchlistAdd
{
    private const array LOCALES = ['fr', 'en', 'ko'];

    public function __construct(
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure             $json,
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure             $generateUrl,
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure             $getUser,
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure             $renderView,
        private LocaleSwitcher      $localeSwitcher,
        private MovieRepository     $movieRepository,
        private MovieService        $movieService,
        private SeriesService       $seriesService,
        private TranslatorInterface $translator,
    )
    {
    }

    #[Route('/series/{id}', name: 'series', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function series(int $id): Response
    {
        /** @var User $user */
        $user = ($this->getUser)();
        $result = $this->seriesService->addSeriesToWatchlist($user, $id);
        $series = $result['series'];

        return ($this->json)([
            'ok' => true,
            'alreadyAdded' => $result['alreadyAdded'],
            'message' => $this->translator->trans($result['alreadyAdded'] ? 'Series already added to your watchlist' : 'Series added to your watchlist'),
            'url' => ($this->generateUrl)('app_tv_series', [
                'id' => $series->getId(),
                'slug' => $result['localizedSlug'] ?: $series->getSlug(),
            ]),
        ]);
    }

    #[Route('/movie/{id}', name: 'movie', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function movie(Request $request, int $id): Response
    {
        /** @var User $user */
        $user = ($this->getUser)();
        $data = json_decode($request->getContent(), true) ?? [];
        $result = $this->movieService->addMovieToWatchlist($user, $id);

        $response = [
            'ok' => true,
            'alreadyAdded' => $result['alreadyAdded'],
            'message' => $this->translator->trans($result['alreadyAdded'] ? 'Movie already added to your watchlist' : 'Movie added to your watchlist'),
            'url' => ($this->generateUrl)('app_movie_show', ['id' => $result['userMovie']->getId()]),
        ];

        // Ajout depuis la page "Mes films" : la liste est rendue à nouveau pour mettre la page à jour.
        // Les routes /api n'ont pas de _locale, on utilise celle de la page.
        if ($data['watchlist'] ?? false) {
            $locale = in_array($data['locale'] ?? null, self::LOCALES) ? $data['locale'] : 'fr';
            $response['watchlist'] = $this->localeSwitcher->runWithLocale($locale, fn() => $this->renderMovieWatchlist($user));
        }

        return ($this->json)($response);
    }

    private function renderMovieWatchlist(User $user): array
    {
        $filters = $this->movieService->getWatchlistFilters($user);
        $filters['page'] = 1;
        $userMovieCount = $this->movieRepository->countMovieCards($user, $filters);
        $totalPages = ceil($userMovieCount / $filters['limit']);

        return [
            'html' => ($this->renderView)('_blocks/movie/_watchlist.html.twig', [
                'userMovies' => $this->movieService->getWatchlistCards($user, $filters),
                'userMovieTotalCount' => $this->movieRepository->countMovieCards($user, ['title' => '']),
            ]),
            'userMovieCount' => $userMovieCount,
            'paginationSections' => array_map(fn(int $index) => ($this->renderView)('_blocks/_pagination.html.twig', [
                'index' => $index,
                'current_page' => 1,
                'total_pages' => $totalPages,
                'route' => 'app_movie_index',
            ]), [1, 2]),
        ];
    }
}
