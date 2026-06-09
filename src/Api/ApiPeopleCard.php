<?php

namespace App\Api;

use App\Entity\User;
use App\Repository\MovieRepository;
use App\Repository\PeopleLocalizedBiographyRepository;
use App\Repository\PeopleUserPreferredNameRepository;
use App\Repository\PeopleUserRatingRepository;
use App\Repository\SeriesRepository;
use App\Service\DateService;
use App\Service\ImageConfiguration;
use App\Service\PeopleService;
use App\Service\TMDBService;
use Closure;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\DependencyInjection\Attribute\AutowireMethodOf;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Contracts\Translation\TranslatorInterface;

/** @method User|null getUser() */
#[Route('/api/people/card', name: 'api_people_card_')]
readonly class ApiPeopleCard
{
    public function __construct(
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure                            $generateUrl,
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure                            $json,
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure                            $renderView,
        private DateService                        $dateService,
        private ImageConfiguration                 $imageConfiguration,
        private PeopleLocalizedBiographyRepository $peopleLocalizedBiographyRepository,
        private PeopleUserPreferredNameRepository  $peopleUserPreferredNameRepository,
        private PeopleService                      $peopleService,
        private PeopleUserRatingRepository         $peopleUserRatingRepository,
        private SeriesRepository                   $seriesRepository,
        private MovieRepository                    $movieRepository,
        private TMDBService                        $tmdbService,
        private TranslatorInterface                $translator,
    )
    {
    }

    #[Route('/show', name: 'show', methods: ['POST'])]
    public function show(#[CurrentUser] User $user, Request $request): Response
    {
        $payload = json_decode($request->getContent(), true);
        $id = $payload['id'];

        $seriesInfos = $this->seriesRepository->userSeriesInfos($user);
        $seriesIds = array_column($seriesInfos, 'id');
        $movieInfos = $this->movieRepository->movieInfos($user);
        $movieIds = array_column($movieInfos, 'tmdbId');
        $indexedSeriesInfos = [];
        foreach ($seriesInfos as $info) {
            $indexedSeriesInfos[$info['id']] = $info;
        }
        $indexedMovieInfos = [];
        foreach ($movieInfos as $info) {
            $indexedMovieInfos[$info['tmdbId']] = $info;
        }

        $standing = $this->tmdbService->getPerson($id, $request->getLocale(), "images,combined_credits,translations");
        $people = json_decode($standing, true);
        $credits = $people['combined_credits'];
        $translations = $people['translations']['translations'] ?? [];

        if ($people['biography'] == '' && !empty($translations)) {
            foreach ($translations as $translation) {
                if ($translation['iso_639_1'] == 'en' && $translation['data']['biography'] != '') {
                    $people['biography'] = $translation['data']['biography'] ?? $people['biography'];
                    break;
                }
            }
        }

        $peopleUserRating = $this->peopleUserRatingRepository->getPeopleUserRating($user->getId(), $id);
        $people['userRating'] = $peopleUserRating['rating'] ?? 0;
        $people['avgRating'] = $peopleUserRating['avg_rating'] ?? 0;

        $people['preferredName'] = $this->peopleUserPreferredNameRepository->findOneBy(['user' => $user, 'tmdbId' => $id]);
        $people['localizedBiography'] = $this->peopleLocalizedBiographyRepository->findOneBy(['tmdbId' => $id, 'locale' => $request->getLocale()]);

        if (key_exists('birthday', $people) && $people['birthday']) {
            $date = $this->dateService->newDate($people['birthday'], "Europe/Paris");
            if (key_exists('deathday', $people) && $people['deathday']) {
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
        $roles = $this->peopleService->makeRoles();

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
            $typeTv = $role['media_type'] == 'tv';
            $role['original_title'] = key_exists('original_title', $cast) ? $cast['original_title'] : (key_exists('original_name', $cast) ? $cast['original_name'] : null);
            $role['poster_path'] = key_exists('poster_path', $cast) ? $cast['poster_path'] : null;
            $role['release_date'] = key_exists('release_date', $cast) ? $cast['release_date'] : (key_exists('first_air_date', $cast) ? $cast['first_air_date'] : null);
            $role['title'] = key_exists('title', $cast) ? $cast['title'] : (key_exists('name', $cast) ? $cast['name'] : null);
            $role['slug'] = $role['title'] ? $slugger->slug($role['title'])->lower()->toString() : null;

            $role['user_added'] = in_array($cast['id'], $typeTv ? $seriesIds : $movieIds);
            if ($role['user_added']) {
                $role['localized_title'] = $indexedSeriesInfos[$cast['id']]['localized_name'] ?? null;
                $role['progress'] = $typeTv ? ($indexedSeriesInfos[$cast['id']]['progress'] ?? null) : ($indexedMovieInfos[$cast['id']]['lastViewedAt'] != null ? 100 : 0 ?? null);
                if ($role['progress']) {
                    $role['progress'] = round($role['progress'], 2);
                }
                $role['rating'] = $typeTv ? ($indexedSeriesInfos[$cast['id']]['rating'] ?? null) : ($indexedMovieInfos[$cast['id']]['rating'] ?? null);
                $role['favorite'] = $typeTv ? ($indexedSeriesInfos[$cast['id']]['favorite'] ?? null) : ($indexedMovieInfos[$cast['id']]['favorite'] ?? null);
            } else {
                $role['localized_title'] = null;
                $role['progress'] = null;
                $role['rating'] = null;
                $role['favorite'] = null;
            }

            if ($role['release_date']) {
                $castDates[$role['release_date']] = $role;
            } else {
                $castNoDates[$noDate++] = $role;
            }
        }
        krsort($castDates);
        $credits['cast'] = array_merge($castNoDates, $castDates);
        $knownFor = $this->peopleService->getKnownFor($credits['cast']);

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
            $knownFor = array_merge($knownFor, $this->peopleService->getKnownFor($dates));
        }
        $credits['crew'] = $sortedCrew;
        krsort($knownFor);
        $credits['known_for'] = $knownFor;

        $view = ($this->renderView)('_blocks/people/_card.html.twig', [
            'people' => $people,
            'credits' => $credits,
            'count' => $count,
            'user' => $user,
            'imageConfig' => $this->imageConfiguration->getConfig(),
        ]);

        $seriesGetOverviewUrl = substr(($this->generateUrl)('app_series_get_overview', ['_locale' => $request->getLocale(), 'id' => 0]), 0, -1);
        $ratingUrl = ($this->generateUrl)('app_people_rating', ['_locale' => $request->getLocale()]);
        $peoplePreferredNameUrl = ($this->generateUrl)('api_people_preferred_name_save', ['_locale' => $request->getLocale()]);
        $imgUrl = $this->imageConfiguration->getUrl('profile_sizes', 2);

        return ($this->json)([
            'success' => true,
            'view' => $view,
            'globs' => [
                'app_series_get_overview' => $seriesGetOverviewUrl,
                "app_people_rating" => $ratingUrl,
                "app_people_preferred_name" => $peoplePreferredNameUrl,
                'imgUrl' => $imgUrl,
                "translations" => [
                    "Add" => $this->translator->trans('Add', [], 'messages'),
                    "Edit" => $this->translator->trans('Edit', [], 'messages'),
                    "biography error" => $this->translator->trans('biography error', [], 'messages'),
                ]
            ]
        ]);
    }
}