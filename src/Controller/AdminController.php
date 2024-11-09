<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/{_locale}/admin', name: 'app_admin_', requirements: ['_locale' => 'fr|en|kr'])]
class AdminController extends AbstractController
{

    public function __construct(
        private readonly UserRepository $userRepository,
    )
    {
    }

    #[Route('/', name: 'index')]
    public function index(): Response
    {
        $users = $this->userRepository->users();
        return $this->render('admin/index.html.twig', [
            'users' => $users,
        ]);
    }
}
