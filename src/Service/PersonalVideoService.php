<?php

namespace App\Service;

use App\Entity\PersonalVideo;
use App\Entity\User;
use App\Repository\PersonalVideoRepository;
use DateTimeImmutable;
use DateTimeZone;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Process\Process;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Throwable;

/**
 * Conversion des vidéos personnelles en MP4 (H.264 / AAC) avec ffmpeg.
 *
 * Sur l'hébergement mutualisé IONOS, un compte est limité à 42 processus / threads (ulimit -u)
 * et 768 Mo de mémoire virtuelle (ulimit -v) : ffmpeg doit impérativement tourner sur un seul thread
 * (-threads 1 pour le décodeur et l'encodeur, -filter_threads 1 pour les filtres), sinon la création
 * des threads échoue (EAGAIN) avec des messages trompeurs (« Invalid data found », « Generic error… »).
 */
class PersonalVideoService
{
    private string $tempDir;
    private string $videoDir;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        string                                   $projectDir,
        #[Autowire('%env(FFMPEG_PATH)%')]
        private readonly string                  $ffmpeg,
        #[Autowire('%env(FFPROBE_PATH)%')]
        private readonly string                  $ffprobe,
        private readonly DateService             $dateService,
        private readonly LoggerInterface         $logger,
        private readonly PersonalVideoRepository $personalVideoRepository,
    )
    {
        $this->tempDir = $projectDir . '/var/videos/temp/';
        $this->videoDir = $projectDir . '/public/videos/personal/';
    }

    /**
     * Enregistre le fichier téléversé, puis le convertit immédiatement s'il suffit de changer de conteneur
     * (H.264 / AAC → MP4, moins d'une seconde). Sinon, la vidéo reste en attente pour la commande app:videos:process.
     */
    public function import(UploadedFile $file, User $user): PersonalVideo
    {
        $now = $this->dateService->getNowImmutable('UTC');
        $title = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = strtolower($file->getClientOriginalExtension() ?: ($file->guessExtension() ?? 'mov'));

        $this->checkForPaths([$this->tempDir]);
        $sourceName = bin2hex(random_bytes(8)) . '.' . $extension;
        $file->move($this->tempDir, $sourceName);

        $video = new PersonalVideo($user, $title, $now);
        $video->setSourcePath($sourceName);
        $this->personalVideoRepository->save($video, true);

        $this->process($video, false);

        return $video;
    }

    /**
     * @param bool $allowTranscode false : seulement un changement de conteneur (rapide), la vidéo reste en attente sinon
     */
    public function process(PersonalVideo $video, bool $allowTranscode): void
    {
        $source = $this->tempDir . $video->getSourcePath();
        if (!$video->getSourcePath() || !file_exists($source)) {
            $this->fail($video, 'Source file not found: ' . $video->getSourcePath());
            return;
        }

        $probe = $this->probe($source);
        if (!$probe) {
            $this->fail($video, 'Unreadable video file (ffprobe)');
            return;
        }
        $this->setMetadata($video, $probe);

        $remux = $this->canRemux($probe);
        if (!$remux && !$allowTranscode) {
            $this->setStatus($video, PersonalVideo::STATUS_PENDING);
            return;
        }

        $userDir = $this->videoDir . $video->getUser()->getId() . '/';
        $thumbnailDir = $userDir . 'thumbnails/';
        $this->checkForPaths([$userDir, $thumbnailDir]);

        $basename = $video->getId() . '-' . strtolower((new AsciiSlugger())->slug($video->getTitle())->slice(0, 80));
        $filename = $basename . '.mp4';
        $thumbnail = $basename . '.webp';

        $this->setStatus($video, PersonalVideo::STATUS_PROCESSING);
        $t0 = microtime(true);
        $error = $remux ? $this->remux($source, $userDir . $filename) : $this->transcode($source, $userDir . $filename);
        if ($error) {
            @unlink($userDir . $filename);
            $this->fail($video, $error);
            return;
        }
        $this->logger->info(sprintf('Personal video %d %s in %.1f s', $video->getId(), $remux ? 'remuxed' : 'transcoded', microtime(true) - $t0));

        // Les dimensions et la durée du fichier final peuvent différer (rotation, mise à l'échelle)
        $output = $this->probe($userDir . $filename);
        if ($output) {
            $this->setMetadata($video, $output);
        }

        $thumbnailError = $this->thumbnail($userDir . $filename, $thumbnailDir . $thumbnail, $video->getDuration() ?? 0);
        if ($thumbnailError) {
            $this->logger->warning('Personal video thumbnail: ' . $thumbnailError);
        }

        @unlink($source);
        $video->setSourcePath(null);
        $video->setFilename($filename);
        $video->setThumbnail($thumbnailError ? null : $thumbnail);
        $video->setSize(filesize($userDir . $filename) ?: null);
        $video->setError(null);
        $this->setStatus($video, PersonalVideo::STATUS_READY);
    }

    public function delete(PersonalVideo $video): void
    {
        $userDir = $this->videoDir . $video->getUser()->getId() . '/';
        if ($video->getFilename()) {
            @unlink($userDir . $video->getFilename());
        }
        if ($video->getThumbnail()) {
            @unlink($userDir . 'thumbnails/' . $video->getThumbnail());
        }
        if ($video->getSourcePath()) {
            @unlink($this->tempDir . $video->getSourcePath());
        }
        $this->personalVideoRepository->remove($video, true);
    }

    public function fail(PersonalVideo $video, string $error): void
    {
        $this->logger->error('Personal video ' . $video->getId() . ': ' . $error);
        if ($video->getSourcePath()) {
            @unlink($this->tempDir . $video->getSourcePath());
            $video->setSourcePath(null);
        }
        $video->setError(mb_substr($error, 0, 2000));
        $this->setStatus($video, PersonalVideo::STATUS_FAILED);
    }

    private function setStatus(PersonalVideo $video, string $status): void
    {
        $video->setStatus($status);
        $video->setUpdatedAt($this->dateService->getNowImmutable('UTC'));
        $this->personalVideoRepository->save($video, true);
    }

    private function probe(string $path): ?array
    {
        $process = new Process([$this->ffprobe, '-v', 'error', '-print_format', 'json', '-show_format', '-show_streams', $path]);
        $process->setTimeout(60);
        try {
            $process->run();
        } catch (Throwable $e) {
            $this->logger->error('ffprobe: ' . $e->getMessage());
            return null;
        }
        if (!$process->isSuccessful()) {
            $this->logger->error('ffprobe: ' . $process->getErrorOutput());
            return null;
        }
        $probe = json_decode($process->getOutput(), true);

        return $this->videoStream($probe) ? $probe : null;
    }

    /**
     * Les navigateurs lisent le H.264 8 bits 4:2:0 et l'AAC : il suffit alors de copier les flux dans un conteneur MP4.
     */
    private function canRemux(array $probe): bool
    {
        $video = $this->videoStream($probe);
        if (($video['codec_name'] ?? '') !== 'h264' || ($video['pix_fmt'] ?? '') !== 'yuv420p') {
            return false;
        }
        foreach ($probe['streams'] as $stream) {
            if (($stream['codec_type'] ?? '') === 'audio' && ($stream['codec_name'] ?? '') !== 'aac') {
                return false;
            }
        }
        return true;
    }

    private function remux(string $source, string $destination): ?string
    {
        return $this->ffmpeg([
            '-i', $source,
            '-map', '0:v:0', '-map', '0:a:0?',
            '-c', 'copy',
            '-movflags', '+faststart',
            $destination,
        ], 300);
    }

    /**
     * Réencodage H.264 / AAC, limité à 1080p (côté le plus court), sur un seul thread.
     */
    private function transcode(string $source, string $destination): ?string
    {
        return $this->ffmpeg([
            '-i', $source,
            '-map', '0:v:0', '-map', '0:a:0?',
            '-vf', "scale='if(gt(iw,ih),-2,trunc(min(1080,iw)/2)*2)':'if(gt(iw,ih),trunc(min(1080,ih)/2)*2,-2)'",
            '-c:v', 'libx264', '-threads', '1', '-preset', 'veryfast', '-crf', '23', '-pix_fmt', 'yuv420p',
            '-c:a', 'aac', '-b:a', '128k',
            '-movflags', '+faststart',
            $destination,
        ], null);
    }

    /**
     * L'image est extraite en PNG puis convertie en WebP avec GD : ffmpeg n'est pas toujours compilé avec libwebp (Homebrew).
     */
    private function thumbnail(string $source, string $destination, int $duration): ?string
    {
        $png = $this->tempDir . pathinfo($destination, PATHINFO_FILENAME) . '.png';
        $error = $this->ffmpeg([
            '-ss', (string)min(1, intdiv($duration, 2)),
            '-i', $source,
            '-frames:v', '1',
            '-vf', "scale='if(gt(iw,ih),640,-2)':'if(gt(iw,ih),-2,640)'",
            '-threads', '1',
            $png,
        ], 60);
        if ($error) {
            @unlink($png);
            return $error;
        }

        $image = @imagecreatefrompng($png);
        @unlink($png);
        if (!$image) {
            return 'Unreadable thumbnail: ' . $png;
        }
        $ok = imagewebp($image, $destination, 80);

        return $ok ? null : 'Thumbnail conversion to WebP failed: ' . $destination;
    }

    /**
     * @return string|null message d'erreur, null en cas de succès
     */
    private function ffmpeg(array $arguments, ?int $timeout): ?string
    {
        $process = new Process([$this->ffmpeg, '-y', '-nostdin', '-v', 'error', '-filter_threads', '1', '-threads', '1', ...$arguments]);
        $process->setTimeout($timeout);
        try {
            $process->run();
        } catch (Throwable $e) {
            return 'ffmpeg: ' . $e->getMessage();
        }
        if (!$process->isSuccessful()) {
            return 'ffmpeg (' . $process->getExitCode() . '): ' . trim(mb_substr($process->getErrorOutput(), -2000));
        }
        return null;
    }

    private function videoStream(?array $probe): ?array
    {
        foreach ($probe['streams'] ?? [] as $stream) {
            if (($stream['codec_type'] ?? '') === 'video' && !($stream['disposition']['attached_pic'] ?? 0)) {
                return $stream;
            }
        }
        return null;
    }

    private function setMetadata(PersonalVideo $video, array $probe): void
    {
        $stream = $this->videoStream($probe);
        $width = $stream['width'] ?? null;
        $height = $stream['height'] ?? null;

        // Vidéos de téléphone : l'image est stockée couchée, avec une rotation à appliquer à l'affichage
        $rotation = (int)($stream['tags']['rotate'] ?? 0);
        foreach ($stream['side_data_list'] ?? [] as $sideData) {
            if (isset($sideData['rotation'])) {
                $rotation = (int)$sideData['rotation'];
            }
        }
        if (abs($rotation) % 180 === 90) {
            [$width, $height] = [$height, $width];
        }

        $video->setWidth($width);
        $video->setHeight($height);
        $video->setDuration(isset($probe['format']['duration']) ? (int)round($probe['format']['duration']) : null);

        if (!$video->getRecordedAt()) {
            $tags = $probe['format']['tags'] ?? [];
            $date = $tags['com.apple.quicktime.creationdate'] ?? $tags['creation_time'] ?? $stream['tags']['creation_time'] ?? null;
            if ($date) {
                try {
                    $video->setRecordedAt((new DateTimeImmutable($date))->setTimezone(new DateTimeZone('UTC')));
                } catch (Throwable) {
                }
            }
        }
    }

    private function checkForPaths(array $paths): void
    {
        foreach ($paths as $path) {
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }
    }
}
