<?php

namespace App\Service;

use App\Repository\ImageConfigRepository;
use JetBrains\PhpStorm\ArrayShape;

class ImageConfiguration
{
    private array $imageConfiguration;

    public function __construct(private readonly TMDBService $tmdbService)
    {
        $config = json_decode($this->tmdbService->imageConfiguration(), true);

        $this->imageConfiguration = [
            'url' => $config['images']['secure_base_url'],
            'backdrop_sizes' => $config['images']['backdrop_sizes'],
            'logo_sizes' => $config['images']['logo_sizes'],
            'poster_sizes' => $config['images']['poster_sizes'],
            'profile_sizes' => $config['images']['profile_sizes'],
            'still_sizes' => $config['images']['still_sizes'],
        ];
    }

    #[ArrayShape(['url' => "string", 'backdrop_sizes' => "array", 'logo_sizes' => "array", 'poster_sizes' => "array", 'profile_sizes' => "array", 'still_sizes' => "array"])]
    public function getConfig(): array
    {
        return $this->imageConfiguration;
    }

    public function getUrl($type, $size): string
    {
        return $this->imageConfiguration['url'] . $this->imageConfiguration[$type][$size];
    }

    public function getCompleteUrl(string $path, $type, $size): string
    {
        return $this->imageConfiguration['url'] . $this->imageConfiguration[$type][$size] . $path;
    }
}