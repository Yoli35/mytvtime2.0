<?php

namespace App\Service;

class ImageService
{

    public static function webpImage(string $sourcePath, string $destPath, int $quality = 100, int $width = 1280, int $height = 720, bool $removeOld = true): ?string
    {
        $destination = $destPath;

        $info = getimagesize($sourcePath);
        $isAlpha = false;
        $sourceWidth = $info[0];
        $sourceHeight = $info[1];

        if ($info['mime'] == 'image/jpeg')
            $image = imagecreatefromjpeg($sourcePath);
        elseif ($isAlpha = $info['mime'] == 'image/gif') {
            $image = imagecreatefromgif($sourcePath);
        } elseif ($isAlpha = $info['mime'] == 'image/png') {
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
            if ($isAlpha) {
                imagealphablending($newImage, false);
                imagesavealpha($newImage, true);
                if ($sourceX || $sourceY)
                    imagefilledrectangle($newImage, 0, 0, $width, $height, imagecolorallocatealpha($newImage, 0, 0, 0, 127));
            } else {
                if ($sourceX || $sourceY)
                    imagefill($newImage, 0, 0, imagecolorallocate($newImage, 0, 0, 0));
            }
            imagecopyresampled($newImage, $image, 0, 0, $sourceX, $sourceY, $width, $height, $sourceWidth, $sourceHeight);

            imagewebp($newImage, $destination, $quality);
            imagedestroy($newImage);
        } else {
            imagewebp($image, $destination, $quality);
            imagedestroy($image);
        }

        if ($removeOld) unlink($sourcePath);

        return $destination;
    }
}