<?php

namespace App\Service;

use Closure;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\DependencyInjection\Attribute\AutowireMethodOf;

readonly class BackgroundService
{
    public function __construct(
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure $getParameter,
    )
    {
    }

    public function getRandomBackgroundImage(string $directory = "/series/posters_blurred"): ?string
    {
        $root = $this->getProjectDir() . '/public';

        $files = $this->fileList($root . $directory);
        if (count($files) === 0) {
            return null;
        }
        $randomFile = $files[array_rand($files)];
        return $directory . '/' . $randomFile;
    }

    private function fileList(string $directory): array
    {
        $files = [];
        if (is_dir($directory)) {
            if ($dh = opendir($directory)) {
                while (($file = readdir($dh)) !== false) {
                    if ($file != '.' && $file != '..') {
                        $files[] = $file;
                    }
                }
                closedir($dh);
            }
        }
        return $files;
    }

    public function getProjectDir(): string
    {
        return ($this->getParameter)('kernel.project_dir');
    }
}
