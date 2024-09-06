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
        dump($data);

        $type = $data['type'] ? 'episode' : 'series';
        $count = intval($data['count']);
        $page = intval($data['page']);
        $vote = $data['vote'];
        $device = $data['device'];
        $provider = $data['provider'];

        $settings = $this->settingsRepository->findOneBy(['user' => $user, 'name' => 'seriesHistory']);
        $last = $settings->getData()["last"];
        $settings->setData(["last" => $last, "list" => $type, "count" => $count, "page" => $page, "vote" => $vote, "device" => $device, "provider" => $provider]);
        $this->settingsRepository->save($settings, true);

        $history = array_map(function ($item) use ($user) {
            $item['url'] = $this->generateUrl('app_series_season', ['id' => $item['id'], 'slug' => $item['slug'], 'seasonNumber' => $item['seasonNumber']]);
            $item['posterPath'] = $item['posterPath'] ? $this->imageConfiguration->getCompleteUrl($item['posterPath'], 'poster_sizes', 2) : null;
            $item['lastWatchAt'] = $this->dateService->formatDateRelativeLong($item['lastWatchAt'], "UTC"/*$user->getTimezone() ?? 'Europe/Paris'*/, $user->getPreferredLanguage() ?? 'fr');
            $item['providerLogoPath'] = $item['providerLogoPath'] ? $this->imageConfiguration->getCompleteUrl($item['providerLogoPath'], 'logo_sizes', 2) : null;
            return $item;
        }, $this->userEpisodeRepository->seriesHistoryForTwig($user, $user->getPreferredLanguage() ?? 'fr', $type, $page, $count));
//        dump($history);
        return $this->json([
            'ok' => true,
            'list' => $history,
            'type' => $type,
            'count' => $count,
            'page' => $page,
            'vote' => $vote,
            'device' => $device,
            'provider' => $provider,
        ]);
    }

    #[Route('/menu/save', name: 'menu_save', methods: ['POST'])]
    public function save(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);

        $settings = $this->settingsRepository->findOneBy(['user' => $user, 'name' => 'seriesHistory']);

        $settings->setData([
            "last" => $settings->getData()["last"],
            "list" =>  $data['type'] ? 'episode' : 'series',
            "count" => intval($data['count']),
            "page" => intval($data['page']),
            "vote" => intval($data['vote']),
            "device" => $data['device'],
            "provider" => $data['provider']
        ]);
        $this->settingsRepository->save($settings, true);

        return $this->json([
            'ok' => true,
        ]);
    }

    #[Route('/menu/last', name: 'menu_last', methods: ['GET'])]
    public function last(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $last = $this->userEpisodeRepository->getLastWatchedEpisode($user);

        return $this->json([
            'ok' => true,
            'last' => $last,
        ]);
    }
}