<?php

namespace App\Api;

use App\Repository\SeriesRepository;
use App\Repository\UserListRepository;
use Closure;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\DependencyInjection\Attribute\AutowireMethodOf;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/series/list', name: 'api_series_list_')]
readonly class SeriesList
{
    public function __construct(
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure            $json,
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure            $getUser,
        private SeriesRepository   $seriesRepository,
        private UserListRepository $userListRepository,
    )
    {
    }

    #[Route('/get/lists', name: 'get_lists', methods: ['POST'])]
    public function get(Request $request): Response
    {
        $inputBag = $request->getPayload();
        $user = ($this->getUser)();
        if (!$user) {
            return ($this->json)([
                'ok' => true,
                'get' => false,
            ]);
        }
        $tmdbId = $inputBag->getInt('tmdbId', -1);
        if ($tmdbId === -1) {
            return ($this->json)([
                'ok' => true,
                'get' => false,
                'error' => 'Invalid parameter.',
            ]);
        }
        $series = $this->seriesRepository->findOneBy(['tmdbId' => $tmdbId]);
        $userLists = array_map(function ($list) {
            return [
                'id' => $list->getId(),
                'name' => $list->getName(),
                'description' => $list->getDescription(),
                'updatedAt' => $list->getUpdatedAt()?->format(DATE_ATOM),
            ];
        }, $this->userListRepository->findBy(['user' => $user], ['updatedAt' => 'DESC']));
        $seriesLists = array_map(function ($list) {
            return [
                'id' => $list->getId(),
                'name' => $list->getName(),
                'description' => $list->getDescription(),
                'updatedAt' => $list->getUpdatedAt()?->format(DATE_ATOM),
            ];
        }, $series ? $series->getUserLists()->toArray() : []);

        return ($this->json)([
            'ok' => true,
            'get' => true,
            'userLists' => $userLists,
            'seriesLists' => $seriesLists,
            'seriesListIds' => $series ? array_map(fn($list) => $list->getId(), $series->getUserLists()->toArray()) : [],
        ]);
    }

    #[Route('/toggle', name: 'toggle', methods: ['POST'])]
    public function toggle(Request $request): Response
    {
        $inputBag = $request->getPayload();
        $userListId = $inputBag->getInt('userListId');
        $seriesId = $inputBag->getInt('seriesId');
        if (!$userListId || !$seriesId) {
            return ($this->json)([
                'ok' => true,
                'toggle' => false,
                'error' => 'Invalid parameters',
            ]);
        }
        $userList = $this->userListRepository->find($userListId);
        $series = $this->seriesRepository->findOneBy(['tmdbId' => $seriesId]);
        $user = ($this->getUser)();
        if (!$user || !$userList || $userList->getUser()->getId() !== $user->getId() || !$series) {
            return ($this->json)([
                'ok' => true,
                'toggle' => false,
                'error' => 'Unauthorized',
            ]);
        }
        if ($userList->getSeriesList()->contains($series)) {
            $userList->removeSeriesList($series);
        } else {
            $userList->addSeriesList($series);
        }
        $this->userListRepository->save($userList, true);

        return ($this->json)([
            'ok' => true,
            'toggle' => true,
            'final_state' => $userList->getSeriesList()->contains($series),
        ]);
    }
}