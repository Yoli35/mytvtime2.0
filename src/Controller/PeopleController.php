<?php

namespace App\Controller;

use App\Entity\PeopleUserRating;
use App\Entity\User;
use App\Repository\MovieRepository;
use App\Repository\PeopleUserRatingRepository;
use App\Repository\SeriesRepository;
use App\Service\DateService;
use App\Service\ImageConfiguration;
use App\Service\TMDBService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[Route('{_locale}/people', name: 'app_people_', requirements: ['_locale' => 'fr|en|ko'])]
class PeopleController extends AbstractController
{

    public function __construct(
        private readonly DateService                $dateService,
        private readonly ImageConfiguration         $imageConfiguration,
        private readonly MovieRepository            $movieRepository,
        private readonly PeopleUserRatingRepository $peopleUserRatingRepository,
        private readonly SeriesRepository           $seriesRepository,
        private readonly TmdbService                $tmdbService
    )
    {
    }

    #[Route('/popular', name: 'index')]
    public function index(Request $request): Response
    {
        $page = $request->query->get('page', 1);
        $people = json_decode($this->tmdbService->getPopularPeople($request->getLocale(), $page), true);
        /*
         * [▼
              "adult" => false
              "gender" => 2
              "id" => 976
              "known_for_department" => "Acting"
              "name" => "Jason Statham"
              "original_name" => "Jason Statham"
              "popularity" => 213.578
              "profile_path" => "/whNwkEQYWLFJA8ij0WyOOAD5xhQ.jpg"
              "known_for" => array:3 [▼
                0 => array:15 [▼
                  "backdrop_path" => "/ibKeXahq4JD63z6uWQphqoJLvNw.jpg"
                  "id" => 345940
                  "title" => "En eaux troubles"
                  "original_title" => "The Meg"
                  "overview" => "
        Missionné par un programme international d'observation de la vie sous-marine, un submersible a été attaqué par une créature gigantesque qu'on croyait disparue.
         ▶
        "
                  "poster_path" => "/sKmxFCYGSync2zH94Aukzcp8yYA.jpg"
                  "media_type" => "movie"
                  "adult" => false
                  "original_language" => "en"
                  "genre_ids" => array:3 [▶]
                  "popularity" => 102.043
                  "release_date" => "2018-08-09"
                  "video" => false
                  "vote_average" => 6.257
                  "vote_count" => 7477
                ]
                1 => array:15 [▶]
                2 => array:15 [▶]
           ]
         */
        $people['results'] = array_map(function ($person) {
            $slugger = new AsciiSlugger();
            $person['slug'] = $slugger->slug($person['name'])->lower()->toString();
            $person['profile_path'] = $person['profile_path'] ? $this->imageConfiguration->getCompleteUrl($person['profile_path'], 'profile_sizes', 2) : null;
            $person['known_for'] = array_map(function ($knownFor) {
                $slugger = new AsciiSlugger();
                $knownFor['slug'] = $slugger->slug($knownFor['media_type'] == 'movie' ? $knownFor['title'] : $knownFor['name'])->lower()->toString();
                $knownFor['poster_path'] = $knownFor['poster_path'] ? $this->imageConfiguration->getCompleteUrl($knownFor['poster_path'], 'poster_sizes', 5) : null;
                return $knownFor;
            }, $person['known_for']);
            return $person;
        }, $people['results']);

//        dump($people);
        return $this->render('people/index.html.twig', [
            'people' => $people,
        ]);
    }

    #[Route('/show/{id}-{slug}', name: 'show', requirements: ['id' => Requirement::DIGITS])]
    public function people(Request $request, $id): Response
    {
        /** @var User $user */
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
//        dump([
//            'indexedMovieInfos' => $indexedMovieInfos,
//            ]);

        $peopleUserRating = $this->peopleUserRatingRepository->getPeopleUserRating($user->getId(), $id);
        $rating = $peopleUserRating[0]['rating'] ?? 0;
        $avgRating = $peopleUserRating[0]['avg_rating'] ?? 0;
        dump($peopleUserRating, $rating, $avgRating);

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

        return $this->render('people/show.html.twig', [
            'people' => $people,
            'rating' => $rating,
            'avgRating' => $avgRating,
            'credits' => $credits,
            'count' => $count,
            'user' => $user,
            'imageConfig' => $this->imageConfiguration->getConfig(),
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
        $rating = $peopleUserRating[0]['rating'] ?? 0;
        $avgRating = $peopleUserRating[0]['avg_rating'] ?? 0;

        $ratingInfosBlock = $this->renderView('_blocks/people/_rating.html.twig', [
            'rating' => $rating,
            'avgRating' => $avgRating,
        ]);

        return $this->json([
            'ok' => true,
            'block' => $ratingInfosBlock,
        ]);
    }
}
