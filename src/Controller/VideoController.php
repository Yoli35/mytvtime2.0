<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\UserVideo;
use App\Entity\Video;
use App\Entity\VideoChannel;
use App\Repository\UserVideoRepository;
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
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
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
        private readonly DateService            $dateService,
        private readonly ImageService           $imageService,
        private readonly TranslatorInterface    $translator,
        private readonly VideoChannelRepository $channelRepository,
        private readonly VideoRepository        $videoRepository,
        private readonly UserVideoRepository    $userVideoRepository,
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

    #[Route('/', name: 'index')]
    public function index(Request $request): Response
    {
        $user = $this->getUser();
        $now = $this->dateService->getNowImmutable($user->getTimezone(), true);
        $newLink = $request->query->get('link');
        if ($newLink) {
            $link = $this->parseLink($newLink);
//            dump(['link' => $link, 'newLink' => $newLink]);
            if ($link) {
                $this->addVideo($link, $now);
            } else {
                $this->addFlash('error', 'Invalid YouTube link: "' . $newLink . '"<br> Please provide a valid YouTube link.');
            }
        }

        $userVideos = $this->userVideoRepository->findBy(['user' => $user], ['createdAt' => 'DESC']);

        foreach ($userVideos as $userVideo) {
            $this->checkVideo($userVideo->getVideo(), $now);
            $this->formatDates($userVideo);
        }
// trier les "user videos" par date de publication de la video
        usort($userVideos, function (UserVideo $a, UserVideo $b) {
            return $b->getVideo()->getPublishedAt() <=> $a->getVideo()->getPublishedAt();
        });

        return $this->render('video/index.html.twig', [
            'videos' => $userVideos,
            'now' => $now,
        ]);
    }

    #[Route('/show/{id}', name: 'show')]
    public function show(UserVideo $userVideo): Response
    {
        $video = $userVideo->getVideo();

        if (!$video) {
            throw $this->createNotFoundException('Video not found');
        }
        $this->formatDates($userVideo);

        return $this->render('video/show.html.twig', [
            'userVideo' => $userVideo,
            'video' => $video,
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
//        dump($channel);
//        if (!$channel) {
//            $channelId = $youtubeVideo->getItems()[0]['snippet']['channelId'];
//            $channel = $this->checkChannel($channelId);
//        }

        // Get comments
        $now = $this->dateService->getNowImmutable($this->getUser()->getTimeZone() ?? 'Europe/Paris');
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

    private function parseLink(string $link): ?string
    {
        $pattern = '/(?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/(?:[^\/\n\s]+\/\S+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([a-zA-Z0-9_-]{11})/';
        preg_match($pattern, $link, $matches);
        return $matches[1] ?? null;
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
            $userVideo = new UserVideo($this->getUser(), $video);
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
//            dump(['type' => 'video', 'basename' => $basename, 'dirname' => $thumbnailUrl]);
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
//                dump(['type' => 'channel', 'basename' => $basename, 'dirname' => $thumbnailUrl]);
                $this->imageService->saveImage2($thumbnailUrl, '/videos/channels/thumbnails' . $basename);
            } else {
                $basename = null;
            }

            if ($channel == null) {
                $channel = new VideoChannel($item['id'], $snippet['title'], $snippet['customUrl'], $thumbnailUrl, $now);
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
        $now = $this->dateService->getNowImmutable($this->getUser()->getTimeZone() ?? 'Europe/Paris');

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
        //dump($comments, $commentArray);
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
            $customUrl = str_replace('http://www.youtube.com/', '', $customUrl);
            $customUrl = str_replace('https://www.youtube.com/', '', $customUrl);
            $customUrl = str_replace('https://youtube.com/', '', $customUrl);
            $customUrl = str_replace('youtube.com/', '', $customUrl);
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

        //dump(['durationInSecond' => $durationInSecond, 'h' => $h, 'm' => $m, 's' => $s, 'duration' => $duration]);

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
}
