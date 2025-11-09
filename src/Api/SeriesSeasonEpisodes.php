<?php

namespace App\Api;

use App\Entity\EpisodeLocalizedOverview;
use App\Entity\User;
use App\Repository\EpisodeLocalizedOverviewRepository;
use App\Repository\EpisodeStillRepository;
use App\Repository\EpisodeSubstituteNameRepository;
use App\Repository\UserEpisodeRepository;
use App\Repository\WatchProviderRepository;
use App\Service\DateService;
use App\Service\ImageConfiguration;
use App\Service\TMDBService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/** @method User|null getUser() */
#[Route('/api/series', name: 'api_series_')]
class SeriesSeasonEpisodes extends AbstractController
{
    public function __construct(
        private readonly EpisodeStillRepository             $episodeStillRepository,
        private readonly EpisodeLocalizedOverviewRepository $episodeLocalizedOverviewRepository,
        private readonly EpisodeSubstituteNameRepository    $episodeSubstituteNameRepository,
        private readonly ImageConfiguration                 $imageConfiguration,
        private readonly DateService                        $dateService,
        private readonly TMDBService                        $tmdbService,
        private readonly TranslatorInterface                $translator,
        private readonly UserEpisodeRepository              $userEpisodeRepository,
        private readonly WatchProviderRepository            $watchProviderRepository,
    )
    {
    }

    #[Route('/season/episode/stills/{id}/{tmdbId}/{seasonNumber}/{slug}', name: 'seasons_episodes', requirements: ['id' => '\d+', 'tmdbId' => '\d+', 'season_number' => '\d+', 'slug' => '.+'], methods: ['GET'])]
    public function next(Request $request, int $id, int $tmdbId, int $seasonNumber, string $slug): JsonResponse
    {
        $user = $this->getUser();
        $logoUrl = $this->imageConfiguration->getUrl('logo_sizes', 2);
        $stillUrl = $this->imageConfiguration->getUrl('still_sizes', 3);
        $locale = $request->getLocale();
        $timezone = $user?->getTimezone() ?? 'Europe/Paris';

        $season = json_decode($this->tmdbService->getTvSeason($tmdbId, $seasonNumber, $user->getPreferredLanguage() ?? $request->getLocale()), true);
        $seasonUS = json_decode($this->tmdbService->getTvSeason($tmdbId, $seasonNumber, 'en-US'), true);
        $episodeIds = array_column($season['episodes'] ?? [], 'id');
        $dbEpisodeSubstituteNames = $this->episodeSubstituteNameRepository->findBy(['episodeId' => $episodeIds]);
        $dbEpisodeLocalizedOverviews = $this->episodeLocalizedOverviewRepository->findBy(['episodeId' => $episodeIds]);
        $dbEpisodeStills = $this->episodeStillRepository->findBy(['episodeId' => $episodeIds]);
        $userEpisodes = $this->userEpisodeRepository->findBy([
            'user' => $user,
            'episodeId' => $episodeIds,
        ]);
        $watchProviderPaths = [];
        $watchProviderNames = [];
        $voteColorBackgrounds = [];
        $voteColors = [];
        $providerIds = array_unique(array_map(function ($ue) {
            return $ue->getProviderId();
        }, array_filter($userEpisodes, function ($ue) {
            return $ue->getProviderId() !== null;
        })));
        if (count($providerIds) > 0) {
            $watchProviders = $this->watchProviderRepository->findBy(['providerId' => $providerIds]);
            foreach ($watchProviders as $wp) {
                $path = $wp->getLogoPath();
                if (str_starts_with($path, '/')) {
                    $path = $logoUrl . $path;
                    $pathForColor = $path;
                } else {
                    $path = '/images/providers' . substr($path, 1);
                    $pathForColor = $this->getParameter('kernel.project_dir') . '/public/' . substr($path, 1);
                }
                $watchProviderPaths[$wp->getProviderId()] = $path;
                $watchProviderNames[$wp->getProviderId()] = $wp->getProviderName();
                $colors = $this->detectColors($pathForColor, 10);

                if ($colors === true) {
                    $colors = [
                        'bgcolor' => '#69968C',
                        'color' => '#E1E7EA',
                    ];
                }
                $voteColorBackgrounds[$wp->getProviderId()] = "#" . $colors['bgcolor'];
                $voteColors[$wp->getProviderId()] = "#" . $colors['color'];
            }
        }

        $episodes = array_map(function ($episode) use ($id, $slug, $seasonNumber, $seasonUS, $dbEpisodeSubstituteNames, $dbEpisodeLocalizedOverviews, $dbEpisodeStills, $userEpisodes, $watchProviderPaths, $watchProviderNames, $voteColors, $voteColorBackgrounds, $locale, $timezone, $stillUrl) {
            //
            // Substitute Name
            //
            $substituteName = array_find($dbEpisodeSubstituteNames, function ($esn) use ($episode) {
                return $esn->getEpisodeId() === $episode['id'];
            });
            if ($substituteName) {
                $episode['name'] .= " - " . $substituteName->getName();
            }
            //
            // Overview
            //
            if ($episode['overview'] === '') {
                // Si overview est vide, on vérifie si on a une version 'fr' (ou 'en', 'ko') dans la base de données.
                $localizedOverview = array_find($dbEpisodeLocalizedOverviews, function ($eo) use ($episode, $locale) {
                    return $eo->getEpisodeId() === $episode['id'] && $eo->getLocale() === $locale;
                });
                if (!$localizedOverview) {
                    // Si overview est vide, on vérifie si on a une version 'en' en base
                    $localizedOverview = array_find($dbEpisodeLocalizedOverviews, function ($eo) use ($episode) {
                        return $eo->getEpisodeId() === $episode['id'] && $eo->getLocale() === 'en';
                    });
                }
                if ($localizedOverview) {
                    $episode['overview'] = $localizedOverview->getOverview();
                }
            }
            if ($episode['overview'] === '') {
//                $episodeUS = json_decode($this->tmdbService->getTvEpisode($episode['show_id'], $seasonNumber, $episode['episode_number'], 'en-US'), true);
                $episodeUS = array_find($seasonUS['episodes'] ?? [], function ($e) use ($episode) {
                    return $e['episode_number'] === $episode['episode_number'];
                });
                $episode['overview'] = $episodeUS['overview'] ?? '';
                if (strlen($episode['overview']) > 0) {
                    $localizedOverview = new EpisodeLocalizedOverview($episode['id'], $episode['overview'], 'en');
                    $this->episodeLocalizedOverviewRepository->save($localizedOverview, true);
                }
            }
            //
            // Still
            //
            if ($episode['still_path'] === null) {
                $episodeStill = array_find($dbEpisodeStills, function ($es) use ($episode) {
                    return $es->getEpisodeId() === $episode['id'];
                });
                if ($episodeStill) {
                    $episode['still_path'] = "/series/stills" . $episodeStill->getPath();
                }
            } else {
                $episode['still_path'] = $stillUrl . $episode['still_path'];
            }
            //
            // User Episode
            //
            $watchedAt = null;
            $providerPath = null;
            $providerName = null;
            $device = null;
            $vote = null;
            if (count($userEpisodes)) {
                $userEpisode = array_find($userEpisodes, function ($ue) use ($episode) {
                    return $ue->getEpisodeId() === $episode['id'];
                });
                $watchedAt = $userEpisode ? $userEpisode->getWatchAt() : false;
                if ($watchedAt) {
                    $providerPath = $userEpisode->getProviderId() ? $watchProviderPaths[$userEpisode->getProviderId()] : null;
                    $providerName = $userEpisode->getProviderId() ? $watchProviderNames[$userEpisode->getProviderId()] : null;
                    $voteColorBackground = $userEpisode->getProviderId() ? $voteColorBackgrounds[$userEpisode->getProviderId()] : null;
                    $voteColor = $userEpisode->getProviderId() ? $voteColors[$userEpisode->getProviderId()] : null;
                    $device = $userEpisode->getDeviceId();
                    $vote = $userEpisode->getVote();
                }
            }
            return [
                'air_date' => $episode['air_date'] ? ucfirst($this->dateService->formatDateRelativeLong($episode['air_date'], $timezone, $locale)) : $this->translator->trans('No date'),
                'device' => $device,
                'episode_number' => $episode['episode_number'],
                'link' => "/$locale/series/season/$id-$slug/$seasonNumber#episode-$seasonNumber-" . $episode['episode_number'],
                'name' => $episode['name'],
                'overview' => $episode['overview'],
                'provider_name' => $providerName,
                'provider_path' => $providerPath,
                'vote_color_background' => $voteColorBackground ?? null,
                'vote_color' => $voteColor ?? null,
                'runtime' => $episode['runtime'] ?? $season['episode_run_time'][0] ?? $seasonUS['episode_run_time'][0] ?? null,
                'still' => $episode['still_path'],
                'vote' => $vote,
                'watchedAt' => $watchedAt ? ucfirst($this->dateService->formatDateRelativeLong($watchedAt->format("Y-m-d H:i"), $timezone, $locale)) : null,
            ];
        }, $season['episodes'] ?? []);

        $episodeCards = array_map(function ($episode) {
            return $this->renderView('_blocks/series/_season_episode_card.html.twig', [
                'episode' => $episode,
            ]);
        }, $episodes);

        return new JsonResponse([
            'episodeCards' => implode("\n", $episodeCards),
        ]);
    }

    /**
     * ============================================================================
     * TECHNIQUE 1: EXTRACTING A COLOR PALETTE (Multiple Dominant Colors)
     * ============================================================================
     *
     * This function samples pixels across an image and identifies the most
     * frequently occurring colors. Think of it as taking a survey of the image's
     * colors and finding the most popular ones.
     *
     * @param string $image - Path to the image file
     * @param int $level - Sampling rate (higher = faster but less accurate)
     *                     1 = check every pixel (slow but accurate)
     *                     5 = check every 5th pixel (faster, still accurate)
     *                     10 = check every 10th pixel (very fast, less accurate)
     *
     * @return array|bool - Array of hex color codes, or true on error
     */
    private function detectColors(string $image, int $level = 5): array|bool
    {
        // This array will store colors as keys and their frequency as values
        // Example: ['FF0000' => 150, '00FF00' => 89, '0000FF' => 234]
        $palette = [];

        // Get image dimensions and basic information
        // Returns array: [width, height, type, attr string]
        $size = getimagesize($image);

        // If getimagesize fails, the file is invalid or doesn't exist
        if (!$size) {
            return true;
        }

        // Load the image into memory as a GD image resource
        // imagecreatefromstring automatically detects the image format (JPEG, PNG, GIF, etc.)
        // This is more flexible than using imagecreatefromjpeg, imagecreatefrompng separately
        $img = imagecreatefromstring(file_get_contents($image));

        // If image creation fails, return early
        if (!$img) {
            return true;
        }

        /**
         * THE PIXEL SAMPLING LOOP
         *
         * This nested loop walks through the image, sampling pixels at regular intervals.
         * Instead of checking EVERY pixel (which would be slow), we skip pixels based
         * on the $level parameter.
         *
         * For a 1000x1000 image:
         * - $level = 1: checks 1,000,000 pixels (slow but comprehensive)
         * - $level = 5: checks 40,000 pixels (much faster, still accurate)
         * - $level = 10: checks 10,000 pixels (very fast)
         */

        // Loop through image width (x-axis), jumping by $level pixels each time
        for ($i = 0; $i < $size[0]; $i += $level) {
            // Loop through image height (y-axis), jumping by $level pixels each time
            for ($j = 0; $j < $size[1]; $j += $level) {
                // Get the color value at this specific pixel coordinate
                // Returns an integer representing the color
                $thisColor = imagecolorat($img, $i, $j);

                // Convert the integer color value to RGB components
                // Returns array: ['red' => 255, 'green' => 128, 'blue' => 0, 'alpha' => 0]
                $rgb = imagecolorsforindex($img, $thisColor);

                // Convert RGB values to hexadecimal format (e.g., "FF8000")
                // sprintf with %02X ensures each component is 2 digits with leading zeros
                // Example: RGB(255, 128, 0) becomes "FF8000"
                $color = sprintf(
                    '%02X%02X%02X',
                    $rgb['red'],
                    $rgb['green'],
                    $rgb['blue']
                );

                // Add this color to our palette or increment its count
                // If color exists: increment counter, if new: set to 1
                // This is a concise way of doing:
                if (isset($palette[$color])) {
                    $palette[$color]['count']++;
                } else {
                    $palette[$color]['count'] = 1;
                    $palette[$color]['lightness'] = $this->hexToLightness($color);
                }
//                $palette[$color] = isset($palette[$color]) ? ++$palette[$color] : 1;
            }
        }

        // Free up memory by destroying the GD image resource
        imagedestroy($img);

        // sort by count
        $paletteC = $palette;
        uasort($paletteC, function ($a, $b) {
            return $b['count'] <=> $a['count'];
        });
        $mostCommonColor = array_key_first($paletteC);
        $mostCommonColorLightness = $paletteC[$mostCommonColor]['lightness'];

        // Remove black from the palette if it is not the most common color
        if ($mostCommonColor !== '000000') {
            unset($palette['000000']);
        }

        // sort palette by lightness
        $paletteL = $palette;
        uasort($paletteL, function ($a, $b) {
            return $b['lightness'] <=> $a['lightness'];
        });
        $lightestColor = array_key_first($paletteL);
        $lightestColorLightness = $paletteL[$lightestColor]['lightness'];
        $darkestColor = array_key_last($paletteL);
        $darkestColorLightness = $paletteL[$darkestColor]['lightness'];

        $backgroundColor = $mostCommonColor;
        if (abs($mostCommonColorLightness - $lightestColorLightness) < abs($mostCommonColorLightness - $darkestColorLightness)) {
            // le plus proche est le plus clair
            $textColor = $darkestColor;
        } else {
            // le plus proche est le plus foncé
            $textColor = $lightestColor;
        }
        return ([
            'bgcolor' => $backgroundColor,
            'color' => $textColor,
        ]);
    }

    private function hexToLightness($hex): float
    {
        $red = hexdec(substr($hex, 0, 2)) / 255;
        $green = hexdec(substr($hex, 2, 2)) / 255;
        $blue = hexdec(substr($hex, 4, 2)) / 255;

        $channelMin = min($red, $green, $blue);
        $channelMax = max($red, $green, $blue);

        return (($channelMax + $channelMin) / 2) * 100;
    }
}