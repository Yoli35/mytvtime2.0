<?php

namespace App\Api;

use App\Entity\User;
use App\Repository\HistoryRepository;
use App\Service\DateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/api/log', name: 'api_log_')]
class LogMenu extends AbstractController
{
    public function __construct(
        private readonly DateService         $dateService,
        private readonly HistoryRepository   $historyRepository,
        private readonly TranslatorInterface $translator,
    )
    {
    }

    #[Route('/load', name: 'menu', methods: ['POST'])]
    public function load(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $dates = [];
        $logs = array_map(function ($l) use ($user, &$dates) {
            $date = $l->getDate();
            $dateKey = $date->format('Y-m-d');
            if (!key_exists($dateKey, $dates)) {
                // on ajoute la date au format 'relative_medium'
                $dates[$dateKey] = ucfirst($this->dateService->formatDateRelativeMedium(
                    $dateKey,
                    $user->getTimezone() ?? "Europe/Paris",
                    $user->getPreferredLanguage() ?? 'fr'));
            }
            return [
                'id' => $l->getId(),
                'title' => $l->getTitle(),
                'time' => $date->format("H:i"),
                'link' => $l->getLink(),
                'date' => $date,
                'dateKey' => $dateKey,
            ];
        }, $this->historyRepository->findBy(['user' => $user], ['date' => 'DESC'], 50));

        $lastId = $logs[0]['id'] ?? 0;

        return $this->json([
            'ok' => true,
            'logs' => $logs,
            'lastId' => $lastId,
            'count' => sprintf("%d / %d %s", count($logs), $this->historyRepository->count(['user' => $user]), $this->translator->trans('history entries')),
            'dates' => $dates,
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