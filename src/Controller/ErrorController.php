<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\HistoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** @method User|null getUser() */
final class ErrorController extends AbstractController
{
    public function __construct(private readonly HistoryRepository $historyRepository)
    {
    }

    #[Route('/error', name: 'app_error')]
    public function show(Request $request): Response
    {
        $user = $this->getUser();
        $lastVisited = $user ? $this->historyRepository->getLastVisitedBeforeError($user) : null;

        return $this->render('error/index.html.twig', [
            'history' => $lastVisited,
        ]);
    }
}
