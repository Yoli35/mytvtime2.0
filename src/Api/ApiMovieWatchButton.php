<?php

namespace App\Api;

use App\Entity\User;
use App\Entity\UserMovie;
use App\Repository\UserMovieRepository;
use App\Service\DateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/** @method User|null getUser() */
#[Route('/api/movie/watch/button', name: 'api_movie_watch_button_')]
class ApiMovieWatchButton extends AbstractController
{
    public function __construct(
        private readonly DateService         $dateService,
        private readonly UserMovieRepository $repository,
    )
    {
    }

    #[Route('/add/{id}', name: 'add', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function add(UserMovie $userMovie): Response
    {
        $now = $this->dateService->getNowImmutable($userMovie->getUser()->getTimezone() ?? 'Europe/Paris');

        $viewed = $userMovie->getViewed();
        if ($viewed > 0) {
            $viewArray = $userMovie->getViewArray();
            $lastViewedAt = $userMovie->getLastViewedAt();
            $viewArray[] = $lastViewedAt->format("Y-m-d H:i:s");
            $viewArray = array_unique($viewArray);
            $userMovie->setViewArray($viewArray);
        }
        $viewed++;
        $userMovie->setViewed($viewed);
        $userMovie->setLastViewedAt($now);
        $this->repository->save($userMovie, true);

        return $this->json([
            'ok' => true,
            'viewed' => $viewed,
            'dateString' => $now->format("Y-m-d H:i:s"),
            'lastViewedAt' => ucfirst($this->dateService->formatDateRelativeLong($now->format("Y-m-d"), 'UTC', $userMovie->getUser()->getPreferredLanguage() ?? 'fr'))
        ]);
    }

    #[Route('/touch/{id}', name: 'touch', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function touch(Request $request, UserMovie $userMovie): Response
    {
        $data = json_decode($request->getContent(), true);
        $lastDatetimeString = $data['lastDatetime'];
        $newDatetimeString = $data['newDatetime'];
        $newDateString = substr($newDatetimeString, 0, 10);
        // Remove the "T" in $lastDatetimeString et $newDatetimeString
        $lastDatetimeString = str_replace('T', ' ', $lastDatetimeString);
        $newDatetimeString = str_replace('T', ' ', $newDatetimeString);

        if ($lastDatetimeString == $userMovie->getLastViewedAt()->format('Y-m-d H:i:s')) {
            $timezone = $userMovie->getUser()->getTimezone() ?? 'Europe/Paris';
            $datetime = $this->dateService->newDateImmutable($newDatetimeString, $timezone);
            $userMovie->setLastViewedAt($datetime);
        } else {
            $viewArray = $userMovie->getViewArray();
            // Trouver dans viewArray la date correspondant à $lastDatetimeString et la remplacer par $newDatetimeString
            $index = array_search($lastDatetimeString, $viewArray);
            if ($index !== false) {
                $viewArray[$index] = $newDatetimeString;
            }
            $userMovie->setViewArray($viewArray);
        }
        $this->repository->save($userMovie, true);

        return $this->json([
            'ok' => true,
            'dateString' => $newDatetimeString,
            'date' => ucfirst($this->dateService->formatDateRelativeLong($newDateString, 'UTC', $userMovie->getUser()->getPreferredLanguage() ?? 'fr'))
        ]);
    }

    #[Route('/remove/{id}', name: 'remove', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function remove(Request $request, UserMovie $userMovie): Response
    {
        $data = json_decode($request->getContent(), true);
        $datetimeString = $data['date'];

        $viewArray = $userMovie->getViewArray();
        // trouver dateString dans 'last_viewed_at' et 'view_array' et mettre à jour 'last_viewed_at' avec la date la plus récente dans 'view_array' ou mettre à 'null'
        if ($datetimeString == $userMovie->getLastViewedAt()->format('Y-m-d H:i:s')) {
            $timezone = $userMovie->getUser()->getTimezone() ?? 'Europe/Paris';
            $datetimeString = array_pop($viewArray);
            if ($datetimeString) {
                $datetime = $this->dateService->newDateImmutable($datetimeString, $timezone);
                $userMovie->setLastViewedAt($datetime);
                $userMovie->setViewArray($viewArray);
                $userMovie->setViewed($userMovie->getViewed() - 1);
            } else {
                $userMovie->setLastViewedAt(null);
                $userMovie->setViewed(0);
            }
        } else {
            $index = array_search($datetimeString, $viewArray);
            if ($index !== false) {
                // Remove element at index
                array_splice($viewArray, $index, 1);
                $userMovie->setViewArray($viewArray);
                $userMovie->setViewed($userMovie->getViewed() - 1);
            }
        }
        $this->repository->save($userMovie, true);

        return $this->json([
            'ok' => true,
            'viewed' => $userMovie->getViewed(),
        ]);
    }
}