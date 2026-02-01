<?php

namespace App\Api;

use App\Entity\EpisodeLocalizedOverview;
use App\Entity\User;
use App\Repository\EpisodeLocalizedOverviewRepository;
use App\Repository\EpisodeStillRepository;
use App\Repository\EpisodeSubstituteNameRepository;
use App\Repository\SeriesBroadcastDateRepository;
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
    private string $logoUrl;
    private string $stillUrl;
    private string $locale;
    private string $timezone;
    private array $seasonUS;
    private array $episodeIds;
    private array $dbBroadcastDateArray;
    private array $dbEpisodeSubstituteNames;
    private array $dbEpisodeLocalizedOverviews;
    private array $dbEpisodeStills;
    private array $userEpisodes;
    private array $providersInfos;

    public function __construct(
        private readonly EpisodeStillRepository             $episodeStillRepository,
        private readonly EpisodeLocalizedOverviewRepository $episodeLocalizedOverviewRepository,
        private readonly EpisodeSubstituteNameRepository    $episodeSubstituteNameRepository,
        private readonly ImageConfiguration                 $imageConfiguration,
        private readonly DateService                        $dateService,
        private readonly SeriesBroadcastDateRepository      $seriesBroadcastDateRepository,
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
        $this->logoUrl = $this->imageConfiguration->getUrl('logo_sizes', 2);
        $this->stillUrl = $this->imageConfiguration->getUrl('still_sizes', 3);
        $this->locale = $request->getLocale();
        $this->timezone = $user?->getTimezone() ?? 'Europe/Paris';

        $season = json_decode($this->tmdbService->getTvSeason($tmdbId, $seasonNumber, $user->getPreferredLanguage() ?? $request->getLocale()), true);
        $this->seasonUS = json_decode($this->tmdbService->getTvSeason($tmdbId, $seasonNumber, 'en-US'), true);
        $this->episodeIds = array_column($season['episodes'] ?? [], 'id');
        $this->dbBroadcastDateArray = $this->getCustomBroadcastDates();
        $this->dbEpisodeSubstituteNames = $this->episodeSubstituteNameRepository->findBy(['episodeId' => $this->episodeIds]);
        $this->dbEpisodeLocalizedOverviews = $this->episodeLocalizedOverviewRepository->findBy(['episodeId' => $this->episodeIds]);
        $this->dbEpisodeStills = $this->episodeStillRepository->findBy(['episodeId' => $this->episodeIds]);
        $this->userEpisodes = $this->userEpisodeRepository->findBy(['user' => $user, 'episodeId' => $this->episodeIds]);
        $this->providersInfos = $this->getProvidersInfos();

        $baseLink = "/" . $this->locale . "/series/season/$id-$slug/$seasonNumber#episode-$seasonNumber-";

        // TODO: Utiliser cette logique pour toutes les saisons
        // Trouver l'épisode final avec 'episode_type' = 'finale' pour supprimer les suivants
        $finaleEpisode = array_find($season['episodes'] ?? [], function ($e) {
            return ($e['episode_type'] ?? 'standard') === 'finale';
        });
        $finalEpisodeNumber = $finaleEpisode ? $finaleEpisode['episode_number'] : null;
        // Filtrer les épisodes pour ne garder que ceux jusqu'à l'épisode final
        if ($finalEpisodeNumber !== null) {
            $season['episodes'] = array_filter($season['episodes'] ?? [], function ($e) use ($finalEpisodeNumber) {
                return $e['episode_number'] <= $finalEpisodeNumber;
            });
        }
        // Fin TODO

        $episodes = array_map(function ($episode) use ($baseLink) {
            // Substitute Name
            $substituteName = array_find($this->dbEpisodeSubstituteNames, function ($esn) use ($episode) {
                return $esn->getEpisodeId() === $episode['id'];
            });
            if ($substituteName) {
                $episode['name'] .= " - " . $substituteName->getName();
            }
            // Overview
            $episode['overview'] = $this->getOverview($episode['overview'], $episode['id'], $episode['episode_number']);
            // Still
            $episode['still_path'] = $this->getStillPath($episode['still_path'], $episode['id']);
            // User Episode
            $userInfos = $this->getUserInfos($episode['id']);
            // Custom Broadcast Date
            if ($this->dbBroadcastDateArray[$episode['id']] ?? false) {
                $episode['air_date'] = $this->dbBroadcastDateArray[$episode['id']];
            }
            return [
                'air_date' => $episode['air_date'] ? ucfirst($this->dateService->formatDateRelativeLong($episode['air_date'], 'UTC', $this->locale)) : $this->translator->trans('No date'),
                'episode_number' => $episode['episode_number'],
                'link' => $baseLink . $episode['episode_number'],
                'name' => $episode['name'],
                'overview' => $episode['overview'],
                'provider_name' => $userInfos['providerName'],
                'provider_path' => $userInfos['providerPath'],
                'vote_color_background' => $userInfos['voteColorBackground'],
                'vote_color' => $userInfos['voteColor'],
                'runtime' => $episode['runtime'] ?? $season['episode_run_time'][0] ?? $seasonUS['episode_run_time'][0] ?? null,
                'still' => $episode['still_path'],
                'vote' => $userInfos['vote'],
                'watchedAt' => $userInfos['watchedAt'] ? ucfirst($this->dateService->formatDateRelativeLong($userInfos['watchedAt']->format("Y-m-d H:i"), $this->timezone, $this->locale)) : null,
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

    private function getCustomBroadcastDates(): array
    {
        $dbBroadcastDates = $this->seriesBroadcastDateRepository->findBy(['episodeId' => $this->episodeIds]);
        $dbBroadcastDateArray = [];
        foreach ($dbBroadcastDates as $dbBroadcastDate) {
            $dbBroadcastDateArray[$dbBroadcastDate->getEpisodeId()] = $dbBroadcastDate->getDate()->format('Y-m-d H:i');
        }
        return $dbBroadcastDateArray;
    }

    private function getProvidersInfos(): array
    {
        $providersInfos = [];
        $providerIds = array_unique(array_map(function ($ue) {
            return $ue->getProviderId();
        }, array_filter($this->userEpisodes, function ($ue) {
            return $ue->getProviderId() !== null;
        })));
        if (count($providerIds) > 0) {
            $watchProviders = $this->watchProviderRepository->findBy(['providerId' => $providerIds]);
            foreach ($watchProviders as $wp) {
                $wpId = $wp->getProviderId();
                $path = $wp->getLogoPath();
                if (str_starts_with($path, '/')) {
                    $path = $this->logoUrl . $path;
                    $pathForColor = $path;
                } else {
                    $path = '/images/providers' . substr($path, 1);
                    $pathForColor = $this->getParameter('kernel.project_dir') . '/public/' . substr($path, 1);
                }

                $colors = $this->detectColors($pathForColor);
                if ($colors === true) {
                    $colors = [
                        'bgcolor' => '#69968C',
                        'color' => '#E1E7EA',
                    ];
                }

                $providersInfos[$wpId] = [
                    'path' => $path,
                    'name' => $wp->getProviderName(),
                    'vote_color_background' => "#" . $colors['bgcolor'],
                    'vote_color' => "#" . $colors['color'],
                ];
            }
        }
        return $providersInfos;
    }

    private function getOverview(string $overview, int $episodeId, int $episodeNumber): string
    {
        if ($overview === '') {
            // Si overview est vide, on vérifie si on a une version 'fr' (ou 'en', 'ko') dans la base de données.
            $localizedOverview = array_find($this->dbEpisodeLocalizedOverviews, function ($eo) use ($episodeId) {
                return $eo->getEpisodeId() === $episodeId && $eo->getLocale() === $this->locale;
            });
            if (!$localizedOverview) {
                // Si overview est vide, on vérifie si on a une version 'en' en base
                $localizedOverview = array_find($this->dbEpisodeLocalizedOverviews, function ($eo) use ($episodeId) {
                    return $eo->getEpisodeId() === $episodeId && $eo->getLocale() === 'en';
                });
            }
            if ($localizedOverview) {
                $overview = $localizedOverview->getOverview();
            }
        }
        if ($overview === '') {
            $episodeUS = array_find($this->seasonUS['episodes'] ?? [], function ($e) use ($episodeNumber) {
                return $e['episode_number'] === $episodeNumber;
            });
            $overview = $episodeUS['overview'] ?? '';
            if (strlen($overview) > 0) {
                $localizedOverview = new EpisodeLocalizedOverview($episodeId, $overview, 'en');
                $this->episodeLocalizedOverviewRepository->save($localizedOverview, true);
            }
        }
        return $overview;
    }

    private function getStillPath(?string $path, int $episodeId): ?string
    {
        if ($path !== null) {
            return $this->stillUrl . $path;
        }

        $episodeStill = array_find($this->dbEpisodeStills, function ($es) use ($episodeId) {
            return $es->getEpisodeId() === $episodeId;
        });
        if ($episodeStill) {
            $path = "/series/stills" . $episodeStill->getPath();
        }

        return $path;
    }

    private function getUserInfos(int $episodeId): array
    {
        if (count($this->userEpisodes) === 0) {
            return [
                'watchedAt' => null,
                'providerPath' => null,
                'providerName' => null,
                'voteColorBackground' => null,
                'voteColor' => null,
                'vote' => null,
            ];
        }

        $providerPath = null;
        $providerName = null;
        $voteColorBackground = null;
        $voteColor = null;
        $vote = null;

        $userEpisode = array_find($this->userEpisodes, function ($ue) use ($episodeId) {
            return $ue->getEpisodeId() === $episodeId;
        });
        $watchedAt = $userEpisode->getWatchAt();
        if ($watchedAt && $wpId = $userEpisode->getProviderId()) {
            $providerPath = $this->providersInfos[$wpId]['path'];
            $providerName = $this->providersInfos[$wpId]['name'];
            $voteColorBackground = $this->providersInfos[$wpId]['vote_color_background'];
            $voteColor = $this->providersInfos[$wpId]['vote_color'];
        }
        if ($watchedAt) {
            $vote = $userEpisode->getVote();
        }

        return [
            'watchedAt' => $watchedAt,
            'providerPath' => $providerPath,
            'providerName' => $providerName,
            'voteColorBackground' => $voteColorBackground,
            'voteColor' => $voteColor,
            'vote' => $vote,
        ];
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
     * @return array|bool - Array of hex bgcolor / color codes, or true on error
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
            }
        }

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