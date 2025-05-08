<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\MovieRepository;
use App\Repository\ProviderRepository;
use App\Repository\SeriesRepository;
use App\Repository\UserRepository;
use App\Service\ImageConfiguration;
use App\Service\TMDBService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/** @method User|null getUser() */
#[IsGranted('ROLE_ADMIN')]
#[Route('/{_locale}/admin', name: 'admin_', requirements: ['_locale' => 'fr|en|ko'])]
class AdminController extends AbstractController
{

    public function __construct(
        private readonly ImageConfiguration  $imageConfiguration,
        private readonly MovieRepository     $movieRepository,
        private readonly ProviderRepository  $providerRepository,
        private readonly SeriesController    $seriesController,
        private readonly SeriesRepository    $seriesRepository,
        private readonly UserRepository      $userRepository,
        private readonly TMDBService         $tmdbService,
        private readonly TranslatorInterface $translator
    )
    {
    }

    #[Route('/', name: 'index')]
    public function index(): Response
    {
        $users = $this->userRepository->users();
        return $this->render('admin/index.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/users', name: 'users')]
    public function adminUsers(): Response
    {
        $users = $this->userRepository->users();
        return $this->render('admin/index.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/series', name: 'series')]
    public function adminSeries(Request $request): Response
    {
        $sort = $request->query->get('s', 'id');
        $order = $request->query->get('o', 'desc');
        $page = $request->query->getInt('p', 1);
        $limit = $request->query->getInt('l', 25);

        $series = $this->seriesRepository->adminSeries($request->getLocale(), $page, $sort, $order, $limit);
        $seriesCount = $this->seriesRepository->count();
        $pageCount = ceil($seriesCount / $limit);

        $logoUrl = $this->imageConfiguration->getUrl('logo_sizes', 3);
        $series = array_map(function ($s) use ($logoUrl) {
            $s['origin_country'] = json_decode($s['origin_country'], true);
            $s['provider_logo'] = $this->seriesController->getProviderLogoFullPath($s['provider_logo'], $logoUrl);
            return $s;
        }, $series);

        $seriesArr = [];
        foreach ($series as $s) {
            if (!key_exists($s['id'], $seriesArr)) {
                $seriesArr[$s['id']] = $s;
            }
        }

        $paginationLinks = $this->generateLinks($pageCount, $page, $this->generateUrl('admin_series'), [
            's' => $sort,
            'o' => $order,
            'l' => $limit,
        ]);

        return $this->render('admin/index.html.twig', [
            'series' => $seriesArr,
            'pagination' => $paginationLinks,
            'seriesCount' => $seriesCount,
            'page' => $page,
            'limit' => $limit,
            'pageCount' => $pageCount,
            'sort' => $sort,
            'order' => $order,
        ]);
    }

    #[Route('/series/{id}', name: 'series_edit')]
    public function adminSeriesEdit(Request $request, int $id): Response
    {
        $sort = $request->query->get('s', 'id');
        $order = $request->query->get('o', 'desc');
        $page = $request->query->getInt('p', 1);
        $limit = $request->query->getInt('l', 20);

        $series = $this->seriesRepository->adminSeriesById($id);
        if (!$series) {
            throw $this->createNotFoundException('Series not found');
        }

        $tmdbSeries = json_decode(
            $this->tmdbService->getTv(
                $series['tmdb_id'],
                $request->getLocale()
            /*, ["images", "videos", "credits", "watch/providers", "content/ratings", "keywords", "similar", "translations"]*/),
            true);

        $logoUrl = $this->imageConfiguration->getUrl('logo_sizes', 3);
        $series['origin_country'] = json_decode($series['origin_country'], true);

        $seriesAdditionalOverviews = $this->seriesRepository->seriesAdditionalOverviews($id);
        $seriesBroadcastSchedules = $this->seriesRepository->seriesBroadcastSchedules($id);
        foreach ($seriesBroadcastSchedules as &$sbs) {
            $sbs['provider_logo'] = $this->seriesController->getProviderLogoFullPath($sbs['provider_logo'], $logoUrl);
            $sbs['broadcast_dates'] = $this->seriesRepository->seriesBroadcastDates($sbs['id']);
        }
        $seriesImages = $this->seriesRepository->seriesImagesById($id);
        $seriesLocalizedNames = $this->seriesRepository->seriesLocalizedNames($id);
        $seriesLocalizedOverviews = $this->seriesRepository->seriesLocalizedOverviews($id);
        $seriesNetworks = $this->seriesRepository->seriesNetworks($id);
        $seriesNetworks = array_map(function ($sn) use ($logoUrl) {
            $sn['logo_path'] = $this->seriesController->getProviderLogoFullPath($sn['logo_path'], $logoUrl);
            return $sn;
        }, $seriesNetworks);
        $seriesWatchLinks = array_map(function ($swl) use ($logoUrl) {
            $swl['provider_logo'] = $this->seriesController->getProviderLogoFullPath($swl['provider_logo'], $logoUrl);
            return $swl;
        }, $this->seriesRepository->seriesWatchLinks($id));

        $seriesLink = $this->generateSeriesLink($this->generateUrl('admin_series'), [
            'l' => $limit,
            'o' => $order,
            'p' => $page,
            's' => $sort,
        ]);

        dump([
            'seriesLink' => $seriesLink,
            'series' => $series,
            'tmdb_series' => $tmdbSeries,
            'series_additional_overviews' => $seriesAdditionalOverviews,
            'series_broadcast_schedule' => $seriesBroadcastSchedules,
            'series_images' => $seriesImages,
            'series_localized_names' => $seriesLocalizedNames,
            'series_localized_overviews' => $seriesLocalizedOverviews,
            'series_networks' => $seriesNetworks,
            'series_watch_links' => $seriesWatchLinks,
        ]);

        return $this->render('admin/index.html.twig', [
            'seriesLink' => $seriesLink,
            'series' => $series,
            'tmdbSeries' => $tmdbSeries,
            'seriesAdditionalOverviews' => $seriesAdditionalOverviews,
            'seriesBroadcastSchedule' => $seriesBroadcastSchedules,
            'seriesImages' => $seriesImages,
            'seriesLocalizedNames' => $seriesLocalizedNames,
            'seriesLocalizedOverviews' => $seriesLocalizedOverviews,
            'seriesNetworks' => $seriesNetworks,
            'seriesWatchLinks' => $seriesWatchLinks,
        ]);
    }

    #[Route('/series/search/id', name: 'series_search_by_id')]
    public function adminSeriesSearchById(Request $request): Response
    {
        $id = $request->query->get('id');
        if (!$id) {
            throw $this->createNotFoundException('TMDB ID not found');
        }
        $tmdbIds = array_column($this->seriesRepository->adminSeriesTmdbId(), 'tmdb_id');
        if (in_array($id, $tmdbIds)) {
            return $this->redirectToRoute('admin_series_edit', [
                'id' => $this->seriesRepository->adminSeriesByTmdbId($id)['id'],
            ]);
        }
        $tmdbSeries = json_decode(
            $this->tmdbService->getTv($id, 'en-US'),
            true
        );

        if (!$tmdbSeries) {
            throw $this->createNotFoundException('TMDB Series not found');
        }

        return $this->render('admin/index.html.twig', [
            'series' => $tmdbSeries,
            'posterUrl' => $this->imageConfiguration->getUrl('poster_sizes', 3),
            'backdropUrl' => $this->imageConfiguration->getUrl('backdrop_sizes', 3),
        ]);
    }

    #[Route('/series/search/name', name: 'series_search_by_name')]
    public function adminSeriesSearchByName(Request $request): Response
    {
        $name = $request->query->get('name', '');
        $page = $request->query->getInt('p', 1);

        if (!$name) {
            throw $this->createNotFoundException('TMDB ID not found');
        }

        $tmdbSeries = json_decode(
            $this->tmdbService->searchTv("&query=$name&include_adult=false&page=$page"),
            true
        );

        if (!$tmdbSeries) {
            throw $this->createNotFoundException('TMDB Series not found');
        }

        if ($tmdbSeries['total_results'] == 1) {
            return $this->redirectToRoute('admin_series_search_by_id', [
                'id' => $tmdbSeries['results'][0]['id'],
            ]);
        }

        $pagination = $this->generateLinks($tmdbSeries['total_pages'], $page, $this->generateUrl('admin_series_search_by_name'), [
            'name' => $name,
        ]);

        return $this->render('admin/index.html.twig', [
            'name' => $name,
            'seriesList' => $tmdbSeries,
            'pagination' => $pagination,
            'posterUrl' => $this->imageConfiguration->getUrl('poster_sizes', 3),
            'backdropUrl' => $this->imageConfiguration->getUrl('backdrop_sizes', 3),
        ]);
    }

    #[Route('/movies', name: 'movies')]
    public function adminMovies(): Response
    {
        $movies = $this->movieRepository->findAll();
        return $this->render('admin/index.html.twig', [
            'movies' => $movies,
        ]);
    }

    #[Route('/providers', name: 'providers')]
    public function adminProviders(): Response
    {
        $providers = $this->providerRepository->findAll();
        return $this->render('admin/index.html.twig', [
            'providers' => $providers,
        ]);
    }

    public function generateLinks(int $totalPages, int $currentPage, string $route, array $queryParams = []): string
    {
        if ($totalPages <= 1) {
            return ''; // No need to display pagination if there's only one page.
        }

        $paginationHtml = '<div class="pagination">';

        // Add "Previous" button
        if ($currentPage > 1) {
            $paginationHtml .= $this->generateLink($currentPage - 1, $this->translator->trans('Previous page'), $route, $queryParams);
        }

        // Display pages 1-4
        for ($page = 1; $page <= min(4, $totalPages); $page++) {
            $activeClass = ($page === $currentPage) ? ' active' : '';
            $paginationHtml .= $this->generateLink($page, (string)$page, $route, $queryParams, $activeClass);
        }

        // If we're past page 4, add an ellipsis
        if ($totalPages > 4 && $currentPage > 4) {
            $paginationHtml .= '<span>...</span>';
        }

        // Add the current page and a couple of neighbors if we're past page 4
        $startPage = max(5, $currentPage);
        $endPage = min($currentPage + 1, $totalPages - 1);  // Stop one before the last page

        for ($page = $startPage; $page <= $endPage; $page++) {
            $activeClass = ($page === $currentPage) ? ' active' : '';
            $paginationHtml .= $this->generateLink($page, (string)$page, $route, $queryParams, $activeClass);
        }

        // Add ellipsis before the last page, if necessary
        if ($endPage < $totalPages - 1) {
            $paginationHtml .= '<span>...</span>';
        }

        // Add the last page link
        if ($totalPages > 1) {
            $activeClass = ($totalPages === $currentPage) ? ' active' : '';
            $paginationHtml .= $this->generateLink($totalPages, (string)$totalPages, $route, $queryParams, $activeClass);
        }

        // Add "Next" button
        if ($currentPage < $totalPages) {
            $paginationHtml .= $this->generateLink($currentPage + 1, $this->translator->trans('Next page'), $route, $queryParams);
        }

        $paginationHtml .= '</div>';

        return $paginationHtml;
    }

     private function generateLink(int $page, string $label, string $route, array $queryParams, string $activeClass = ''): string
    {
        $queryParams['p'] = $page;
        $url = $route . '?' . http_build_query($queryParams);
        return sprintf('<a href="%s" class="page%s">%s</a>', $url, $activeClass, $label);
    }

    private function generateSeriesLink(string $route, array $queryParams): string
    {
        return $route . '?' . http_build_query($queryParams);
    }
}
