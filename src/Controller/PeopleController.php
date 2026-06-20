<?php

namespace App\Controller;

use App\Entity\People;
use App\Entity\PeopleUserRating;
use App\Entity\User;
use App\Repository\MovieRepository;
use App\Repository\PeopleLocalizedBiographyRepository;
use App\Repository\PeopleRepository;
use App\Repository\PeopleUserPreferredNameRepository;
use App\Repository\PeopleUserRatingRepository;
use App\Repository\SeriesRepository;
use App\Service\DateService;
use App\Service\ImageConfiguration;
use App\Service\ImageService;
use App\Service\PeopleService;
use App\Service\TMDBService;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Contracts\Translation\TranslatorInterface;

/** @method User|null getUser() */
#[IsGranted('ROLE_USER')]
#[Route('{_locale}/people', name: 'app_people_', requirements: ['_locale' => 'fr|en|ko'])]
class PeopleController extends AbstractController
{

    public function __construct(
        private readonly DateService                        $dateService,
        private readonly ImageConfiguration                 $imageConfiguration,
        private readonly ImageService                       $imageService,
        private readonly MovieRepository                    $movieRepository,
        private readonly PeopleLocalizedBiographyRepository $peopleLocalizedBiographyRepository,
        private readonly PeopleRepository                   $peopleRepository,
        private readonly PeopleService                      $peopleService,
        private readonly PeopleUserPreferredNameRepository  $peopleUserPreferredNameRepository,
        private readonly PeopleUserRatingRepository         $peopleUserRatingRepository,
        private readonly SeriesRepository                   $seriesRepository,
        private readonly TmdbService                        $tmdbService,
        private readonly TranslatorInterface                $translator,
    )
    {
    }

    #[Route('/popular', name: 'index')]
    public function index(Request $request): Response
    {
        $posterUrl = $this->imageConfiguration->getUrl('poster_sizes', 5);
        $profileUrl = $this->imageConfiguration->getUrl('profile_sizes', 2);

        /********************************************************************************
         * Popular People                                                               *
         ********************************************************************************/
        $page = $request->query->get('page', 1);
        $people = json_decode($this->tmdbService->getPopularPeople($request->getLocale(), $page), true);

        $people['results'] = array_map(function ($person) use ($posterUrl, $profileUrl) {
            $slugger = new AsciiSlugger();
            $person['slug'] = $slugger->slug($person['name'])->lower()->toString();
            $person['profile_path'] = $person['profile_path'] ? $profileUrl . $person['profile_path'] : null;
            $person['known_for'] = array_map(function ($knownFor) use ($posterUrl, $slugger) {
                $knownFor['slug'] = $slugger->slug($knownFor['media_type'] == 'movie' ? $knownFor['title'] : $knownFor['name'])->lower()->toString();
                $knownFor['poster_path'] = $knownFor['poster_path'] ? $posterUrl . $knownFor['poster_path'] : null;
                return $knownFor;
            }, $person['known_for']);
            return $person;
        }, $people['results']);

        return $this->render('people/index.html.twig', [
            'people' => $people,
        ]);
    }

    #[Route('/star', name: 'star')]
    public function star(Request $request): Response
    {
        $user = $this->getUser();
        $now = $this->dateService->getNowImmutable("Europe/Paris", true);
        $posterUrl = $this->imageConfiguration->getUrl('poster_sizes', 5);
        $profilUrl = $this->imageConfiguration->getUrl('profile_sizes', 2);
        /********************************************************************************
         * User Star People                                                             *
         ********************************************************************************/
        $starPeople = $this->peopleUserRatingRepository->findBy(['user' => $user], ['rating' => 'DESC'], 20);
        $starPeopleCount = $this->peopleUserRatingRepository->count(['user' => $user]);
        $starPeopleIds = array_map(fn($star) => $star->getTmdbId(), $starPeople);
        /* Gender
            is 0	Not set / not specified
            1	Female
            2	Male
            3	Non-binary */
        $dbStarPeopleArr = $this->peopleRepository->getPeopleByTMDBId($starPeopleIds);
        $dbStarPeopleIds = array_column($dbStarPeopleArr, 'tmdb_id');
        $dbStarPeopleFinalArr = [];
        $newPeople = 0;
        $slugger = new AsciiSlugger();
        foreach ($starPeopleIds as $id) {
            $rating = array_find($starPeople, fn($person) => $person->getTmdbId() == $id);
            if (in_array($id, $dbStarPeopleIds)) {
                $dbPeople = array_find($dbStarPeopleArr, fn($person) => $person['tmdb_id'] == $id);
                $dbPeople['slug'] = $slugger->slug($dbPeople['name'])->lower()->toString();
                $dbPeople['age'] = $this->peopleService->age($now, $dbPeople['birthday'], $dbPeople['deathday']);
                $dbPeople['rating'] = $rating->getRating();
                $this->imageService->saveImage('profiles', $dbPeople['profile_path'], $profilUrl, '/people/');
                $dbStarPeopleFinalArr[] = $dbPeople;
                continue;
            }
            $standing = $this->tmdbService->getPerson($id, $request->getLocale());
            $people = json_decode($standing, true);
            $dbPeople = $this->savePeople($people);
            $dbPeople = $dbPeople->toArray();
            $dbPeople['age'] = $this->peopleService->age($now, $dbPeople['birthday'], $dbPeople['deathday']);
            $dbPeople['rating'] = $rating->getRating();
            $this->imageService->saveImage('profiles', $dbPeople['profile_path'], $profilUrl, '/people/');
            $dbStarPeopleFinalArr[] = $dbPeople;
            $newPeople++;
        }
        if ($newPeople > 0) {
            $this->addFlash('success', $this->translator->trans('Number of new people added: %newPeople%', [
                '%newPeople%' => $newPeople,
            ]));
        }
        // Tri par vote décroissant puis par date de naissance décroissante
        usort($dbStarPeopleFinalArr, function ($a, $b) {
            if ($a['rating'] == $b['rating']) {
                return $a['birthday'] <=> $b['birthday'];
            }
            return $b['rating'] <=> $a['rating'];
        });

        return $this->render('people/star.html.twig', [
            'people' => $dbStarPeopleFinalArr,
            'peopleCount' => $starPeopleCount,
            'profileUrl' => $profilUrl,
            'posterUrl' => $posterUrl,
        ]);
    }


    #[Route('/show/{id}-{slug}', name: 'show', requirements: ['id' => Requirement::DIGITS])]
    public function people(Request $request, int $id): Response
    {
        $user = $this->getUser();
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

                $role['progress'] = $this->getProgress($typeTv, $cast, $indexedSeriesInfos, $indexedMovieInfos);//$typeTv ? ($indexedSeriesInfos[$cast['id']]['progress'] ?? null) : ($indexedMovieInfos[$cast['id']]['lastViewedAt'] != null ? 100 : 0 ?? null);

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
dump($credits);
        return $this->render('people/show.html.twig', [
            'people' => $people,
            'credits' => $credits,
            'count' => $count,
            'user' => $user,
            'imageConfig' => $this->imageConfiguration->getConfig(),
        ]);
    }

    private function getProgress(bool $isTv, array $cast, array $indexedSeriesInfos, array $indexedMovieInfos): float|string
    {
        if ($isTv) {
            return round(($indexedSeriesInfos[$cast['id']]['progress'] ?? 0), 2);
        } else {
            if ($indexedMovieInfos[$cast['id']]['lastViewedAt']) {
                return ucfirst($this->dateService->formatDateRelativeShort($indexedMovieInfos[$cast['id']]['lastViewedAt'], 'UTC', 'fr'));
            }
        }
        return 0;
    }

    #[Route('/rating', name: 'rating', methods: ['POST'])]
    public function rating(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);
        $id = $data['id'];
        $rating = $data['rating'];

        $peopleUserRating = $this->peopleUserRatingRepository->findOneBy(['user' => $user, 'tmdbId' => $id]);

        if (!$peopleUserRating) {
            $peopleUserRating = new PeopleUserRating($user, $id, $rating);
        } else {
            $peopleUserRating->setRating($rating);
        }
        $this->peopleUserRatingRepository->save($peopleUserRating, true);

        $peopleUserRating = $this->peopleUserRatingRepository->getPeopleUserRating($user->getId(), $id);
        $rating = $peopleUserRating['rating'] ?? 0;
        $avgRating = $peopleUserRating['avg_rating'] ?? 0;

        $ratingInfosBlock = $this->renderView('_blocks/people/_rating.html.twig', [
            'rating' => $rating,
            'avgRating' => $avgRating,
        ]);

        return $this->json([
            'ok' => true,
            'block' => $ratingInfosBlock,
        ]);
    }

    public function savePeople(array $people): People
    {
        $dbPeople = new People(
            $people['adult'],
            $people['also_known_as'],
            $people['biography'],
            $this->dateService->newDate($people['birthday'], "Europe/Paris"),
            key_exists('deathday', $people) && $people['deathday'] ? $this->dateService->newDate($people['deathday'], "Europe/Paris") : null,
            $people['gender'],
            $people['homepage'],
            $people['id'],
            key_exists('imdb_id', $people) ? $people['imdb_id'] : null,
            $people['known_for_department'],
            $people['name'],
            $people['place_of_birth'],
            $people['profile_path']
        );
        $this->peopleRepository->save($dbPeople, true);

        return $dbPeople;
    }
}
