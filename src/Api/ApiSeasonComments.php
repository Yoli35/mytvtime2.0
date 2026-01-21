<?php

namespace App\Api;

use App\Entity\EpisodeComment;
use App\Entity\EpisodeCommentImage;
use App\Entity\Series;
use App\Entity\User;
use App\Repository\EpisodeCommentImageRepository;
use App\Repository\EpisodeCommentRepository;
use App\Service\DateService;
use App\Service\ImageService;
use Closure;
use DateTimeImmutable;
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
        private Closure                       $json,
        private DateService                   $dateService,
        private EpisodeCommentImageRepository $episodeCommentImageRepository,
        private EpisodeCommentRepository      $episodeCommentRepository,
        private ImageService                  $imageService,
    )
    {
    }

    #[Route('/comments/{id}', name: 'comments', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function get(#[CurrentUser] User $user, Request $request, Series $series): JsonResponse
    {
        $inputBag = $request->getPayload();
        $seasonNumber = $inputBag->get('seasonNumber');
        $availableEpisodeCount = $inputBag->get('availableEpisodeCount');
        $episodeArr = json_decode($inputBag->get('episodeArr'), true);

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

        $ids = array_map(fn($c) => $c['id'], $comments);
        $images = $this->episodeCommentImageRepository->findByEpisodeCommentIds($ids);
        $imagesArr = [];
        foreach ($images as $image) {
            $commentId = $image->getEpisodeComment()->getId();
            if (!isset($imagesArr[$commentId])) {
                $imagesArr[$commentId] = [];
            }
            $imagesArr[$commentId][] = $image->getPath();
        }

        foreach ($comments as $comment) {
            $episodeArr[$comment['episodeNumber'] - 1]['commentCount']++;
        }

        return ($this->json)([
            'ok' => true,
            'comments' => $comments,
            'images' => $imagesArr,
            'episodeArr' => $episodeArr,
            'availableEpisodeCount' => $availableEpisodeCount,
        ]);
    }

    #[Route('/comment/add/{id}', name: 'comment_add', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function add(#[CurrentUser] User $user, Request $request, Series $series): JsonResponse
    {
//        $inputBag = $request->getPayload();
//        $seasonNumber = $inputBag->get('seasonNumber');
//        $episodeNumber = $inputBag->get('episodeNumber');
//        $episodeId = $inputBag->get('episodeId');
//        $message = $inputBag->get('message');

        $data = $request->request->all();
        $files = $request->files->all();
        if (empty($data) && empty($files)) {
            return ($this->json)([
                'ok' => false,
                'message' => 'No data',
            ]);
        }
        $seasonNumber = $data['seasonNumber'] ?? null;
        $episodeNumber = $data['episodeNumber'] ?? null;
        $episodeId = $data['episodeId'] ?? null;
        $message = $data['message'] ?? null;

        if (!$seasonNumber || !$episodeNumber || !$episodeId || !$message) {
            return ($this->json)([
                'ok' => false,
                'message' => 'Missing data',
            ]);
        }

        $commentEntity = new EpisodeComment(
            user: $user,
            series: $series,
            tmdbId: $episodeId,
            seasonNumber: $seasonNumber,
            episodeNumber: $episodeNumber,
            message: $message,
            createdAt: $this->now($user),
        );
        $this->episodeCommentRepository->save($commentEntity, true);

        if (count($files) === 0) {
            $comment = $commentEntity->toArray();
            $comment['createdAt'] = $this->dateService->formatDateRelativeLong($comment['createdAt'], 'UTC', $user->getPreferredLanguage() ?? $request->getLocale());

            return ($this->json)([
                'ok' => true,
                'comment' => $comment,
                'images'=> [],
            ]);
        }

        /******************************************************************************
         * Images ajoutées depuis des fichiers locaux (type : UploadedFile)           *
         ******************************************************************************/
        $localizedName = $series->getLocalizedName($user->getPreferredLanguage() ?? $request->getLocale());
        $title = $localizedName?->getName() ?? $series->getName();
        $location = 'series-' . $series->getId() . '-comment-' . $commentEntity->getId();
        $n = 1;

        $images = [];
        foreach ($files as $file) {
            $image = $this->imageService->fileToWebp($file, $title, $location, $n, '/public/images/comments/', $seasonNumber, $episodeNumber);
            if ($image) {
                $episodeCommentImage = new EpisodeCommentImage($commentEntity, $image, $this->now($user));
                $this->episodeCommentImageRepository->save($episodeCommentImage, true);
                $images[] = $image;
            }
            $n++;
        }

        $comment = $commentEntity->toArray();
        $comment['createdAt'] = $this->dateService->formatDateRelativeLong($comment['createdAt'], 'UTC', $user->getPreferredLanguage() ?? $request->getLocale());

        return ($this->json)([
            'ok' => true,
            'comment' => $comment,
            'images'=> $images,
        ]);
    }

    private function now(User $user): DateTimeImmutable
    {
        $timezone = $user->getTimezone() ?? 'Europe/Paris';
        return $this->dateService->newDateImmutable('now', $timezone);
    }
}