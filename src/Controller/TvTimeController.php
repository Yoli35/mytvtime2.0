<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\TvTimeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/tv/time', name: 'app_tv_time_')]
final class TvTimeController extends AbstractController
{
    public function __construct(
        private readonly TvTimeService        $tvTimeService,
    )
    {
    }

    #[Route('/index', name: 'index')]
    public function index(#[CurrentUser] User $user, Request $request): Response
    {
        $locale = $user->getPreferredLanguage() ?? $request->getLocale();

        $data = $this->tvTimeService->getTvTimeData($user, $locale);

        return $this->render('tv_time/index.html.twig', [
            'tab' => $data['tab'],
            'sort' => $data['sort'],
            'seriesKind' => $data['series-kind'],
            'moviesKind' => $data['movies-kind'],
            'comingKind' => $data['coming-kind'],
            'series' => $data['series']['content'],
            'watchLinks' => $data['series']['watchLinks'],
            'tmdbIds' => $data['series']['tmdbIds'],
            'users' => $data['series']['users'],
            'movies' => $data['movies'],
            'coming' => $data['coming'],
            'noVoteArr' => $data['noVoteArr'],
            'loadCount' => $data['loadCount'],
            'list' => $data['list'],
            'week' => $data['week'],
            'remainingSecondes' => $data['remainingSecondes'],
        ]);
    }
}
