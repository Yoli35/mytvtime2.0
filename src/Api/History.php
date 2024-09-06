<?php

namespace App\Api;

use App\Entity\User;
use App\Repository\SettingsRepository;
use App\Repository\UserEpisodeRepository;
use App\Service\DateService;
use App\Service\ImageConfiguration;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/history', name: 'api_history_')]
class History extends AbstractController
{
    public function __construct(
        private readonly ImageConfiguration    $imageConfiguration,
        private readonly DateService           $dateService,
        private readonly SettingsRepository    $settingsRepository,
        private readonly UserEpisodeRepository $userEpisodeRepository
    )
    {
        // Inject dependencies if needed
    }

    #[Route('/menu', name: 'menu', methods: ['POST'])]
    public function load(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);
        $type = $data['type'];

        $settings = $this->settingsRepository->findOneBy(['user' => $user, 'name' => 'seriesHistory']);
        $count = $settings->getData()['count'] ?? 10;
        $settings->setData(['list' => $type, 'count' => $count]);
        $this->settingsRepository->save($settings, true);

        $history = array_map(function ($item) use ($user) {
            $item['url'] = $this->generateUrl('app_series_season', ['id' => $item['id'], 'slug' => $item['slug'], 'seasonNumber' => $item['seasonNumber']]);
            $item['posterPath'] = $item['posterPath'] ? $this->imageConfiguration->getCompleteUrl($item['posterPath'], 'poster_sizes', 2) : null;
            $item['lastWatchAt'] = $this->dateService->formatDateRelativeLong($item['lastWatchAt'], "UTC"/*$user->getTimezone() ?? 'Europe/Paris'*/, $user->getPreferredLanguage() ?? 'fr');
            return $item;
        }, $this->userEpisodeRepository->seriesHistoryForTwig($user, $user->getCountry() ?? 'FR', $user->getPreferredLanguage() ?? 'fr', $type, $count));
//        dump($history);
        return $this->json([
            'ok' => true,
            'list' => $history,
            'type' => $type
        ]);
    }
}