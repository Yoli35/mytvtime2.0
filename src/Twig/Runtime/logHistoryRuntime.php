<?php

namespace App\Twig\Runtime;

use App\Controller\SeriesController;
use App\Entity\History;
use App\Entity\User;
use App\Repository\HistoryRepository;
use Twig\Extension\RuntimeExtensionInterface;

class logHistoryRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly HistoryRepository $historyRepository,
        private readonly SeriesController $seriesController,
    )
    {
    }

    public function logHistory(User $user, string $title, string $link): void
    {
//        $titleArr = explode('→', $title);
//        $title = end($titleArr);

        $date = $this->seriesController->now();
        $log = new History($user, $title, $link, $date);
        $this->historyRepository->save($log, true);
    }

    public function getHistory(User $user): array
    {
        return $this->historyRepository->findBy(['user' => $user], ['date' => 'DESC'], 30);
    }

    public function getHistoryCount(User $user): int
    {
        return $this->historyRepository->count(['user' => $user]);
    }
}
