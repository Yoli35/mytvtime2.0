<?php

namespace App\Api;

use App\Entity\SeriesWatchLink;
use App\Repository\SeriesRepository;
use App\Repository\SeriesWatchLinkRepository;
use App\Repository\WatchProviderRepository;
use App\Service\ImageConfiguration;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/api/series/direct/link', name: 'api_series_direct_link_')]
class SeriesDirectLink extends AbstractController
{
    public function __construct(
        private readonly ImageConfiguration        $imageConfiguration,
        private readonly SeriesRepository          $seriesRepository,
        private readonly SeriesWatchLinkRepository $seriesWatchLinkRepository,
        private readonly WatchProviderRepository   $watchProviderRepository,
    )
    {
    }

    #[Route('/create', name: 'create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);
        $seriesId = $data['seriesId'];
        $url = $data['url'];
        $name = $data['name'];
        $providerId = $data['provider'];
        if ($providerId == "") $providerId = null;
        $series = $this->seriesRepository->findOneBy(['id' => $seriesId]);

        $watchLink = new SeriesWatchLink($url, $name, $series, $providerId);
        $this->seriesWatchLinkRepository->save($watchLink, true);

        return $this->json([
            'ok' => true,
        ]);
    }

    #[Route('/read/{id}', name: 'read', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function read(SeriesWatchLink $seriesWatchLink): Response
    {
        $provider = $this->watchProviderRepository->findOneBy(['providerId' => $seriesWatchLink->getProviderId()]);
        return $this->json([
            'ok' => true,
            'body' => [
                'link' => [
                    'name' => $seriesWatchLink->getName(),
                    'provider' => [
                        'id' => $provider->getProviderId(),
                        'name' => $provider->getProviderName(),
                        'logoPath' => $provider->getLogoPath() ? $this->imageConfiguration->getCompleteUrl($provider->getLogoPath(), 'logos', 3) : null,
                    ],
                    'url' => $seriesWatchLink->getUrl(),
                ]]
        ]);
    }

    #[Route('/update/{id}', name: 'update', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function update(Request $request, SeriesWatchLink $watchLink): Response
    {
        $data = json_decode($request->getContent(), true);
        $url = $data['url'];
        $name = $data['name'];
        $providerId = $data['provider'];
        if ($providerId == "") $providerId = null;

        $watchLink->setUrl($url);
        $watchLink->setName($name);
        $watchLink->setProviderId($providerId);
        $this->seriesWatchLinkRepository->save($watchLink, true);

        return $this->json([
            'ok' => true,
        ]);
    }

    #[Route('/delete/{id}', name: 'delete', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function delete(SeriesWatchLink $watchLink): Response
    {
        $this->seriesWatchLinkRepository->delete($watchLink);

        return $this->json([
            'ok' => true,
        ]);
    }
}