<?php

namespace App\Api;

use App\Entity\User;
use App\Repository\EpisodeCommentRepository;
use Closure;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\DependencyInjection\Attribute\AutowireMethodOf;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** @method User|null getUser() */
#[Route('/api/comment/menu', name: 'api_comment_menu_')]
readonly class ApiCommentMenu
{
    public function __construct(
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure                  $json,
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure                  $getUser,
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure                  $renderView,
        private EpisodeCommentRepository $episodeCommentRepository,
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
        $lastCommentId = $inputBag->getInt('lastCommentId', -1);
        $actualLastCommentId = $this->episodeCommentRepository->lastCommentId();

        if ($lastCommentId === $actualLastCommentId) {
            return ($this->json)([
                'ok' => true,
                'update' => false,
            ]);
        }

        return ($this->json)([
            'ok' => true,
            'update' => true,
            'block' => ($this->renderView)('_blocks/_comment_menu.html.twig', [
                'seriesList' => $this->episodeCommentRepository->commentCountBySeries(),
                'lastCommentId' => $actualLastCommentId,
            ]),
            'lastCommentId' => $actualLastCommentId,
        ]);
    }
}