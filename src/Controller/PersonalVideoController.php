<?php

namespace App\Controller;

use App\Entity\PersonalVideo;
use App\Entity\User;
use App\Repository\PersonalVideoRepository;
use App\Service\DateService;
use App\Service\PersonalVideoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

/** @method User|null getUser() */
#[Route('/{_locale}/video/personal', name: 'app_video_personal_', requirements: ['_locale' => 'en|fr|ko'])]
#[IsGranted('ROLE_USER')]
final class PersonalVideoController extends AbstractController
{
    public function __construct(
        private readonly DateService             $dateService,
        private readonly PersonalVideoRepository $personalVideoRepository,
        private readonly PersonalVideoService    $personalVideoService,
        private readonly TranslatorInterface     $translator,
    )
    {
    }

    #[Route('/', name: 'index')]
    public function index(): Response
    {
        $videos = $this->personalVideoRepository->findByUser($this->getUser());

        return $this->render('personal_video/index.html.twig', [
            'videos' => $videos,
            'maxFileSize' => UploadedFile::getMaxFilesize(),
            'hasPending' => (bool)array_filter($videos, fn(PersonalVideo $v) => !$v->isReady() && $v->getStatus() !== PersonalVideo::STATUS_FAILED),
        ]);
    }

    #[Route('/upload', name: 'upload', methods: ['POST'])]
    public function upload(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('personal_video_upload', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        // Fichier plus gros que post_max_size : PHP vide $_POST et $_FILES
        $files = $request->files->all('videos');
        if (empty($files)) {
            $this->addFlash('error', $this->translator->trans('No video file received'));
            return $this->redirectToRoute('app_video_personal_index');
        }

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }
            $name = $file->getClientOriginalName();
            if ($file->getError() !== UPLOAD_ERR_OK) {
                $this->addFlash('error', $name . ' : ' . $file->getErrorMessage());
                continue;
            }
            if (!str_starts_with($file->getMimeType() ?? '', 'video/')) {
                $this->addFlash('error', $name . ' : ' . $this->translator->trans('Not a video file'));
                continue;
            }
            try {
                $video = $this->personalVideoService->import($file, $this->getUser());
            } catch (Throwable $e) {
                $this->addFlash('error', $name . ' : ' . $e->getMessage());
                continue;
            }
            match ($video->getStatus()) {
                PersonalVideo::STATUS_READY => $this->addFlash('success', $name . ' : ' . $this->translator->trans('Video added')),
                PersonalVideo::STATUS_PENDING => $this->addFlash('success', $name . ' : ' . $this->translator->trans('Video added, conversion pending')),
                default => $this->addFlash('error', $name . ' : ' . $this->translator->trans('Video conversion failed')),
            };
        }

        return $this->redirectToRoute('app_video_personal_index');
    }

    #[Route('/show/{id}', name: 'show', requirements: ['id' => Requirement::DIGITS])]
    public function show(PersonalVideo $video): Response
    {
        $this->checkOwner($video);

        return $this->render('personal_video/show.html.twig', [
            'video' => $video,
        ]);
    }

    #[Route('/edit/{id}', name: 'edit', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function edit(Request $request, PersonalVideo $video): Response
    {
        $this->checkOwner($video);
        if (!$this->isCsrfTokenValid('personal_video_edit_' . $video->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $title = mb_substr(trim($request->request->getString('title')), 0, 255);
        $description = trim($request->request->getString('description')) ?: null;
        if ($title === '') {
            $title = $video->getTitle();
        }
        if ($title !== $video->getTitle() || $description !== $video->getDescription()) {
            $video->setTitle($title);
            $video->setDescription($description);
            $video->setUpdatedAt($this->dateService->getNowImmutable('UTC'));
            $this->personalVideoRepository->save($video, true);
            $this->addFlash('success', $this->translator->trans('Video updated'));
        }

        // Vignette composée dans la page (PVideo.js) à partir de l'image sélectionnée dans la liste
        $thumbnail = $request->files->get('thumbnail_image');
        if ($thumbnail instanceof UploadedFile) {
            $error = $this->personalVideoService->replaceThumbnail($video, $thumbnail);
            if ($error) {
                $this->addFlash('error', $this->translator->trans('Thumbnail update failed') . ' : ' . $error);
            } else {
                $this->addFlash('success', $this->translator->trans('Thumbnail updated'));
            }
        }

        return $this->redirectToRoute('app_video_personal_show', ['id' => $video->getId()]);
    }

    #[Route('/delete/{id}', name: 'delete', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function delete(Request $request, PersonalVideo $video): Response
    {
        $this->checkOwner($video);
        if (!$this->isCsrfTokenValid('personal_video_delete_' . $video->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        if ($video->getStatus() === PersonalVideo::STATUS_PROCESSING) {
            $this->addFlash('error', $this->translator->trans('Video is being converted, try again later'));
            return $this->redirectToRoute('app_video_personal_show', ['id' => $video->getId()]);
        }

        $this->personalVideoService->delete($video);
        $this->addFlash('success', $this->translator->trans('Video deleted'));

        return $this->redirectToRoute('app_video_personal_index');
    }

    private function checkOwner(PersonalVideo $video): void
    {
        if ($video->getUser() !== $this->getUser()) {
            throw $this->createNotFoundException();
        }
    }
}
