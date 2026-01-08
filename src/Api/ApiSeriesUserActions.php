<?php

namespace App\Api;

use App\Entity\UserPinnedSeries;
use App\Entity\UserSeries;
use App\Repository\UserEpisodeRepository;
use App\Repository\UserPinnedSeriesRepository;
use App\Repository\UserSeriesRepository;
use Closure;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\DependencyInjection\Attribute\AutowireMethodOf;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/api/series/user', name: 'api_series_user_')]
readonly class ApiSeriesUserActions
{
    public function __construct(
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure                    $json,
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure                    $getUser,
        private UserEpisodeRepository      $userEpisodeRepository,
        private UserPinnedSeriesRepository $userPinnedSeriesRepository,
        private UserSeriesRepository       $userSeriesRepository,
    )
    {
    }

    #[Route('/pinned/{id}', name: 'pinned', requirements: ['id' => Requirement::DIGITS])]
    public function pin(Request $request, UserSeries $userSeries): Response
    {
        $user = ($this->getUser)();
        $data = json_decode($request->getContent(), true);
        $newPinnedValue = $data['newStatus'];

        if ($newPinnedValue) {
            $userPinnedSeries = new UserPinnedSeries($user, $userSeries);
            $this->userPinnedSeriesRepository->add($userPinnedSeries, true);
        } else {
            $userPinnedSeries = $this->userPinnedSeriesRepository->findOneBy(['user' => $user, 'userSeries' => $userSeries]);
            $this->userPinnedSeriesRepository->remove($userPinnedSeries, true);
        }

        return ($this->json)([
            'ok' => true,
        ]);
    }

    #[Route('/favorite/{id}', name: 'favorite', requirements: ['id' => Requirement::DIGITS])]
    public function favorite(Request $request, UserSeries $userSeries): Response
    {
        $data = json_decode($request->getContent(), true);
        $newFavoriteValue = $data['favorite'];
        $userSeries->setFavorite($newFavoriteValue);
        $this->userSeriesRepository->save($userSeries, true);

        return ($this->json)([
            'ok' => true,
        ]);
    }

    #[Route('/rating/{id}', name: 'rating', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function rating(Request $request, UserSeries $userSeries): Response
    {
        $data = json_decode($request->getContent(), true);
        $rating = $data['rating'];
        $userSeries->setRating($rating);
        $this->userSeriesRepository->save($userSeries, true);

        return ($this->json)([
            'ok' => true,
        ]);
    }

    #[Route('/remove/{id}', name: 'remove', requirements: ['id' => Requirement::DIGITS])]
    public function remove(UserSeries $userSeries): Response
    {
        $userSeries->setLastUserEpisode(null);
        $userSeries->setNextUserEpisode(null);
        $userEpisodes = $this->userEpisodeRepository->findBy(['userSeries' => $userSeries]);
        foreach ($userEpisodes as $userEpisode) {
            $userEpisode->setPreviousOccurrence(null);
        }
        foreach ($userEpisodes as $userEpisode) {
            $this->userEpisodeRepository->remove($userEpisode);
        }
        $this->userEpisodeRepository->flush();
        $this->userSeriesRepository->remove($userSeries);

        return ($this->json)([
            'ok' => true,
        ]);
    }
}