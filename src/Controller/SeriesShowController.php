<?php

namespace App\Controller;

use App\Api\ApiWatchLink;
use App\Entity\Series;
use App\Entity\SeriesExternal;
use App\Entity\SeriesImage;
use App\Entity\SeriesVideo;
use App\Entity\Settings;
use App\Entity\User;
use App\Entity\UserEpisode;
use App\Entity\UserSeries;
use App\Form\AddBackdropType;
use App\Form\SeriesVideoType;
use App\Repository\DeviceRepository;
use App\Repository\EpisodeStillRepository;
use App\Repository\FilmingLocationRepository;
use App\Repository\PeopleUserPreferredNameRepository;
use App\Repository\SeasonLocalizedOverviewRepository;
use App\Repository\SeriesCastRepository;
use App\Repository\SeriesExternalRepository;
use App\Repository\SeriesImageRepository;
use App\Repository\SeriesRepository;
use App\Repository\SeriesVideoRepository;
use App\Repository\SettingsRepository;
use App\Repository\SourceRepository;
use App\Repository\UserEpisodeRepository;
use App\Repository\UserSeriesRepository;
use App\Repository\WatchProviderRepository;
use App\Service\DateService;
use App\Service\ImageConfiguration;
use App\Service\ImageService;
use App\Service\ProviderService;
use App\Service\SeriesService;
use App\Service\TMDBService;
use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeInterface;
use Psr\Log\LoggerInterface as MonologLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extra\Intl\IntlExtension;

#[Route('/{_locale}/tv', name: 'app_tv_', requirements: ['_locale' => 'fr|en|ko'])]
final class SeriesShowController extends AbstractController
{
    private bool $reloadUserEpisodes = false;

    public function __construct(
        private readonly ApiWatchLink                      $watchLinkApi,
        private readonly DateService                       $dateService,
        private readonly DeviceRepository                  $deviceRepository,
        private readonly EpisodeStillRepository            $episodeStillRepository,
        private readonly FilmingLocationRepository         $filmingLocationRepository,
        private readonly ImageConfiguration                $imageConfiguration,
        private readonly ImageService                      $imageService,
        private readonly MonologLogger                     $logger,
        private readonly PeopleUserPreferredNameRepository $peopleUserPreferredNameRepository,
        private readonly ProviderService                   $providerService,
        private readonly SeasonLocalizedOverviewRepository $seasonLocalizedOverviewRepository,
        private readonly SeriesCastRepository              $seriesCastRepository,
        private readonly SeriesExternalRepository          $seriesExternalRepository,
        private readonly SeriesImageRepository             $seriesImageRepository,
        private readonly SeriesRepository                  $seriesRepository,
        private readonly SeriesService                     $seriesService,
        private readonly SeriesVideoRepository             $seriesVideoRepository,
        private readonly SettingsRepository                $settingsRepository,
        private readonly SourceRepository                  $sourceRepository,
        private readonly TMDBService                       $tmdbService,
        private readonly TranslatorInterface               $translator,
        private readonly UserEpisodeRepository             $userEpisodeRepository,
        private readonly UserSeriesRepository              $userSeriesRepository,
        private readonly WatchProviderRepository           $watchProviderRepository,
    )
    {
    }

    #[Route('/tmdb/{id}-{slug}', name: 'tmdb', requirements: ['id' => Requirement::DIGITS])]
    public function tmdb(#[CurrentUser] User $user, Request $request, int $id): Response
    {
        $series = $this->seriesRepository->findOneBy(['tmdbId' => $id]);
        $userSeries = $series ? $this->userSeriesRepository->findOneBy(['user' => $user, 'series' => $series]) : null;

        if ($userSeries) {
            return $this->redirectToRoute('app_tv_series', [
                'id' => $series->getId(),
                'slug' => $series->getSlug(),
                'oldSeriesAdded' => 'false',
            ], 301);
        }

        $country = $user->getCountry() ?: 'FR';
        $locale = $user->getPreferredLanguage() ?: $request->getLocale();

        $tv = json_decode($this->tmdbService->getTv($id, $locale, ["images", "videos", "credits", "watch/providers", "content/ratings", "keywords", "similar", "translations"]), true);
        $overview = null;
        if ($series) {
            $series->setVisitNumber($series->getVisitNumber() + 1);
            $this->seriesRepository->save($series, true);
            $localizedName = $series->getLocalizedName($locale);
            $localizedOverview = $series->getLocalizedOverview($locale);
            $overview = $localizedOverview?->getOverview() ?? null;
        } else {
            $localization = $this->localizeSeries($tv);
            $localizedName = $localization['localizedName'];
            $localizedOverview = $localization['localizedOverview'];
        }

        if ($tv['overview'] == "" && $overview) {
            $tv['overview'] = $overview;
        }
        if ($tv['overview'] == "" && !$localizedOverview) {
            $enTranslations = array_find($tv['translations']['translations'], function ($item) {
                return $item['iso_639_1'] == 'en';
            });
            $tv['overview'] = $enTranslations['data']['overview'] ?? '';
//            $this->addFlash('info', 'The series overview is missing. "' . ($enTranslations['data']['overview'] ?? 'null') . '" found.');
        }
        $this->imageService->saveImage("posters", $tv['poster_path'], $this->imageConfiguration->getUrl('poster_sizes', 5));
        $this->imageService->saveImage("backdrops", $tv['backdrop_path'], $this->imageConfiguration->getUrl('backdrop_sizes', 3));
        $tv['blurredPosterPath'] = $this->imageService->blurPoster($tv['poster_path'], 'series', 8);

        $tv['credits'] = $this->castAndCrew($tv, $series);
        $tv['networks'] = $this->seriesService->networks($tv);
        $tv['seasons'] = $this->seriesService->seasonsPosterPath($tv['seasons']);
        $tv['watch/providers'] = $this->watchProviders($tv, 'FR');
        $tv['translations'] = $this->seriesService->getTranslations($tv['translations']['translations'], $country, $locale);
        $translatedName = $tv['translations']['data']['name'] ?? null;
        $c = count($tv['episode_run_time']);
        $tv['average_episode_run_time'] = $c ? array_reduce($tv['episode_run_time'], function ($carry, $item) {
                return $carry + $item;
            }, 0) / $c : 0;

        return $this->render('series_show/tmdb.html.twig', [
            'tv' => $tv,
            'localizedName' => $localizedName,
            'translatedName' => $translatedName,
            'localizedOverview' => $localizedOverview,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/series/{id}-{slug}', name: 'series', requirements: ['id' => Requirement::DIGITS])]
    public function series(#[CurrentUser] User $user, Request $request, Series $series, string $slug): Response
    {
        $locale = $user->getPreferredLanguage() ?? $request->getLocale();
        $country = $user->getCountry() ?? 'FR';

        $forms = $this->handleSerieShowForms($request, $series);

        $userSeries = $this->userSeriesRepository->findOneBy(['user' => $user, 'series' => $series]);
        $userEpisodes = $this->userEpisodeRepository->findBy(['userSeries' => $userSeries, 'previousOccurrence' => null], ['seasonNumber' => 'ASC', 'episodeNumber' => 'ASC']);
        $this->adjustNextEpisodeToWatch($userSeries, $userEpisodes);

        $seriesAround = $this->seriesService->getSeriesAround($user->getId(), $userSeries->getId(), $locale);

        $blurredPosterPath = $this->imageService->blurPoster($series->getPosterPath(), 'series', 8);

        $series->setUpdates([]);
        $seriesArr = $series->toArray($user);
        $seriesArr['blurredPosterPath'] = $blurredPosterPath;

        $tv = $this->seriesService->getTv($series, $country, $locale);

        if (!$tv) {
            $series->setUpdates(['Series not found']);
            $noTv['additional_overviews'] = $series->getSeriesAdditionalLocaleOverviews($locale);
            $noTv['credits'] = $this->castAndCrew($tv, $series);
            $noTv['localized_name'] = $series->getLocalizedName($locale);
            $noTv['localized_overviews'] = $series->getLocalizedOverviews($locale);
            $noTv['seasons'] = $this->getUserSeasons($series, $userEpisodes);
            $noTv['sources'] = $this->sourceRepository->findBy([], ['name' => 'ASC']);
            $noTv['average_episode_run_time'] = 0;

            return $this->render("series_show/series_not_found.html.twig", [
                'series' => $seriesArr,
                'userSeries' => $userSeries,
                'previousSeries' => $seriesAround['previous'],
                'nextSeries' => $seriesAround['next'],
                'tv' => $noTv,
                'providers' => $this->watchLinkApi->getWatchProviders($country),
            ]);
        }

        $tv['credits'] = $this->castAndCrew($tv, $series);
        $tv['watch/providers'] = $this->watchProviders($tv, $country);
        $tv['status_css'] = $this->statusCss($tv);

        $tv['seasons'] = $this->trimSeasons($tv['id'], $locale, $tv['seasons']);

        $series = $this->seriesService->updateSeries($series, $tv, $seriesArr['images']);

        $userSeries = $this->updateUserSeries($userSeries, $tv);
        $userEpisodes = $this->checkSeasons($userSeries, $userEpisodes, $tv);
        if ($this->reloadUserEpisodes) {
            $userEpisodes = $this->userEpisodeRepository->findBy(['userSeries' => $userSeries, 'previousOccurrence' => null], ['seasonNumber' => 'ASC', 'episodeNumber' => 'ASC']);
            $this->addFlash('info', $this->translator->trans('Your episodes have been updated according to the series information.'));
            $this->reloadUserEpisodes = false;
        }

        $schedules = $this->seriesSchedulesV2($userSeries, $tv);
        $alternateSchedules = $this->seriesService->alternateSchedules($tv['seasons'], $series, $userEpisodes);
        $seriesArr['seasons'] = $this->overrideSeasonAirDate($tv['seasons'], $schedules);
        $seriesArr['seriesAround'] = $seriesAround;
        $seriesArr['userVotes'] = $this->seriesService->getUserVotes($tv['seasons'], $userEpisodes);
        $seriesArr['schedules'] = $schedules;
        $seriesArr['timezoneMenu'] = (new IntlExtension)->getTimezoneNames('fr_FR');
        $seriesArr['emptySchedule'] = $this->seriesService->emptySchedule();
        $seriesArr['alternateSchedules'] = $alternateSchedules;
//        $seriesArr['seriesInProgress'] = $this->userEpisodeRepository->isFullyReleased($userSeries);
        $seriesArr['images'] = $this->getSeriesImages($seriesArr['images']);
        $seriesArr['videos'] = $this->seriesService->getSeriesVideoList($series);
        $seriesArr['videoListFolded'] = $this->seriesService->isVideoListFolded(count($seriesArr['videos']), $user);

        if ($tv['backdrop_path'] == null && count($seriesArr['images']['backdrops']) > 0) {
            $tv['backdrop_path'] = substr($seriesArr['images']['backdrops'][0], strlen("/series/backdrops"));
        }

        $filmingLocationsWithBounds = $this->seriesService->getFilmingLocations($series, $tv['localized_name']);

        if ($seriesArr['seriesToWatchLater']) {
            $messages[] = [
                'status' => 'series-to-watch-later',
                'message' => [
                    'content' => $this->translator->trans('This series belongs to your watch later list!'),
                    'localized_name' => $tv['localized_name']?->getName(),
                    'name' => $seriesArr['name'],
                    'poster_path' => $series->getPosterPath(),
                    'link' => $this->generateUrl('app_tv_episode', ['_locale' => $locale, 'id' => $series->getId(), 'seasonNumber' => 1, 'episodeNumber' => 1, 'slug' => $slug]),
                    'link_text' => 'Go',
                    'remove_link' => $this->generateUrl('api_series_list_remove_from_watch_later', ['s' => $series->getId()]),
                    'remove_link_text' => $this->translator->trans('Remove this series'),
                ]
            ];
        }

        return $this->render("series_show/series.html.twig", [
            'series' => $seriesArr,
            'tv' => $tv,
            'userSeries' => $userSeries,
            'messages' => $messages ?? [],
            'providers' => $this->watchLinkApi->getWatchProviders($country),
            'locations' => $filmingLocationsWithBounds['filmingLocations'],
            'locationsBounds' => $filmingLocationsWithBounds['bounds'],
            'emptyLocation' => $filmingLocationsWithBounds['emptyLocation'],
            'addLocationFormData' => $this->seriesService->getLocationFormData($tv['id'], $series->getId()),
            'fieldList' => ['series-id', 'tmdb-id', 'crud-type', 'crud-id', 'title', 'location', 'season-number', 'episode-number', 'description', 'latitude', 'longitude', 'radius', "source-name", "source-url"],
            'mapSettings' => $this->settingsRepository->findOneBy(['name' => 'mapbox']),
            'externals' => $this->getExternals($series, $tv['keywords']['results'], $tv['external_ids'] ?? [], $locale),
            'translations' => $this->seriesService->getSeriesShowTranslations(),
            'forms' => $forms,
            'oldSeriesAdded' => $request->query->get('oldSeriesAdded') === 'true',
            'devices' => $this->deviceRepository->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/season/{id}-{slug}/{seasonNumber}', name: 'season', requirements: ['id' => Requirement::DIGITS, 'seasonNumber' => Requirement::DIGITS])]
    public function season(#[CurrentUser] User $user, Request $request, Series $series, int $seasonNumber, string $slug): Response
    {
        $locale = $user->getPreferredLanguage() ?? $request->getLocale();
        $countries = ['fr' => 'FR', 'en' => 'US', 'ko' => 'KR'];
        $country = $user->getCountry() ?? $countries[$locale] ?? 'FR';
        $this->logger->info('showSeason', ['series' => $series->getId(), 'season' => $seasonNumber, 'slug' => $slug]);

        $userSeries = $this->userSeriesRepository->findOneBy(['user' => $user, 'series' => $series]);
        $this->adjustNextEpisodeToWatch($userSeries, null);

        $season = json_decode($this->tmdbService->getTvSeason($series->getTmdbId(), $seasonNumber, $request->getLocale(), ['credits', 'watch/providers']), true);
        if (!$season) {
            return $this->redirectToRoute('app_tv_series', [
                'id' => $series->getId(),
                'slug' => $series->getSlug(),
            ]);
        }
        if (key_exists('error', $season)) {
            $this->addFlash('warning', 'Season not found on TMDB. You tried to access season ' . $seasonNumber . ' but the series "' . $series->getName() . '" has only ' . $series->getNumberOfSeason() . ' seasons.');
            return $this->redirectToRoute('app_tv_series', [
                'id' => $series->getId(),
                'slug' => $series->getSlug(),
            ]);
        }
        //$tv = $this->seriesService->getTvMini($series);

        $season['poster_path'] = $this->seriesService->cacheSeasonPoster($season, $series);
        $season['backdrop_path'] = $series->getBackdropPath();
        $season['blurred_poster_path'] = $this->imageService->blurPoster($season['poster_path'], 'series', 8);

        $season['deepl'] = null;//$this->seasonLocalizedOverview($series, $season, $seasonNumber, $request);
        $season['episodes'] = $this->seasonEpisodes($season, $userSeries, $this->seriesService->getFinaleEpisodeNumber($season), $country);
        $season['progress'] = $this->userEpisodeRepository->seasonProgress($userSeries, $seasonNumber);
        $season['air_date'] = $this->adjustSeasonAirDate($user, $season, 'date');
        $season['air_date_string'] = $this->adjustSeasonAirDate($user, $season, 'string');

        $season['credits'] = $this->castAndCrew($season, $series);
        $season['watch/providers'] = $this->watchProviders($season, $country);
        if ($season['overview'] == "") {
            $season['overview'] = $series->getOverview();
            $season['is_series_overview'] = true;
        } else {
            $season['is_series_overview'] = false;
        }
        $season['sources'] = $this->sourceRepository->findBy([], ['name' => 'ASC']);
        $season['season_localized_overview'] = $this->seasonLocalizedOverviewRepository->getSeasonLocalizedOverview($series->getId(), $seasonNumber, $request->getLocale());
        $season['series_localized_name'] = $series->getLocalizedName($request->getLocale());
        $season['series_localized_overviews'] = $series->getLocalizedOverviews($request->getLocale());
        $season['series_additional_overviews'] = $series->getSeriesAdditionalLocaleOverviews($request->getLocale());

        $providers = $this->watchLinkApi->getWatchProviders($country);
        $devices = $this->deviceRepository->deviceArray();

        // Nouvelle saison, premier épisode non vu
        if ($season['season_number'] > 1 && count($season['episodes']) && $season['episodes'][0]['user_episode']['watch_at'] == null) {
            $firstEpisode = $this->userEpisodeRepository->findOneBy(['userSeries' => $userSeries, 'seasonNumber' => $season['season_number'], 'episodeNumber' => 1]);
            $previousSeasonNumber = $season['season_number'] - 1;
            $lastEpisode = $this->userEpisodeRepository->findOneBy(['userSeries' => $userSeries, 'seasonNumber' => $previousSeasonNumber], ['episodeNumber' => 'DESC']);
            $season['episodes'][0]['user_episode']['provider_id'] = $providerId = $lastEpisode->getProviderId();
            $season['episodes'][0]['user_episode']['provider_logo_path'] = $providerId ? $providers['logos'][$providerId] : null;
            $season['episodes'][0]['user_episode']['device_id'] = $deviceId = $lastEpisode->getDeviceId();
            if ($firstEpisode->getProviderId() != $providerId) {
                $firstEpisode->setProviderId($providerId);
                $firstEpisode->setDeviceId($deviceId);
                $this->userEpisodeRepository->save($firstEpisode, true);
            }
        }

//        $tvKeywords = json_decode($this->tmdbService->getTvKeywords($series->getTmdbId()), true);
//        $tvExternalIds = json_decode($this->tmdbService->getTvExternalIds($series->getTmdbId()), true);

        $tv = json_decode($this->tmdbService->getTv($series->getTmdbId(), 'en-US'), true);
        if ($series->getNumberOfEpisode() != $tv['number_of_episodes'] || $series->getNumberOfSeason() != $tv['number_of_seasons']) {
            $this->addFlash('info', 'The number of episodes has changed, the series has been updated.');
            if ($series->getNumberOfEpisode() != $tv['number_of_episodes'])
                $series->setUpdates(['Number of episodes changed from ' . $series->getNumberOfEpisode() . ' to ' . $tv['number_of_episodes']]);
            if ($series->getNumberOfSeason() != $tv['number_of_seasons'])
                $series->setUpdates(['Number of seasons changed from ' . $series->getNumberOfSeason() . ' to ' . $tv['number_of_seasons']]);

            $series->setNumberOfEpisode($tv['number_of_episodes']);
            $series->setNumberOfSeason($tv['number_of_seasons']);
            $this->seriesRepository->save($series, true);
        }

        $filmingLocation = $this->filmingLocationRepository->location($series->getTmdbId());

        return $this->render('series_show/season.html.twig', [
            'series' => $series,
            'userSeries' => $userSeries,
            'tv' => $tv,
            'translations' => $this->seriesService->getSeasonShowTranslations(),
            'quickLinks' => $this->getQuickLinks($user, $season['episodes']),
            'season' => $season,
            'today' => $this->now($user)->format('Y-m-d H:I:s'),
            'filmingLocation' => $filmingLocation,
            'language' => $locale . '-' . $country,
            'changes' => $this->seasonChanges($user, $season['id']),
            'now' => $this->now($user)->format('Y-m-d H:i O'),
            'episodeDiv' => $this->getEpisodeDivSize($userSeries),
            'status' => $tv['status'],
            'statusTitle' => null,
            'providers' => $providers,
            'devices' => $devices,
//            'externals' => $this->getExternals($series, $tvKeywords['results'] ?? [], $tvExternalIds, $request->getLocale()),
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/episode/{id}-{slug}/{seasonNumber}/{episodeNumber}', name: 'episode', requirements: ['id' => Requirement::DIGITS, 'seasonNumber' => Requirement::DIGITS, 'episodeNumber' => Requirement::DIGITS])]
    public function episode(#[CurrentUser] User $user, Request $request, Series $series, int $seasonNumber, int $episodeNumber, string $slug): Response
    {
        $locale = $user->getPreferredLanguage() ?? $request->getLocale();
        $countries = ['fr' => 'FR', 'en' => 'US', 'ko' => 'KR'];
        $country = $user->getCountry() ?? $countries[$locale] ?? 'FR';
        $status = null;
        $statusTitle = null;

        $this->logger->info('showEpisode', ['series' => $series->getId(), 'season' => $seasonNumber, 'episode' => $episodeNumber, 'slug' => $slug]);

        $userSeries = $this->userSeriesRepository->findOneBy(['user' => $user, 'series' => $series]);
        $season = json_decode($this->tmdbService->getTvSeason($series->getTmdbId(), $seasonNumber, $locale, ['credits', 'watch/providers']), true);
        if (key_exists('error', $season)) {
            $this->seriesService->removeUserEpisodes($userSeries, $seasonNumber);
            $this->addFlash('error', $this->translator->trans('The season could not be loaded'));
            return $this->redirectToRoute('app_tv_series', ['_locale' => $locale, 'id'=> $series->getId(), 'slug' => $series->getSlug()]);
        }
        $episode = json_decode($this->tmdbService->getTvEpisode($series->getTmdbId(), $seasonNumber, $episodeNumber, $locale, ['credits', 'watch/providers']), true);
        if (key_exists('error', $episode)) {
            $this->addFlash('error', $this->translator->trans('The episode could not be loaded'));
            return $this->redirectToRoute('app_tv_season', ['_locale' => $locale, 'id'=> $series->getId(), 'slug' => $series->getSlug(), 'seasonNumber' => $seasonNumber]);
        }
        $episode['language_query'] = $locale . '-' . $country;
        if (key_exists('episode_type', $episode) && $episode['episode_type'] === 'finale') {
            $tv = json_decode($this->tmdbService->getTv($series->getTmdbId(), $locale), true);
            if (key_exists($seasonNumber + 1, $tv['seasons'])) {
                $status = 'More episodes to come';
                $nextSeasonEpisodeCount = $tv['seasons'][$seasonNumber]['episode_count'];/* saison suivante (number + 1) - 1 */
                if ($nextSeasonEpisodeCount > 1) {
                    $statusTitle = $this->translator->trans('count new episodes are now available in season number', ['count' => $nextSeasonEpisodeCount, 'number' => $seasonNumber + 1]);
                }
            }
            if ($status === null) {
                $status = $tv['status'];
            }
        }

        $finaleEpisodeNumber = $this->seriesService->getFinaleEpisodeNumber($season);
        $userEpisodes = $this->userEpisodeRepository->getUserEpisodesDB($userSeries->getId(), $season['season_number'], $locale, true);
        $stills = $this->episodeStillRepository->getSeasonStills([$episode['id']]);

        $episode = $this->seasonEpisode($episode, $userSeries, $userEpisodes, $seasonNumber, $finaleEpisodeNumber, $stills);
        $profileUrl = $this->imageConfiguration->getUrl('profile_sizes', 2);
        $peopleUserPreferredNames = $this->getPreferredNames($user);
        $episode['guest_stars'] = $this->episodeGuestStars($episode, new AsciiSlugger(), $series, $profileUrl, $peopleUserPreferredNames);

        $season['poster_path'] = $this->seriesService->cacheSeasonPoster($season, $series);
        $season['episode_count'] = $finaleEpisodeNumber;
        $season['watch/providers'] = $this->watchProviders($season, $country);
        $season['credits'] = $this->castAndCrew($season, $series);
        $season['series_localized_name'] = $series->getLocalizedName($request->getLocale());
        $season['blurred_poster_path'] = $this->imageService->blurPoster($season['poster_path'], 'series', 8);

        $filmingLocationsWithBounds = $this->seriesService->getFilmingLocations($series, $season['series_localized_name'], $seasonNumber, $episodeNumber);

        $nextEpisode = array_find($season['episodes'], fn($ep) => $ep['episode_number'] == $episodeNumber + 1);
        $episode['next_episode_is_available'] = $nextEpisode && $this->isNextEpisodeAvailable($user, $nextEpisode['air_date']);

        $episode['show_id'] = $series->getTmdbId();
        $episode['stills'] = $this->episodeStillRepository->findBy(['episodeId' => $episode['id']], ['id' => 'DESC']);

        $providers = $this->watchLinkApi->getWatchProviders($country);
        $devices = $this->deviceRepository->deviceArray();

        return $this->render('series_show/episode.html.twig', [
            'userSeries' => $userSeries,
            'series' => $series,
            'season' => $season,
            'episode' => $episode,
            'slug' => $slug,
            'status' => $status,
            'statusTitle' => $statusTitle,
            'language' => $locale . '-' . $country,
            'locations' => $filmingLocationsWithBounds['filmingLocations'],
            'locationsBounds' => $filmingLocationsWithBounds['bounds'],
            'emptyLocation' => $filmingLocationsWithBounds['emptyLocation'],
            'addLocationFormData' => $this->seriesService->getLocationFormData($series->getTmdbId(), $series->getId()),
            'fieldList' => ['series-id', 'tmdb-id', 'crud-type', 'crud-id', 'title', 'location', 'season-number', 'episode-number', 'description', 'latitude', 'longitude', 'radius', "source-name", "source-url"],
            'mapSettings' => $this->settingsRepository->findOneBy(['name' => 'mapbox']),
            'translations' => $this->seriesService->getEpisodeShowTranslations(),
            'providers' => $providers,
            'devices' => $devices,
        ]);
    }

    private function isNextEpisodeAvailable(User $user, string $dateString): bool
    {
        $timezone = $user->getTimezone() ?? 'Europe/Paris';
        $date = $this->dateService->newDateImmutable($dateString, $timezone);
        $now = $this->dateService->newDateImmutable('now', $timezone);

        return $date->getTimestamp() <= $now->getTimestamp();
    }

    private function handleSerieShowForms(Request $request, Series $series): array
    {
        $addBackdropForm = $this->createForm(AddBackdropType::class);
        $addBackdropForm->handleRequest($request);
        if ($addBackdropForm->isSubmitted() && $addBackdropForm->isValid()) {
            $data = $addBackdropForm->getData();
            $this->addBackdrop($series, $data['file']);
        }
        $addVideoForm = $this->createForm(SeriesVideoType::class, new SeriesVideo($series, "", ""));
        $addVideoForm->handleRequest($request);
        if ($addVideoForm->isSubmitted() && $addVideoForm->isValid()) {
            $data = $addVideoForm->getData();
            $this->addVideo($data);
        }
        return ['backdropForm' => $addBackdropForm->createView(), 'videoForm' => $addVideoForm->createView()];
    }

    private function addBackdrop(Series $series, UploadedFile $backdropFile): void
    {
        $source = $backdropFile->getPathname();
        $serverPath = '/public/series/backdrops/';
        $destination = $this->getParameter('kernel.project_dir') . $serverPath . $backdropFile->getClientOriginalName();
        if (copy($source, $destination)) {
            $seriesImage = new SeriesImage($series, "backdrop", '/' . $backdropFile->getClientOriginalName());
            $this->seriesImageRepository->save($seriesImage, true);
            $this->addFlash('success', 'The backdrop has been added.');
        }
    }

    private function addVideo(SeriesVideo $video): void
    {
        $this->seriesVideoRepository->save($video, true);
    }

    private function updateUserSeries(UserSeries $userSeries, array $tv): UserSeries
    {
        $change = false;
        $episodeCount = $this->checkNumberOfEpisodes($tv);

        $series = $userSeries->getSeries();
        if ($series->getNumberOfEpisode() != $episodeCount) {
            $series->setNumberOfEpisode($episodeCount);
            $this->seriesRepository->save($series, true);
            $this->addFlash('success', 'Number of episode updated to ' . $episodeCount);
        }

        if ($episodeCount == 0 && $userSeries->getProgress() != 0) {
            $this->addFlash('warning', 'Number of episodes is zero');
            $userSeries->setProgress(0);
            $change = true;
        } else {
            if (/*$userSeries->getProgress() == 100 && */ $userSeries->getViewedEpisodes() < $episodeCount) {
                $newProgress = round(100 * $userSeries->getViewedEpisodes() / $episodeCount, 2);
                if ($newProgress != $userSeries->getProgress()) {
                    $userSeries->setProgress($newProgress);
                    $this->addFlash('success', 'Progress updated to ' . $newProgress . '%');
                    $change = true;
                }
            }
            if ($userSeries->getProgress() != 100 && $episodeCount && $userSeries->getViewedEpisodes() === $episodeCount) {
                $userSeries->setNextUserEpisode(null);
                $userSeries->setProgress(100);
                $this->addFlash('success', 'Progress fixed to 100%');
                $change = true;
            }
        }
        if ($userSeries->getViewedEpisodes() == 0 && $userSeries->getProgress() != 0) {
            $userSeries->setProgress(0);
            $this->addFlash('warning', 'Progress reset to 0%');
            $change = true;
        }
        if ($change) {
            $this->userSeriesRepository->save($userSeries, true);
        }
        return $userSeries;
    }

    private function checkSeasons(UserSeries $userSeries, array $userEpisodes, array $tv): array
    {
        $user = $userSeries->getUser();
        $series = $userSeries->getSeries();
        $newEpisodeCount = 0;
        foreach ($tv['seasons'] as $season) {
            $seasonNumber = $season['season_number'];
            list($n, $r) = $this->seriesService->addSeasonToUser($user, $userSeries, $seasonNumber, array_filter($userEpisodes, function ($ue) use ($seasonNumber) {
                return $ue->getSeasonNumber() == $seasonNumber;
            }));
            $newEpisodeCount += $n;
            $this->reloadUserEpisodes = $r;
        }
        if ($newEpisodeCount) {
            $series->addUpdate($newEpisodeCount . ' ' . $this->translator->trans('new episodes have been added to the series'));
            return $this->userEpisodeRepository->findBy(['userSeries' => $userSeries, 'previousOccurrence' => null], ['seasonNumber' => 'ASC', 'episodeNumber' => 'ASC']);
        }
        return $userEpisodes;
    }

    private function adjustSeasonAirDate(User $user, array $season, string $type): ?string
    {
        if ($type == 'string') {
            $timezone = $user->getTimezone() ?? "Europe/Berlin";
            $locale = $user->getPreferredLanguage() ?? 'fr';
            if (count($season['episodes']) < 1) {
                return $season['air_date'] ? $this->dateService->formatDateRelativeLong($season['air_date'], $timezone, $locale) : $this->translator->trans('No date yet');
            }
            $firstEpisode = $season['episodes'][0];
            $airDate = $firstEpisode['air_date'];
            return $airDate ? $this->dateService->formatDateRelativeLong($airDate, $timezone, $locale) : $this->translator->trans('No date yet');
        }
        if (count($season['episodes']) < 1) {
            return $season['air_date'];
        }
        $firstEpisode = $season['episodes'][0];
        return $firstEpisode['air_date'];
    }

    private function seasonEpisodes(array $season, UserSeries $userSeries, int $finaleEpisodeNumber, string $country): array
    {
        $user = $userSeries->getUser();
        $series = $userSeries->getSeries();
        $slugger = new AsciiSlugger();
        $locale = $user->getPreferredLanguage() ?? 'fr';
        $seasonEpisodes = [];
        $episodeArr = [];
        $userEpisodes = $this->userEpisodeRepository->getUserEpisodesDB($userSeries->getId(), $season['season_number'], $locale, true);
        $peopleUserPreferredNames = $this->getPreferredNames($user);

        $episodeIds = array_column($userEpisodes, 'episode_id');
        $stills = $this->episodeStillRepository->getSeasonStills($episodeIds);

        /*$finaleEpisodeNumber = $this->seriesService->getFinaleEpisodeNumber($season);*/
        if (count($season['episodes']) > $finaleEpisodeNumber) {
            $surplus = count($season['episodes']) - $finaleEpisodeNumber;
            array_splice($season['episodes'], $finaleEpisodeNumber, $surplus);
        }
        foreach ($season['episodes'] as $episode) {

//            $episode['substitute_name'] = $this->userEpisodeRepository->getSubstituteName($episode['id']);
            $episode['locale'] = $locale;
            $episode['language_query'] = $locale . '-' . $country;
            $episodeArr[] = $this->seasonEpisode($episode, $userSeries, $userEpisodes, $season['season_number'], $finaleEpisodeNumber, $stills);
        }
        $profileUrl = $this->imageConfiguration->getUrl('profile_sizes', 2);
        foreach ($episodeArr as $episode) {
            $episode['guest_stars'] = $this->episodeGuestStars($episode, $slugger, $series, $profileUrl, $peopleUserPreferredNames);
            $seasonEpisodes[] = $episode;
        }

        $newCount = array_reduce($seasonEpisodes, function ($carry, $episode) {
            return $carry + $episode['new'] ? 1 : 0;
        }, 0);

        if ($newCount) {
            $this->addFlash('warning', $newCount . ' new episode' . ($newCount > 1 ? 's' : '') . ' added to your watchlist');
            $tv = json_decode($this->tmdbService->getTv($series->getTmdbId(), 'en-US'), true);
            $series->setNumberOfSeason($tv['number_of_seasons']);
            $series->setNumberOfEpisode($tv['number_of_episodes']);
            $this->seriesRepository->save($series, true);
            $this->addFlash('success', sprintf("Series updated (%d season%s, %d episode%s)", $tv['number_of_seasons'], $tv['number_of_seasons'] > 1 ? 's' : '', $tv['number_of_episodes'], $tv['number_of_episodes'] > 1 ? 's' : ''));
        }
        return $seasonEpisodes;
    }

    private function seasonChanges(User $user, int $seasonId): array
    {
        $now = $this->now($user);
        try {
            $startDate = $now->modify('-14 day')->format('Y-m-d');
        } catch (DateMalformedStringException $e) {
            $this->addFlash('error', 'Series changes, error while computing date: ' . $e->getMessage());
            $startDate = $now->format('Y-m-d');
        }
        $endDate = $now->format('Y-m-d');
        $results = json_decode($this->tmdbService->getTvSeasonChanges($seasonId, $endDate, $startDate), true);

        if (!isset($results['changes'])) {
            return [];
        }
        $changes = $results['changes'];
        $changes['keys'] = array_column($changes, 'key');
        return $results['changes'];
    }

    private function getPreferredNames(User $user): array
    {
        $arr = $this->peopleUserPreferredNameRepository->getUserPreferredNames($user->getId());
        $peopleUserPreferredNames = [];
        foreach ($arr as $people) {
            $peopleUserPreferredNames[$people['tmdb_id']] = $people;
        }
        return $peopleUserPreferredNames;
    }

    private function seasonEpisode(array $episode, UserSeries $userSeries, array $userEpisodes, int $seasonNumber, int $finaleEpisodeNumber, array $stills): array
    {
        $user = $userSeries->getUser();
        if ($episode['episode_number'] > $finaleEpisodeNumber) {
            $this->addFlash('warning', "// Skip episode " . sprintf("S%02dE%02d", $seasonNumber, $episode['episode_number']) . " after a finale");
            return [];
        }
        $episode['new'] = false;
        $userEpisode = $this->getUserEpisode($userEpisodes, $episode['episode_number']);
        if (!$userEpisode) {
            $nue = new UserEpisode($userSeries, $episode['id'], $seasonNumber, $episode['episode_number'], null);
            $nue->setAirDate($episode['air_date'] ? $this->date($user, $episode['air_date']) : null);
            if ($episode['episode_number'] > 1) {
                $previousEpisode = $this->getUserEpisode($userEpisodes, $episode['episode_number'] - 1);
                if ($previousEpisode) {
                    $nue->setProviderId($previousEpisode['provider_id']);
                    $nue->setDeviceId($previousEpisode['device_id']);
                }
            }
            $this->userEpisodeRepository->save($nue, true);
            $userEpisode = $this->userEpisodeRepository->getUserEpisodeDB($nue->getId(), $episode['locale']);
            $episode['new'] = true;
        }
        $series = $userSeries->getSeries();
        $next_episode_to_air = $series->getNextEpisodeAirDate();
        if (!$userEpisode['custom_date'] && !$next_episode_to_air && !$episode['air_date']) {
            return [];
        }
        if (!$userEpisode['air_date'] && $episode['air_date']) {
            $ue = $this->userEpisodeRepository->findOneBy(['userSeries' => $userSeries, 'episodeId' => $episode['id']]);
            if ($ue) {
                $airDate = $this->date($user, $episode['air_date']);
                $ue->setAirDate($airDate);
                $this->userEpisodeRepository->save($ue, true);
                $userEpisode['air_date'] = $airDate;
                $this->addFlash('success',
                    $this->translator->trans('Episode air date updated')
                    . ' (' . sprintf('S%02dE%02d', $seasonNumber, $episode['episode_number'])
                    . ' → ' . $airDate->format('Y-m-d') . ')');
            }
        }

        $userEpisodeList = $this->getUserEpisodes($userEpisodes, $episode['episode_number']);

        $stillUrl = $this->imageConfiguration->getUrl('still_sizes', 3);

        $episode['still_path'] = $episode['still_path'] ? $stillUrl . $episode['still_path'] : null; // w300
        $episode['stills'] = array_filter($stills, function ($still) use ($episode) {
            return $still['episode_id'] == $episode['id'];
        });
        if ($userEpisode['custom_date']) {
            $episode['air_date'] = $userEpisode['custom_date'];
        }

        $userEpisode['watch_at_db'] = $userEpisode['watch_at'];
        if ($userEpisode['watch_at']) {
            $userEpisode['watch_at'] = $this->date($user, $userEpisode['watch_at']);
        }
        $episode['user_episode'] = $userEpisode;
        $episode['user_episodes'] = $userEpisodeList;

        $language = $episode['language_query'];
        $noOverview = !strlen($episode['overview'])  && !strlen($userEpisode['localized_overview'] ?? '');
        $noFRName = $language === 'fr-FR' && $episode['name'] && str_starts_with($episode['name'], 'Épisode ') && !$userEpisode['substitute_name'];

        if (($noOverview || $noFRName) && $language !== 'en-US') {
            $episodeUS = json_decode($this->tmdbService->getTvEpisode($series->getTmdbId(), $episode['season_number'], $episode['episode_number'], 'en-US'), true);
            $episode['overview'] = $episodeUS['overview'];
            if ($noFRName) {
                $episode['name'] = $episodeUS['name'];
            }
        }
        return $episode;
    }

    private function getUserEpisodes(array $userEpisodes, int $episodeNumber): array
    {
        $episodes = array_values(array_filter($userEpisodes, function ($userEpisode) use ($episodeNumber) {
            return $userEpisode['episode_number'] == $episodeNumber;
        }));
        $logoUrl = $this->imageConfiguration->getUrl('logo_sizes', 2);
        $ues = [];
        foreach ($episodes as $episode) {
            $episode['provider_logo_path'] = $this->providerService->getProviderLogoFullPath($episode['provider_logo_path'], $logoUrl);
            /*if ($episode['provider_id'] > 0)
                $episode['provider_logo_path'] = $episode['provider_logo_path'] ? $logoUrl . $episode['provider_logo_path'] : null;
            else
                $episode['provider_logo_path'] = '/images/providers/' . $episode['provider_logo_path'];*/
            if (!key_exists('watch_at_db', $episode)) {
                $episode['watch_at_db'] = $episode['watch_at'];
                if ($episode['watch_at']) {
                    $episode['watch_at'] = $this->dateService->newDateImmutable($episode['watch_at'], 'UTC');
                }
            }
            $ues[] = $episode;
        }
        return $ues;
    }

    private function getUserEpisode(array $userEpisodes, int $episodeNumber): ?array
    {
        $logoUrl = $this->imageConfiguration->getUrl('logo_sizes', 2);
        foreach ($userEpisodes as $userEpisode) {
            if ($userEpisode['episode_number'] == $episodeNumber) {
                $userEpisode['provider_logo_path'] = $this->providerService->getProviderLogoFullPath($userEpisode['provider_logo_path'], $logoUrl);
                if ($userEpisode['custom_date']) {
                    $cd = $this->dateService->newDateImmutable($userEpisode['custom_date'], 'Europe/Paris');
                    $userEpisode['custom_date'] = $cd->format('Y-m-d H:i O');
                }
                if ($userEpisode['air_at']) {
                    // 10:00:00 → 10:00
                    $userEpisode['air_at'] = $this->dateService->newDateImmutable($userEpisode['air_at'], 'Europe/Paris');
                    $userEpisode['air_at'] = $userEpisode['air_at']->format('H:i');
                }
                $userEpisode['watch_at_db'] = $userEpisode['watch_at'];
                /*if ($userEpisode['watch_at']) {
                    $ue['watch_at'] = $this->dateService->newDateImmutable($userEpisode['watch_at'], 'UTC');
                }*/
                return $userEpisode;
            }
        }
        return null;
    }

    private function episodeGuestStars(array $episode, AsciiSlugger $slugger, Series $series, string $profileUrl, array $peopleUserPreferredNames): array
    {
        $guestStars = array_filter($episode['guest_stars'] ?? [], function ($guest) {
            return key_exists('id', $guest);
        });
        usort($guestStars, function ($a, $b) {
            return !$a['profile_path'] <=> !$b['profile_path'];
        });

        return array_map(function ($guest) use ($slugger, $series, $profileUrl, $peopleUserPreferredNames) {
            $guest['profile_path'] = $guest['profile_path'] ? $profileUrl . $guest['profile_path'] : null; // w185
            $guest['slug'] = $slugger->slug($guest['name'])->lower()->toString();
            if (!$guest['profile_path']) {
                $guest['google'] = 'https://www.google.com/search?q=' . urlencode($guest['name'] . ' ' . $series->getName());
            }
            if (key_exists($guest['id'], $peopleUserPreferredNames)) {
                $guest['preferred_name'] = $peopleUserPreferredNames[$guest['id']]['name'];
            } else {
                $guest['preferred_name'] = null;
            }
            return $guest;
        }, $guestStars);
    }

    private function getQuickLinks(User $user, array $episodes): array
    {
        if (!count($episodes)) {
            return ['items' => [], 'count' => 0, 'itemPerLine' => 0, 'lineCount' => 0];
        }
        $now = $this->now($user);
        $nowString = $now->format('Y-m-d H:i');

        $quickLinks = array_map(function ($link) use ($nowString) {
            if (!$link['air_date']) {
                $class = "quick-episode future";
                $future = true;
            } else {
                $airAt = $link['user_episode']['air_at'] ?? ' 09:00';
                $airString = $link['air_date'] . " " . $airAt;
                $class = "quick-episode";
                if ($link['user_episode']['watch_at_db']) {
                    $class .= " watched";
                }
                if ($airString > $nowString) {
                    $class .= " future";
                } else {
                    $class .= " enabled";
                }
                $future = $airString > $nowString;
            }
            return [
                'name' => $link['name'],
                'episode_number' => $link['episode_number'],
                'air_date' => $link['air_date'],
                'watched' => (bool)$link['user_episode']['watch_at_db'],
                'future' => $future,
                'class' => $class,
            ];
        }, $episodes);

        $count = count($quickLinks);
        if ($count <= 10) {
            $quickLinks[0]['class'] .= ' first';
            $quickLinks[$count - 1]['class'] .= ' last';
            $itemPerLine = $count;
            $lineCount = 1;
        } else {
            if ($count % 2 == 0)
                $itemPerLine = $count / 2;
            else {
                $quickLinks[] = ['name' => null, 'episode_number' => null, 'air_date' => null, 'watched' => null, 'future' => null, 'class' => 'quick-episode empty'];
                $itemPerLine = ($count + 1) / 2;
                $count += 1;
            }
            if ($itemPerLine > 10) {
                if ($count % 19 == 0) $itemPerLine = 19;
                if ($count % 17 == 0) $itemPerLine = 17;
                if ($count % 15 == 0) $itemPerLine = 15;
                if ($count % 13 == 0) $itemPerLine = 13;
                if ($count % 11 == 0) $itemPerLine = 11;
                if ($count % 10 == 0) $itemPerLine = 10;
                if ($count % 9 == 0) $itemPerLine = 9;
                if ($count % 8 == 0) $itemPerLine = 8;
                if ($count % 7 == 0) $itemPerLine = 7;
            }
            $lineCount = ceil($count / $itemPerLine);
            $quickLinks[0]['class'] .= ' top-left';
            $quickLinks[$itemPerLine - 1]['class'] .= ' top-right';
            $quickLinks[$count - $itemPerLine]['class'] .= ' bottom-left';
            $quickLinks[$count - 1]['class'] .= ' bottom-right';
        }
        return ['items' => $quickLinks, 'count' => $count, 'itemPerLine' => $itemPerLine, 'lineCount' => $lineCount];
    }

    private function seriesSchedulesV2(UserSeries $userSeries, ?array $tv): array
    {
        $schedules = [];
        $user = $userSeries->getUser();
        $series = $userSeries->getSeries();
        $locale = $user->getPreferredLanguage() ?? 'fr';
        $logoUrl = $this->imageConfiguration->getUrl('logo_sizes', 5);

        foreach ($series->getSeriesBroadcastSchedules() as $schedule) {
            $seasonNumber = $schedule->getSeasonNumber();
            if ($schedule->isMultiPart()) {
                $multiPart = true;
                $firstEpisode = $schedule->getSeasonPartFirstEpisode();
                $episodeCount = $schedule->getSeasonPartEpisodeCount();
                $lastEpisode = $firstEpisode + $episodeCount - 1;
            } else {
                $multiPart = false;
                $firstEpisode = 1;
                $episodeCount = $tv ? $this->getSeasonEpisodeCount($tv['seasons'], $seasonNumber) : 0;
                $lastEpisode = $episodeCount;
            }
            $airAt = $schedule->getAirAt();
            $firstAirDate = $schedule->getFirstAirDate();
            $frequency = $schedule->getFrequency();
            $override = $schedule->isOverride();
            $dayOfWeekArr = [
                'en' => ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
                'fr' => ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'],
                'ko' => ['일요일', '월요일', '화요일', '수요일', '목요일', '금요일', '토요일'],
            ];
            $daysOfWeek = $schedule->getDaysOfWeek();
            $scheduleDayOfWeek = [];
            foreach ($daysOfWeek as $key => $day) {
                if ($day) {
                    $scheduleDayOfWeek[] = $dayOfWeekArr[$locale][$key];
                }
            }
            $scheduleDayOfWeek = ucfirst(implode(', ', $scheduleDayOfWeek));
            $dayArr = $daysOfWeek;

            $now = $this->dateService->newDateImmutable('now', 'Europe/Paris');

            $userLastEpisode = $this->userEpisodeRepository->getScheduleLastEpisode($schedule->getId(), $userSeries->getId());
            $userNextEpisode = $this->userEpisodeRepository->getScheduleNextEpisode($schedule->getId(), $userSeries->getId());
            $userLastEpisode = $userLastEpisode[0] ?? null;
            $userNextEpisode = $userNextEpisode[0] ?? null;

            $userLastEpisode = $this->setEpisodeDatetime($user, $userLastEpisode, $airAt);
            $userNextEpisode = $this->setEpisodeDatetime($user, $userNextEpisode, $airAt);

            $endOfSeason = $userLastEpisode && $userLastEpisode['episode_number'] == $episodeCount;

            $target = null;
            $targetTimestamp = null;
            if (!$userNextEpisode && $userLastEpisode) {
                if ($multiPart) {
                    if ($userLastEpisode['episode_number'] >= $firstEpisode && $userLastEpisode['episode_number'] <= $lastEpisode) {
                        $targetTimestamp = $userLastEpisode['date']->getTimestamp();
                    } else {
                        $userLastEpisode = null;
                    }
                } else {
                    $targetTimestamp = $userLastEpisode['date']->getTimestamp();
                }
            }
            if ($userNextEpisode) {
                $userNextEpisode['episode'] = sprintf("S%02dE%02d", $seasonNumber, $userNextEpisode['episode_number']);
                if ($multiPart) {
                    if ($userNextEpisode['episode_number'] >= $firstEpisode && $userNextEpisode['episode_number'] <= $lastEpisode) {
                        if ($userNextEpisode['date'])
                            $targetTimestamp = $userNextEpisode['date']->getTimestamp();
                        else
                            $userNextEpisode = null;
                    } else {
                        $userNextEpisode = null;
                    }
                } else {
                    $targetTimestamp = $userNextEpisode['date']?->getTimestamp() ?? null;
                }
            }

            if ($userNextEpisode && $targetTimestamp) {
                $userNextEpisodes = $this->userEpisodeRepository->getScheduleNextEpisodes($schedule->getId(), $userSeries->getId(), $userNextEpisode['air_date']);
                $count = count($userNextEpisodes);
                $multiple = $count > 1;
                if ($multiple) {
                    $userLastNextEpisode = $userNextEpisodes[$count - 1];
                } else {
                    $multiple = false;
                    $userLastNextEpisode = null;
                }
            } else {
                $multiple = false;
                $userLastNextEpisode = null;
            }
            if ($userNextEpisode == null && $userLastEpisode) {
                $targetTimestamp = $userLastEpisode['date']->getTimestamp();
            }

            $providerId = $schedule->getProviderId();
            if ($providerId) {
                $provider = $this->watchProviderRepository->getNameAndLogo($providerId);
                $providerName = $provider['provider_name'];
                $providerLogo = $this->providerService->getProviderLogoFullPath($provider['logo_path'], $logoUrl);
            } else {
                $providerName = null;
                $providerLogo = null;
            }

            $schedules[] = [
                'id' => $schedule->getId(),
                'seasonNumber' => $schedule->getSeasonNumber(),
                'multiPart' => $schedule->isMultiPart(),
                'seasonPart' => $schedule->getSeasonPart(),
                'seasonPartFirstEpisode' => $schedule->getSeasonPartFirstEpisode(),
                'seasonPartEpisodeCount' => $schedule->getSeasonPartEpisodeCount(),
                'upToDate' => $userNextEpisode == null,
                'seasonCompleted' => $endOfSeason,
                'airAt' => $airAt->format('H:i'),
                'firstAirDate' => $firstAirDate,
                'timezone' => $user->getTimezone() ?? 'Europe/Paris',
                'frequency' => $frequency ?? 0,
                'override' => $override ?? false,
                'providerId' => $providerId,
                'providerName' => $providerName ?? null,
                'providerLogo' => $providerLogo ?? null,
                'targetTimestamp' => $targetTimestamp,
                'before' => $target ? $now->diff($target) : null,
                'dayList' => $scheduleDayOfWeek,
                'dayArr' => $dayArr,
                'userLastEpisode' => $userLastEpisode,
                'userNextEpisode' => $userNextEpisode,
                'multiple' => $multiple,
                'userLastNextEpisode' => $userLastNextEpisode,
                'toBeContinued' => $tv ? $this->isToBeContinued($tv, $userLastEpisode) : $userNextEpisode != null,
                'tmdbStatus' => $tv['status'] ?? 'series not found',
            ];
        }
        return $schedules;
    }

    private function getSeasonEpisodeCount(array $seasons, int $seasonNumber): int
    {
        foreach ($seasons as $season) {
            if ($season['season_number'] == $seasonNumber) {
                return $season['episode_count'];
            }
        }
        return 0;
    }

    private function overrideSeasonAirDate(array $tvSeasons, array $schedules): array
    {
        $alternativeSchedules = array_filter($schedules, function ($s) {
            return $s['override'];
        });
        if ($alternativeSchedules) {
            foreach ($tvSeasons as &$season) {
                $seasonNumber = $season['season_number'];
                $seasonSchedules = array_filter($alternativeSchedules, function ($s) use ($seasonNumber) {
                    return $s['seasonNumber'] == $seasonNumber;
                });
                $seasonSchedules = array_values($seasonSchedules);
                if ($seasonSchedules) {
                    // Remplacer la date de la saison par la date du schedule
                    $time = explode(':', $seasonSchedules[0]['airAt']);
                    $season['final_air_date'] = $seasonSchedules[0]['firstAirDate']->setTime($time[0], $time[1], 0)->format('Y-m-d H:i:s');
                }
            }
        }
        return $tvSeasons;
    }

    private function getSeriesImages(array $seriesImages): array
    {
        $seriesBackdrops = array_filter($seriesImages, fn($image) => $image->getType() == "backdrop");
        $seriesLogos = array_filter($seriesImages, fn($image) => $image->getType() == "logo");
        $seriesPosters = array_filter($seriesImages, fn($image) => $image->getType() == "poster");

        $seriesBackdrops = array_values(array_map(fn($image) => "/series/backdrops" . $image->getImagePath(), $seriesBackdrops));
        $seriesLogos = array_values(array_map(fn($image) => "/series/logos" . $image->getImagePath(), $seriesLogos));
        $seriesPosters = array_values(array_map(fn($image) => "/series/posters" . $image->getImagePath(), $seriesPosters));

        return [
            'backdrops' => $seriesBackdrops,
            'logos' => $seriesLogos,
            'posters' => $seriesPosters
        ];
    }

    private function getExternals(Series $series, array $keywords, array $externalIds, string $locale): array
    {
        $keywordIds = array_map(fn($k) => $k['id'], $keywords);

        $seriesCountries = $series->getOriginCountry();
        $dbExternals = $this->seriesExternalRepository->findAll();
        $externals = [];
        $displayName = $series->getLocalizedName($locale)?->getName() ?? $series->getName();

        /** @var SeriesExternal $dbExternal */
        foreach ($dbExternals as $dbExternal) {
            $dbKeywordIds = array_map(fn($k) => $k['id'], $dbExternal->getKeywords());
            if (count($dbKeywordIds) && !array_intersect($keywordIds, $dbKeywordIds)) {
                continue;
            }
            $countries = $dbExternal->getCountries();
            $searchQuery = $dbExternal->getSearchQuery();
            $searchType = $dbExternal->getSearchType();
            if ($searchType == "name") {
                $searchSeparator = $dbExternal->getSearchSeparator();
                $searchName = strtolower($searchSeparator ? str_replace(' ', $searchSeparator, $displayName) : $displayName);
                if (!count($countries) || array_intersect($seriesCountries, $countries)) {
                    $dbExternal->fullUrl = $searchQuery ? $searchName : null;
                    $externals[] = $dbExternal;
                }
            } else {
                $id = $externalIds[$searchType] ?? null;
                if ($id) {
                    $dbExternal->fullUrl = $id;
                    $externals[] = $dbExternal;
                }
            }
        }

        return $externals;
    }

    private function getEpisodeDivSize(UserSeries $userSeries): array
    {
        $episodeSizeSettings = $this->settingsRepository->findOneBy(['user' => $userSeries->getUser(), 'name' => 'episode_div_size_' . $userSeries->getId()]);
        if ($episodeSizeSettings) {
            $value = $episodeSizeSettings->getData();
            $episodeDivSize = $value['height'];
            $aspectRatio = $value['aspect-ratio'] ?? '16 / 9';
        } else {
            $episodeSizeSettings = new Settings($userSeries->getUser(), 'episode_div_size_' . $userSeries->getId(), ['height' => '18rem', 'aspect-ratio' => '16 / 9']);
            $this->settingsRepository->save($episodeSizeSettings, true);
            $episodeDivSize = '18rem';
            $aspectRatio = '16 / 9';
        }
        return [
            'height' => $episodeDivSize,
            'aspectRatio' => $aspectRatio
        ];
    }

    private function setEpisodeDatetime(User $user, ?array $episode, DateTimeInterface $time): ?array
    {
        if (!$episode) return null;
        if (!$episode['air_date']) {
            $episode['date'] = null;
            return $episode;
        }
        $date = $episode['air_date'];
        $date = $this->date($user, $date, true);

        $date = $date->setTime($time->format('H'), $time->format('i'));
        $episode['date'] = $date;
        $date = $date->format('Y-m-d H:i');
        $episode['air_date'] = str_replace(' ', 'T', $date);

        return $episode;
    }

    private function isToBeContinued(?array $tv, ?array $userLastEpisode): bool
    {
        if (($tv['next_episode_to_air'] && $tv['next_episode_to_air']['episode_type'] == 'standard')) {
            return true;
        }

        if (!$tv['next_episode_to_air'] && $userLastEpisode) {
            $episodeSeason = $this->getSeason($tv['seasons'], $userLastEpisode['season_number']);
            if ($episodeSeason && $episodeSeason['episode_count'] < $userLastEpisode['episode_number']) {
                return true;
            }
            if ($tv['status'] == 'Returning Series') {
                return true;
            }
        }

        if (in_array($tv['status'], ['Planned', 'In Production', 'Pilot', 'Returning Series'])) {
            return true;
        }
        return false;
    }

    private function getSeason(array $seasons, int $seasonNumber): array
    {
        foreach ($seasons as $season) {
            if ($season['season_number'] == $seasonNumber) {
                return $season;
            }
        }
        return [];
    }

    private function adjustNextEpisodeToWatch(UserSeries $userSeries, ?array $userEpisodes): void
    {
        if ($userSeries->getNextUserEpisode() === null) {
            if (!$userEpisodes) {
                $userEpisodes = $this->userEpisodeRepository->findBy(['userSeries' => $userSeries, 'watchAt' => null, 'previousOccurrence' => null], ['seasonNumber' => 'ASC', 'episodeNumber' => 'ASC']);
            } else {
                $userEpisodes = array_filter($userEpisodes, function ($ue) {
                    return $ue->getWatchAt() === null && $ue->getPreviousOccurrence() === null;
                });
                usort($userEpisodes, function ($a, $b) {
                    if ($a->getSeasonNumber() == $b->getSeasonNumber()) {
                        return $a->getEpisodeNumber() <=> $b->getEpisodeNumber();
                    }
                    return $a->getSeasonNumber() <=> $b->getSeasonNumber();
                });
            }
            if (count($userEpisodes) > 0) {
                $ep = $userEpisodes[0];
                $userSeries->setNextUserEpisode($ep);
                $this->userSeriesRepository->save($userSeries, true);
                $this->addFlash('info', $this->translator->trans('Next episode to watch is %episode%', ['%episode%' => sprintf('S%02dE%02d', $ep->getSeasonNumber(), $ep->getEpisodeNumber())]));
            }
        }
    }

    private function localizeSeries(array $tv): array
    {
        $localizedName = null;
        $localizedSlug = null;
        $localizedOverview = null;
        $translations = array_find($tv['translations']['translations'], function ($item) {
            return $item['iso_639_1'] == 'en';
        });
        if ($translations) {
            $localizedName = $this->seriesService->getLatinPart($tv['name']);
            if ($this->seriesService->hasNoLatinChars($tv['name'])) {
                $localizedName = $translations['data']['name'];
            }
            $localizedOverview = $translations['data']['overview'];
            $localizedSlug = new AsciiSlugger()->slug($localizedName)->lower()->toString();
        }
        return [
            'localizedName' => $localizedName,
            'localizedOverview' => $localizedOverview,
            'localizedSlug' => $localizedSlug,
        ];
    }

    private function castAndCrew(?array $tv, ?Series $series): array
    {
        if (!$tv) {
            return ['cast' => [], 'crew' => [], 'guest_stars' => []];
        }
        if ($series) {
            $seriesCastArr = array_map(function ($sc) {
                $sc['original_name'] = '';
                $sc['popularity'] = 0;
                $sc['character'] = $sc['character_name'];
                $sc['credit_id'] = '';
                $sc['order'] = -1;
                return $sc;
            }, $this->seriesCastRepository->getSeriesCatsBySeriesId($series->getId()));
            $tv['credits']['cast'] = array_merge($tv['credits']['cast'] ?? [], $seriesCastArr);
        }
        $peopleIds = array_column($tv['credits']['cast'], 'id');
        $peopleIds = array_merge($peopleIds, array_column($tv['credits']['guest_stars'] ?? [], 'id'));
        $peopleIds = array_merge($peopleIds, array_column($tv['credits']['crew'] ?? [], 'id'));
        $peopleIds = array_unique($peopleIds);
        $arr = $this->peopleUserPreferredNameRepository->getPreferredNames($peopleIds);
        $preferredNames = [];
        foreach ($arr as $name) {
            $preferredNames[$name['tmdb_id']] = $name['name'];
        }

        /******************************************************************************************
         * slug() doesn't work well with some non-Latin characters (e.g. Laos, Cambodia, Myanmar) *
         ******************************************************************************************/

        $slugger = new AsciiSlugger();
        $profileUrl = $this->imageConfiguration->getUrl('profile_sizes', 2);
        $tv['credits']['cast'] = array_map(function ($cast) use ($slugger, $profileUrl, $preferredNames) {
            $cast['profile_path'] = $cast['profile_path'] ? $profileUrl . $cast['profile_path'] : null; // w185
            $cast['preferred_name'] = null;
            if (key_exists($cast['id'], $preferredNames)) {
                $cast['preferred_name'] = $preferredNames[$cast['id']];
                $cast['slug'] = $slugger->slug($cast['preferred_name'])->lower()->toString();
            } else {
                $cast['slug'] = $slugger->slug($cast['name'])->lower()->toString();
            }
            if ($cast['slug'] == '') {
                $cast['slug'] = 'person-' . $cast['id'];
            }
            return $cast;
        }, $tv['credits']['cast']);
        $tv['credits']['guest_stars'] = array_map(function ($cast) use ($slugger, $profileUrl, $preferredNames) {
            $cast['profile_path'] = $cast['profile_path'] ? $profileUrl . $cast['profile_path'] : null; // w185
            $cast['preferred_name'] = null;
            if (key_exists($cast['id'], $preferredNames)) {
                $cast['preferred_name'] = $preferredNames[$cast['id']];
                $cast['slug'] = $slugger->slug($cast['preferred_name'])->lower()->toString();
            } else {
                $cast['slug'] = $slugger->slug($cast['name'])->lower()->toString();
            }
            return $cast;
        }, $tv['credits']['guest_stars'] ?? []);
        $crew = [];
        foreach ($tv['credits']['crew'] as $c) {
            $id = $c['id'];
            if (!key_exists($id, $crew)) {
                $crew[$id] = $c;
                $crew[$id]['jobs'] = [];
            }
            $crew[$id]['jobs'][] = $this->translator->trans($c['job']) . ' - ' . $this->translator->trans($c['department']);
        }
        $crew = array_values($crew);
        $tv['credits']['crew'] = array_map(function ($c) use ($slugger, $profileUrl, $preferredNames) {
            $c['profile_path'] = $c['profile_path'] ? $profileUrl . $c['profile_path'] : null; // w185
            $c['preferred_name'] = null;
            if (key_exists($c['id'], $preferredNames)) {
                $c['preferred_name'] = $preferredNames[$c['id']];
                $c['slug'] = $slugger->slug($c['preferred_name'])->lower()->toString();
            } else {
                $c['slug'] = $slugger->slug($c['name'])->lower()->toString();
            }
            if ($c['slug'] == '') {
                $c['slug'] = 'person-' . $c['id'];
            }
            return $c;
        }, $crew);

        usort($tv['credits']['cast'], function ($a, $b) {
            return !$a['profile_path'] <=> !$b['profile_path'];
        });
        usort($tv['credits']['guest_stars'], function ($a, $b) {
            return !$a['profile_path'] <=> !$b['profile_path'];
        });
        usort($tv['credits']['crew'], function ($a, $b) {
            return !$a['profile_path'] <=> !$b['profile_path'];
        });
        return $tv['credits'];
    }

    private function watchProviders(array $tv, string $country): array
    {
        $watchProviders = [];
        if (isset($tv['watch/providers']['results'][$country])) {
            $watchProviders = $tv['watch/providers']['results'][$country];
        }
        $flatrate = $watchProviders['flatrate'] ?? [];
        $rent = $watchProviders['rent'] ?? [];
        $buy = $watchProviders['buy'] ?? [];

        $logoUrl = $this->imageConfiguration->getUrl('logo_sizes', 2);

        $flatrate = array_map(function ($wp) use ($logoUrl) {
            return [
                'provider_id' => $wp['provider_id'],
                'provider_name' => $wp['provider_name'],
                'logo_path' => $this->providerService->getProviderLogoFullPath($wp['logo_path'], $logoUrl),
            ];
        }, $flatrate);
        $rent = array_map(function ($wp) use ($logoUrl) {
            return [
                'provider_id' => $wp['provider_id'],
                'provider_name' => $wp['provider_name'],
                'logo_path' => $this->providerService->getProviderLogoFullPath($wp['logo_path'], $logoUrl),
            ];
        }, $rent);
        $buy = array_map(function ($wp) use ($logoUrl) {
            return [
                'provider_id' => $wp['provider_id'],
                'provider_name' => $wp['provider_name'],
                'logo_path' => $this->providerService->getProviderLogoFullPath($wp['logo_path'], $logoUrl),
            ];
        }, $buy);
        return [
            'flatrate' => $flatrate,
            'rent' => $rent,
            'buy' => $buy,
        ];
    }

    private function getUserSeasons(Series $series, array $userEpisodes): array
    {
        $seasonArr = [];
        $posterPath = '/series/posters' . $series->getPosterPath();
        foreach ($userEpisodes as $ue) {
            $seasonNumber = $ue->getSeasonNumber();
            $episodeNumber = $ue->getEpisodeNumber();
            $seasonArr[$seasonNumber][$episodeNumber]['air_date'] = $ue->getAirDate();
        }
        $seasons = [];
        foreach ($seasonArr as $seasonNumber => $seasonItem) {
            $season['air_date'] = $seasonItem[1]['air_date']->format('Y-m-d');
            $season['episode_count'] = count($seasonItem);
            $season['name'] = $this->translator->trans('Season') . ' ' . $seasonNumber;
            $season['overview'] = null;
            $season['poster_path'] = $posterPath;
            $season['season_number'] = $seasonNumber;
            $seasons[] = $season;
        }
        return $seasons;
    }

    private function checkNumberOfEpisodes(array $tv): int
    {
        $seasonEpisodeCount = 0;
        foreach ($tv['seasons'] as $season) {
            if ($season['season_number'] > 0) {
                // Si la série n'a plus d'épisode à venir, on compte les épisodes
                // (nombre d'épisodes égal à un, signifie que la saison est à venir, juste annoncée)
                // de la saison qui ont une date de diffusion.
                // Sinon, on se fie au nombre d'épisodes de la saison fourni par l'API
                if (!$tv['next_episode_to_air']) {
                    $s = json_decode($this->tmdbService->getTvSeason($tv['id'], $season['season_number'], 'fr-FR'), true);
                    $episodeCount = 0;
                    foreach ($s['episodes'] as $episode) {
                        if ($episode['air_date']) $episodeCount++;
                        if ($episode['episode_type'] == 'finale') {
                            $count = count($s['episodes']);
                            if ($count > $episode['episode_number']) {
                                $this->addFlash('warning', 'Finale episode number: ' . sprintf("S%02dE%02d", $s['season_number'], $episode['episode_number']) . ' - episode count: ' . $count);
                            }
                            break;
                        }
                    }
                    $seasonEpisodeCount += $episodeCount;
                } else {
                    $seasonEpisodeCount += $season['episode_count'];
                }
            }
        }
        return $seasonEpisodeCount;
    }

    private function statusCss(array $tv): string
    {
        $status = $tv['status'];
        $statusCss = 'status-';
        if ($status == 'Returning Series') {
            $statusCss .= 'returning';
        } elseif ($status == 'Ended') {
            $statusCss .= 'ended';
        } elseif ($status == 'Canceled') {
            $statusCss .= 'canceled';
        } elseif ($status == 'In Production') {
            $statusCss .= 'in-production';
        } elseif ($status == 'Planned') {
            $statusCss .= 'planned';
        } elseif ($status == 'Pilot') {
            $statusCss .= 'pilot';
        } elseif ($status == 'Rumored') {
            $statusCss .= 'rumored';
        } else {
            $statusCss .= 'unknown';
        }
        return $statusCss;
    }

    private function trimSeasons(int $tvId, string $locale, array $seasons): array
    {
        return array_map(function ($season) use ($tvId, $locale) {
            $tmdbSeason = json_decode($this->tmdbService->getTvSeason($tvId, $season['season_number'], $locale), true);
            $finaleEpisode = array_find($tmdbSeason['episodes'] ?? [], function ($e) {
                return ($e['episode_type'] ?? 'standard') === 'finale';
            });
            $finalEpisodeNumber = $finaleEpisode ? $finaleEpisode['episode_number'] : null;
            // Filtrer les épisodes pour ne garder que ceux jusqu'à l'épisode final
            if ($finalEpisodeNumber !== null) {
                $season['episode_count'] = $finalEpisodeNumber;
            }
            return $season;
        }, $seasons);
    }

    private function date(User $user, string $dateString, bool $allDays = false): DateTimeImmutable
    {
        $timezone = $user->getTimezone() ?? 'Europe/Paris';
        return $this->dateService->newDateImmutable($dateString, $timezone, $allDays);
    }

    public function now(User $user): DateTimeImmutable
    {
        $timezone = $user->getTimezone() ?? 'Europe/Paris';
        return $this->dateService->newDateImmutable('now', $timezone);
    }
}
