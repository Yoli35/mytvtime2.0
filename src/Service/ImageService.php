<?php

namespace App\Service;

use DateTimeImmutable;
use Exception;
use GdImage;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Throwable;

class ImageService extends AbstractController
{

    public function __construct(
        private readonly LoggerInterface $logger,
    )
    {
    }

    public function blobToWebp2(string $blob, string $title, string $location, int $n, string $path = '/public/images/map/'): ?string
    {
        $kernelProjectDir = $this->getParameter('kernel.project_dir');
        try {
            // Define constants for paths
            $imageMapPath = $kernelProjectDir . '/public/images/map/';
            $imageTempPath = $kernelProjectDir . '/public/images/temp/';
//            $blob = $this->blobs[$name];

            // Create necessary directories
            if (!file_exists($imageMapPath)) {
                mkdir($imageMapPath, 0755, true);
            }
            if (!file_exists($imageTempPath)) {
                mkdir($imageTempPath, 0755, true);
            }

            $slugger = new AsciiSlugger();
            $basename = $slugger->slug($title)->lower()->toString() . '-' . $slugger->slug($location)->lower()->toString() . '-' . $n;

            // Extract image type and extension
            if (preg_match('/^data:image\/(\w+);base64,/', $blob, $matches)) {
                $extension = $matches[1];
                $tempName = $imageTempPath . $basename . '.' . $extension;
                $destination = $imageMapPath . $basename . '.webp';

                // Remove 'data:image/' prefix and decode
                $decodedBlob = base64_decode(substr($blob, strlen('data:image/' . $extension . ';base64,') - 1));

                // Ensure we have a valid image data string
                if ($decodedBlob !== false && file_put_contents($tempName, $decodedBlob)) {
                    // Convert to WebP format
                    $webp = $this->webpImage($title . (str_contains('poi', $path) ? ' - ' . $location : ''), $tempName, $destination, 90);

                    if ($webp) {
                        return '/' . $basename . '.webp';
                    }
                }
//                throw new \RuntimeException('Failed to process the image blob');
                return null;
            }

            // If no valid image type found
            return null;

        } catch (Throwable $e) {
            // Log any errors and rethrow if required
            $this->logger?->error('Error in blobToWebp: ' . $e->getMessage());
            /*throw $e;*/
            return null;
        }
    }

    public function urlToWebp(string $url, string $title, string $location, int $n, string $path = '/public/images/map/'): ?string
    {
        $kernelProjectDir = $this->getParameter('kernel.project_dir');
        $slugger = new AsciiSlugger();
        $imageMapPath = $kernelProjectDir . $path;
        $imageTempPath = $kernelProjectDir . '/public/images/temp/';

        $extension = pathinfo($url, PATHINFO_EXTENSION);
        $basename = $slugger->slug($title)->lower()->toString() . '-' . $slugger->slug($location)->lower()->toString() . '-' . $n;
        $tempName = $imageTempPath . $basename . '.' . $extension;
        $destination = $imageMapPath . $basename . '.webp';

        $copied = $this->saveImageFromUrl($url, $tempName, true);
        if ($copied) {
            $webp = $this->webpImage($title . (str_contains('poi', $path) ? ' - ' . $location : ''), $tempName, $destination);
            if ($webp) {
                $image = '/' . $basename . '.webp';
            } else {
                $image = null;
            }
        } else {
            $image = null;
        }
        return $image;
    }

    public function fileToWebp(UploadedFile $file, string $title, string $location, int $n, string $path = '/public/images/map/'): ?string
    {
        $kernelProjectDir = $this->getParameter('kernel.project_dir');
        $slugger = new AsciiSlugger();
        $imageMapPath = $kernelProjectDir . $path;
        $imageTempPath = $kernelProjectDir . '/public/images/temp/';

        $filename = $file->getClientOriginalName();
        $isGoogleMapsImage = str_contains($filename, 'maps');
        $isAppleMapsImage = str_contains($filename, 'apple');
        $extension = $file->guessExtension();
        $basename = $slugger->slug($title)->lower()->toString() . '-' . $slugger->slug($location)->lower()->toString() . '-' . ($isGoogleMapsImage ? 'maps-' : '') . ($isAppleMapsImage ? 'apple-' : '') . $n;
        $tempName = $imageTempPath . $basename . '.' . $extension;
        $destination = $imageMapPath . $basename . '.webp';

        try {
            $file->move($imageTempPath, $basename . '.' . $extension);
            $webp = $this->webpImage($title . (str_contains('poi', $path) ? ' - ' . $location : ''), $tempName, $destination);
            if ($webp) {
                $image = '/' . $basename . '.webp';
            } else {
                $image = null;
            }
        } catch (FileException $e) {
            $this->logger?->error('Error in fileToWebp: ' . $e->getMessage());
            $image = null;
        }
        return $image;
    }

    public function userFiles2Webp(UploadedFile $file, string $type, string $username): ?string
    {
        $kernelProjectDir = $this->getParameter('kernel.project_dir');
        $slugger = new AsciiSlugger();
        $imagePath = $kernelProjectDir . '/public/images/users/' . $type . '/';
        $imageTempPath = $kernelProjectDir . '/public/images/temp/';

        $extension = $file->guessExtension();
        $basename = $slugger->slug($username)->lower()->toString() . '-' . new DateTimeImmutable()->format('Y-m-d-H-i-s');
        $tempName = $imageTempPath . $basename . '.' . $extension;
        $destination = $imagePath . $basename . '.webp';

        try {
            $file->move($imageTempPath, $basename . '.' . $extension);
            $webp = $this->webpImage("", $tempName, $destination, 90, -1); // width: -1 → no resize
            if ($webp) {
                $image = $basename . '.webp';
            } else {
                $image = null;
            }
        } catch (FileException $e) {
            $this->logger?->error('Error in userFiles2Webp: ' . $e->getMessage());
            $image = null;
        }
        return $image;
    }

    public function webpImage(string $title, string $sourcePath, string $destPath, int $quality = 100, int $width = 1920, int $height = 1080, bool $removeOld = true): ?string
    {
        $kernelProjectDir = $this->getParameter('kernel.project_dir');

        $info = getimagesize($sourcePath);
        if ($info === false) {
            return null;
        }
        $isAlpha = false;
        $sourceWidth = $info[0];
        $sourceHeight = $info[1];
//        $sourceRation = $sourceWidth / $sourceHeight;

        if ($info['mime'] == 'image/jpeg')
            $image = imagecreatefromjpeg($sourcePath);
        elseif ($isAlpha = $info['mime'] == 'image/png') {
            $image = imagecreatefrompng($sourcePath);
        } elseif ($isAlpha = $info['mime'] == 'image/webp') {
            $image = imagecreatefromwebp($sourcePath);
        } else {
            return null;
        }
        if ($isAlpha) {
            imagepalettetotruecolor($image);
            imagealphablending($image, true);
            imagesavealpha($image, true);
        }
        if ($width > 0) {
            if ($sourceWidth != $width || $sourceHeight != $height) {
                $destRatio = $width / $height;
                $sourceRation = $sourceWidth / $sourceHeight;
                $sourceX = 0;
                $sourceY = 0;
                if ($sourceRation > $destRatio) {
                    $sourceWidth = $sourceHeight * $destRatio;
                    $sourceX = ($info[0] - $sourceWidth) / 2;
                } else {
                    $sourceHeight = $sourceWidth / $destRatio;
                    $sourceY = ($info[1] - $sourceHeight) / 2;
                }

                $newImage = imagecreatetruecolor($width, $height);
                // On ajoute un fond noir pour les images dont l'aspect ratio est différent de 16 / 9 (1920 / 1080).
                if ($sourceX || $sourceY) {
                    if ($isAlpha) {
                        imagealphablending($newImage, false);
                        imagesavealpha($newImage, true);
                        imagefilledrectangle($newImage, 0, 0, $width, $height, imagecolorallocatealpha($newImage, 0, 0, 0, 127));
                    } else {
                        imagefill($newImage, 0, 0, imagecolorallocate($newImage, 0, 0, 0));
                    }
                }
                $successfullyResampled = imagecopyresampled($newImage, $image, 0, 0, $sourceX, $sourceY, $width, $height, $sourceWidth, $sourceHeight);

                if (!$successfullyResampled) {
                    imagedestroy($newImage);
                    imagedestroy($image);
                    return null;
                }
                // If the filename ($destPath) contains "maps", add "Google Maps" on the image
//                $this->markAsGoogleMaps($destPath, $kernelProjectDir, $newImage, $width, $height);
//                $this->addTitle($title, $destPath, $kernelProjectDir, $newImage, $width, $height);
                // Convert to WebP
//                $successfullyConverted = imagewebp($newImage, $destPath, $quality);
//                imagedestroy($newImage);
                $successfullyConverted = $this->composeImage($newImage, $title, $destPath, $width, $height, $quality);
            } else {
//                $this->markAsGoogleMaps($destPath, $kernelProjectDir, $image, $width, $height);
//                $this->addTitle($title, $destPath, $kernelProjectDir, $image, $width, $height);
//                $successfullyConverted = imagewebp($image, $destPath, $quality);
                $successfullyConverted = $this->composeImage($image, $title, $destPath, $width, $height, $quality);
            }
        } else {
//            $this->markAsGoogleMaps($destPath, $kernelProjectDir, $image, $sourceWidth, $sourceHeight);
//            $this->addTitle($title, $destPath, $kernelProjectDir, $image, $sourceWidth, $sourceHeight);
//            $successfullyConverted = imagewebp($image, $destPath, $quality);
            $successfullyConverted = $this->composeImage($image, $title, $destPath, $sourceWidth, $sourceHeight, $quality);
        }
        imagedestroy($image);

        if ($successfullyConverted && $removeOld) unlink($sourcePath);

        return $destPath;
    }

    private function composeImage(GdImage $gdImage, string $title, string $destPath, int $width, int $height, int $quality): bool
    {
        $kernelProjectDir = $this->getProjectDir();
        // If the filename ($destPath) contains "maps", add "Google Maps" on the image with a dark background
        $this->markAsGoogleMaps($destPath, $kernelProjectDir, $gdImage, $width, $height);
        // If the filename ($destPath) contains "apple", add "Apple Maps" on the image with a dark background
        $this->markAsAppleMaps($destPath, $kernelProjectDir, $gdImage, $width, $height);
        // If the title is not empty, add it on the image with a dark background
        $this->addTitle($title, $destPath, $kernelProjectDir, $gdImage, $width, $height);
        $successfullyConverted = imagewebp($gdImage, $destPath, $quality);
        imagedestroy($gdImage);
        return $successfullyConverted;
    }

    private function markAsGoogleMaps(string $destPath, string $kernelProjectDir, GdImage $newImage, int $width, int $height): void
    {
        // If the filename ($destPath) contains "maps", add "Google Maps" on the image with a dark background
        if (str_contains($destPath, 'maps')) {
            $font = $kernelProjectDir . '/public/fonts/google-sans/ProductSans-Regular.ttf';
            $text = 'Google Maps';
            $fontSize = 40;
            $radius = 8;
            $textColor = imagecolorallocate($newImage, 240, 240, 240);
            $bbox = imagettfbbox($fontSize, 0, $font, $text);
            $textWidth = $bbox[2] - $bbox[0];
            $textHeight = $bbox[1] - $bbox[7];
            // Draw a dark rectangle behind the text
            $rectangleColor = imagecolorallocate($newImage, 10, 10, 10); // semi-transparent black
            //imagefilledrectangle($newImage, $width - $textWidth - 30, $height - $textHeight - 30, $width - 10, $height - 10, $rectangleColor);
            $this->ImageRoundFilledRectangle($newImage, $width - $textWidth - 60, $height - $textHeight - 30, $width - 20, $height - 10, $radius, $rectangleColor);
            // Add the text
            imagettftext($newImage, $fontSize, 0, $width - $textWidth - 40, $height - 30, $textColor, $font, $text);
        }
    }

    private function markAsAppleMaps(string $destPath, string $kernelProjectDir, GdImage $newImage, int $width, int $height): void
    {
        if (str_contains($destPath, 'apple')) {
            $font = $kernelProjectDir . '/public/fonts/google-sans/ProductSans-Regular.ttf';
            $text = 'Apple Maps';
            $fontSize = 40;
            $radius = 8;
            $textColor = imagecolorallocate($newImage, 240, 240, 240);
            $bbox = imagettfbbox($fontSize, 0, $font, $text);
            $textWidth = $bbox[2] - $bbox[0];
            $textHeight = $bbox[1] - $bbox[7];

            $rectangleColor = imagecolorallocate($newImage, 10, 10, 10); // semi-transparent black
            $this->ImageRoundFilledRectangle($newImage, $width - $textWidth - 60, $height - $textHeight - 30, $width - 20, $height - 10, $radius, $rectangleColor);
            imagettftext($newImage, $fontSize, 0, $width - $textWidth - 40, $height - 30, $textColor, $font, $text);
        }
    }

    private function addTitle(string $title, string $destPath, string $kernelProjectDir, GdImage $newImage, int $width, int $height): void
    {
        if ($title === "") {
            return; // No title to add
        }
        $font = $kernelProjectDir . '/public/fonts/google-sans/ProductSans-Regular.ttf';
        $fontSize = 40;
        $radius = 8;
        $textColor = imagecolorallocate($newImage, 240, 240, 240); // #f0f0f0
        $bbox = imagettfbbox($fontSize, 0, $font, $title);
        $textWidth = $bbox[2] - $bbox[0];
        $textHeight = $bbox[1] - $bbox[7];
        // Draw a dark rectangle behind the text
        $rectangleColor = imagecolorallocate($newImage, 76, 32, 4); // semi-transparent black
        //imagefilledrectangle($newImage, $width - $textWidth - 30, $height - $textHeight - 30, $width - 10, $height - 10, $rectangleColor);
        $this->ImageRoundFilledRectangle($newImage, 20, $height - $textHeight - 40, $textWidth + 60, $height - 10, $radius, $rectangleColor);
        // Add the text
        imagettftext($newImage, $fontSize, 0, 40, $height - 30, $textColor, $font, $title);
    }

    private function ImageRoundFilledRectangle(GdImage &$im, int $x1, int $y1, int $x2, int $y2, int $radius, int $color): void
    {
// draw rectangle without corners
        imagefilledrectangle($im, $x1 + $radius, $y1, $x2 - $radius, $y2, $color);
        imagefilledrectangle($im, $x1, $y1 + $radius, $x2, $y2 - $radius, $color);
// draw circled corners
        imagefilledellipse($im, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($im, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($im, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($im, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
    }

    public function saveImage($type, $imagePath, $imageUrl, $localPath = "/series/"): bool
    {
        if (!$imagePath) return false;
        $kernelProjectDir = $this->getParameter('kernel.project_dir');

        return $this->saveImageFromUrl(
            $imageUrl . $imagePath,
            $kernelProjectDir . "/public" . $localPath . $type . $imagePath
        );
    }

    public function saveImage2(string $src, string $dest): bool
    {
        if (!$dest) return false;
        $kernelProjectDir = $this->getParameter('kernel.project_dir');

        return $this->saveImageFromUrl(
            $src,
            $kernelProjectDir . "/public" . $dest
        );
    }

    public function saveImageFromUrl(string $imageUrl, string $localeFile, bool $dontValidate = false): bool
    {
        if (!file_exists($localeFile)) {

            // Vérifier si l'URL de l'image est valide
            if ($dontValidate || filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                // Récupérer le contenu de l'image à partir de l'URL
                try {
                    $imageContent = file_get_contents($imageUrl);

                    // Ouvrir un fichier en mode écriture binaire
                    $file = fopen($localeFile, 'wb');

                    // Écrire le contenu de l'image dans le fichier
                    fwrite($file, $imageContent);

                    // Fermer le fichier
                    fclose($file);

                    return true;
                } catch (Exception) {
                    return false;
                }
            } else {
                return false;
            }
        }
        return true;
    }

    public function getProjectDir(): string
    {
        return $this->getParameter('kernel.project_dir');
    }
}