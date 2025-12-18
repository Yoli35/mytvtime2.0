<?php

namespace App\Api;

use App\Entity\UserList;
use App\Repository\SeriesRepository;
use App\Repository\UserListRepository;
use Closure;
use DateTimeImmutable;
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

    #[Route('/create', name: 'create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $inputBag = $request->getPayload();
        $user = ($this->getUser)();
        if (!$user) {
            return ($this->json)([
                'ok' => true,
                'create' => false,
                'error' => 'Unauthorized',
            ]);
        }
        $name = $inputBag->get('name', '');
        $description = $inputBag->get('description', '');
        $public = $inputBag->getBoolean('public', false);
        $add = $inputBag->getBoolean('add', false);
        $tmdbId = $inputBag->getInt('tmdbId', -1);
        if ($add && $tmdbId === -1) {
            return ($this->json)([
                'ok' => true,
                'create' => false,
                'error' => 'Invalid parameters',
            ]);
        }
        if (empty($name)) {
            return ($this->json)([
                'ok' => true,
                'create' => false,
                'error' => 'Name cannot be empty',
            ]);
        }
        $userList = new UserList($user, $name, $description, $public);
        $this->userListRepository->save($userList, true);

        if ($add) {
            $series = $this->seriesRepository->findOneBy(['tmdbId' => $tmdbId]);
            if ($series) {
                $userList->addSeriesList($series);
                $userList->setUpdatedAt(new DateTimeImmutable());
                $this->userListRepository->save($userList, true);
            }
        }

        return ($this->json)([
            'ok' => true,
            'create' => true,
            'final_state' => $add ? $userList->getSeriesList()->contains($series) : null,
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
        $userList->setUpdatedAt(new DateTimeImmutable());
        $this->userListRepository->save($userList, true);

        return ($this->json)([
            'ok' => true,
            'toggle' => true,
            'final_state' => $userList->getSeriesList()->contains($series),
        ]);
    }
}