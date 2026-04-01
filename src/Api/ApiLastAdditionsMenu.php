<?php

namespace App\Api;

use App\Entity\User;
use App\Repository\EpisodeCommentRepository;
use App\Repository\UserMovieRepository;
use App\Repository\UserSeriesRepository;
use Closure;
use DateInterval;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\DependencyInjection\Attribute\AutowireMethodOf;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** @method User|null getUser() */
#[Route('/api/last/additions/menu', name: 'api_last_additions_menu_')]
readonly class ApiLastAdditionsMenu
{
    public function __construct(
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure              $json,
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure              $generateUrl,
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure              $getUser,
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure              $renderView,
        private UserMovieRepository  $userMovieRepository,
        private UserSeriesRepository $userSeriesRepository,
    )
    {
    }

    #[Route('/update', name: 'update', methods: ['POST'])]
    public function update(Request $request): Response
    {
        $inputBag = $request->getPayload();
        $user = ($this->getUser)();
        if (!$user) {
            return ($this->json)([
                'ok' => true,
                'update' => false,
            ]);
        }
        $lastItemId = $inputBag->getInt('lastCommentId', -1);
        $actualLastUserSeries = $this->userSeriesRepository->findOneBy([], ['addedAt' => 'DESC']);
        $actualLastUserMovie = $this->userMovieRepository->findOneBy([], ['createdAT' => 'DESC']);
        if ($actualLastUserMovie->getCreatedAT() > $actualLastUserSeries->getAddedAt()) {
            $lastActualItemId = $actualLastUserMovie->getId();
        } else {
            $lastActualItemId = $actualLastUserSeries->getId();
        }

        if ($lastItemId === $lastActualItemId) {
            return ($this->json)([
                'ok' => true,
                'update' => false,
            ]);
        }

        $now = new DateTime();
        $twoMonthsAgo = $now->sub(new DateInterval('P2M'));
        $date = $twoMonthsAgo->format('Y-m-d H:i:s');
        $lastSeries = $this->userSeriesRepository->lastAdditions($user, $date, $user->getPreferredLanguage() ?? $request->getLocale());
        $lastMovies = $this->userMovieRepository->lastAdditions($user, $date, $user->getPreferredLanguage() ?? $request->getLocale());
        $list = array_map(function ($item) {
            if ($item['type'] === 'movie') {
                if ($item['poster_path']) {
                    $item['poster_path'] = '/movies/posters/' . $item['poster_path'];
                }
                $item['origin_country'] = json_decode($item['origin_country'], true) ?? [];
                $item['url'] = ($this->generateUrl)('app_movie_show', ['id' => $item['id']]);
            }
            if ($item['type'] === 'series') {
                if ($item['poster_path']) {
                    $item['poster_path'] = '/series/posters/' . $item['poster_path'];
                }
                $item['origin_country'] = json_decode($item['origin_country'], true) ?? [];
                $item['url'] = ($this->generateUrl)('app_show_series', ['id' => $item['id'], 'slug' => $item['slug']]);
            }
            return $item;
        }, array_merge($lastSeries, $lastMovies));

        usort($list, function ($a, $b) {
            return $b['date'] <=> $a['date'];
        });

        return ($this->json)([
            'ok' => true,
            'update' => true,
            'block' => ($this->renderView)('_blocks/_last_additions_menu.html.twig', [
                'list' => $list,
                'lastItemId' => $lastActualItemId,
            ]),
            'lastItemId' => $lastActualItemId,
        ]);
    }
}