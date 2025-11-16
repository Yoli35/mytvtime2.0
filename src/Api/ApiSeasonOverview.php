<?php

namespace App\Api;

use App\Entity\SeasonLocalizedOverview;
use App\Entity\Series;
use App\Repository\SeasonLocalizedOverviewRepository;
use App\Repository\SeriesRepository;
use App\Repository\SourceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/api/season/overview', name: 'api_season_overview_')]
class ApiSeasonOverview extends AbstractController
{
    public function __construct(
        private readonly SeriesRepository                  $seriesRepository,
        private readonly SeasonLocalizedOverviewRepository $seasonLocalizedOverviewRepository,
        private readonly SourceRepository                  $sourceRepository,
    )
    {
    }

    #[Route('/add/{id}', name: 'add', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function add(Request $request, Series $series): Response
    {
        $data = json_decode($request->getContent(), true);
        $overviewId = $data['overviewId'];
        $overviewId = $overviewId == "-1" ? null : intval($overviewId);
        $overviewType = $data['type'];
        $seasonNumber = $data['seasonNumber'];
        $overview = $data['overview'];
        $locale = $data['locale'];
        $source = null;

        if ($overviewType == "additional") {
            $sourceId = $data['source'];
            $source = $this->sourceRepository->findOneBy(['id' => $sourceId]);
        }
        if ($overviewType == "localized") {
            if ($overviewId) {
                $seasonLocalizedOverview = $this->seasonLocalizedOverviewRepository->findOneBy(['id' => $overviewId]);
                $seasonLocalizedOverview->setOverview($overview);
                $seasonLocalizedOverview->setSource($source);
                $this->seasonLocalizedOverviewRepository->save($seasonLocalizedOverview, true);
            } else {
                $seasonLocalizedOverview = new SeasonLocalizedOverview($series, $seasonNumber, $overview, $locale, $source);
                $this->seasonLocalizedOverviewRepository->save($seasonLocalizedOverview, true);
                $overviewId = $seasonLocalizedOverview->getId();
            }
        }

        return $this->json([
            'success' => true,
            'id' => $overviewId,
            'source' => $source ? ['id' => $source->getId(), 'name' => $source->getName(), 'path' => $source->getPath(), 'logoPath' => $source->getLogoPath()] : null,
        ]);
    }

    #[Route('/remove/{id}', name: 'remove', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function remove(?SeasonLocalizedOverview $overview): Response
    {
        if ($overview) {
            $series = $overview->getSeries();
            $series->removeSeasonLocalizedOverview($overview);
            $this->seasonLocalizedOverviewRepository->remove($overview);
            $this->seriesRepository->save($series, true);
        }

        return $this->json([
            'success' => true,
        ]);
    }
}