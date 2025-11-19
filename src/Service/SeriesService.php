<?php

namespace App\Service;

use App\Entity\Network;
use App\Entity\Series;
use App\Entity\SeriesBroadcastSchedule;
use App\Entity\SeriesLocalizedName;
use App\Entity\Settings;
use App\Entity\User;
use App\Entity\UserEpisode;
use App\Repository\NetworkRepository;
use App\Repository\SeriesLocalizedNameRepository;
use App\Repository\SettingsRepository;
use App\Repository\SourceRepository;
use App\Repository\UserSeriesRepository;
use DateMalformedStringException;
use DateTimeImmutable;
use Psr\Log\LoggerInterface as MonologLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Contracts\Translation\TranslatorInterface;

/** @method User|null getUser() */
class SeriesService extends AbstractController
{
    public function __construct(
        private readonly DateService                   $dateService,
        private readonly ImageConfiguration            $imageConfiguration,
        private readonly ImageService                  $imageService,
        private readonly KeywordService                $keywordService,
        private readonly MonologLogger                 $logger,
        private readonly NetworkRepository             $networkRepository,
        private readonly SeriesLocalizedNameRepository $seriesLocalizedNameRepository,
        private readonly SettingsRepository            $settingsRepository,
        private readonly SourceRepository              $sourceRepository,
        private readonly TMDBService                   $tmdbService,
        private readonly TranslatorInterface           $translator,
        private readonly UserSeriesRepository          $userSeriesRepository,
    )
    {
    }

    public function getTv(Series $series, string $country, string $locale): ?array
    {
        $seriesTmdbId = $series->getTmdbId();
        $tv = json_decode($this->tmdbService->getTv($seriesTmdbId, $locale, [
            "changes",
            "credits",
            "external_ids",
            "images",
            "keywords",
            "lists",
            "similar",
            "translations",
            "videos",
            "watch/providers",
        ]), true);

        if (!$tv) {
            return null;
        }
        if ($tv['lists']['total_results'] == 0) {
            // Get with en-US language to get the lists
            $tv['lists'] = json_decode($this->tmdbService->getTvLists($seriesTmdbId), true);
        }
        if ($tv['similar']['total_results'] == 0) {
            // Get with en-US language to get the similar series
            $tv['similar'] = json_decode($this->tmdbService->getTvSimilar($seriesTmdbId), true);
        }
        $posterUrl = $this->imageConfiguration->getUrl('poster_sizes', 5);
        $backdropUrl = $this->imageConfiguration->getUrl('backdrop_sizes', 3);
        $tv['similar']['results'] = $this->getSimilarSeries($tv['similar']['results'], $posterUrl);

        $this->imageService->saveImage("posters", $tv['poster_path'], $posterUrl);
        $this->imageService->saveImage("backdrops", $tv['backdrop_path'], $backdropUrl);

        $tv['seasons'] = $this->seasonsPosterPath($tv['seasons']);
        $tv['additional_overviews'] = $series->getSeriesAdditionalLocaleOverviews($locale);
        $tv['translations'] = $this->getTranslations($tv['translations']['translations'], $country, $locale);
        $tv['localized_name'] = $this->getTvLocalizedName($tv, $series, $locale);
        $tv['localized_overviews'] = $series->getLocalizedOverviews($locale);
        $tv['keywords']['results'] = $this->keywordService->keywordsCleaning($tv['keywords']['results']);
        $tv['missing_translations'] = $this->keywordService->keywordsTranslation($tv['keywords']['results'], $locale);
        $tv['networks'] = $this->networks($tv);
        $tv['sources'] = $this->sourceRepository->findBy([], ['name' => 'ASC']);
        $tv['last_episode_to_air'] = $this->getEpisodeToAir($tv['last_episode_to_air'], $series);
        $tv['next_episode_to_air'] = $this->getEpisodeToAir($tv['next_episode_to_air'], $series);
        $tv['average_episode_run_time'] = $this->getEpisodeRunTime($tv['episode_run_time']);

        return $tv;
    }

    public function seasonsPosterPath(array $seasons): array
    {
        /*$slugger = new AsciiSlugger();*/
        $posterUrl = $this->imageConfiguration->getUrl('poster_sizes', 5);
        return array_map(function ($season) use (/*$slugger,*/ $posterUrl) {
//            $season['slug'] = $slugger->slug($season['name'])->lower()->toString();
            $season['poster_path'] = $season['poster_path'] ? $posterUrl . $season['poster_path'] : null;
            return $season;
        }, $seasons);
    }

    public function getTranslations(array $translations, string $country, string $locale): ?array
    {
        $translation = null;

        foreach ($translations as $t) {
            if ($t['iso_3166_1'] == $country && $t['iso_639_1'] == $locale) {
                $translation = $t;
                break;
            }
        }
        if ($translation == null) {
            foreach ($translations as $t) {
                if ($t['iso_3166_1'] == $country) {
                    $translation = $t;
                    break;
                }
            }
        }
        if ($translation == null) {
            foreach ($translations as $t) {
                if ($t['iso_639_1'] == $locale) {
                    $translation = $t;
                    break;
                }
            }
        }
        // get en-Us if null
        if ($translation == null) {
            foreach ($translations as $t) {
                if ($t['iso_3166_1'] == 'US' && $t['iso_639_1'] == 'en') {
                    $translation = $t;
                    break;
                }
            }
        }
        return $translation;
    }

    public function getSeriesAround(int $userId, int $userSeriesId, string $locale): array
    {
        $seriesAround = $this->userSeriesRepository->getSeriesAround($userId, $userSeriesId, $locale);
        $previousSeries = null;
        $nextSeries = null;
        if (count($seriesAround) == 2) {
            $previousSeries = $seriesAround[0];
            $nextSeries = $seriesAround[1];
        }
        if (count($seriesAround) == 1 and $seriesAround[0]['id'] < $userSeriesId) {
            $previousSeries = $seriesAround[0];
            $nextSeries = $this->userSeriesRepository->getFirstSeries($userId, $locale)[0];
        }
        if (count($seriesAround) == 1 and $seriesAround[0]['id'] > $userSeriesId) {
            $previousSeries = $this->userSeriesRepository->getLastSeries($userId, $locale)[0];
            $nextSeries = $seriesAround[0];
        }
        return [
            'previous' => $previousSeries,
            'next' => $nextSeries,
        ];
    }

    public function getSimilarSeries(array $tvSimilarResults, string $posterUrl): array
    {
        return array_map(function ($s) use ($posterUrl) {
            $s['poster_path'] = $s['poster_path'] ? $posterUrl . $s['poster_path'] : null;
            $s['tmdb'] = true;
            $s['slug'] = new AsciiSlugger()->slug($s['name']);
            return $s;
        }, $tvSimilarResults);
    }

    public function getSeriesShowTranslations(): array
    {
        return [
            'Add to favorites' => $this->translator->trans('Add to favorites'),
            'Add' => $this->translator->trans('Add'),
            'Additional overviews' => $this->translator->trans('Additional overviews'),
            'After tomorrow' => $this->translator->trans('After tomorrow'),
            'Available' => $this->translator->trans('Available'),
            'Delete' => $this->translator->trans('Delete'),
            'Edit' => $this->translator->trans('Edit'),
            'Ended' => $this->translator->trans('Ended'),
            'Localized overviews' => $this->translator->trans('Localized overviews'),
            'Now' => $this->translator->trans('Now'),
            'Remove from favorites' => $this->translator->trans('Remove from favorites'),
            'Since' => $this->translator->trans('Since'),
            'That\'s all!' => $this->translator->trans('That\'s all!'),
            'This field is required' => $this->translator->trans('This field is required'),
            'To be continued' => $this->translator->trans('To be continued'),
            'Today' => $this->translator->trans('Today'),
            'Tomorrow' => $this->translator->trans('Tomorrow'),
            'Update' => $this->translator->trans('Update'),
            'Watch on' => $this->translator->trans('Watch on'),
            'available' => $this->translator->trans('available'),
            'day' => $this->translator->trans('day'),
            'days' => $this->translator->trans('days'),
            "and" => $this->translator->trans('and'),
            'since' => $this->translator->trans('since'),
            'Season completed' => $this->translator->trans('Season completed'),
            'Up to date' => $this->translator->trans('Up to date'),
            'Not a valid file type. Update your selection' => $this->translator->trans('Not a valid file type. Update your selection'),
        ];
    }

    public function getSeriesSeasonTranslations(): array
    {
        return [
            'Add to favorites' => $this->translator->trans('Add to favorites'),
            'Add' => $this->translator->trans('Add'),
            'Additional overviews' => $this->translator->trans('Additional overviews'),
            'After tomorrow' => $this->translator->trans('After tomorrow'),
            'Available' => $this->translator->trans('Available'),
            'Delete' => $this->translator->trans('Delete'),
            'Desktop' => $this->translator->trans('Desktop'),
            'Edit' => $this->translator->trans('Edit'),
            'Ended' => $this->translator->trans('Ended'),
            'Laptop' => $this->translator->trans('Laptop'),
            'Localized overviews' => $this->translator->trans('Localized overviews'),
            'Mobile' => $this->translator->trans('Mobile'),
            'No votes' => $this->translator->trans('No votes'),
            'Not a valid file type. Update your selection' => $this->translator->trans('Not a valid file type. Update your selection'),
            'Now' => $this->translator->trans('Now'),
            'Remove from favorites' => $this->translator->trans('Remove from favorites'),
            'Search' => $this->translator->trans('Search'),
            'Season completed' => $this->translator->trans('Season completed'),
            'Since' => $this->translator->trans('Since'),
            'Tablet' => $this->translator->trans('Tablet'),
            'Television' => $this->translator->trans('Television'),
            'That\'s all!' => $this->translator->trans('That\'s all!'),
            'This field is required' => $this->translator->trans('This field is required'),
            'To be continued' => $this->translator->trans('To be continued'),
            'Today' => $this->translator->trans('Today'),
            'Tomorrow' => $this->translator->trans('Tomorrow'),
            'Up to date' => $this->translator->trans('Up to date'),
            'Update' => $this->translator->trans('Update'),
            'Watch on' => $this->translator->trans('Watch on'),
            'add' => $this->translator->trans('Add'),
            'additional' => $this->translator->trans('No overview'),
            "and" => $this->translator->trans('and'),
            'available' => $this->translator->trans('available'),
            'click' => $this->translator->trans('Click here to add an image'),
            'copied' => $this->translator->trans('The link has been copied to your clipboard'),
            'day' => $this->translator->trans('day'),
            'days' => $this->translator->trans('days'),
            'device' => $this->translator->trans('Choose a device'),
            'hour' => $this->translator->trans('hour'),
            'hours' => $this->translator->trans('hours'),
            'loading' => $this->translator->trans('Loading filming locations…'),
            'markAsWatched' => $this->translator->trans('Mark this episode as seen'),
            'minute' => $this->translator->trans('minute'),
            'minutes' => $this->translator->trans('minutes'),
            'now' => $this->translator->trans('Now'),
            'paste' => $this->translator->trans('Paste your image here (green)'),
            'poiToggler' => $this->translator->trans('Points of Interest toggle display'),
            'provider' => $this->translator->trans('Choose a provider'),
            'rating' => $this->translator->trans('Give a rating'),
            'second' => $this->translator->trans('second'),
            'seconds' => $this->translator->trans('seconds'),
            'since' => $this->translator->trans('since'),
        ];
    }

    public function getSeriesVideoList(Series $series): array
    {
        return array_map(function ($v) {
            $vArr['title'] = $v->getTitle();
            $link = $v->getLink();
            if (strlen($link) === 11) {
                $vArr['link'] = 'https://www.youtube.com/embed/' . $link;
                $vArr['iframe'] = true;
            } else {
                $vArr['link'] = $link;
                $vArr['iframe'] = false;
            }
            return $vArr;
        }, $series->getSeriesVideos()->toArray());
    }

    public function isVideoListFolded(int $videoCount, User $user): bool
    {
        if ($videoCount) {
            $settings = $this->settingsRepository->findOneBy(['user' => $user, 'name' => 'series_video_list_folded']);
            if (!$settings) {
                $settings = new Settings($user, 'series_video_list_folded', ['folded' => true]);
                $this->settingsRepository->save($settings, true);
            }
            return $settings->getData()['folded'];
        }
        return true;
    }

    public function getTvLocalizedName(array $tv, Series $series, string $locale): ?SeriesLocalizedName
    {
        $newLocalizedName = $series->getLocalizedName($locale);

        if (!$newLocalizedName && $tv['translations'] && $tv['name'] != $tv['translations']['data']['name']) {
            if (strlen($tv['translations']['data']['name'])) {
                $slugger = new AsciiSlugger();
                $slug = $slugger->slug($tv['translations']['data']['name'])->lower()->toString();
                $newLocalizedName = new SeriesLocalizedName($series, $tv['translations']['data']['name'], $slug, $locale);
                $this->seriesLocalizedNameRepository->save($newLocalizedName, true);
                $this->addFlash('success', 'The series name “' . $newLocalizedName->getName() . '” has been added to the database.');
            }
        }
        return $newLocalizedName;
    }

    public function getEpisodeToAir(?array $ep, Series $series): ?array
    {
        if (!$ep) return null;

        $ep['still_path'] = $ep['still_path'] ? $this->imageConfiguration->getUrl('still_sizes', 2) . $ep['still_path'] : null;
        $ep['url'] = $this->generateUrl('app_series_season', [
                'id' => $series->getId(),
                'slug' => $series->getSlug(),
                'seasonNumber' => $ep['season_number'],
            ]) . "#episode-" . $ep['season_number'] . '-' . $ep['episode_number'];

        return $ep;
    }

    public function getEpisodeRuntime(array $episodeRuntimeArray): int
    {
        $c = count($episodeRuntimeArray);
        return $c ? array_reduce($episodeRuntimeArray, function ($carry, $item) {
                return $carry + $item;
            }, 0) / $c : 0;
    }

    public function networks(array $tv): array
    {
        if (!count($tv['networks'])) return [];

        $logoUrl = $this->imageConfiguration->getUrl('logo_sizes', 5);
        $ids = array_column($tv['networks'], 'id');
        $networkDbs = $this->networkRepository->findBy(['networkId' => $ids]);

        $now = $this->now();

        foreach ($tv['networks'] as $tvNetwork) {
            $id = $tvNetwork['id'];
            /** @var Network $networkDb */
            $arr = array_filter($networkDbs, fn($n) => $n->getNetworkId() == $id);//$this->networkRepository->findOneBy(['networkId' => $id]);
            $networkDb = array_values($arr)[0] ?? null;

            if (!$networkDb) {
                $tmdbNetwork = json_decode($this->tmdbService->getNetworkDetails($id), true);

                if (!$tmdbNetwork) {
                    $this->addFlash('error', $this->translator->trans('network.not_found') . ' → ' . $tvNetwork['name'] . ' (ID: ' . $id . ')');
                    continue;
                }
                $networkDb = new Network($tmdbNetwork['logo_path'], $tmdbNetwork['name'], $id, $tmdbNetwork['origin_country'], $now);
                $networkDb->setHeadquarters($tmdbNetwork['headquarters']);
                $networkDb->setHomepage($tmdbNetwork['homepage']);
                $this->networkRepository->save($networkDb);
                $this->addFlash('success', $this->translator->trans('network.added') . ' → ' . $networkDb->getName());
            } else {
                $diff = $networkDb->getUpdatedAt()->diff($now);
                if ($diff->days > 30) {
                    $tmdbNetwork = json_decode($this->tmdbService->getNetworkDetails($networkDb->getNetworkId()), true);

                    if (!$tmdbNetwork) {
                        $this->addFlash('error', $this->translator->trans('network.not_found') . ' → ' . $networkDb->getName() . ' (ID: ' . $networkDb->getNetworkId() . ')');
                        continue;
                    }
                    $networkDb->setHeadquarters($tmdbNetwork['headquarters']);
                    $networkDb->setHomepage($tmdbNetwork['homepage']);
                    $networkDb->setLogoPath($tmdbNetwork['logo_path']);
                    $networkDb->setName($tmdbNetwork['name'] ?? 'The name was null');
                    $networkDb->setOriginCountry($tmdbNetwork['origin_country']);
                    $networkDb->setUpdatedAt($now);
                    $this->networkRepository->save($networkDb);
                    $this->addFlash('success', $this->translator->trans('network.updated') . ' → ' . $networkDb->getName());
                }
            }
        }

        $dbNetworks = $this->networkRepository->findBy(['networkId' => $ids]);
        return array_map(function ($network) use ($logoUrl, $dbNetworks) {
            $network['logo_path'] = $network['logo_path'] ? $logoUrl . $network['logo_path'] : null; // w92
            $dbNetwork = array_values(array_filter($dbNetworks, fn($n) => $n->getNetworkId() == $network['id']))[0] ?? null;
            if ($dbNetwork) {
                $network['headquarters'] = $dbNetwork->getHeadquarters();
                $network['homepage'] = $dbNetwork->getHomepage();
            } else {
                $network['headquarters'] = null;
                $network['homepage'] = null;
            }
            return $network;
        }, $tv['networks']);
    }

    public function getUserVotes(array $tvSeasons, array $userEpisodes): array
    {
        $userVotes = [];
        foreach ($userEpisodes as $ue) {
            $userVotes[$ue->getSeasonNumber()]['ues'][] = $ue;
            $userVotes[$ue->getSeasonNumber()]['avs'][] = 0;
        }
        foreach ($tvSeasons as $season) {
            if (key_exists('vote_average', $season)) {
                $userVotes[$season['season_number']]['avs'] = array_fill(0, $season['episode_count'], $season['vote_average']);
            } else {
                $userVotes[$season['season_number']]['avs'] = array_fill(0, $season['episode_count'], 0);
            }
            if (!key_exists('ues', $userVotes[$season['season_number']])) {
                $userVotes[$season['season_number']]['ues'] = $season['episode_count'] ? [] : array_fill(0, $season['episode_count'], null);
            }
        }
        return $userVotes;
    }

    public function alternateSchedules(array $tvSeasons, Series $series, array $userEpisodes): array
    {
        $alternateSchedules = array_map(function ($s) use ($tvSeasons, $userEpisodes) {
            // Ajouter la "user" séries pour les épisodes vus
            return $this->getAlternateSchedule($s, $tvSeasons, array_filter($userEpisodes, function ($ue) use ($s) {
                return $ue->getSeasonNumber() == $s->getSeasonNumber();
            }));
        }, $series->getSeriesBroadcastSchedules()->toArray());
        foreach ($alternateSchedules as &$s) {
            $s['airDays'] = array_map(function ($day) use ($s, $series) {
                $day['url'] = $this->generateUrl('app_series_season', [
                        'id' => $series->getId(),
                        'slug' => $series->getSlug(),
                        'seasonNumber' => $s['seasonNumber'],
                    ]) . "#episode-" . $s['seasonNumber'] . '-' . $day['episodeNumber'];
                return $day;
            }, $s['airDays']);
        }
        return $alternateSchedules;
    }

    public function getAlternateSchedule(SeriesBroadcastSchedule $schedule, array $tvSeasons, array $userEpisodes): array
    {
        $errorArr = ['override' => false, 'seasonNumber' => 0, 'multiPart' => false, 'seasonPart' => 0, 'airDays' => []];
        $now = $this->now();
        $seasonNumber = $schedule->getSeasonNumber();
        $override = $schedule->isOverride();
        $multiPart = $schedule->isMultiPart();
        $seasonPart = $schedule->getSeasonPart();

        if (!count($tvSeasons)) {
            $dayArr = [];
            $episodeNumber = 1;
            $airAt = $schedule->getAirAt();
            foreach ($userEpisodes as $userEpisode) {
                $ue = $userEpisode;
                $date = $ue->getAirDate()->setTime($airAt->format('H'), $airAt->format('i'));
                $dayArr[] = ['date' => $date, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $episodeNumber), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $episodeNumber), 'future' => $now < $date];
                $episodeNumber++;
            }
            return ['override' => false, 'seasonNumber' => $seasonNumber, 'multiPart' => false, 'seasonPart' => 0, 'airDays' => $dayArr];
        }
        /*if (!$seasonNumber) {
            return $errorArr;
        }*/
        $frequency = $schedule->getFrequency();
        $firstAirDate = $schedule->getFirstAirDate();
        $airAt = $schedule->getAirAt();
        $daysOfWeek = $schedule->getDaysOfWeek();

        if ($schedule->isMultiPart()) {
            $firstEpisode = $schedule->getSeasonPartFirstEpisode();
            $episodeCount = $schedule->getSeasonPartEpisodeCount();
            $lastEpisode = $firstEpisode + $episodeCount - 1;
        } else {
            $firstEpisode = 1;
            $season = array_find($tvSeasons, fn($season) => $season['season_number'] === $seasonNumber);
            $episodeCount = $season['episode_count'];
            $lastEpisode = $episodeCount;
        }
        // Frequency values:
        //  1 - All at once
        //  2 - Daily
        //  3 - Weekly, one at a time
        //  4 - Weekly, two at a time
        //  5 - Weekly, three at a time
        // 11 - Weekly, four at a time
        //  6 - Weekly, two, then one
        //  7 - Weekly, three, then one
        //  8 - Weekly, four, then one
        //  9 - Weekly, four, then two
        // 10 - Weekly, selected days
        // 12 - Selected days, then weekly, one at a time

        $firstAirDate = $firstAirDate->setTime($airAt->format('H'), $airAt->format('i'));
        $date = $firstAirDate;
        $dayArr = [];
        switch ($frequency) {
            case 1: // All at once
                for ($i = $firstEpisode; $i <= $lastEpisode; $i++) {
                    $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $i), 'episodeNumber' => $i, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $i), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $i), 'future' => $now < $date];
                }
                break;
            case 2: //
                $n = array_reduce($daysOfWeek, function ($carry, $day) {
                    return $carry + $day;
                }, 0);
                if ($n) {
                    // day of week of first air date
                    $firstAirDayOfWeek = intval($firstAirDate->format('w'));
                    $weekNumber = 0;
                    $episodeIndex = $firstEpisode;
                    /*$this->addFlash('success',
                        'date: ' . $firstAirDate->format('Y-m-d')
                        . ' firstAirDayOfWeek: ' . $firstAirDayOfWeek
                        . ' daysOfWeek: ' . implode(',', $daysOfWeek),
                    );*/
                    while ($episodeIndex <= $lastEpisode) {
                        /*$this->addFlash('success', 'weekNumber: ' . $weekNumber);*/
                        for ($i = 0; $i < 7 && $episodeIndex <= $lastEpisode; $i++) {
                            for ($j = 0; $j < $daysOfWeek[$i] && $episodeIndex <= $lastEpisode; $j++) {
                                $date = $this->dateModify($firstAirDate, '+' . (($i - $firstAirDayOfWeek + 7) % 7) . ' days');
                                $date = $this->dateModify($date, "+$weekNumber week");
                                $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $episodeIndex), 'episodeNumber' => $episodeIndex, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $episodeIndex), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $episodeIndex), 'future' => $now < $date];
                                $episodeIndex++;
                                /*$this->addFlash('success', 'episode index: ' . $episodeIndex);*/
                            }
                        }
                        $weekNumber++;
                    }
                } else {
                    for ($i = $firstEpisode; $i <= $lastEpisode; $i++) {
                        $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $i), 'episodeNumber' => $i, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $i), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $i), 'future' => $now < $date];
                        $date = $this->dateModify($date, '+1 day');
                    }
                }
                break;
            case 3: // Weekly, one at a time
                for ($i = $firstEpisode; $i <= $lastEpisode; $i++) {
                    $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $i), 'episodeNumber' => $i, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $i), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $i), 'future' => $now < $date];
                    $date = $this->dateModify($date, '+1 week');
                }
                break;
            case 4: // Weekly, two at a time
                for ($i = $firstEpisode; $i <= $lastEpisode; $i += 2) {
                    $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $i), 'episodeNumber' => $i, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $i), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $i), 'future' => $now < $date];
                    $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $i + 1), 'episodeNumber' => $i + 1, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $i + 1), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $i + 1), 'future' => $now < $date];
                    $date = $this->dateModify($date, '+1 week');
                }
                break;
            case 5: // Weekly, three at a time
                for ($i = $firstEpisode; $i <= $lastEpisode; $i += 3) {
                    $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $i), 'episodeNumber' => $i, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $i), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $i), 'future' => $now < $date];
                    $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $i + 1), 'episodeNumber' => $i + 1, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $i + 1), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $i + 1), 'future' => $now < $date];
                    $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $i + 2), 'episodeNumber' => $i + 2, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $i + 2), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $i + 2), 'future' => $now < $date];
                    $date = $this->dateModify($date, '+1 week');
                }
                break;
            case 11: // Weekly, four at a time
                for ($i = $firstEpisode; $i <= $lastEpisode; $i += 4) {
                    $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $i), 'episodeNumber' => $i, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $i), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $i), 'future' => $now < $date];
                    $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $i + 1), 'episodeNumber' => $i + 1, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $i + 1), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $i + 1), 'future' => $now < $date];
                    $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $i + 2), 'episodeNumber' => $i + 2, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $i + 2), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $i + 2), 'future' => $now < $date];
                    $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $i + 3), 'episodeNumber' => $i + 3, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $i + 3), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $i + 3), 'future' => $now < $date];
                    $date = $this->dateModify($date, '+1 week');
                }
                break;
            case 6: // Weekly, two, then one
                $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $firstEpisode), 'episodeNumber' => $firstEpisode, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $firstEpisode), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $firstEpisode), 'future' => $now < $date];
                for ($i = $firstEpisode + 1; $i <= $lastEpisode; $i++) {
                    $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $i), 'episodeNumber' => $i, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $i), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $i), 'future' => $now < $date];
                    $date = $this->dateModify($date, '+1 week');
                }
                break;
            case 7: // Weekly, three, then one
                $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $firstEpisode), 'episodeNumber' => $firstEpisode, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $firstEpisode), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $firstEpisode), 'future' => $now < $date];
                $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $firstEpisode + 1), 'episodeNumber' => $firstEpisode + 1, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $firstEpisode + 1), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $firstEpisode + 1), 'future' => $now < $date];
                for ($i = $firstEpisode + 2; $i <= $lastEpisode; $i++) {
                    $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $i), 'episodeNumber' => $i, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $i), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $i), 'future' => $now < $date];
                    $date = $this->dateModify($date, '+1 week');
                }
                break;
            case 8: // 4, then 1
                $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $firstEpisode), 'episodeNumber' => $firstEpisode, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $firstEpisode), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $firstEpisode), 'future' => $now < $date];
                $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $firstEpisode + 1), 'episodeNumber' => $firstEpisode + 1, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $firstEpisode + 1), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $firstEpisode + 1), 'future' => $now < $date];
                $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $firstEpisode + 2), 'episodeNumber' => $firstEpisode + 2, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $firstEpisode + 2), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $firstEpisode + 2), 'future' => $now < $date];
                for ($i = $firstEpisode + 3; $i <= $lastEpisode; $i++) {
                    $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $i), 'episodeNumber' => $i, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $i), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $i), 'future' => $now < $date];
                    $date = $this->dateModify($date, '+1 week');
                }
                break;
            case 9: // 4, then 2
                $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $firstEpisode), 'episodeNumber' => $firstEpisode, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $firstEpisode), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $firstEpisode), 'future' => $now < $date];
                $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $firstEpisode + 1), 'episodeNumber' => $firstEpisode + 1, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $firstEpisode + 1), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $firstEpisode + 1), 'future' => $now < $date];
                for ($i = $firstEpisode + 2; $i <= $lastEpisode; $i += 2) {
                    $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $i), 'episodeNumber' => $i, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $i), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $i), 'future' => $now < $date];
                    $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $i + 1), 'episodeNumber' => $i, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $i + 1), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $i + 1), 'future' => $now < $date];
                    $date = $this->dateModify($date, '+1 week');
                }
                break;
            case 10:
                $firstDayOfWeek = intval($date->format('w'));
                $selectedDayCount = array_reduce($daysOfWeek, function ($carry, $day) {
                    return $carry + ($day > 0);
                }, 0);
                $selectedEpisodeCount = array_reduce($daysOfWeek, function ($carry, $day) {
                    return $carry + $day;
                }, 0);
                if (!$this->isValidDaysOfWeek($daysOfWeek, $selectedDayCount, $firstDayOfWeek)) {
                    return $errorArr;
                }
                // First  day of week: 5
                // DaysOfWeek: [1,0,0,0,0,1,1], [1,1,0,0,0,1,1], [1,1,1,0,0,0,1], [1,1,1,1,0,0,0]
                //$dayIndexArr = array_keys($daysOfWeek, 1);
                // → dayIndexArr: [0,5,6], [0,1,5,6], [0,1,2,6], [0,1,2,3]
                //for ($i = 0; $i < $selectedDayCount; $i++) {
                //    if ($dayIndexArr[$i] < $firstDayOfWeek) {
                //        $dayIndexArr[$i] += 7;
                //    }
                //}
                // → dayIndexArr: [7,5,6], [7,1,5,6], [7,1,2,6], [7,1,2,3]
                //sort($dayIndexArr);
                // → dayIndexArr: [5,6,7], [1,5,6,7], [1,2,6,7], [1,2,3,7]*/
                $dayIndexArr = $this->daysOfWeekToDayIndexArr($daysOfWeek, $selectedDayCount, $firstDayOfWeek);

                for ($i = $firstEpisode, $k = 1; $i <= $lastEpisode; $i += $selectedEpisodeCount, $k++) {
                    $j = $i;
                    $firstDateOfWeek = $date;
                    foreach ($dayIndexArr as $day) {
                        if ($j <= $lastEpisode) {
                            $d = $day - $firstDayOfWeek;
                            if ($d < 0) $d += 7;
                            if ($d) $date = $this->dateModify($firstDateOfWeek, '+' . $d . ' day');
                            for ($jj = 0; $jj < $daysOfWeek[$day]; $jj++) { // $jj → number of episodes on this day
                                if ($j <= $lastEpisode) {
                                    $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $j), 'episodeNumber' => $j, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $j), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $j), 'future' => $now < $date];
                                    $j++;
                                }
                            }
//                            $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $j), 'episodeNumber' => $j, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $j), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $j), 'future' => $now < $date];
//                            $j++;
                        }
                    }
//                    $date = $firstAirDate->modify('+' . $k . ' week');
                    $date = $this->dateModify($firstAirDate, '+' . $k . ' week');
                }
                break;
            case 12: // Selected days, then weekly, one at a time
                $firstDayOfWeek = intval($date->format('w'));
                $selectedDayCount = array_reduce($daysOfWeek, function ($carry, $day) {
                    return $carry + ($day > 0);
                }, 0);
                if (!$this->isValidDaysOfWeek($daysOfWeek, $selectedDayCount, $firstDayOfWeek)) {
                    return $errorArr;
                }
                $dayIndexArr = $this->daysOfWeekToDayIndexArr($daysOfWeek, $selectedDayCount, $firstDayOfWeek);
                $j = $firstEpisode;
                foreach ($dayIndexArr as $day) {
                    $d = $day - $firstDayOfWeek;
                    if ($d < 0) $d += 7;
                    if ($d) $date = $this->dateModify($date, '+' . $d . ' day');
                    for ($jj = 0; $jj < $daysOfWeek[$day]; $jj++) { // number of episodes on this day
                        if ($j <= $lastEpisode) {
                            $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $j), 'episodeNumber' => $j, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $j), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $j), 'future' => $now < $date];
                            $j++;
                        }
                    }
                }
                $secondAirDate = $this->dateModify($firstAirDate, '+' . ($dayIndexArr[$selectedDayCount - 1] - $dayIndexArr[0]) . ' day');
                for ($i = $selectedDayCount + 1; $i <= $lastEpisode; $i++) { // $i → episode number
                    $date = $this->dateModify($secondAirDate, '+' . $i - $selectedDayCount . ' week');
                    $dayArr[] = ['date' => $date, 'episodeId' => $this->getEpisodeId($userEpisodes, $seasonNumber, $i), 'episodeNumber' => $i, 'episode' => sprintf('S%02dE%02d', $seasonNumber, $i), 'watched' => $this->isEpisodeWatched($userEpisodes, $seasonNumber, $i), 'future' => $now < $date];
                }
                break;
        }
        return ['override' => $override, 'seasonNumber' => $seasonNumber, 'multiPart' => $multiPart, 'seasonPart' => $seasonPart, 'airDays' => $dayArr];
    }

    private function isValidDaysOfWeek(array $daysOfWeek, int $selectedDayCount, int $firstDayOfWeek): bool
    {
        if (!$selectedDayCount) {
            // No selected days of week
            $this->addFlash('error', $this->translator->trans('No selected days of week.'));
            return false;
        }

        if (!$daysOfWeek[$firstDayOfWeek]) {
            $dayStrings = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
            $selectedDaysString = '';
            foreach ($daysOfWeek as $key => $day) {
                if ($day) {
                    $selectedDaysString .= $this->translator->trans($dayStrings[$key]) . ', ';
                }
            }
            $selectedDaysString = rtrim($selectedDaysString, ', ');
            $firstDayString = $this->translator->trans($dayStrings[$firstDayOfWeek]);
            $this->addFlash('error', $this->translator->trans('The first day of the week must be in the selected days of the week.')
                . '<br>' . $this->translator->trans('Selected days of the week → %days%', ['%days%' => $selectedDaysString])
                . '<br>' . $this->translator->trans('First day of the week → %day%', ['%day%' => $firstDayString]));
            return false;
        }
        return true;
    }

    private function daysOfWeekToDayIndexArr(array $daysOfWeek, int $selectedDayCount, int $firstDayOfWeek): array
    {
        // First  day of week: 5
        // DaysOfWeek: [1,0,0,0,0,1,1], [1,1,0,0,0,1,1], [1,1,1,0,0,0,1], [1,1,1,1,0,0,0]
        $dayIndexArr = [];
        foreach ($daysOfWeek as $key => $day) {
            if ($day) {
                $dayIndexArr[] = $key;
            }
        }
        // → dayIndexArr: [0,5,6], [0,1,5,6], [0,1,2,6], [0,1,2,3]
        for ($i = 0; $i < $selectedDayCount; $i++) {
            if ($dayIndexArr[$i] < $firstDayOfWeek) {
                $dayIndexArr[$i] = ($dayIndexArr[$i] + 7) % 7;
            }
        }
        // → dayIndexArr: [7,5,6], [7,1,5,6], [7,1,2,6], [7,1,2,3]
        sort($dayIndexArr);
        // → dayIndexArr: [5,6,7], [1,5,6,7], [1,2,6,7], [1,2,3,7]

        return $this->reorderDayIndexArr($dayIndexArr, $firstDayOfWeek);
    }

    function reorderDayIndexArr(array $dayIndexArr, int $firstDayOfWeek): array
    {
        $first = ($firstDayOfWeek % 7 + 7) % 7;
        // normaliser en 0..6
        $normalized = array_map(fn($v) => ($v % 7 + 7) % 7, $dayIndexArr);
        // garder les valeurs uniques
        $unique = array_values(array_unique($normalized));
        // trier par distance cyclique depuis $first
        usort($unique, function (int $a, int $b) use ($first) {
            $da = ($a - $first + 7) % 7;
            $db = ($b - $first + 7) % 7;
            return $da <=> $db;
        });
        return $unique;
    }

    public function isEpisodeWatched(array $episodes, int $seasonNumber, int $episodeNumber): bool
    {
        /** @var UserEpisode $episode */
        foreach ($episodes as $episode) {
            if ($episode->getSeasonNumber() == $seasonNumber && $episode->getEpisodeNumber() == $episodeNumber) {
                return $episode->getWatchAt() != null;
            }
        }
        return false;
    }

    public function getEpisodeId(array $episodes, int $seasonNumber, int $episodeNumber): ?int
    {
        /** @var UserEpisode $episode */
        foreach ($episodes as $episode) {
            if ($episode->getSeasonNumber() == $seasonNumber && $episode->getEpisodeNumber() == $episodeNumber) {
                return $episode->getEpisodeId();
            }
        }
        return null;
    }

    public function emptySchedule(): array
    {
        $now = $this->now();
        $dayArrEmpty = array_fill(0, 7, 0);
        return [
            'id' => 0,
            'seasonNumber' => 1,
            'multiPart' => false,
            'seasonPart' => 1,
            'seasonPartFirstEpisode' => 1,
            'seasonPartEpisodeCount' => 1,
            'airAt' => "12:00",
            'timezone' => $this->getUser()->getTimezone() ?? 'UTC',
            'firstAirDate' => $now,
            'frequency' => 0,
            'override' => false,
            'providerId' => null,
            'providerName' => null,
            'providerLogo' => null,
            'targetTS' => null,
            'before' => null,
            'dayList' => [],
            'dayArr' => $dayArrEmpty,
            'userLastEpisode' => null,
            'userNextEpisode' => null,
            'multiple' => null,
            'userLastNextEpisode' => null,
            'tvLastEpisode' => null,
            'tvNextEpisode' => null,
            'toBeContinued' => null,
            'tmdbStatus' => null,
        ];
    }

    public function now(): DateTimeImmutable
    {
        $user = $this->getUser();
        $timezone = $user?->getTimezone() ?? 'Europe/Paris';
        return $this->dateService->newDateImmutable('now', $timezone);
    }

    public function date(string $dateString, bool $allDays = false): DateTimeImmutable
    {
        $user = $this->getUser();
        $timezone = $user ? $user->getTimezone() : 'Europe/Paris';
        return $this->dateService->newDateImmutable($dateString, $timezone, $allDays);
    }

    public function dateModify(DateTimeImmutable $date, string $modify): DateTimeImmutable
    {
        try {
            return $date->modify($modify);
        } catch (DateMalformedStringException $e) {
            $this->logger->error($e->getMessage());
            return $date;
        }
    }
}