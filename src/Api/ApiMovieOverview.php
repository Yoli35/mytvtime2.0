<?php

namespace App\Api;

use App\Entity\MovieAdditionalOverview;
use App\Entity\MovieLocalizedOverview;
use App\Entity\UserMovie;
use App\Repository\MovieAdditionalOverviewRepository;
use App\Repository\MovieLocalizedOverviewRepository;
use App\Repository\MovieRepository;
use App\Repository\SourceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/api/movie/overview', name: 'api_movie_overview_')]
class ApiMovieOverview extends abstractController
{
    public function __construct(
        private readonly MovieAdditionalOverviewRepository $movieAdditionalOverviewRepository,
        private readonly MovieLocalizedOverviewRepository $movieLocalizedOverviewRepository,
        private readonly MovieRepository $movieRepository,
        private readonly SourceRepository $sourceRepository,
    ) {}

//    #[IsGranted('ROLE_USER')]
    #[Route('/add/{id}', name: 'add', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function add(Request $request, UserMovie $userMovie): Response
    {
        $movie = $userMovie->getMovie();
        $data = json_decode($request->getContent(), true);
        // overviewId: -1 (new) or id (edit)
        $overviewId = $data['overviewId'] ?? "";
        $overviewId = $overviewId == "-1" ? null : intval($overviewId);
        $overviewType = $data['type'];
        $overview = $data['overview'];
        $locale = $data['locale'];
        $source = null;

        if ($overviewType == "additional") {
            $sourceId = $data['source'];
            $source = $this->sourceRepository->findOneBy(['id' => $sourceId]);
            if ($overviewId) {
                $movieAdditionalOverview = $this->movieAdditionalOverviewRepository->findOneBy(['id' => $overviewId]);
                $movieAdditionalOverview->setOverview($overview);
                $movieAdditionalOverview->setSource($source);
                $this->movieAdditionalOverviewRepository->save($movieAdditionalOverview, true);
            } else {
                $seriesAdditionalOverview = new MovieAdditionalOverview($movie, $overview, $locale, $source);
                $this->movieAdditionalOverviewRepository->save($seriesAdditionalOverview, true);
                $overviewId = $seriesAdditionalOverview->getId();
            }
        }
        if ($overviewType == "localized") {
            if ($overviewId) {
                $movieLocalizedOverview = $this->movieLocalizedOverviewRepository->findOneBy(['id' => $overviewId]);
                $movieLocalizedOverview->setOverview($overview);
                $this->movieLocalizedOverviewRepository->save($movieLocalizedOverview, true);
            } else {
                $movieLocalizedOverview = new MovieLocalizedOverview($movie, $overview, $locale);
                $this->movieLocalizedOverviewRepository->save($movieLocalizedOverview, true);
                $overviewId = $movieLocalizedOverview->getId();
            }
        }

        return $this->json([
            'success' => true,
            'id' => $overviewId,
            'source' => $source ? ['id' => $source->getId(), 'name' => $source->getName(), 'path' => $source->getPath(), 'logoPath' => $source->getLogoPath()] : null,
        ]);
    }

    #[Route('/remove/{id}', name: 'remove', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function remove(Request $request, int $id): Response
    {
        $data = json_decode($request->getContent(), true);
        $overviewType = $data['overviewType'];
        if ($overviewType == "additional") {
            $overview = $this->movieAdditionalOverviewRepository->findOneBy(['id' => $id]);
        } else {
            $overview = $this->movieLocalizedOverviewRepository->findOneBy(['id' => $id]);
        }
        if ($overview) {
            $movie = $overview->getMovie();
            if ($overviewType == "additional") {
                $movie->removeMovieAdditionalOverview($overview);
                $this->movieAdditionalOverviewRepository->remove($overview);
            } else {
                $movie->removeMovieLocalizedOverview($overview);
                $this->movieLocalizedOverviewRepository->remove($overview);
            }
            $this->movieRepository->save($movie, true);
        }

        return $this->json([
            'success' => true,
        ]);
    }

}