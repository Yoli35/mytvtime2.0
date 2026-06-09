<?php

namespace App\Service;

use DateTimeImmutable;
use Symfony\Component\String\Slugger\AsciiSlugger;

class PeopleService
{
    public function __construct(
        private readonly DateService     $dateService,
        private readonly ImageConfiguration $imageConfiguration,
    ) {
    }

    public function age(DateTimeImmutable $now, mixed $birthday, mixed $deathday): int
    {
        if (is_string($birthday)) {
            $birthday = $this->dateService->newDate($birthday, "Europe/Paris", true);
        }
        $interval = $now->diff($birthday);
        if ($deathday) {
            if (is_string($birthday)) {
                $deathday = $this->dateService->newDate($deathday, "Europe/Paris", true);
            }
            $interval = $deathday->diff($birthday);
        }
        return $interval->y;
    }

    public function makeRoles(): array
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

    public function getKnownFor($dates): array
    {
        $knownFor = [];
        $slugger = new AsciiSlugger();
        $posterUrl = $this->imageConfiguration->getUrl('poster_sizes', 5);
        $now = $this->dateService->getNowImmutable("Europe/Paris", true);

        foreach ($dates as $date => $media) {
            $item = [];
            // si la clé est numérique, on la transforme en date "aaaa-mm-dd" ($now + clé * un mois).
            if (is_numeric($date)) {
                $offset = 1 + $date;
                $date = $now->modify("+$offset month")->format('Y-m-d');
            }
            if ($media['title'] && $media['poster_path']) {
                $item['id'] = $media['id'];
                $item['slug'] = $slugger->slug($media['title'])->lower()->toString();
                $item['media_type'] = $media['media_type'];
                $item['title'] = $media['title'];
                $item['poster_path'] = $posterUrl . $media['poster_path'];
                $item['added'] = $media['user_added'] ?? false;
                $knownFor[$date] = $item;
            }
        }

        return $knownFor;
    }

}