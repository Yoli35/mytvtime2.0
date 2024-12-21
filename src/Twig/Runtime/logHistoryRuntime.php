<?php

namespace App\Twig\Runtime;

use App\Entity\History;
use App\Entity\User;
use App\Repository\HistoryRepository;
use Twig\Extension\RuntimeExtensionInterface;

class logHistoryRuntime implements RuntimeExtensionInterface
{
    public function __construct(private readonly HistoryRepository $historyRepository)
    {
    }

    public function logHistory(User $user, string $title, string $link): void
    {
//        $titleArr = explode('→', $title);
//        $title = end($titleArr);

        $log = new History($user, $title, $link);
        $this->historyRepository->save($log, true);
    }

    public function getHistory(User $user): array
    {
        return $this->historyRepository->findBy(['user' => $user], ['date' => 'DESC']);
    }
}
