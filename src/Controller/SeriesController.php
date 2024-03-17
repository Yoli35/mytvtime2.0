<?php

namespace App\Controller;

use App\DTO\SeriesAdvancedSearchDTO;
use App\DTO\SeriesSearchDTO;
use App\Entity\Series;
use App\Entity\SeriesImage;
use App\Entity\SeriesLocalizedName;
use App\Entity\SeriesWatchLink;
use App\Entity\User;
use App\Entity\UserEpisode;
use App\Entity\UserSeries;
use App\Form\SeriesAdvancedSearchType;
use App\Form\SeriesSearchType;
use App\Repository\DeviceRepository;
use App\Repository\KeywordRepository;
use App\Repository\SeriesImageRepository;
use App\Repository\SeriesLocalizedNameRepository;
use App\Repository\SeriesRepository;
use App\Repository\SeriesWatchLinkRepository;
use App\Repository\UserEpisodeRepository;
use App\Repository\UserSeriesRepository;
use App\Service\DateService;
use App\Service\DeeplTranslator;
use App\Service\ImageConfiguration;
use App\Service\TMDBService;
use DateTimeImmutable;
use DateTimeZone;
use DeepL\DeepLException;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\DatePoint;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/{_locale}/series', name: 'app_series_', requirements: ['_locale' => 'fr|en|de|es'])]
class SeriesController extends AbstractController
{
    public function __construct(
        private readonly ClockInterface                $clock,
        private readonly DateService                   $dateService,
        private readonly DeviceRepository              $deviceRepository,
        private readonly DeeplTranslator               $deeplTranslator,
        private readonly ImageConfiguration            $imageConfiguration,
        private readonly KeywordRepository             $keywordRepository,
        private readonly SeriesImageRepository         $seriesImageRepository,
        private readonly SeriesRepository              $seriesRepository,
        private readonly SeriesLocalizedNameRepository $seriesLocalizedNameRepository,
        private readonly SeriesWatchLinkRepository     $seriesWatchLinkRepository,
        private readonly TMDBService                   $tmdbService,
        private readonly TranslatorInterface           $translator,
        private readonly UserEpisodeRepository         $userEpisodeRepository,
        private readonly UserSeriesRepository          $userSeriesRepository,
    )
    {
    }

    #[Route('/', name: 'index')]
    public function index(): Response
    {
        return $this->redirectToRoute('app_home');
//        return $this->render('series/index.html.twig', [
//            'controller_name' => 'SeriesController',
//        ]);
    }

    #[Route('/search', name: 'search')]
    public function search(Request $request): Response
    {
        $series = [];
        $slugger = new AsciiSlugger();
        $simpleSeriesSearch = new SeriesSearchDTO($request->getLocale(), 1);
        $simpleForm = $this->createForm(SeriesSearchType::class, $simpleSeriesSearch);

        $simpleForm->handleRequest($request);
        if ($simpleForm->isSubmitted() && $simpleForm->isValid()) {
            $query = $simpleSeriesSearch->getQuery();
            $language = $simpleSeriesSearch->getLanguage();
            $page = $simpleSeriesSearch->getPage();
            $firstAirDateYear = $simpleSeriesSearch->getFirstAirDateYear();

            $searchString = "&query=$query&include_adult=false&page=$page";
            if (strlen($firstAirDateYear)) $searchString .= "&first_air_date_year=$firstAirDateYear";
            if (strlen($language)) $searchString .= "&language=$language";

            $searchResult = json_decode($this->tmdbService->searchTv($searchString), true);
            if ($searchResult['total_results'] == 1) {
                return $this->getOneResult($searchResult['results'][0], $slugger);
            }
            $series = $this->getSearchResult($searchResult, $slugger);
        }

        return $this->render('series/search.html.twig', [
            'form' => $simpleForm->createView(),
            'seriesList' => $series,
            'results' => [
                'total_results' => $searchResult['total_results'] ?? -1,
                'total_pages' => $searchResult['total_pages'] ?? 0,
                'page' => $searchResult['page'] ?? 0,
            ],
        ]);
    }

    #[Route('/advanced/search', name: 'advanced_search')]
    public function advancedSearch(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $series = [];
        $slugger = new AsciiSlugger();
        $watchProviders = $this->getWatchProviders($user?->getPreferredLanguage() ?? $request->getLocale(), $user?->getCountry() ?? 'FR');
        $keywords = $this->getKeywords();

        $seriesSearch = new SeriesAdvancedSearchDTO($user?->getPreferredLanguage() ?? $request->getLocale(), $user?->getCountry() ?? 'FR', $user?->getTimezone() ?? 'Europe/Paris', 1);
        $seriesSearch->setWatchProviders($watchProviders['select']);
        $seriesSearch->setKeywords($keywords);
        $form = $this->createForm(SeriesAdvancedSearchType::class, $seriesSearch);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $searchString = $this->getSearchString($form->getData());
            $searchResult = json_decode($this->tmdbService->getFilterTv($searchString), true);

            if ($searchResult['total_results'] == 1) {
                return $this->getOneResult($searchResult['results'][0], $slugger);
            }
            $series = $this->getSearchResult($searchResult, $slugger);
        }
//        dump($series);

        return $this->render('series/search-advanced.html.twig', [
            'form' => $form->createView(),
            'seriesList' => $series,
            'results' => [
                'total_results' => $searchResult['total_results'] ?? -1,
                'total_pages' => $searchResult['total_pages'] ?? 0,
                'page' => $searchResult['page'] ?? 0,
            ],
        ]);
    }

    #[Route('/tmdb/{id}-{slug}', name: 'tmdb', requirements: ['id' => Requirement::DIGITS])]
    public function tmdb(Request $request, $id, $slug): Response
    {
        $user = $this->getUser();
        $series = $this->seriesRepository->findOneBy(['tmdbId' => $id]);
        $userSeries = $user ? $this->userSeriesRepository->findOneBy(['user' => $user, 'series' => $series]) : null;

        if ($userSeries) {
            return $this->redirectToRoute('app_series_show', [
                'id' => $series->getId(),
                'slug' => $series->getSlug(),
            ], 301);
        }

        if ($series) {
            $series->setVisitNumber($series->getVisitNumber() + 1);
            $this->seriesRepository->save($series, true);
            $localizedName = $series->getLocalizedName($request->getLocale());
        } else {
            $localizedName = null;
        }
        $tv = json_decode($this->tmdbService->getTv($id, $request->getLocale(), ["images", "videos", "credits", "watch/providers", "content/ratings", "keywords"]), true);

//        dump($localizedName);
        $this->checkTmdbSlug($tv, $slug, $localizedName?->getSlug());

        $this->saveImage("posters", $tv['poster_path'], $this->imageConfiguration->getUrl('poster_sizes', 5));
        $this->saveImage("backdrops", $tv['backdrop_path'], $this->imageConfiguration->getUrl('backdrop_sizes', 3));

        $tv['credits'] = $this->castAndCrew($tv);
        $tv['networks'] = $this->networks($tv);
        $tv['seasons'] = $this->seasonsPosterPath($tv['seasons']);
        $tv['watch/providers'] = $this->watchProviders($tv, 'FR');

//        dump($tv);
        return $this->render('series/tmdb.html.twig', [
            'tv' => $tv,
            'localizedName' => $localizedName,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/show/{id}-{slug}', name: 'show', requirements: ['id' => Requirement::DIGITS])]
    public function show(Request $request, $id, $slug): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $series = $this->seriesRepository->findOneBy(['id' => $id]);
        $series->setVisitNumber($series->getVisitNumber() + 1);
        $this->seriesRepository->save($series, true);

        $this->checkSlug($series, $slug, $user->getPreferredLanguage() ?? $request->getLocale());
        $tv = json_decode($this->tmdbService->getTv($series->getTmdbId(), $request->getLocale(), ["images", "videos", "credits", "watch/providers", "content/ratings", "keywords"]), true);

        $series = $this->updateSeries($series, $tv);

        $tv['credits'] = $this->castAndCrew($tv);
        $tv['localized_name'] = $series->getLocalizedName($request->getLocale());
        $tv['networks'] = $this->networks($tv);
        $tv['overview'] = $this->localizedOverview($tv, $series, $request);
        $tv['seasons'] = $this->seasonsPosterPath($tv['seasons']);
        $tv['watch/providers'] = $this->watchProviders($tv, $user->getCountry() ?? 'FR');

        $userSeries = $this->userSeriesRepository->findOneBy(['user' => $user, 'series' => $series]);
        $providers = $this->getWatchProviders($user->getPreferredLanguage() ?? $request->getLocale(), $user->getCountry() ?? 'FR');

//        dump([
//            'series' => $series,
//            'tv' => $tv,
//            'userSeries' => $userSeries,
//            'providers' => $providers,
//        ]);
        return $this->render('series/show.html.twig', [
            'series' => $series,
            'tv' => $tv,
            'userSeries' => $userSeries,
            'providers' => $providers,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/add/{id}', name: 'add', requirements: ['id' => Requirement::DIGITS])]
    public function addUserSeries(int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $date = $this->now();
        $series = $this->addSeries($id, $date);
        $userSeries = $this->userSeriesRepository->findOneBy(['user' => $user, 'series' => $series]);

        if ($userSeries) {
            $this->addFlash('warning', $this->translator->trans('Series already added to your watchlist'));
        } else {
            $userSeries = new UserSeries($user, $series, $date);
            $this->userSeriesRepository->save($userSeries, true);
            $this->addFlash('success', $this->translator->trans('Series added to your watchlist'));
        }
        return $this->redirectToRoute('app_series_show', [
            'id' => $series->getId(),
            'slug' => $series->getSlug(),
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/remove/{id}', name: 'remove', requirements: ['id' => Requirement::DIGITS])]
    public function removeUserSeries(Series $series): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $userSeries = $this->userSeriesRepository->findOneBy(['user' => $user, 'series' => $series]);

        $this->userSeriesRepository->remove($userSeries);

        return $this->redirectToRoute('app_series_show', [
            'id' => $series->getId(),
            'slug' => $series->getSlug(),
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/favorite/{id}', name: 'favorite', requirements: ['id' => Requirement::DIGITS])]
    public function favoriteSeries(Request $request, UserSeries $userSeries): Response
    {
        $data = json_decode($request->getContent(), true);
        $newFavoriteValue = $data['favorite'];
        $userSeries->setFavorite($newFavoriteValue);
        $this->userSeriesRepository->save($userSeries, true);

        return $this->json([
            'ok' => true,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/rating/{id}', name: 'rating', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function ratingSeries(Request $request, UserSeries $userSeries): Response
    {
        $data = json_decode($request->getContent(), true);
        $rating = $data['rating'];
        $userSeries->setRating($rating);
        $this->userSeriesRepository->save($userSeries, true);

        return $this->json([
            'ok' => true,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/show/season/{id}-{slug}/{seasonNumber}', name: 'season', requirements: ['id' => Requirement::DIGITS, 'seasonNumber' => Requirement::DIGITS])]
    public function showSeason(Request $request, $id, $seasonNumber, $slug): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $series = $this->seriesRepository->findOneBy(['id' => $id]);
        $userSeries = $this->userSeriesRepository->findOneBy(['user' => $user, 'series' => $series]);
        $this->checkSlug($series, $slug, $user->getPreferredLanguage() ?? $request->getLocale());
        $slugger = new AsciiSlugger();

        $season = json_decode($this->tmdbService->getTvSeason($series->getTmdbId(), $seasonNumber, $request->getLocale(), ['credits', 'watch/providers']), true);
        if ($season['poster_path']) {
            $this->saveImage("posters", $season['poster_path'], $this->imageConfiguration->getUrl('poster_sizes', 5));
        } else {
            $season['poster_path'] = $series->getPosterPath();
        }
        $season['deepl'] = $this->seasonLocalizedOverview($series, $season, $seasonNumber, $request);
        $season['episodes'] = array_map(function ($episode) use ($userSeries, $series, $season, $slugger) {
            $episode['still_path'] = $episode['still_path'] ? $this->imageConfiguration->getCompleteUrl($episode['still_path'], 'still_sizes', 3) : null; // w300
            $episode['crew'] = array_map(function ($crew) use ($slugger) {
                $crew['profile_path'] = $crew['profile_path'] ? $this->imageConfiguration->getCompleteUrl($crew['profile_path'], 'profile_sizes', 2) : null; // w185
                $crew['slug'] = $slugger->slug($crew['name'])->lower()->toString();
                return $crew;
            }, $episode['crew'] ?? []);
            $episode['guest_stars'] = array_map(function ($guest) use ($slugger) {
                $guest['profile_path'] = $guest['profile_path'] ? $this->imageConfiguration->getCompleteUrl($guest['profile_path'], 'profile_sizes', 2) : null; // w185
                $guest['slug'] = $slugger->slug($guest['name'])->lower()->toString();
                return $guest;
            }, $episode['guest_stars'] ?? []);
            $episode['user_episode'] = $userSeries->getEpisode($episode['id']);
            return $episode;
        }, $season['episodes']);
        $season['credits'] = $this->castAndCrew($season);
        $season['watch/providers'] = $this->watchProviders($season, $user->getCountry() ?? 'FR');
        $season['localized_name'] = $series->getLocalizedName($request->getLocale());

        $providers = $this->getWatchProviders($user->getPreferredLanguage() ?? $request->getLocale(), $user->getCountry() ?? 'FR');
        $devices = $this->deviceRepository->deviceArray();
        dump([
//            'series' => $series,
            'season' => $season,
//            'userSeries' => $userSeries,
//            'providers' => $providers,
//            'devices' => $devices,
        ]);
        return $this->render('series/season.html.twig', [
            'series' => $series,
            'season' => $season,
            'providers' => $providers,
            'devices' => $devices,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/add/watch/link/{id}', name: 'add_watch_link', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function addWatchLink(Request $request, int $id): Response
    {
        $data = json_decode($request->getContent(), true);
        $url = $data['url'];
        $name = $data['name'];
        $providerId = $data['provider'];
        $series = $this->seriesRepository->findOneBy(['id' => $id]);

        $watchLink = new SeriesWatchLink($url, $name, $series, $providerId);
        $this->seriesWatchLinkRepository->save($watchLink, true);

        return $this->json([
            'ok' => true,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/add/localized/name/{id}', name: 'add_localized_name', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function localizedName(Request $request, int $id): Response
    {
        $data = json_decode($request->getContent(), true);
        $name = $data['name'];
        $series = $this->seriesRepository->findOneBy(['id' => $id]);
        $slugger = new AsciiSlugger();

        $localizedName = $series->getLocalizedName($request->getLocale());
        if ($localizedName) {
            $localizedName->setName($name);
            $localizedName->setSlug($slugger->slug($name));
        } else {
            $slug = $slugger->slug($name)->lower()->toString();
            $localizedName = new SeriesLocalizedName($series, $name, $slug, $request->getLocale());
        }
        $this->seriesLocalizedNameRepository->save($localizedName, true);

        return $this->json([
            'ok' => true,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/add/episode/{id}', name: 'add_episode', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function addUserEpisode(Request $request, int $id): Response
    {
        $data = json_decode($request->getContent(), true);
        $showId = $data['showId'];
        $seasonNumber = $data['seasonNumber'];
        $episodeNumber = $data['episodeNumber'];
        /** @var User $user */
        $user = $this->getUser();
        $locale = $user->getPreferredLanguage() ?? $request->getLocale();
        $series = $this->seriesRepository->findOneBy(['tmdbId' => $showId]);
        $userSeries = $this->userSeriesRepository->findOneBy(['user' => $user, 'series' => $series]);
        $userEpisode = $this->userEpisodeRepository->findOneBy(['user' => $user, 'episodeId' => $id]);
        if ($userEpisode) {
            $this->addFlash('warning', $this->translator->trans('Episode already added to your watchlist'));
            return $this->json([
                'ok' => true,
            ]);
        }

        $tv = json_decode($this->tmdbService->getTv($showId, $locale), true);
        $episode = json_decode($this->tmdbService->getTvEpisode($showId, $seasonNumber, $episodeNumber, $locale), true);
        $airDate = $this->dateService->newDate($episode['air_date'], $user->getTimezone() ?? "Europe/Paris");
        $now = $this->now();
        $diff = $now->diff($airDate);
        $userEpisode = new UserEpisode($user, $userSeries, $id, $episode['season_number'], $episode['episode_number'], $now);
        $userEpisode->setQuickWatchDay($diff->days < 1);
        $userEpisode->setQuickWatchWeek($diff->days < 7);
        $this->userEpisodeRepository->save($userEpisode, true);

//        $userSeries->addUserEpisode($userEpisode);
        $userSeries->setLastWatchAt($now);
        $userSeries->setLastEpisode($episode['episode_number']);
        $userSeries->setLastSeason($episode['season_number']);
        $userSeries->setViewedEpisodes($userSeries->getViewedEpisodes() + 1);
        $userSeries->setProgress($userSeries->getViewedEpisodes() / $tv['number_of_episodes'] * 100);
        $this->userSeriesRepository->save($userSeries, true);
        return $this->json([
            'ok' => true,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/episode/provider/{episodeId}', name: 'episode_provider', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function userEpisodeProvider(Request $request, int $episodeId): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $userEpisode = $this->userEpisodeRepository->findOneBy(['user' => $user, 'episodeId' => $episodeId]);
        $data = json_decode($request->getContent(), true);
        $providerId = $data['providerId'];

        $userEpisode->setProviderId($providerId);
        $this->userEpisodeRepository->save($userEpisode, true);

        return $this->json([
            'ok' => true,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/episode/device/{episodeId}', name: 'episode_device', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function userEpisodeDevice(Request $request, int $episodeId): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $userEpisode = $this->userEpisodeRepository->findOneBy(['user' => $user, 'episodeId' => $episodeId]);
        $data = json_decode($request->getContent(), true);
        $deviceId = $data['deviceId'];

        $userEpisode->setDeviceId($deviceId);
        $this->userEpisodeRepository->save($userEpisode, true);

        return $this->json([
            'ok' => true,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/episode/vote/{episodeId}', name: 'episode_vote', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function userEpisodeVote(Request $request, int $episodeId): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $userEpisode = $this->userEpisodeRepository->findOneBy(['user' => $user, 'episodeId' => $episodeId]);
        $data = json_decode($request->getContent(), true);
        $vote = $data['vote'];

        $userEpisode->setVote($vote);
        $this->userEpisodeRepository->save($userEpisode, true);

        return $this->json([
            'ok' => true,
        ]);
    }

    #[Route('/people/{id}-{slug}', name: 'people', requirements: ['id' => Requirement::DIGITS])]
    public function people(Request $request, $id): Response
    {
        $standing = $this->tmdbService->getPerson($id, $request->getLocale(), "images,combined_credits");
        $people = json_decode($standing, true);
        $credits = $people['combined_credits'];

        if (key_exists('birthday', $people)) {
            $date = $this->dateService->newDate($people['birthday'], "Europe/Paris");
            if (key_exists('deathday', $people)) {
                $now = $this->dateService->newDate($people['deathday'], "Europe/Paris");
            } else {
                $people['deathday'] = null;
                $now = $this->dateService->newDate('now', "Europe/Paris");
            }
            $interval = $now->diff($date);
            $age = $interval->y;
            $people['age'] = $age;
        } else {
            $people['birthday'] = null;
        }
        if (!key_exists('deathday', $people)) {
            $people['deathday'] = null;
        }

        if (!key_exists('cast', $credits)) {
            $credits['cast'] = [];
        }
        if (!key_exists('crew', $credits)) {
            $credits['crew'] = [];
        }
        $count = count($credits['cast']) + count($credits['crew']);
        $castNoDates = [];
        $castDates = [];
        $noDate = 0;
        $roles = $this->makeRoles();

        $locale = $request->getLocale();
        $slugger = new AsciiSlugger();

        foreach ($credits['cast'] as $cast) {
            $role['id'] = $cast['id'];
            if ($locale == 'fr') {
                $role['character'] = key_exists('character', $cast) ? ($cast['character'] ? preg_replace($roles['en'], $roles['fr'], $cast['character'] . $people['gender']) : null) : null;
            } else {
                $role['character'] = key_exists('character', $cast) ? ($cast['character'] ?: null) : null;
            }
            $role['media_type'] = key_exists('media_type', $cast) ? $cast['media_type'] : null;
            $role['original_title'] = key_exists('original_title', $cast) ? $cast['original_title'] : (key_exists('original_name', $cast) ? $cast['original_name'] : null);
            $role['poster_path'] = key_exists('poster_path', $cast) ? $cast['poster_path'] : null;
            $role['release_date'] = key_exists('release_date', $cast) ? $cast['release_date'] : (key_exists('first_air_date', $cast) ? $cast['first_air_date'] : null);
            $role['title'] = key_exists('title', $cast) ? $cast['title'] : (key_exists('name', $cast) ? $cast['name'] : null);
            $role['slug'] = $role['title'] ? $slugger->slug($role['title'])->lower()->toString() : null;

            if ($role['release_date']) {
                $castDates[$role['release_date']] = $role;
            } else {
                $castNoDates[$noDate++] = $role;
            }
        }
        krsort($castDates);
        $credits['cast'] = array_merge($castNoDates, $castDates);
        $knownFor = $this->getKnownFor($castDates);

        $crewDates = [];
        $noDate = 0;
        foreach ($credits['crew'] as $crew) {
            $role['id'] = $crew['id'];
            $role['department'] = key_exists('department', $crew) ? $crew['department'] : null;
            $role['job'] = key_exists('job', $crew) ? $crew['job'] : null;
            $role['media_type'] = key_exists('media_type', $crew) ? $crew['media_type'] : null;
            $role['release_date'] = key_exists('release_date', $crew) ? $crew['release_date'] : (key_exists('first_air_date', $crew) ? $crew['first_air_date'] : null);
            $role['poster_path'] = key_exists('poster_path', $crew) ? $crew['poster_path'] : null;
            $role['title'] = key_exists('title', $crew) ? $crew['title'] : (key_exists('name', $crew) ? $crew['name'] : null);
            $role['original_title'] = key_exists('original_title', $crew) ? $crew['original_title'] : null;
            $role['slug'] = $role['title'] ? $slugger->slug($role['title'])->lower()->toString() : null;

            if ($role['release_date']) {
                $crewDates[$role['department']][$role['release_date']] = $role;
            } else {
                $crewDates[$role['department']][$noDate++] = $role;
            }
        }
        $sortedCrew = [];
        foreach ($crewDates as $department => $crewDate) {
            $noDates = [];
            $dates = [];
            foreach ($crewDate as $date) {
                if (!$date['release_date']) {
                    $noDates[] = $date;
                    unset($date);
                } else {
                    $dates[$date['release_date']] = $date;
                }
            }
            krsort($dates);
            $sortedCrew[$department] = array_merge($noDates, $dates);
            $knownFor = array_merge($knownFor, $this->getKnownFor($dates));
        }
        $credits['crew'] = $sortedCrew;
        krsort($knownFor);
        $credits['known_for'] = $knownFor;

        return $this->render('series/people.html.twig', [
            'people' => $people,
            'credits' => $credits,
            'count' => $count,
            'user' => $this->getUser(),
            'imageConfig' => $this->imageConfiguration->getConfig(),
        ]);
    }

    #[Route('/overview/{id}', name: 'get_overview', methods: 'GET')]
    public function getOverview(Request $request, $id): Response
    {
        $type = $request->query->get("type");
        $content = null;

        $standing = match ($type) {
            "tv" => $this->tmdbService->getTv($id, $request->getLocale()),
            "movie" => $this->tmdbService->getMovie($id, $request->getLocale()),
            default => null,
        };

        if ($standing) {
            $content = json_decode($standing, true);
        }
        return $this->json([
            'overview' => $content ? $content['overview'] : "",
            'media_type' => $this->translator->trans($type),
        ]);
    }

    public function updateSeries(Series $series, array $tv): Series
    {
        $slugger = new AsciiSlugger();
        $series->setUpdates([]);
        if ($tv['name'] != $series->getName()) {
            $series->setName($tv['name']);
            $series->setSlug($slugger->slug($tv['name']));
            $series->addUpdate('Name updated');
        }
        $slug = $slugger->slug($tv['name'])->lower()->toString();
        if ($series->getSlug() != $slug) {
            $series->setSlug($slugger->slug($slug));
            $series->addUpdate('Slug updated');
        }

        if ($tv['original_name'] != $series->getOriginalName()) {
            $series->setOriginalName($tv['original_name']);
            $series->addUpdate('Original name updated');
        }
        if ($tv['first_air_date'] && !$series->getFirstDateAir()) {
            $series->setFirstDateAir(new DatePoint($tv['first_air_date']));
            $series->addUpdate('First date air updated');
        }

        if (strcmp($tv['overview'], $series->getOverview())) {
            $series->setOverview($tv['overview']);
            $series->addUpdate('Overview updated');
        }

        $seriesImages = $series->getSeriesImages()->toArray();
        $seriesPosters = array_filter($seriesImages, fn($image) => $image->getType() == "poster");
        $seriesBackdrops = array_filter($seriesImages, fn($image) => $image->getType() == "backdrop");

        if (!$this->inImages($tv['poster_path'], $seriesPosters)) {
            $seriesImage = new SeriesImage($series, "poster", $tv['poster_path']);
            $this->seriesImageRepository->save($seriesImage, true);
            $series->addUpdate('Poster added');
        }
        if (!$this->inImages($tv['backdrop_path'], $seriesBackdrops)) {
            $seriesImage = new SeriesImage($series, "backdrop", $tv['backdrop_path']);
            $this->seriesImageRepository->save($seriesImage, true);
            $series->addUpdate('Backdrop added');
//            dump($series->getUpdates());
        }
        if ($tv['poster_path'] != $series->getPosterPath()) {
            $series->setPosterPath($tv['poster_path']);
            $this->saveImage("posters", $series->getPosterPath(), $this->imageConfiguration->getUrl('poster_sizes', 5));
            $series->addUpdate('Poster updated');
        }
        if ($tv['backdrop_path'] != $series->getBackdropPath()) {
            $series->setBackdropPath($tv['backdrop_path']);
            $this->saveImage("backdrops", $series->getBackdropPath(), $this->imageConfiguration->getUrl('backdrop_sizes', 3));
            $series->addUpdate('Backdrop updated');
        }
        $this->seriesRepository->save($series, true);
        return $series;
    }

    public function inImages(string $image, array $images): bool
    {
        foreach ($images as $img) {
            if ($img->getimagePath() == $image) return true;
        }
        return false;
    }

    public function addSeries($id, $date): Series
    {
        $series = $this->seriesRepository->findOneBy(['tmdbId' => $id]);
        if ($series) return $series;

        $slugger = new AsciiSlugger();
        $tv = json_decode($this->tmdbService->getTv($id, 'en-US'), true);
        $series = new Series();
        $series->setTmdbId($id);
        $series->setName($tv['name']);
        $series->setSlug($slugger->slug($tv['name']));
        $series->setOriginalName($tv['original_name']);
        $series->setOverview($tv['overview']);
        $series->setPosterPath($tv['poster_path']);
        $series->setBackdropPath($tv['backdrop_path']);
        $series->setFirstDateAir(new DatePoint($tv['first_air_date']));
        $series->setVisitNumber(0);
        $series->setCreatedAt($date);
        $series->setUpdatedAt($date);
        $this->seriesRepository->save($series, true);

        return $this->seriesRepository->findOneBy(['tmdbId' => $id]);
    }

    public function now(): DateTimeImmutable
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($user->getTimezone()) {
            $timezone = $user->getTimezone();
        } else {
            $timezone = 'Europe/Paris';
        }
        $now = $this->clock->now();
        try {
            $now = $now->setTimezone(new DateTimeZone($timezone));
        } catch (Exception) {
        }
        return $now;
    }

    public function checkSlug($series, $slug, $locale = 'fr'): bool|Response
    {
        $localizedName = $series->getLocalizedName($locale);
        $seriesSlug = $localizedName ? $localizedName->getSlug() : $series->getSlug();
        if ($seriesSlug !== $slug) {
            return $this->redirectToRoute('app_series_show', [
                'id' => $series->getId(),
                'slug' => $series->getSlug(),
            ], 301);
        }
        return true;
    }

    public function checkTmdbSlug($series, $slug, $localizedSlug = null): bool|Response
    {
        if ($localizedSlug) {
            $realSlug = $localizedSlug;
        } else {
            $slugger = new AsciiSlugger();
            $realSlug = $slugger->slug($series['name'])->lower()->toString();
        }
        if ($realSlug != "" && $realSlug !== $slug) {
            return $this->redirectToRoute('app_series_tmdb', [
                'id' => $series['id'],
                'slug' => $realSlug,
            ], 301);
        }
        return true;
    }

    public function localizedOverview($tv, $series, $request): string
    {
        if ($tv['overview']) return $tv['overview'];

        if (!strlen($series->getOverview())) {
            $usSeries = json_decode($this->tmdbService->getTv($series->getTmdbId(), 'en-US'), true);
            $overview = $usSeries['overview'];
            if (strlen($overview)) {
                try {
                    $usage = $this->deeplTranslator->translator->getUsage();
                    if ($usage->character->count + strlen($overview) < $usage->character->limit) {
                        $overview = $this->deeplTranslator->translator->translateText($usSeries['overview'], null, $request->getLocale());
                        $series->setOverview($overview);
                        $this->seriesRepository->save($series, true);
                    }
                } catch (DeepLException) {
                }
            }
        }
        return $series->getOverview();
    }

    public function castAndCrew($tv): array
    {
        $slugger = new AsciiSlugger();
        $tv['credits']['cast'] = array_map(function ($cast) use ($slugger) {
            $cast['slug'] = $slugger->slug($cast['name'])->lower()->toString();
            $cast['profile_path'] = $cast['profile_path'] ? $this->imageConfiguration->getCompleteUrl($cast['profile_path'], 'profile_sizes', 2) : null; // w185
            return $cast;
        }, $tv['credits']['cast'] ?? []);
        $tv['credits']['guest_stars'] = array_map(function ($cast) use ($slugger) {
            $cast['slug'] = $slugger->slug($cast['name'])->lower()->toString();
            $cast['profile_path'] = $cast['profile_path'] ? $this->imageConfiguration->getCompleteUrl($cast['profile_path'], 'profile_sizes', 2) : null; // w185
            return $cast;
        }, $tv['credits']['guest_stars'] ?? []);
        $tv['credits']['crew'] = array_map(function ($crew) use ($slugger) {
            $crew['slug'] = $slugger->slug($crew['name'])->lower()->toString();
            $crew['profile_path'] = $crew['profile_path'] ? $this->imageConfiguration->getCompleteUrl($crew['profile_path'], 'profile_sizes', 2) : null; // w185
            return $crew;
        }, $tv['credits']['crew'] ?? []);

        usort($tv['credits']['crew'], function ($a, $b) {
            return !$a['profile_path'] <=> !$b['profile_path'];
        });
        return $tv['credits'];
    }

    public function networks($tv): array
    {
        return array_map(function ($network) {
            $network['logo_path'] = $network['logo_path'] ? $this->imageConfiguration->getCompleteUrl($network['logo_path'], 'logo_sizes', 2) : null; // w92
            return $network;
        }, $tv['networks']);
    }

    public function seasonsPosterPath($seasons): array
    {
        $slugger = new AsciiSlugger();
        return array_map(function ($season) use ($slugger) {
            $season['slug'] = $slugger->slug($season['name'])->lower()->toString();
            $season['poster_path'] = $season['poster_path'] ? $this->imageConfiguration->getCompleteUrl($season['poster_path'], 'poster_sizes', 5) : null; // w500
            return $season;
        }, $seasons);
    }

    public function seasonLocalizedOverview($series, $season, $seasonNumber, $request): array|null
    {
        if (!strlen($season['overview'])) {
            $usSeason = json_decode($this->tmdbService->getTvSeason($series->getTmdbId(), $seasonNumber, 'en-US'), true);
            $season['overview'] = $usSeason['overview'];
            $localized = false;
            $localizedOverview = null;
            $localizedResult = null;
            if (strlen($season['overview'])) {
                try {
                    $usage = $this->deeplTranslator->translator->getUsage();
                    if ($usage->character->count + strlen($season['overview']) < $usage->character->limit) {
                        $localizedOverview = $this->deeplTranslator->translator->translateText($season['overview'], null, $request->getLocale());
                        $localized = true;
                    } else {
                        $localizedResult = 'Limit exceeded';
                    }
                } catch (DeepLException $e) {
                    $localizedResult = 'Error: code ' . $e->getCode() . ', message: ' . $e->getMessage();
                    $usage = [
                        'character' => [
                            'count' => 0,
                            'limit' => 500000
                        ]
                    ];
                }
            }
            return [
                'localized' => $localized,
                'localizedOverview' => $localizedOverview,
                'localizedResult' => $localizedResult,
                'usage' => $usage ?? null
            ];
        }
        return null;
    }

    public function watchProviders($tv, $country): array
    {
        $watchProviders = [];
        if (isset($tv['watch/providers']['results'][$country])) {
            $watchProviders = $tv['watch/providers']['results'][$country];
        }
        $flatrate = $watchProviders['flatrate'] ?? [];
        $rent = $watchProviders['rent'] ?? [];
        $buy = $watchProviders['buy'] ?? [];

        $flatrate = array_map(function ($wp) {
            return [
                'provider_id' => $wp['provider_id'],
                'provider_name' => $wp['provider_name'],
                'logo_path' => $wp['logo_path'] ? $this->imageConfiguration->getCompleteUrl($wp['logo_path'], 'logo_sizes', 2) : null, // w45
            ];
        }, $flatrate);
        $rent = array_map(function ($wp) {
            return [
                'provider_id' => $wp['provider_id'],
                'provider_name' => $wp['provider_name'],
                'logo_path' => $wp['logo_path'] ? $this->imageConfiguration->getCompleteUrl($wp['logo_path'], 'logo_sizes', 2) : null, // w45
            ];
        }, $rent);
        $buy = array_map(function ($wp) {
            return [
                'provider_id' => $wp['provider_id'],
                'provider_name' => $wp['provider_name'],
                'logo_path' => $wp['logo_path'] ? $this->imageConfiguration->getCompleteUrl($wp['logo_path'], 'logo_sizes', 2) : null, // w45
            ];
        }, $buy);
        return [
            'flatrate' => $flatrate,
            'rent' => $rent,
            'buy' => $buy,
        ];
    }

    public function getWatchProviders($language, $watchRegion): array
    {
        $providers = json_decode($this->tmdbService->getTvWatchProviderList($language, $watchRegion), true);
        $providers = $providers['results'];
        $watchProviders = [];
        foreach ($providers as $provider) {
            $watchProviders[$provider['provider_name']] = $provider['provider_id'];
        }
        $watchProviderNames = [];
        foreach ($providers as $provider) {
            $watchProviderNames[$provider['provider_id']] = $provider['provider_name'];
        }
        $watchProviderLogos = [];
        foreach ($providers as $provider) {
            $watchProviderLogos[$provider['provider_id']] = $this->imageConfiguration->getCompleteUrl($provider['logo_path'], 'logo_sizes', 2);
        }
        ksort($watchProviders);
        $list = [];
        foreach ($watchProviders as $key => $value) {
            $list[] = ['provider_id' => $value, 'provider_name' => $key, 'logo_path' => $watchProviderLogos[$value]];
        }

        return [
            'select' => $watchProviders,
            'logos' => $watchProviderLogos,
            'names' => $watchProviderNames,
            'list' => $list,
        ];
    }

    public function getKeywords(): array
    {
        $keywords = $this->keywordRepository->findby([], ['name' => 'ASC']);

        $keywordArray = [];
        foreach ($keywords as $keyword) {
            $keywordArray[$keyword->getName()] = $keyword->getKeywordId();
        }
        return $keywordArray;
    }

    public function getSearchString($data): string
    {
        // App\DTO\SeriesAdvancedSearchDTO {#811 ▼
        //  *-language: "fr"
        //  *-timezone: "Europe/Paris"
        //  *-watchRegion: "FR"
        //  -firstAirDateYear: 2023                     -> first_air_date_year
        //  -firstAirDateGTE: null                      -> first_air_date.gte
        //  -firstAirDateLTE: null                      -> first_air_date.lte
        //  -withOriginCountry: null                    -> with_origin_country
        //  -withOriginalLanguage: null                 -> with_original_language
        //  -withWatchMonetizationTypes: "flatrate"     -> with_watch_monetization_types
        //  -withWatchProviders: "119"                  -> with_watch_providers
        //  -watchProviders: array:59 [▶]
        //  -withRuntimeGTE: 0                          -> with_runtime.gte
        //  -withRuntimeLTE: 0                          -> with_runtime.lte
        //  -withStatus: null                           -> with_status
        //  -withType: null                             -> with_type
        //  -sortBy: "popularity.desc"                  -> sort_by
        //  *-page: 1
        //}
        $page = $data->getPage();
        $language = $data->getLanguage();
        $timezone = $data->getTimezone();
        $watchRegion = $data->getWatchRegion();
        $firstAirDateYear = $data->getFirstAirDateYear();
        $firstAirDateGTE = $data->getFirstAirDateGTE()?->format('Y-m-d');
        $firstAirDateLTE = $data->getFirstAirDateLTE()?->format('Y-m-d');
        $withOriginCountry = $data->getWithOriginCountry();
        $withOriginalLanguage = $data->getWithOriginalLanguage();
        $withWatchMonetizationTypes = $data->getWithWatchMonetizationTypes();
        $withWatchProviders = $data->getWithWatchProviders();
        $withKeywords = $data->getWithKeywords();
        $withRuntimeGTE = $data->getWithRuntimeGTE();
        $withRuntimeLTE = $data->getWithRuntimeLTE();
        $withStatus = $data->getWithStatus();
        $withType = $data->getWithType();
        $sortBy = $data->getSortBy();

        $searchString = "&include_adult=false&page=$page&language=$language&timezone=$timezone&watch_region=$watchRegion";
        if ($firstAirDateYear) $searchString .= "&first_air_date_year=$firstAirDateYear";
        if ($firstAirDateGTE) $searchString .= "&first_air_date.gte=$firstAirDateGTE";
        if ($firstAirDateLTE) $searchString .= "&first_air_date.lte=$firstAirDateLTE";
        if ($withOriginCountry) $searchString .= "&with_origin_country=$withOriginCountry";
        if ($withOriginalLanguage) $searchString .= "&with_original_language=$withOriginalLanguage";
        if ($withWatchMonetizationTypes) $searchString .= "&with_watch_monetization_types=$withWatchMonetizationTypes";
        if ($withWatchProviders) $searchString .= "&with_watch_providers=$withWatchProviders";
        if ($withKeywords) $searchString .= "&with_keywords=$withKeywords";
        if ($withRuntimeGTE) $searchString .= "&with_runtime.gte=$withRuntimeGTE";
        if ($withRuntimeLTE) $searchString .= "&with_runtime.lte=$withRuntimeLTE";
        if ($withStatus) $searchString .= "&with_status=$withStatus";
        if ($withType) $searchString .= "&with_type=$withType";
        if ($sortBy) $searchString .= "&sort_by=$sortBy";
        return $searchString;
    }

    public function getSearchResult($searchResult, $slugger): array
    {
        return array_map(function ($tv) use ($slugger) {
            $this->saveImage("posters", $tv['poster_path'], $this->imageConfiguration->getUrl('poster_sizes', 5));
            $tv['poster_path'] = $tv['poster_path'] ? '/series/posters' . $tv['poster_path'] : null;

            $name = $tv['name'];
            $slug = $slugger->slug($name)->lower()->toString();
            if ($slug == "") {
                $tvUS = $this->tmdbService->getTv($tv['id'], 'en-US');
                $tvUS = json_decode($tvUS, true);
                $slug = $slugger->slug($tvUS['name'])->lower()->toString();
                if ($slug == "") {
                    $slug = $tv['id'];
                } else {
                    $name = $tvUS['name'];
                }
            }

            return [
                'tmdb' => true,
                'id' => $tv['id'],
                'name' => $name,
                'slug' => $slug,
                'poster_path' => $tv['poster_path'],
            ];
        }, $searchResult['results'] ?? []);
    }

    public function getOneResult($tv, $slugger): Response
    {
        return $this->redirectToRoute('app_series_tmdb', [
            'id' => $tv['id'],
            'slug' => $slugger->slug($tv['name'])->lower()->toString(),
        ]);
    }

    private function makeRoles(): array
    {
        $genderedTerms = [
            'Self', 'Host', 'Narrator', 'Bartender', 'Guest', 'Musical Guest', 'Wedding Guest', 'Party Guest',
            'uncredited', 'Partygoer', 'Passenger', 'Singer', 'Thumbs Up Giver', 'Academy Awards Presenter',
            'British High Commissioner', 'CIA Director', 'U.S. President', 'President', 'Professor',
            'Sergeant', 'Commander',
        ];
        $unisexTerms = [
            'archive footage', 'voice', 'singing voice', 'CIA Agent', 'Performer',
            'Portrait Subject & Interviewee', 'President of Georgia', 'Preppie Kid at Fight',
            'Themselves', 'Various', '\'s Voice Over', 'Officer', 'Judge', 'Young Agent', 'Agent',
            'Detective', 'Audience', 'Filmmaker',
        ];
        $maleTerms = [
            'Guy at Beach with Drink', 'Courtesy of the Gentleman at the Bar', 'Himself', 'himself',
            'Waiter', 'Young Man in Coffee Shop', 'Weatherman', 'the Studio Chairman', 'The Man',
            'Santa Claus', 'Hero Boy', 'Father', 'Conductor',
        ];
        $femaleTerms = [
            'Beaver Girl', 'Girl in Wheelchair \/ China Girl', 'Herself', 'Woman at Party',
            'Countess', 'Queen',
        ];

        foreach ($genderedTerms as $term) {
            $roles['en'][] = '/(.*)' . $term . '(.*)(1)/';      // féminin
            $roles['en'][] = '/(.*)' . $term . '(.*)([0|2])/';  // non genré ou masculin
        }
        foreach ($unisexTerms as $term) {
            $roles['en'][] = '/(.*)' . $term . '(.*)([0|1|2])/';
        }
        foreach ($maleTerms as $term) {
            $roles['en'][] = '/(.*)' . $term . '(.*)([0|1|2])/';
        }
        foreach ($femaleTerms as $term) {
            $roles['en'][] = '/(.*)' . $term . '(.*)([0|1|2])/';
        }
        $roles['en'][] = '/(.+)([0|1|2])/';

        $roles['fr'] = [
            /* Gendered Terms */
            '${1}Elle-même${2}${3}', /* Ligne 1 */
            '${1}Lui-même${2}${3}',
            '${1}Hôtesse${2}${3}',
            '${1}Hôte${2}${3}',
            '${1}Narratrice${2}${3}',
            '${1}Narrateur${2}${3}',
            '${1}Barmaid${2}${3}',
            '${1}Barman${2}${3}',
            '${1}Invitée${2}${3}',
            '${1}Invité${2}${3}',
            '${1}Invitée musicale${2}${3}',
            '${1}Invité musical${2}${3}',
            '${1}Invitée du mariage${2}${3}',
            '${1}Invité du mariage${2}${3}',
            '${1}Invitée de la fête{2}${3}',
            '${1}Invité de la fête{2}${3}',
            '${1}non créditée${2}${3}', /* ligne 2 */
            '${1}non crédité${2}${3}',
            '${1}Fêtarde${2}${3}',
            '${1}Fêtard${2}${3}',
            '${1}Passagère${2}${3}',
            '${1}Passager${2}${3}',
            '${1}Chanteuse${2}${3}',
            '${1}Chanteur${2}${3}',
            '${1}Donneuse d\'ordre${2}${3}',
            '${1}Donneur d\'ordre${2}${3}',
            '${1}Présentatrice des Oscars${2}${3}',
            '${1}Présentateur des Oscars${2}${3}',
            '${1}Haute commissaire britannique${2}${3}', /* Ligne 3 */
            '${1}Haut commissaire britannique${2}${3}',
            '${1}Directrice de la CIA${2}${3}',
            '${1}Directeur de la CIA${2}${3}',
            '${1}Présidente des États-unis${2}${3}',
            '${1}Président des États-unis${2}${3}',
            '${1}Présidente${2}${3}',
            '${1}Président${2}${3}',
            '${1}Professeure${2}${3}',
            '${1}Professeur${2}${3}',
            '${1}Sergente${2}${3}', /* Ligne 4 */
            '${1}Sergent${2}${3}',
            '${1}Commandante${2}${3}',
            '${1}Commandant${2}${3}',
            /* Unisex Terms */
            '${1}images d\'archives${2}${3}', /* Ligne 1 */
            '${1}voix${2}${3}',
            '${1}chant${2}${3}',
            '${1}Agent de la CIA${2}${3}',
            '${1}Interprète${2}${3}',
            '${1}Portrait du sujet et de la personne${2}${3}', /* Ligne 2 */
            '${1}Président de la Géorgie${2}${3}',
            '${1}Gamin BCBG à la bagarre${2}${3}',
            '${1}Eux-mêmes${2}${3}', /* Ligne 3 */
            '${1}Multiples personnages${2}${3}',
            'Voix off de ${1}${2}${3}',
            '${1}Officer${2}${3}',
            '${1}Juge${2}${3}',
            '${1}Jeune agent${2}${3}',
            '${1}Agent${2}${3}',
            '${1}Détective${2}${3}', /* Ligne 4 */
            '${1}Dans le public${2}${3}',
            '${1}Cinéaste${2}${3}',
            /* Male Terms */
            '${1}Gars à la plage avec un verre${2}${3}', /* Ligne 1 */
            '${1}Avec l\'aimable autorisation du gentleman au bar${2}${3}',
            '${1}Lui-même${2}${3}',
            '${1}lui-même${2}${3}',
            '${1}Serveur${2}${3}', /* Ligne 2 */
            '${1}Jeune homme dans la café${2}${3}',
            '${1}Monsieur Météo${2}${3}',
            '${1}le président du studio${2}${3}',
            '${1}L\'homme${2}${3}',
            '${1}Le Père Noël${2}${3}', /* Ligne 3 */
            '${1}Le garçon héroïque${2}${3}',
            '${1}Le père${2}${3}',
            '${1}Le conducteur${2}${3}',
            /* Female Terms */
            '${1}La fille castor${2}${3}', /* Ligne 1 */
            '${1}Fille en fauteuil roulant${2}${3}',
            '${1}Elle-même${2}${3}',
            '${1}Femme à la fête${2}${3}',
            '${1}Comtesse${2}${3}', /* Ligne 2 */
            '${1}Queen${2}${3}',
        ];
        $roles['fr'][] = '${1}';

        return $roles;
    }

    private function getKnownFor($dates): array
    {
        $knownFor = [];
        $slugger = new AsciiSlugger();

        foreach ($dates as $date) {
            $item = [];
            if ($date['title'] && $date['poster_path']) {
                $item['id'] = $date['id'];
                $item['slug'] = $slugger->slug($date['title'])->lower()->toString();
                $item['media_type'] = $date['media_type'];
                $item['title'] = $date['title'];
                $item['poster_path'] = $this->imageConfiguration->getCompleteUrl($date['poster_path'], 'poster_sizes', 5);
                $knownFor[$date['release_date']] = $item;
            }
        }

        return $knownFor;
    }

    public function saveImage($type, $imagePath, $imageUrl): void
    {
        if (!$imagePath) return;
        $root = $this->getParameter('kernel.project_dir');
        $this->saveImageFromUrl(
            $imageUrl . $imagePath,
            $root . "/public/series/" . $type . $imagePath
        );
    }

    public function saveImageFromUrl($imageUrl, $localeFile): bool
    {
        if (!file_exists($localeFile)) {

            // Vérifier si l'URL de l'image est valide
            if (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                // Récupérer le contenu de l'image à partir de l'URL
                $imageContent = file_get_contents($imageUrl);

                // Ouvrir un fichier en mode écriture binaire
                $file = fopen($localeFile, 'wb');

                // Écrire le contenu de l'image dans le fichier
                fwrite($file, $imageContent);

                // Fermer le fichier
                fclose($file);

                return true;
            } else {
                return false;
            }
        }
        return true;
    }
}
