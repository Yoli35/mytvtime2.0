<?php

namespace App\Api;

use App\Entity\Movie;
use App\Entity\MovieComment;
use App\Entity\MovieCommentImage;
use App\Entity\User;
use App\Repository\MovieCommentImageRepository;
use App\Repository\MovieCommentRepository;
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

#[Route('/api/movie', name: 'api_movie_')]
readonly class ApiMovieComments
{
    public function __construct(
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure                     $json,
        private DateService                 $dateService,
        private MovieCommentImageRepository $movieCommentImageRepository,
        private MovieCommentRepository      $movieCommentRepository,
        private ImageService                $imageService,
    )
    {
    }

    #[Route('/comments/{id}', name: 'comments', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    public function get(#[CurrentUser] User $user, Request $request, Movie $movie): JsonResponse
    {
        $locale = $user->getPreferredLanguage() ?? $request->getLocale();
        $timezone = $this->timezone($user);

        $comments = array_map(function (MovieComment $c) use ($timezone, $locale) {
            $comment = $c->toArray();
            $comment['createdAt'] = $this->dateService->formatDateRelativeLong($comment['createdAt'], $timezone, $locale);
            return $comment;
        }, $this->movieCommentRepository->findByMovie($movie));

        $ids = array_map(fn($c) => $c['id'], $comments);
        $images = $this->movieCommentImageRepository->findByMovieCommentIds($ids);
        $imagesArr = [];
        foreach ($images as $image) {
            $imagesArr[$image['movie_comment_id']][] = $image['path'];
        }

        return ($this->json)([
            'ok' => true,
            'comments' => $comments,
            'images' => $imagesArr,
        ]);
    }

    #[Route('/comment/add/{id}', name: 'comment_add', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function add(#[CurrentUser] User $user, Request $request, Movie $movie): JsonResponse
    {
        $data = $request->request->all();
        $files = $request->files->all();
        $replyToId = $data['replyToId'] ?? null;
        $message = trim($data['message'] ?? '');

        if (!$message) {
            return ($this->json)([
                'ok' => false,
                'message' => 'Missing data',
            ]);
        }

        $commentEntity = new MovieComment(
            user: $user,
            movie: $movie,
            message: $message,
            createdAt: $this->now($user),
        );
        if ($replyToId) {
            $replyTo = $this->movieCommentRepository->find($replyToId);
            if ($replyTo && $replyTo->getMovie() === $movie) {
                $commentEntity->setReplyTo($replyTo);
            }
        }
        $this->movieCommentRepository->save($commentEntity, true);

        /******************************************************************************
         * Images ajoutées depuis des fichiers locaux (type : UploadedFile)           *
         ******************************************************************************/
        $title = $movie->getTitle();
        $location = 'movie-' . $movie->getId() . '-comment-' . $commentEntity->getId();
        $n = 1;

        $images = [];
        foreach ($files as $file) {
            $image = $this->imageService->fileToWebp($file, $title, $location, $n, '/images/comments/');
            if ($image) {
                $movieCommentImage = new MovieCommentImage($commentEntity, $image, $this->now($user));
                $this->movieCommentImageRepository->save($movieCommentImage, true);
                $images[] = $image;
            }
            $n++;
        }

        $comment = $commentEntity->toArray();
        $comment['createdAt'] = $this->dateService->formatDateRelativeLong($comment['createdAt'], $this->timezone($user), $user->getPreferredLanguage() ?? $request->getLocale());

        return ($this->json)([
            'ok' => true,
            'comment' => $comment,
            'images' => $images,
        ]);
    }

    private function now(User $user): DateTimeImmutable
    {
        return $this->dateService->newDateImmutable('now', $this->timezone($user));
    }

    /**
     * Les dates sont enregistrées à l'heure locale de l'utilisateur (cf. now()) : elles doivent être relues dans ce même fuseau.
     */
    private function timezone(User $user): string
    {
        return $user->getTimezone() ?? 'Europe/Paris';
    }
}
