<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\UserVideo;
use App\Entity\Video;
use App\Entity\VideoChannel;
use App\Repository\UserVideoRepository;
use App\Repository\VideoCategoryRepository;
use App\Repository\VideoChannelRepository;
use App\Repository\VideoRepository;
use App\Service\DateService;
use App\Service\ImageService;
use DateInterval;
use DateTimeImmutable;
use Google\Exception;
use Google\Service\YouTube\ChannelListResponse;
use Google\Service\YouTube\CommentSnippet;
use Google\Service\YouTube\CommentThreadListResponse;
use Google\Service\YouTube\VideoListResponse;
use Google_Client;
use Google_Service_YouTube;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/** @method User|null getUser() */
#[Route('/video', name: 'app_video_')]
final class VideoController extends AbstractController
{
    private Google_Service_YouTube $service_YouTube;
    private int $maxResults = 10;

    /**
     * @throws Exception
     */
    public function __construct(
        private readonly DateService             $dateService,
        private readonly ImageService            $imageService,
        private readonly TranslatorInterface     $translator,
        private readonly VideoCategoryRepository $categoryRepository,
        private readonly VideoChannelRepository  $channelRepository,
        private readonly VideoRepository         $videoRepository,
        private readonly UserVideoRepository     $userVideoRepository,
    )
    {
        $client = new Google_Client();
        $client->setApplicationName('mytvtime');
//        $client->setScopes(['https://www.googleapis.com/auth/youtube.readonly',]);
        $client->setScopes(['https://www.googleapis.com/auth/youtube.force-ssl',]);
        $client->setAuthConfig('../config/google/mytvtime-349019-001b2f815d02.json');
        $client->setAccessType('offline');

        $this->service_YouTube = new Google_Service_YouTube($client);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/list', name: 'index')]
    public function index(Request $request): Response
    {
        $user = $this->getUser();
        $now = $this->dateService->getNowImmutable($user->getTimezone() ?? 'Europe/Paris');
        $page = $request->query->getInt('page', 1);
        $limit = 10;
        $categoryId = $request->query->getInt('category');

        $newLink = $request->query->get('link');
        if ($newLink) {
            $link = $this->parseLink($newLink);
            if ($link) {
                $this->addVideo($link, $now);
            } else {
                $this->addFlash('error', 'Invalid YouTube link: "' . $newLink . '"<br> Please provide a valid YouTube link.');
            }
            $page = 1; // Reset to first page after adding a new video
            $categoryId = 0; // Reset category filter
        }

        $dbUserVideos = $this->userVideoRepository->getUserVideosWithVideos($user->getId(), $categoryId, $page, $limit);

        $ids = array_map(fn($dbUserVideo) => $dbUserVideo['id'], $dbUserVideos);
        $dbVideoCategories = $this->userVideoRepository->getVideoCategories($ids);

        $dbVideos = [];
        foreach ($dbUserVideos as $dbUserVideo) {
            $formattedDates = $this->dbFormatDates($dbUserVideo);
            $dbUserVideo['published_at'] = $formattedDates['published_at'];
            $dbUserVideo['added_at'] = $formattedDates['added_at'];
            $dbUserVideo['duration'] = $this->formatDuration($dbUserVideo['duration']);
            $dbVideos[$dbUserVideo['id']] = $dbUserVideo;
        }
        $dbUserVideos = $dbVideos;
        foreach ($dbVideoCategories as $dbVideoCategory) {
            if (!key_exists('categories', $dbUserVideos[$dbVideoCategory['video_id']])) {
                $dbUserVideos[$dbVideoCategory['video_id']]['categories'] = [];
            }
            $dbUserVideos[$dbVideoCategory['video_id']]['categories'][] = $dbVideoCategory;
        }

        $totalDuration = $this->userVideoRepository->getUserVideosTotalDuration($user->getId());
        $totalDurationString = $this->getSeconds2human($this->userVideoRepository->count(['user' => $user]), $totalDuration);

        if (!$categoryId) {
            $count = $this->userVideoRepository->count(['user' => $user]);
        } else {
            $count = $this->userVideoRepository->countVideoByCategory($user->getId(), $categoryId);
        }
        $categories = $this->getCategories();

        return $this->render('video/index.html.twig', [
            'dbUserVideos' => $dbUserVideos,
            'categories' => $categories,
            'totalDuration' => $totalDurationString,
            'now' => $now,
            'categoryId' => $categoryId,
            'page' => $page,
            'pagination' => $this->pagination($page, $categoryId, $count, $limit),
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/show/{id}', name: 'show')]
    public function show(Request $request, UserVideo $userVideo): Response
    {
        $user = $this->getUser();
        $video = $userVideo->getVideo();
        $page = $request->query->getInt('page', 1);
        $categoryId = $request->query->getInt('category');

        if (!$video) {
            throw $this->createNotFoundException('Video not found');
        }

        $categories = $this->getCategories();
        $previousVideo = $this->videoRepository->getPreviousVideo($video, $user);
        $nextVideo = $this->videoRepository->getNextVideo($video, $user);

        $this->formatDates($userVideo);

        return $this->render('video/show.html.twig', [
            'userVideo' => $userVideo,
            'video' => $video,
            'page' => $page,
            'categories' => $categories,
            'categoryId' => $categoryId,
            'previousVideo' => $previousVideo,
            'nextVideo' => $nextVideo,
        ]);
    }

    #[Route('/share/{id}', name: 'share')]
    public function share(Request $request, ?Video $video): Response
    {
        if (!$video) {
            $user = $this->getUser();
            $this->addFlash('error', 'Video not found');
            return $this->redirectToRoute('app_home_index', ['_locale' => $request->getLocale()]);
        }
        $publishedDate = $this->sharedVideoFormatDate($video);

        return $this->render('video/share.html.twig', [
            'video' => $video,
            'publishedAt' => $publishedDate,

        ]);
    }

    #[Route('/details/{id}', name: 'details', methods: ['POST'])]
    public function details(Request $request, ?Video $video): JsonResponse
    {
        if (!$request->isMethod('POST')) {
            return new JsonResponse(['error' => 'Invalid request method'], Response::HTTP_BAD_REQUEST);
        }
        if (!$video) {
            throw $this->createNotFoundException('Video not found');
        }
        $youtubeVideo = $this->getYouTubeVideo($video->getLink());

        $channel = $video->getChannel()->toArray();

        // Get comments
        $link = $video->getLink();

        $results = $this->getComments($link, null);

        return new JsonResponse([
            'video' => $youtubeVideo->getItems()[0],
            'channel' => $channel,
            'comments' => $results['comments'],
            'nextPageToken' => $results['nextPageToken'],
        ]);
    }

    #[Route('/comments', name: 'comments', methods: ['POST'])]
    public function comments(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $link = $data['link'];
        $nextPageToken = $data['nextPageToken'];

        $results = $this->getComments($link, $nextPageToken);

        return new JsonResponse([
            'comments' => $results['comments'],
            'nextPageToken' => $results['nextPageToken'],
        ]);
    }

    #[Route('/category/add', name: 'category_add', methods: ['POST'])]
    public function addCategoryToVideo(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $categoryId = $data['categoryId'] ?? null;
        $videoId = $data['videoId'] ?? null;

        if (!$categoryId || !$videoId) {
            return new JsonResponse(['error' => 'Category & video IDs are required'], Response::HTTP_BAD_REQUEST);
        }
        $video = $this->videoRepository->find($videoId);
        if (!$video) {
            return new JsonResponse(['error' => 'Video not found'], Response::HTTP_NOT_FOUND);
        }
        $category = $this->categoryRepository->find($categoryId);
        if (!$category) {
            return new JsonResponse(['error' => 'Category not found'], Response::HTTP_NOT_FOUND);
        }
        $video->addCategory($category);
        $this->videoRepository->save($video, true);

        return new JsonResponse([
            'message' => 'Category added successfully',
            'id' => $category->getId(),
            'name' => $this->translator->trans($category->getName()),
            'color' => $category->getColor(),
        ]);
    }

    #[Route('/category/delete', name: 'category_delete', methods: ['POST'])]
    public function removeCategoryFromVideo(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $categoryId = $data['categoryId'] ?? null;
        $videoId = $data['videoId'] ?? null;

        if (!$categoryId || !$videoId) {
            return new JsonResponse(['error' => 'Category & video IDs are required'], Response::HTTP_BAD_REQUEST);
        }
        $video = $this->videoRepository->find($videoId);
        if (!$video) {
            return new JsonResponse(['error' => 'Video not found'], Response::HTTP_NOT_FOUND);
        }
        $category = $this->categoryRepository->find($categoryId);
        if (!$category) {
            return new JsonResponse(['error' => 'Category not found'], Response::HTTP_NOT_FOUND);
        }
        $video->removeCategory($category);
        $this->videoRepository->save($video, true);

        return new JsonResponse(['message' => 'Category removed successfully', 'id' => $category->getId()]);
    }

    private function getCategories() :array
    {
        $categories = array_map(function ($cat) {
            return [
                'id' => $cat->getId(),
                'name' => $this->translator->trans($cat->getName()),
                'color' => $cat->getColor(),
            ];
        }, $this->categoryRepository->findAll());

        usort($categories, function ($a, $b) {
            // Remplacer les accents pour une comparaison correcte
            $a['name'] = preg_replace('/[ÉÈÊË]/u', 'E', $a['name']);
            $b['name'] = preg_replace('/[ÉÈÊË]/u', 'E', $b['name']);
            $a['name'] = preg_replace('/[éèêë]/u', 'e', $a['name']);
            $b['name'] = preg_replace('/[éèêë]/u', 'e', $b['name']);
            // Comparer les noms des catégories
            return strcmp($a['name'], $b['name']);
        });

        return $categories;
    }

    private function parseLink(string $userLink): ?string
    {
        // Check if the link is a valid YouTube URL and extract the video ID
        $pattern = '/(?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/(?:[^\/\n\s]+\/\S+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([a-zA-Z0-9_-]{11})/';
        preg_match($pattern, $userLink, $matches);
        if (key_exists(1, $matches) && strlen($matches[1]) === 11) {
            $videoLink = $matches[1];
        } else {
            // And another pattern for YouTube short links: https://youtube.com/shorts/VsMVTAOY9h4?si=NLj0Ztc-WtneY5yG
            $pattern = '/https?:\/\/(?:www\.)?youtube\.com\/shorts\/([a-zA-Z0-9_-]{11})/';
            preg_match($pattern, $userLink, $matches);
            $videoLink = $matches[1] ?? null;
        }
        return $videoLink;
    }

    private function getYouTubeVideo(string $videoId): ?VideoListResponse
    {
        try {
            return $this->service_YouTube->videos->listVideos('contentDetails,snippet,statistics', ['id' => $videoId]);
        } catch (\Google\Service\Exception) {
            return null;
        }
    }

    private function addVideo(string $link, DateTimeImmutable $now): void
    {
        $video = $this->videoRepository->findOneBy(['link' => $link]);
        if (!$video) {
            $youtubeVideo = $this->getYouTubeVideo($link);
            $video = new Video($youtubeVideo->getItems()[0]['snippet']['title'], $link);
            $this->checkVideo($video, $now);
        }
        $userVideo = $this->userVideoRepository->findOneBy(['user' => $this->getUser(), 'video' => $video]);
        if (!$userVideo) {
            $userVideo = new UserVideo($this->getUser(), $video, $now);
            $this->userVideoRepository->save($userVideo, true);
        } else {
            $this->addFlash('error', 'You already added this video: "' . $link . '"<br> Please provide a different YouTube link.');
        }
    }

    private function checkVideo(?Video $video, DateTimeImmutable $now): void
    {
        if (!$video) {
            return;
        }
        $lastUpdateAt = $video->getUpdatedAt();
        if (!$lastUpdateAt || $now->diff($lastUpdateAt)->days > 1) {
            $link = $video->getLink();
            $youtubeVideo = $this->getYouTubeVideo($link);

            if ($youtubeVideo) {
                $this->video($youtubeVideo, $video, $now);
            } else {
                $this->addFlash('error', 'Invalid YouTube video: "' . $link . '"<br> Please provide a valid YouTube video link.');
            }
        }
        $video->setDurationString($this->formatDuration($video->getDuration()));
    }

    private function video(VideoListResponse $youtubeVideo, Video $video, DateTimeImmutable $now): void
    {
        $channelId = $youtubeVideo->getItems()[0]['snippet']['channelId'];
        $channel = $this->checkChannel($channelId);
        $video->setChannel($channel);
        $video->setPublishedAt(date_create_immutable($youtubeVideo->getItems()[0]['snippet']['publishedAt']));
        $thumbnailUrl = null;
        $thumbnails = (array)$youtubeVideo->getItems()[0]['snippet']['thumbnails'];
        dump($thumbnails);
        if (array_key_exists('high', $thumbnails) && $thumbnails['high']['url']) {
            $thumbnailUrl = $thumbnails['high']['url'];
        } else {
            if (array_key_exists('medium', $thumbnails) && $thumbnails['medium']['url']) {
                $thumbnailUrl = $thumbnails['medium']['url'];
            } else {
                if (array_key_exists('default', $thumbnails) && $thumbnails['default']['url']) {
                    $thumbnailUrl = $thumbnails['default']['url'];
                }
            }
        }
        if ($thumbnailUrl) {
            $url = pathinfo($thumbnailUrl);
            $basename = '/' . $video->getLink() . '-' . $url['basename'];
            $this->imageService->saveImage2($thumbnailUrl, '/videos/thumbnails/' . $basename);
        } else {
            $basename = null;
        }
        $video->setThumbnail($basename);
        $video->setTitle($youtubeVideo->getItems()[0]['snippet']['title']);
        $video->setDuration($this->iso8601ToSeconds($youtubeVideo->getItems()[0]['contentDetails']['duration']));
        $video->setUpdatedAt($now);
        $this->videoRepository->save($video, true);
    }

    private function checkChannel(string $channelId): VideoChannel
    {
        /** @var VideoChannel|null $channel */
        $channel = $this->channelRepository->findOneBy(['youTubeId' => $channelId]);

        $user = $this->getUser();
        $now = $this->dateService->getNowImmutable($user->getTimeZone() ?? 'Europe/Paris');
        $lastUpdateAt = $channel?->getUpdatedAt();

        $performChecks = $lastUpdateAt == null || $lastUpdateAt->diff($now)->days > 1;

        if ($performChecks) {
            $channelListResponse = $this->getChannelSnippet($channelId);
            $items = $channelListResponse->getItems();
            $item = $items[0];
            $snippet = $item['snippet'];
            $thumbnails = (array)$snippet['thumbnails'];
            $thumbnailUrl = null;
            if (array_key_exists('high', $thumbnails) && $thumbnails['high']['url']) {
                $thumbnailUrl = $thumbnails['high']['url'];
            } else {
                if (array_key_exists('medium', $thumbnails) && $thumbnails['medium']['url']) {
                    $thumbnailUrl = $thumbnails['medium']['url'];
                } else {
                    if (array_key_exists('default', $thumbnails) && $thumbnails['default']['url']) {
                        $thumbnailUrl = $thumbnails['default']['url'];
                    }
                }
            }
            if ($thumbnailUrl) {
                $basename = '/' . $channelId;
                $this->imageService->saveImage2($thumbnailUrl, '/videos/channels/thumbnails' . $basename);
            } else {
                $basename = null;
            }

            if ($channel == null) {
                $channel = new VideoChannel($item['id'], $snippet['title'], $snippet['customUrl'], $basename, $now);
            } else {
                $channel->setYouTubeId($item['id']);
                $channel->setTitle($snippet['title']);
                $channel->setCustomUrl($snippet['customUrl']);
                $channel->setThumbnail($basename);
                $channel->setUpdatedAt($now);
            }

            $this->channelRepository->save($channel, true);
        }

        return $channel;
    }

    private function getChannelSnippet($channelId): ?ChannelListResponse
    {
        try {
            return $this->service_YouTube->channels->listChannels('snippet', ['id' => $channelId]);
        } catch (\Google\Service\Exception) {
            return null;
        }
    }

    private function getComments(string $link, ?string $nextPageToken): array
    {
        $commentArray = [];
        $now = $this->dateService->getNowImmutable($this->getUser()?->getTimeZone() ?? 'Europe/Paris');

        $list = $this->getVideoComments($link, $this->maxResults, $nextPageToken);
        $comments = $list->getItems();
        $nextPageToken = $list->getNextPageToken();

        foreach ($comments as $item) {
            $snippet = $item['snippet'];
            $comment = $snippet['topLevelComment']['snippet'];
            $channelThumbnail = $this->getChannelThumbnail($comment, $now);
            $replies = $item['replies']['comments'] ?? [];
            $repliesArray = [];
            foreach ($replies as $reply) {
                $replyComment = $reply['snippet'];
                $replyChannelThumbnail = $this->getChannelThumbnail($replyComment, $now);
                $repliesArray[] = [
                    'author' => $replyComment['authorDisplayName'],
                    'authorProfileImageUrl' => $replyChannelThumbnail,
                    'text' => $replyComment['textDisplay'],
                    'publishedAt' => $this->formatCommentDate($replyComment['publishedAt']),
                ];
            }
            $commentArray[] = [
                'author' => $comment['authorDisplayName'],
                'authorProfileImageUrl' => $channelThumbnail,
                'text' => $comment['textDisplay'],
                'publishedAt' => $this->formatCommentDate($comment['publishedAt']),
                'replies' => $repliesArray,
            ];
        }
        return [
            'comments' => $commentArray,
            'nextPageToken' => $nextPageToken,
        ];
    }

    private function getVideoComments(string $videoId, int $maxResults, ?string $nextPageToken): ?CommentThreadListResponse
    {
        if ($nextPageToken) {
            try {
                return $this->service_YouTube->commentThreads->listCommentThreads('snippet,replies', ['videoId' => $videoId, 'textFormat' => 'plainText', 'maxResults' => $maxResults, 'pageToken' => $nextPageToken]);
            } catch (\Google\Service\Exception) {
                return null;
            }
        }
        try {
            return $this->service_YouTube->commentThreads->listCommentThreads('snippet,replies', ['videoId' => $videoId, 'textFormat' => 'plainText', 'maxResults' => $maxResults]);
        } catch (\Google\Service\Exception) {
            return null;
        }
    }

    private function getChannelThumbnail(CommentSnippet $snippet, DateTimeImmutable $now): ?string
    {
        $channelId = $snippet->getAuthorChannelId()->getValue();

        $channel = $this->channelRepository->findOneBy(['youTubeId' => $channelId]);
        if ($channel) {
            return $channel->getThumbnail();
        }

        $basename = '/' . $channelId;
        $thumbnail = $snippet->getAuthorProfileImageUrl();
        $this->imageService->saveImage2($thumbnail, '/videos/channels/thumbnails' . $basename);

        $title = $snippet->getAuthorDisplayName();
        $customUrl = $snippet->getAuthorChannelUrl();
        if ($customUrl) {
            $customUrl = preg_replace('/^https?:\/\/(www\.)?youtube\.com\//', '', $customUrl);
        }

        $channel = new VideoChannel($channelId, $title, $customUrl, $basename, $now);
        $this->channelRepository->save($channel, true);
        return $basename;
    }

    private function iso8601ToSeconds($input): int
    {
        try {
            $duration = new DateInterval($input);
            $hours_to_seconds = $duration->h * 60 * 60;
            $minutes_to_seconds = $duration->i * 60;
            $seconds = $duration->s;
            return $hours_to_seconds + $minutes_to_seconds + $seconds;
        } catch (\Exception) {
            return 0;
        }
    }

    public function formatDuration(int $durationInSecond): string
    {
        $durationInSecond--;
        $h = floor($durationInSecond / 3600);
        $m = floor(($durationInSecond % 3600) / 60);
        $s = $durationInSecond % 60;
        $duration = "";
        if ($h > 0) {
            $duration .= $h . ":";
        }
        if ($m < 10) {
            $m = "0" . $m;
        }
        $duration .= $m . ":";
        if ($s < 10) {
            $s = "0" . $s;
        }
        $duration .= $s;

        return $duration;
    }

    public function formatDates(UserVideo $userVideo): void
    {
        $publishedDate = $userVideo->getVideo()->getPublishedAt();
        $addedDate = $userVideo->getCreatedAt();

        $publishedAt = $this->dateService->formatDateRelativeShort($publishedDate->format('Y-m-d H:i:s'), 'Europe/Paris', 'fr');
        $addedAt = $this->dateService->formatDateRelativeShort($addedDate->format('Y-m-d H:i:s'), 'Europe/Paris', 'fr');

        if (is_numeric($publishedAt[0])) {
            $publishedAt = $this->translator->trans("Published at") . ' ' . $publishedAt;
        } else {
            $publishedAt = $this->translator->trans("Published") . ' ' . $publishedAt;
        }
        if (is_numeric($addedAt[0])) {
            $addedAt = $this->translator->trans("Added at") . ' ' . $addedAt;
        } else {
            $addedAt = $this->translator->trans("Added") . ' ' . $addedAt;
        }

        $publishedAt .= ' ' . $this->translator->trans("at") . ' ' . $publishedDate->format('H:i');
        $addedAt .= ' ' . $this->translator->trans("at") . ' ' . $addedDate->format('H:i');

        $userVideo->setPublishedAtString($publishedAt);
        $userVideo->setAddedAtString($addedAt);
    }

    public function dbFormatDates(array $dbVideo): array
    {
        $publishedDate = $dbVideo['published_at'];
        $addedDate = $dbVideo['added_at'];

        $publishedAt = $this->dateService->formatDateRelativeShort($publishedDate, 'Europe/Paris', 'fr');
        $addedAt = $this->dateService->formatDateRelativeShort($addedDate, 'Europe/Paris', 'fr');

        if (is_numeric($publishedAt[0])) {
            $publishedAt = $this->translator->trans("Published at") . ' ' . $publishedAt;
        } else {
            $publishedAt = $this->translator->trans("Published") . ' ' . $publishedAt;
        }
        if (is_numeric($addedAt[0])) {
            $addedAt = $this->translator->trans("Added at") . ' ' . $addedAt;
        } else {
            $addedAt = $this->translator->trans("Added") . ' ' . $addedAt;
        }

        $publishedAt .= ' ' . $this->translator->trans("at") . ' ' . substr($publishedDate, 11, 5);
        $addedAt .= ' ' . $this->translator->trans("at") . ' ' . substr($addedDate, 11, 5);

        return [
            'published_at' => $publishedAt,
            'added_at' => $addedAt,
        ];
    }

    public function sharedVideoFormatDate(Video $video): string
    {
        $publishedDate = $video->getPublishedAt()->format('Y-m-d H:i:s');

        $publishedAt = $this->dateService->formatDateRelativeShort($publishedDate, 'Europe/Paris', 'fr');

        if (is_numeric($publishedAt[0])) {
            $publishedAt = $this->translator->trans("Published at") . ' ' . $publishedAt;
        } else {
            $publishedAt = $this->translator->trans("Published") . ' ' . $publishedAt;
        }

        $publishedAt .= ' ' . $this->translator->trans("at") . ' ' . substr($publishedDate, 11, 5);

        return $publishedAt;
    }

    public function formatCommentDate(string $date): string
    {
        $publishedAt = $this->dateService->formatDateRelativeShort($date, 'Europe/Paris', 'fr');

        if (is_numeric($publishedAt[0])) {
            $publishedAt = $this->translator->trans("Published at") . ' ' . $publishedAt;
        } else {
            $publishedAt = $this->translator->trans("Published") . ' ' . $publishedAt;
        }
        $publishedAt .= ' ' . $this->translator->trans("at") . ' ' . substr($date, 11, 5);
        return $publishedAt;
    }

    private function getSeconds2human(int $count, int $secondes): string
    {
        if ($secondes) {
            // convert total runtime ($total in secondes) in years, months, days, hours, minutes, secondes
            $now = new DateTimeImmutable();
            try {
                // past = now - total
                $past = $now->sub(new DateInterval('PT' . $secondes . 'S'));
            } catch (\Exception) {
                $past = $now;
            }
            // "5156720 secondes" → "5 156 720 secondes"
            $secondesStr = number_format($secondes, 0, '', ' ');

            $diff = $now->diff($past);
            // diff string with years, months, days, hours, minutes, seconds
            $runtimeString = $this->translator->trans('Time spent watching these count videos', ['count' => $count]) . " : ";
            $runtimeString .= $secondesStr . '&nbsp;' . $this->translator->trans('seconds or') . " ";
            $runtimeString .= $diff->days ? $diff->days . '&nbsp;' . ($diff->days > 1 ? $this->translator->trans('days') : $this->translator->trans('day')) . ($diff->y + $diff->m + $diff->d + $diff->h + $diff->i + $diff->s ? (', ' . $this->translator->trans('or') . ' ') : '') : '';
            $runtimeString .= $diff->y ? ($diff->y . '&nbsp;' . ($diff->y > 1 ? $this->translator->trans('years') : $this->translator->trans('year')) . ($diff->m + $diff->d + $diff->h + $diff->i + $diff->s ? ', ' : '')) : '';
            $runtimeString .= $diff->m ? ($diff->m . '&nbsp;' . ($diff->m > 1 ? $this->translator->trans('months') : $this->translator->trans('month')) . ($diff->d + $diff->h + $diff->i + $diff->s ? ', ' : '')) : '';
            $runtimeString .= $diff->d ? ($diff->d . '&nbsp;' . ($diff->d > 1 ? $this->translator->trans('days') : $this->translator->trans('day')) . ($diff->h + $diff->i + $diff->s ? ', ' : '')) : '';
            $runtimeString .= $diff->h ? ($diff->h . '&nbsp;' . ($diff->h > 1 ? $this->translator->trans('hours') : $this->translator->trans('hour')) . ($diff->i + $diff->s ? ', ' : '')) : '';
            $runtimeString .= $diff->i ? ($diff->i . '&nbsp;' . ($diff->i > 1 ? $this->translator->trans('minutes') : $this->translator->trans('minute')) . ($diff->s ? ', ' : '')) : '';
            $runtimeString .= $diff->s ? ($diff->s . '&nbsp;' . ($diff->s > 1 ? $this->translator->trans('seconds') : $this->translator->trans('second'))) : '';

        } else {
            $runtimeString = "";
        }
        return $runtimeString;
    }

    private function pagination(int $page, int $categoryId, int $totalResults, int $maxResults): string
    {
        if ($totalResults == 0) return "";

        $totalPages = ceil($totalResults / $maxResults);
        if ($totalPages < 2) return "";

        $previousPage = $page > 1 ? $page - 1 : null;
        $nextPage = $page < $totalPages ? $page + 1 : null;

        if ($page < 1 || $page > $totalPages) {
            throw new InvalidArgumentException('Page number out of range');
        }
        $html = '<div class="pagination">';

        // Previous page button
        if ($previousPage) {
            $html .= '<a href="?page=' . $previousPage . ($categoryId ? '&category=' . $categoryId : '') . '"><button class="page">' . $this->translator->trans("Previous page") . '</button></a> ';
        }

        // Wrapper for page buttons
        $html .= '<span class="page-buttons">';

        // Page buttons from 1 to page-1
        for ($i = 1; $i < $page; $i++) {
            $html .= '<a href="?page=' . $i . ($categoryId ? '&category=' . $categoryId : '') . '"><button class="page">' . $i . '</button></a> ';
        }

        // Current page button
        $html .= '<button class="page active">' . $page . '</button> ';

        // Page buttons from page+1 to totalPages
        for ($i = $page + 1; $i <= $totalPages; $i++) {
            $html .= '<a href="?page=' . $i . ($categoryId ? '&category=' . $categoryId : '') . '"><button class="page">' . $i . '</button></a> ';
        }

        // Close wrapper for page buttons
        $html .= '</span> ';

        // Next page button
        if ($nextPage) {
            $html .= '<a href="?page=' . $nextPage . ($categoryId ? '&category=' . $categoryId : '') . '"><button class="page">' . $this->translator->trans("Next page") . '</button></a> ';
        }
        $html .= '<span class="total-pages">' . $this->translator->trans("count videos", ["count" => $totalResults]) . ' / ' . $this->translator->trans("count pages", ["count" => $totalPages]) . '</span>';
        $html .= '</div>';

        return $html;
    }
}
