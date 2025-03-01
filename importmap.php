<?php

/**
 * Returns the importmap for this application.
 *
 * - "path" is a path inside the asset mapper system. Use the
 *     "debug:asset-map" command to see the full list of paths.
 *
 * - "entrypoint" (JavaScript only) set to true for any module that will
 *     be used as an "entrypoint" (and passed to the importmap() Twig function).
 *
 * The "importmap:require" command can be used to add new entries to this file.
 */
return [
    'app' => [
        'path' => './assets/app.js',
        'entrypoint' => true,
    ],
    'AverageColor' => [
        'path' => './assets/js/images/AverageColor.js',
    ],
    'DayCountHistory' => [
        'path' => './assets/js/home/DayCountHistory.js',
    ],
    'Diaporama' => [
        'path' => './assets/js/images/Diaporama.js',
    ],
    'FlashMessage' => [
        'path' => './assets/js/FlashMessage.js',
    ],
    'HighlightSeries' => [
        'path' => './assets/js/home/HighlightSeries.js',
    ],
    'Index' => [
        'path' => './assets/js/series/Index.js',
    ],
    'Keyword' => [
        'path' => './assets/js/Keyword.js',
    ],
    /*'LeafletIcon' => [
        'path' => 'vendor/leaflet/dist/images/marker.png',
    ],
    'LeafletIcon@x2' => [
        'path' => './assets/leaflet/images/yellow-marker@x2.png ',
    ],*/
    'Menu' => [
        'path' => './assets/js/Menu.js',
    ],
    'Movie' => [
        'path' => './assets/js/movies/Movie.js',
    ],
    'NavBar' => [
        'path' => './assets/js/NavBar.js',
    ],
    'NetworkAndProvider' => [
        'path' => './assets/js/user/NetworkAndProvider.js',
    ],
    'PeopleShow' => [
        'path' => './assets/js/people/PeopleShow.js',
    ],
    'PosterHover' => [
        'path' => './assets/js/images/PosterHover.js',
    ],
    'Profile' => [
        'path' => './assets/js/user/Profile.js',
    ],
    'ProviderSelect' => [
        'path' => './assets/js/home/ProviderSelect.js',
    ],
    'ToolTips' => [
        'path' => './assets/js/ToolTips.js',
    ],
    'Season' => [
        'path' => './assets/js/series/Season.js',
    ],
    'Show' => [
        'path' => './assets/js/series/Show.js',
    ],
    '@hotwired/stimulus' => [
        'version' => '3.2.2',
    ],
    '@symfony/stimulus-bundle' => [
        'path' => './vendor/symfony/stimulus-bundle/assets/dist/loader.js',
    ],
    '@hotwired/turbo' => [
        'version' => '7.3.0',
    ],
];
