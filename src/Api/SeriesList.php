<?php

namespace App\Api;

use App\Entity\User;
use App\Repository\SeriesRepository;
use App\Repository\UserListRepository;
use Closure;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\DependencyInjection\Attribute\AutowireMethodOf;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** @method User|null getUser() */
#[Route('/api/series/list', name: 'api_series_list_')]
readonly class SeriesList
{
    public function __construct(
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure               $json,
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure               $getUser,
        private SeriesRepository      $seriesRepository,
        private UserListRepository    $userListRepository,
    )
    {
    }

    #[Route('/get', name: 'get', methods: ['POST'])]
    public function update(Request $request): Response
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
        ]);
    }

}