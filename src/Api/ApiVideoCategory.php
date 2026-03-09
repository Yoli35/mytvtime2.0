<?php

namespace App\Api;

use App\Entity\User;
use App\Entity\VideoCategory;
use App\Repository\VideoCategoryRepository;
use App\Repository\VideoRepository;
use App\Service\VideoService;
use Closure;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\DependencyInjection\Attribute\AutowireMethodOf;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** @method User|null getUser() */
#[Route('/api/video/category', name: 'api_video_category_')]
readonly class ApiVideoCategory
{
    public function __construct(
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure                 $json,
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure                 $getUser,
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure                 $renderView,
        private VideoCategoryRepository $videoCategoryRepository,
        private VideoRepository         $videoRepository,
        private VideoService            $videoService,
    )
    {
    }

    #[Route('/new', name: 'new', methods: ['POST'])]
    public function new(Request $request): Response
    {
        $inputBag = $request->getPayload();
        $user = ($this->getUser)();
        if (!$user) {
            return ($this->json)([
                'ok' => true,
                'success' => false,
            ]);
        }
        $categoryName = $inputBag->get('category_name');
        $categoryColor = $inputBag->get('category_color');
        $videoId = $inputBag->getInt('video_id');

        $category = new VideoCategory();
        $category->setName($categoryName);
        $category->setColor($categoryColor);
        $this->videoCategoryRepository->save($category, true);

        $categories = $this->videoService->getCategories();

        $video = $this->videoRepository->find($videoId);

        return ($this->json)([
            'ok' => true,
            'success' => true,
            'block' => ($this->renderView)('_blocks/video/_categories.html.twig', [
                'categories' => $categories,
                'video' => $video,
            ]),
        ]);
    }
}