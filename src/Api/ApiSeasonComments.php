<?php

namespace App\Api;

use App\Entity\Series;
use App\Entity\User;
use App\Repository\EpisodeCommentRepository;
use App\Service\DateService;
use Closure;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\DependencyInjection\Attribute\AutowireMethodOf;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/season', name: 'api_season_')]
readonly class ApiSeasonComments
{
    public function __construct(
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure                  $json,
        private DateService              $dateService,
        private EpisodeCommentRepository $episodeCommentRepository,
    )
    {
    }

    #[Route('/comments/{id}', name: 'comments', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function add(#[CurrentUser] User $user, Request $request, Series $series): JsonResponse
    {
        $inputBag = $request->getPayload();
        $seasonNumber = $inputBag->get('seasonNumber');
        $timezone = 'UTC';//$user->getTimezone() ?? 'UTC';
        $locale = $user->getPreferredLanguage() ?? $request->getLocale();

        $comments = array_map(function ($c) use ($timezone, $locale) {
            return [
                'user' => [
                    'id' => $c->getUser()->getId(),
                    'username' => $c->getUser()->getUsername(),
                    'avatar' => $c->getUser()->getAvatar(),
                ],
                'id' => $c->getId(),
                'tmdbId' => $c->getTmdbId(),
                'seasonNumber' => $c->getSeasonNumber(),
                'episodeNumber' => $c->getEpisodeNumber(),
                'message' => $c->getMessage(),
                'createdAt' => $this->dateService->formatDateRelativeLong($c->getCreatedAt()->format('Y-m-d H:i:s'), $timezone, $locale),
                'replyTo' => $c->getReplyTo()?->getId(),
            ];
        }, $this->episodeCommentRepository->findBy(['series' => $series, 'seasonNumber' => $seasonNumber]));

        return ($this->json)([
            'ok' => true,
            'comments' => $comments,
        ]);
    }
}