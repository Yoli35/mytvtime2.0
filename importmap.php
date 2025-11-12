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
    'AdminMovieEdit' => [
        'path' => './assets/js/admin/AdminMovieEdit.js',
    ],
    'AdminPointsOfInterest' => [
        'path' => './assets/js/admin/AdminPointsOfInterest.js',
    ],
    'AdminSeriesUpdates' => [
        'path' => './assets/js/admin/AdminSeriesUpdates.js',
    ],
    'AlbumShow' => [
        'path' => './assets/js/album/AlbumShow.js',
    ],
    'AverageColor' => [
        'path' => './assets/js/images/AverageColor.js',
    ],
    'Diaporama' => [
        'path' => './assets/js/images/Diaporama.js',
    ],
    'EpisodeHistory' => [
        'path' => './assets/js/home/EpisodeHistory.js',
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
    'Location' => [
        'path' => './assets/js/images/Location.js',
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
    'MovieIndex' => [
        'path' => './assets/js/movies/MovieIndex.js',
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
    'PeopleStar' => [
        'path' => './assets/js/people/PeopleStar.js',
    ],
    'Photos' => [
        'path' => './assets/js/album/Photos.js',
    ],
//    'PosterHover' => [
//        'path' => './assets/js/images/PosterHover.js',
//    ],
    'Profile' => [
        'path' => './assets/js/user/Profile.js',
    ],
    'ProviderSelect' => [
        'path' => './assets/js/home/ProviderSelect.js',
    ],
    'ToolTips' => [
        'path' => './assets/js/ToolTips.js',
    ],
    'TranslationsForms' => [
        'path' => './assets/js/translations/TranslationsForms.js',
    ],
    'Season' => [
        'path' => './assets/js/series/Season.js',
    ],
    'Show' => [
        'path' => './assets/js/series/Show.js',
    ],
    'VideoList' => [
        'path' => './assets/js/videos/VideoList.js',
    ],
    'Video' => [
        'path' => './assets/js/videos/Video.js',
    ],
    'WatchLinkCrud' => [
        'path' => './assets/js/watch-link/WatchLinkCrud.js',
    ],
    '@symfony/stimulus-bundle' => [
        'path' => './vendor/symfony/stimulus-bundle/assets/dist/loader.js',
    ],
    '@hotwired/stimulus' => [
        'version' => '3.2.2',
    ],
    '@hotwired/turbo' => [
        'version' => '8.0.13',
    ],
    'mapbox-gl' => [
        'version' => '3.14.0',
    ],
    'mapbox-gl/dist/mapbox-gl.min.css' => [
        'version' => '3.14.0',
        'type' => 'css',
    ],
    '@mapbox/mapbox-gl-language' => [
        'version' => '1.0.1',
    ],
    '@mapbox/mapbox-gl-geocoder' => [
        'version' => '5.1.0',
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
    'subtag' => [
        'version' => '0.5.0',
    ],
    '@mapbox/mapbox-gl-geocoder/lib/mapbox-gl-geocoder.min.css' => [
        'version' => '5.1.0',
        'type' => 'css',
    ],
    'fuzzy' => [
        'version' => '0.1.3',
    ],
    '@mapbox/parse-mapbox-token' => [
        'version' => '0.2.0',
    ],
    'eventemitter3' => [
        'version' => '5.0.1',
    ],
    '@mapbox/fusspot' => [
        'version' => '0.8.1',
    ],
    'base-64' => [
        'version' => '1.0.0',
    ],
    'is-plain-obj' => [
        'version' => '4.1.0',
    ],
    'nanoid' => [
        'version' => '3.3.11',
    ],
    '@mapbox/search-js-web' => [
        'version' => '1.3.0',
    ],
    '@mapbox/search-js-core' => [
        'version' => '1.3.0',
    ],
    '@floating-ui/dom' => [
        'version' => '0.5.4',
    ],
    'no-scroll' => [
        'version' => '2.1.1',
    ],
    'focus-trap' => [
        'version' => '6.9.4',
    ],
    '@mapbox/sphericalmercator' => [
        'version' => '1.2.0',
    ],
    '@floating-ui/core' => [
        'version' => '0.7.3',
    ],
    'tabbable' => [
        'version' => '5.3.3',
    ],
];
