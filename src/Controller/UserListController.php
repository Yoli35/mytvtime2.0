<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserListRepository;
use App\Service\UserListService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/{_locale}/user/list', name: 'app_user_list_', requirements: ['_locale' => 'en|fr|ko'])]
final class UserListController extends AbstractController
{
    public function __construct(
        private TranslatorInterface $translator,
        private readonly UserListRepository $userListRepository,
        private readonly UserListService $userListService,
    )
    {
    }

    #[Route('/index', name: 'index')]
    public function index(#[CurrentUser] User $user, Request $request): Response
    {
        $locale = $user->getPreferredLanguage() ?? $request->getLocale();
        $userLists = $this->userListRepository->getUserLists($user);
        $result = $this->userListService->getUserList($user, $userLists[0]['id'], $locale);

        return $this->render('user_list/index.html.twig', [
            'lists' => $userLists,
            'infos' => $result['userList'],
            'seriesList' => $result['userListContent'],
            'years' => $result['years'],
            'translations' => [
                'All' => $this->translator->trans('All'),
            ]
        ]);
    }
}
