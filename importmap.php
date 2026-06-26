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
 *
 * @return array<string, array{    // Import name as key, description of the imported file as value
 *     path: string,               // Logical, relative or absolute path to the file
 *     type?: 'js'|'css'|'json',   // Type of the file, defaults to 'js'
 *     entrypoint?: bool,          // Whether the file is an entrypoint, for 'js' only
 * }|array{
 *     version: string,            // Version of the remote package
 *     package_specifier?: string, // Remote "package-name/path" specifier, defaults to the import name
 *     type?: 'js'|'css'|'json',
 *     entrypoint?: bool,
 * }>
 */
return [
    '@floating-ui/core' => ['version' => '1.7.5'],
    '@floating-ui/dom' => ['version' => '1.7.6'],
    '@floating-ui/utils' => ['version' => '0.2.11'],
    '@floating-ui/utils/dom' => ['version' => '0.2.11'],
    '@hotwired/stimulus' => ['version' => '3.2.2'],
    '@hotwired/turbo' => ['version' => '8.0.23'],
    '@symfony/stimulus-bundle' => ['path' => './vendor/symfony/stimulus-bundle/assets/dist/loader.js'],
    'AddCast' => ['path' => './assets/js/series/AddCast.js'],
    'AdminApi' => ['path' => './assets/js/admin/AdminApi.js'],
    'AdminKeyword' => ['path' => './assets/js/admin/AdminKeyword.js'],
    'AdminMovieEdit' => ['path' => './assets/js/admin/AdminMovieEdit.js'],
    'AdminPointsOfInterest' => ['path' => './assets/js/admin/AdminPointsOfInterest.js'],
    'AdminSeriesUpdates' => ['path' => './assets/js/admin/AdminSeriesUpdates.js'],
    'AdminTools' => ['path' => './assets/js/admin/AdminTools.js'],
    'AlbumShow' => ['path' => './assets/js/album/AlbumShow.js'],
    'Application' => ['path' => './assets/js/Application.js'],
    'AverageColor' => ['path' => './assets/js/images/AverageColor.js'],
    'CopyName' => ['path' => './assets/js/series/CopyName.js'],
    'Diaporama' => ['path' => './assets/js/images/Diaporama.js'],
    'Episode' => ['path' => './assets/js/series/Episode.js'],
    'EpisodeActions' => ['path' => './assets/js/series/EpisodeActions.js'],
    'EpisodeHistory' => ['path' => './assets/js/home/EpisodeHistory.js'],
    'FetchEpisodeCards' => ['path' => './assets/js/series/FetchEpisodeCards.js'],
    'FlashMessage' => ['path' => './assets/js/FlashMessage.js'],
    'HighlightSeries' => ['path' => './assets/js/home/HighlightSeries.js'],
    'Index' => ['path' => './assets/js/series/Index.js'],
    'Keyword' => ['path' => './assets/js/Keyword.js'],
    'Location' => ['path' => './assets/js/images/Location.js'],
    'Map' => ['path' => './assets/js/Map.js'],
    'Menu' => ['path' => './assets/js/Menu.js'],
    'Movie' => ['path' => './assets/js/movies/Movie.js'],
    'MovieIndex' => ['path' => './assets/js/movies/MovieIndex.js'],
    'NavBar' => ['path' => './assets/js/NavBar.js'],
    'NetworkAndProvider' => ['path' => './assets/js/user/NetworkAndProvider.js'],
    'PeopleCard' => ['path' => './assets/js/people/PeopleCard.js'],
    'PeopleShow' => ['path' => './assets/js/people/PeopleShow.js'],
    'PeopleStar' => ['path' => './assets/js/people/PeopleStar.js'],
    'Photos' => ['path' => './assets/js/album/Photos.js'],
    'PreferredName' => ['path' => './assets/js/people/PreferredName.js'],
    'Profile' => ['path' => './assets/js/user/Profile.js'],
    'ProviderSelect' => ['path' => './assets/js/home/ProviderSelect.js'],
    'RgbToHsl' => ['path' => './assets/js/images/RgbToHsl.js'],
    'RgbToLch' => ['path' => './assets/js/images/RgbToLch.js'],
    'Season' => ['path' => './assets/js/series/Season.js'],
    'SeasonComments' => ['path' => './assets/js/series/SeasonComments.js'],
    'SeriesStatistics' => ['path' => './assets/js/home/SeriesStatistics.js'],
    'Show' => ['path' => './assets/js/series/Show.js'],
    'ToolTips' => ['path' => './assets/js/ToolTips.js'],
    'TranslationsForms' => ['path' => './assets/js/translations/TranslationsForms.js'],
    'UserList' => ['path' => './assets/js/list/UserList.js'],
    'Video' => ['path' => './assets/js/videos/Video.js'],
    'VideoList' => ['path' => './assets/js/videos/VideoList.js'],
    'WatchButton' => ['path' => './assets/js/components/WatchButton.js'],
    'WatchLinkCrud' => ['path' => './assets/js/watch-link/WatchLinkCrud.js'],
    'WhatNext' => ['path' => './assets/js/series/WhatNext.js'],
    'app' => ['path' => './assets/app.js', 'entrypoint' => true],
    'base-64' => ['version' => '1.0.0'],
    'eventemitter3' => ['version' => '5.0.4'],
    'focus-trap' => ['version' => '8.2.2'],
    'fuzzy' => ['version' => '0.1.1'],
    'is-plain-obj' => ['version' => '4.1.0'],
    'js-confetti' => ['version' => '0.13.1'],
    'lodash.debounce' => ['version' => '4.0.8'],
    'mapbox-gl/dist/mapbox-gl.min.css' => ['version' => '3.25.0', 'type' => 'css'],
    'nanoid' => ['version' => '5.1.15'],
    'no-scroll' => ['version' => '2.1.1'],
    'subtag' => ['version' => '0.5.0'],
    'suggestions' => ['version' => '1.3.1'],
    'tabbable' => ['version' => '6.5.0'],
    'xtend' => ['version' => '4.0.1'],
    'mapbox-gl' => ['version' => '3.25.0'],
];
