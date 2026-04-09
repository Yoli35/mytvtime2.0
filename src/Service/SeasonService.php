<?php

namespace App\Service;

use App\Entity\SeasonPoster;
use App\Repository\SeasonPosterRepository;
use Closure;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\DependencyInjection\Attribute\AutowireMethodOf;

class SeasonService
{
    public function __construct(
        private readonly ImageService           $imageService,
        private readonly SeasonPosterRepository $seasonPosterRepository,
    )
    {
    }

    public function posters(array $seasons, int $seriesId, int $tvId, string $posterUrl): array
    {
        $new = 0;
        $seasons = array_map(function ($season) use ($posterUrl, $seriesId, $tvId, &$new) {
            $seasonPoster = $this->seasonPosterRepository->findOneBy(['seasonId' => $season['id']]);
            $localPath = "/series/season_posters/";
            if ($seasonPoster) {
                $season['poster_path'] = $localPath . $seasonPoster->getPosterPath();
                return $season;
            }
            if (!$season['poster_path']) return $season;

            $webp = $this->imageService->seasonPosterToWebp($season['poster_path'], $posterUrl);
            if ($webp) {
                $seasonPoster = new SeasonPoster($webp, $season['id'], $season['season_number'], $seriesId, $tvId);
                $this->seasonPosterRepository->save($seasonPoster);
                $new++;
                $season['poster_path'] = $localPath . $webp;
            }
            return $season;
        }, $seasons);
        if ($new) {
            $this->seasonPosterRepository->flush();
        }
        return $seasons;
    }
}