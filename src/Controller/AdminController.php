<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\MovieRepository;
use App\Repository\ProviderRepository;
use App\Repository\SeriesRepository;
use App\Repository\UserRepository;
use App\Service\ImageConfiguration;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** @method User|null getUser() */
#[IsGranted('ROLE_ADMIN')]
#[Route('/{_locale}/admin', name: 'app_admin_', requirements: ['_locale' => 'fr|en|ko'])]
class AdminController extends AbstractController
{

    public function __construct(
        private readonly ImageConfiguration $imageConfiguration,
        private readonly MovieRepository    $movieRepository,
        private readonly ProviderRepository $providerRepository,
        private readonly SeriesController   $seriesController,
        private readonly SeriesRepository   $seriesRepository,
        private readonly UserRepository     $userRepository,
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
        $sort = $request->query->get('sort', 'id');
        $order = $request->query->get('order', 'desc');
        $page = $request->query->getInt('page', 1);
        $perPage = $request->query->getInt('perPage', 20);

        $series = $this->seriesRepository->adminSeries($request->getLocale(), $page, $sort, $order, $perPage);
        $seriesCount = $this->seriesRepository->count();
        $pageCount = ceil($seriesCount / $perPage);

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

        $paginationLinks = $this->generateLinks($pageCount, $page, $this->generateUrl('app_admin_series'), [
            'sort' => $sort,
            'order' => $order,
        ]);

        return $this->render('admin/index.html.twig', [
            'series' => $seriesArr,
            'pagination' => $paginationLinks,
            'seriesCount' => $seriesCount,
            'page' => $page,
            'perPage' => $perPage,
            'pageCount' => $pageCount,
            'sort' => $sort,
            'order' => $order,
        ]);
    }

    #[Route('/series/{id}', name: 'series_edit')]
    public function adminSeriesEdit(int $id): Response
    {
        $series = $this->seriesRepository->adminSeriesById($id);
        if (!$series) {
            throw $this->createNotFoundException('Series not found');
        }
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

        dump([
            'series' => $series,
            'series_additional_overviews' => $seriesAdditionalOverviews,
            'series_broadcast_schedule' => $seriesBroadcastSchedules,
            'series_images' => $seriesImages,
            'series_localized_names' => $seriesLocalizedNames,
            'series_localized_overviews' => $seriesLocalizedOverviews,
            'series_networks' => $seriesNetworks,
            'series_watch_links' => $seriesWatchLinks,
        ]);

        return $this->render('admin/index.html.twig', [
            'series' => $series,
            'seriesAdditionalOverviews' => $seriesAdditionalOverviews,
            'seriesBroadcastSchedule' => $seriesBroadcastSchedules,
            'seriesImages' => $seriesImages,
            'seriesLocalizedNames' => $seriesLocalizedNames,
            'seriesLocalizedOverviews' => $seriesLocalizedOverviews,
            'seriesNetworks' => $seriesNetworks,
            'seriesWatchLinks' => $seriesWatchLinks,
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

    private const MAX_VISIBLE_PAGES = 5;

    /**
     * Generate pagination links.
     *
     * @param int $totalPages
     * @param int $currentPage
     * @param string $route
     * @param array $queryParams
     * @return string
     */
    public function generateLinks(int $totalPages, int $currentPage, string $route, array $queryParams = []): string
    {
        if ($totalPages <= 1) {
            return ''; // No need to display pagination if there's only one page.
        }

        $paginationHtml = '<div class="pagination">';

        // Add "Previous" button
        if ($currentPage > 1) {
            $paginationHtml .= $this->generateLink($currentPage - 1, 'Previous', $route, $queryParams);
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
            $paginationHtml .= $this->generateLink($currentPage + 1, 'Next', $route, $queryParams);
        }

        $paginationHtml .= '</div>';

        return $paginationHtml;
    }

    /**
     * Helper function to generate a pagination link.
     *
     * @param int $page
     * @param string $label
     * @param string $route
     * @param array $queryParams
     * @param string $activeClass
     * @return string
     */
    private function generateLink(int $page, string $label, string $route, array $queryParams, string $activeClass = ''): string
    {
        $queryParams['page'] = $page;
        $url = $route . '?' . http_build_query($queryParams);
        return sprintf('<a href="%s" class="page%s">%s</a>', $url, $activeClass, $label);
    }
}
