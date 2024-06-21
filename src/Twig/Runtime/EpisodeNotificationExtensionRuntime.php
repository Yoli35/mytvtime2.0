<?php

namespace App\Twig\Runtime;

use App\Entity\User;
use App\Repository\EpisodeNotificationRepository;
use App\Repository\UserEpisodeRepository;
use Twig\Extension\RuntimeExtensionInterface;

readonly class EpisodeNotificationExtensionRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private EpisodeNotificationRepository $episodeNotificationRepository,
        private UserEpisodeRepository         $userEpisodeRepository
    )
    {
        // Inject dependencies if needed
    }

    public function countEpisodeNotifications(User $user): int
    {
        $count = $this->episodeNotificationRepository->episodeNotificationCount($user);
        return $count[0]['count'];
    }

    public function listEpisodeNotifications(User $user): array
    {
        return $this->episodeNotificationRepository->episodeNotificationList($user);
    }

    public function listEpisodeOfTheDay(User $user, string $country = 'FR', string $locale = 'fr'): array
    {
        return $this->userEpisodeRepository->episodesOfTheDayForTwig($user, $country, $locale);
    }
}
