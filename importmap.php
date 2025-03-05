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
    'Map' => [
        'path' => './assets/js/Map.js',
    ],
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
    'mapbox-gl' => [
        'version' => '3.10.0',
    ],
    'mapbox-gl/dist/mapbox-gl.min.css' => [
        'version' => '3.10.0',
        'type' => 'css',
    ],
    '@mapbox/mapbox-gl-geocoder' => [
        'version' => '5.0.3',
    ],
    'suggestions' => [
        'version' => '1.7.1',
    ],
    'lodash.debounce' => [
        'version' => '4.0.8',
    ],
    'xtend' => [
        'version' => '4.0.2',
    ],
    '@mapbox/mapbox-sdk' => [
        'version' => '0.16.1',
    ],
    '@mapbox/mapbox-sdk/services/geocoding' => [
        'version' => '0.16.1',
    ],
    'nanoid' => [
        'version' => '3.3.7',
    ],
    'subtag' => [
        'version' => '0.5.0',
    ],
    '@mapbox/mapbox-gl-geocoder/lib/mapbox-gl-geocoder.min.css' => [
        'version' => '5.0.3',
        'type' => 'css',
    ],
    'fuzzy' => [
        'version' => '0.1.3',
    ],
    '@mapbox/parse-mapbox-token' => [
        'version' => '0.2.0',
    ],
    'eventemitter3' => [
        'version' => '3.1.2',
    ],
    '@mapbox/fusspot' => [
        'version' => '0.4.0',
    ],
    'base-64' => [
        'version' => '0.1.0',
    ],
    'is-plain-obj' => [
        'version' => '1.1.0',
    ],
    '@mapbox/mapbox-gl-language' => [
        'version' => '1.0.1',
    ],
];
