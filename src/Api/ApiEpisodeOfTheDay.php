<?php

namespace App\Api;

use App\Entity\User;
use App\Repository\UserEpisodeRepository;
use App\Service\ImageConfiguration;
use App\Service\ProviderService;
use Closure;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\DependencyInjection\Attribute\AutowireMethodOf;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/episode', name: 'app_episode_')]
readonly class ApiEpisodeOfTheDay
{
    public function __construct(
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure               $renderView,
        private ImageConfiguration    $imageConfiguration,
        private ProviderService       $providerService,
        private UserEpisodeRepository $userEpisodeRepository,
    )
    {
    }

    #[Route('/today', name: 'today', methods: ['POST'])]
    public function get(#[CurrentUser] User $user, Request $request): JsonResponse
    {
        $locale = $request->getLocale();
        $posterUrl = $this->imageConfiguration->getUrl('poster_sizes', 2);
        $logoUrl = $this->imageConfiguration->getUrl('logo_sizes', 2);
        $date = new DateTime()->format('Y-m-d');

        $inputBag = $request->getPayload();
        $show = $inputBag->getBoolean('show');

        $episodes = array_map(function ($episode) use ($locale, $posterUrl, $logoUrl) {
            $episode['link'] = '/' . $locale . $episode['link'];
            $episode['poster_path'] = $episode['poster_path'] ? $posterUrl . $episode['poster_path'] : null;
            $episode['sbs_time'] = $episode['sbs_time'] ? substr($episode['sbs_time'], 0, 5) : null;
            $episode['wplp'] = $this->providerService->getProviderLogoFullPath($episode['wplp'], $logoUrl);
            $episode['wpbc'] = $episode['wpbc'] ? "#" . $episode['wpbc'] : null;
            $episode['wpc'] = $episode['wpc'] ? "#" . $episode['wpc'] : null;
            return $episode;
        }, $this->userEpisodeRepository->episodesOfTheDayV2($user->getId(), $locale));

        $block = ($this->renderView)('_blocks/episode/_today.html.twig', ['date' => $date, 'episodes' => $episodes, 'show' => $show]);

        return new JsonResponse(['view' => $block]);
    }
}