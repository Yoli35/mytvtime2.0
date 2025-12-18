<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserListRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/{_locale}/user/list', name: 'app_user_list_', requirements: ['_locale' => 'en|fr|ko'])]
final class UserListController extends AbstractController
{
    public function __construct(
        private readonly UserListRepository $userListRepository,
    )
    {
    }

    #[Route('/index', name: 'index')]
    public function index(#[CurrentUser] User $user, Request $request): Response
    {
        $userLists = $this->userListRepository->getUserLists($user);
        dump($userLists);
        $firstListContent = array_map(function ($s) {
            $s['poster_path'] = $s['poster_path'] ? '/series/posters' . $s['poster_path'] : null;
            $s['sln_name'] = $s['localized_name'] ?: $s['name'];
            $s['sln_slug'] = $s['localized_slug'] ?: $s['slug'];
            $s['is_series_in_list'] = true;
            return $s;
        }, $this->userListRepository->getListContent($user, $userLists[0]['id'], $user->getPreferredLanguage() ?? $request->getLocale()));
        dump($firstListContent);
        return $this->render('user_list/index.html.twig', [
            'lists' => $userLists,
            'firstListContent' => $firstListContent,
        ]);
    }
}
