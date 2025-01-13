<?php

namespace App\Service;

class ImageService
{
    public static function webpImage(string $sourcePath, string $destPath, int $quality = 100, int $width = 1920, int $height = 1080, bool $removeOld = true): ?string
    {
        $destination = $destPath;

        $info = getimagesize($sourcePath);
        if ($info === false) {
            return null;
        }
        $isAlpha = false;
        $sourceWidth = $info[0];
        $sourceHeight = $info[1];

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
            dump([
                'destRatio' => $destRatio,
                'sourceRation' => $sourceRation,
                'sourceWidth' => $sourceWidth,
                'sourceHeight' => $sourceHeight,
                'sourceX' => $sourceX,
                'sourceY' => $sourceY,
            ]);
            $newImage = imagecreatetruecolor($width, $height);
            // On ajoute un fond noir pour les images dont l'aspect ratio est différent de 16 / 9 (1920 / 1080)
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
            $successfullyConverted = imagewebp($newImage, $destination, $quality);
            imagedestroy($newImage);
            imagedestroy($image);
        } else {
            $successfullyConverted = imagewebp($image, $destination, $quality);
            imagedestroy($image);
        }

        if ($successfullyConverted && $removeOld) unlink($sourcePath);

        return $destination;
    }
}