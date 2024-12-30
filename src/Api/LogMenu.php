<?php

namespace App\Api;

use App\Entity\User;
use App\Repository\HistoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/log', name: 'api_log_')]
class LogMenu extends AbstractController
{
    public function __construct(
        private readonly HistoryRepository    $historyRepository,
    )
    {
    }

    #[Route('/load', name: 'menu', methods: ['POST'])]
    public function load(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $log = array_map(function($l) {
            return [
                'id' => $l->getId(),
                'title' => $l->getTitle(),
                'link' => $l->getLink(),
                'date' => $l->getDate(),
            ];
        }, $this->historyRepository->findBy(['user' => $user], ['date' => 'DESC'], 30));
        dump($log);
        return $this->json([
            'ok' => true,
            'log' => $log,
        ]);
    }

    #[Route('/last', name: 'menu_last', methods: ['GET'])]
    public function last(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $last = $this->historyRepository->findOneBy(['user' => $user], ['date' => 'DESC']);

        return $this->json([
            'ok' => true,
            'last' => $last->getId(),
        ]);
    }
}