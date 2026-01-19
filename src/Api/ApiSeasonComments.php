<?php

namespace App\Api;

use App\Entity\Series;
use App\Entity\User;
use App\Repository\EpisodeCommentRepository;
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
        private EpisodeCommentRepository $episodeCommentRepository,
    )
    {
    }

    #[Route('/comments/{id}', name: 'comments', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function add(Request $request, Series $series): JsonResponse
    {
        $inputBag = $request->getPayload();
        $seasonNumber = $inputBag->get('seasonNumber');
        $comments = array_map(function ($c) {
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
                'createdAt' => $c->getCreatedAt()->format('Y-m-d H:i:s'),
                'replyTo' => $c->getReplyTo() ? [
                    'id' => $c->getReplyTo()->getId(),
                    'message' => $c->getReplyTo()->getMessage(),
                    'createdAt' => $c->getReplyTo()->getCreatedAt()->format('Y-m-d H:i:s'),
                ] : null,
            ];
        }, $this->episodeCommentRepository->findBy(['series' => $series, 'seasonNumber' => $seasonNumber]));
        /*dump($comments);*/

        return ($this->json)([
            'ok' => true,
            'comments' => $comments,
        ]);
    }
}