<?php

namespace App\Api;

use App\Entity\User;
use App\Repository\UserSeriesRepository;
use App\Service\ImageConfiguration;
use App\Service\ProviderService;
use Closure;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\DependencyInjection\Attribute\AutowireMethodOf;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/provider/ranking', name: 'api_provider_ranking')]
readonly class ApiProviderRanking
{
    public function __construct(
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure              $renderView,
        private ImageConfiguration   $imageConfiguration,
        private ProviderService      $providerService,
        private UserSeriesRepository $userSeriesRepository,
    )
    {
    }

    #[Route('/get', name: 'get', methods: 'POST')]
    public function get(#[CurrentUser] User $user, Request $request): JsonResponse
    {
        $inputBag = $request->getPayload();
        $type = $inputBag->getString('type'); // evo | month
        $percent = $inputBag->getInt('percent') / 100;
        dump($percent);

        $firstDate = $this->userSeriesRepository->getFirstDate($user);
        if ($firstDate === null) {
            return new JsonResponse([], Response::HTTP_NO_CONTENT);
        }
        dump($firstDate);
        $now = new \DateTimeImmutable();
        $rankingStart = new \DateTimeImmutable($firstDate);
        if ($rankingStart > $now) {
            return new JsonResponse([], Response::HTTP_NO_CONTENT);
        }
        $rankingStartString = $rankingStart->format('Y-m-d');
        $rankingEndString = $now->format('Y-m-d');

        // Nombre de jours
        $nbDay = $now->diff($rankingStart)->days;

        if ($type === 'month') {
            // Nombre de mois
            $nbMonth = 12 * $now->diff($rankingStart)->y + $now->diff($rankingStart)->m;
            $p = intval((1.0 - $percent) * $nbMonth);
            dump(['jours' => $nbDay, 'mois' => $nbMonth, 'décalage' => $p]);
            $rankingEnd = $now->modify('-' . $p . ' month');
            $rankingStart = $now->modify('-' . ($p + 1) . ' month');
            if ($rankingEnd > $now) {
                return new JsonResponse([], Response::HTTP_NO_CONTENT);
            }
            $rankingStartString = $rankingStart->format('Y-m-d');
            $rankingEndString = $rankingEnd->format('Y-m-d');
            dump([
                'start' => $rankingStartString,
                'end' => $rankingEndString
            ]);
        }

        if ($type === 'evo') {
            $rankingEnd = $rankingStart->modify('+' . intval($percent * $nbDay) . ' days');
            if ($rankingEnd > $now) {
                return new JsonResponse([], Response::HTTP_NO_CONTENT);
            }
            $rankingEndString = $rankingEnd->format('Y-m-d');
            dump($rankingEndString);
        }

        $logoUrl = $this->imageConfiguration->getUrl('logo_sizes', 2);

        $arr = match ($type) {
            'month' => array_map(function ($provider) use ($logoUrl) {
                $provider['logo_path'] = $this->providerService->getProviderLogoFullPath($provider['logo_path'], $logoUrl);
                return $provider;
            }, $this->userSeriesRepository->getUserEpisodeByProvider($user, $rankingStartString, $rankingEndString)),
            'evo' => array_map(function ($provider) use ($logoUrl) {
                $provider['logo_path'] = $this->providerService->getProviderLogoFullPath($provider['logo_path'], $logoUrl);
                return $provider;
            }, $this->userSeriesRepository->getUserAllEpisodeByProvider($user, $rankingEndString)),
            default => [],
        };

        $block = ($this->renderView)('_blocks/home/_provider_ranking_content.html.twig', [
            'providerArray' => $arr,
            'total' => array_reduce($arr, fn($carry, $item) => $carry + $item['count'], 0),
        ]);

        return new JsonResponse([
            'block' => $block,
            'firstDate' => $firstDate,
            'rankingStartString' => $rankingStartString,
            'rankingEndString' => $rankingEndString
        ]);
    }
}