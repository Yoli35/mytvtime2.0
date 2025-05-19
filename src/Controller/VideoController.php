<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\UserVideo;
use App\Repository\UserVideoRepository;
use App\Repository\VideoRepository;
use App\Service\DateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** @method User|null getUser() */
#[ Route('/video', name: 'app_video_')]
final class VideoController extends AbstractController
{
    public function __construct(
        private readonly DateService $dateService,
        private readonly UserVideoRepository $userVideoRepository,
    )
    {
    }

    #[Route('/', name: 'index')]
    public function index(Request $request): Response
    {
        $user = $this->getUser();
        $now = $this->dateService->getNowImmutable($user->getTimezone(), true);
        $videos = $this->userVideoRepository->findBy(['user' => $user], ['createdAt' => 'DESC']);

        return $this->render('video/index.html.twig', [
            'videos' => $videos,
            'now' => $now,
        ]);
    }

    #[Route('/{id}', name: 'show')]
    public function show(Request $request, UserVideo $userVideo): Response
    {
        $video = $userVideo->getVideo();

        if (!$video) {
            throw $this->createNotFoundException('Video not found');
        }

        return $this->render('video/show.html.twig', [
            'userVideo' => $userVideo,
            'video' => $video,
        ]);
    }
}
