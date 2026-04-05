import {AddCast} from 'AddCast';
import {EpisodeActions} from "EpisodeActions";
import {FetchEpisodeCards} from "FetchEpisodeCards";
import {Location} from "Location";
import {SeasonComments} from "SeasonComments";
import {WhatNext} from "WhatNext";

/**
 * @typedef FilmingLocationImage
 * @type {Object}
 * @property {number} id
 * @property {number} filming_location_id
 * @property {string} path
 */
/**
 * @typedef FilmingLocation
 * @type {Object}
 * @property {number} id
 * @property {number} is_series
 * @property {number} tmdb_id
 * @property {string} title
 * @property {number} season_number
 * @property {number} episode_number
 * @property {string} location
 * @property {string} description
 * @property {number} latitude
 * @property {number} longitude
 * @property {number} radius
 * @property {number} still_id
 * @property {string} source_name
 * @property {string} source_url
 * @property {string} uuid
 * @property {string} still_path
 * @property {Array.<FilmingLocationImage>} filmingLocationImages
 */
/**
 * @typedef Globs
 * @type {Object}
 * @property {number} seriesId
 * @property {number} episodeId
 * @property {string} seriesName
 * @property {number} seasonNumber
 * @property {number} episodeNumber
 */
/**
 * @typedef GlobsMap
 * @type {Object}
 * @property {Array.<FilmingLocation>} locations
 * @property {Array} bounds
 * @property {Array} emptyLocation
 * @property {Array} fieldList
 * @property {string} locationImagePath
 * @property {string} poiImagePath
 */
/**
 * @typedef Source
 * @type {Object}
 * @property {string} name
 * @property {string} path
 * @property {string} logoPath
 */

let self = null;

export class Episode {

    constructor(flashMessage, toolTips, menu) {
        self = this;
        /** @var {Globs} */
        this.globs = JSON.parse(document.querySelector('div#globs').textContent);
        /** @var {GlobsMap} */
        this.globsMap = JSON.parse(document.querySelector('div#globs-map').textContent);
        this.lang = document.documentElement.lang;
        this.toolTips = toolTips;
        this.flashMessage = flashMessage;
        this.menu = menu;
        this.episodeActions = new EpisodeActions({...this.globs, ...this.globsMap}, flashMessage, toolTips, menu);
        this.episodeId = this.globs.episodeId;
        this.fetchEpisodeCards = new FetchEpisodeCards(this.toolTips);

        this.backToSeason = this.backToSeason.bind(this);
    }

    init() {
        const user = this.globs.user;
        const seriesId = this.globs.seriesId;
        const seriesName = this.globs.seriesName;
        const episodeId = this.globs.episodeId;
        const seasonNumber = this.globs.seasonNumber;
        const translations = this.globs.translations;

        const fieldList = this.globsMap.fieldList;
        const emptyLocation = this.globsMap.emptyLocation;
        const filmingLocations = this.globsMap.locations;
        const locationImagePath = this.globsMap.locationImagePath;
        console.log(this.globsMap);

        /******************************************************************************
         * user actions                                                               *
         ******************************************************************************/
        this.episodeActions.init();

        /******************************************************************************
         * Fetch episode stills for each season.                                      *
         ******************************************************************************/
        this.fetchEpisodeCards.init(this.episodeId);

        /******************************************************************************
         * mapbox gl                                                                  *
         ******************************************************************************/
        const data = {
            translations: translations,
            locations: filmingLocations,
            emptyLocation: emptyLocation,
            imagePath: locationImagePath,
            seriesId: seriesId,
            seriesName: seriesName
        };
        new Location('loc', data, fieldList);

        /******************************************************************************
         * Overview                                                                   *
         ******************************************************************************/
        this.initEditOverview();

        /******************************************************************************
         * Comments                                                                   *
         ******************************************************************************/
        const seasonComments = new SeasonComments(user, seriesId, seasonNumber, translations);
        seasonComments.init();

        /******************************************************************************
         * What next to watch                                                         *
         ******************************************************************************/
        const whatToWatchNextButton = document.querySelector('.what-s-next');
        if (whatToWatchNextButton) {
            new WhatNext(whatToWatchNextButton, this.flashMessage, this.toolTips);
        }

        /******************************************************************************
         * Add a person to the cast - Search input                                    *
         ******************************************************************************/
        const addCast = new AddCast();
        addCast.init(this.menu, this.toolTips, this.flashMessage);

        /******************************************************************************
         * Refactor "back to top" to "back to season"                                 *
         ******************************************************************************/
        const backToTop = document.querySelector('.back-to-top');
        backToTop.setAttribute("data-title", translations['Back to the season'] + ' ' + seasonNumber);
        backToTop.addEventListener('click', this.backToSeason);

        /******************************************************************************
         * Watch link copy button                                                     *
         ******************************************************************************/
        const userActions = document.querySelector('.user-actions');
        const watchLinkCopyDivs = userActions.querySelectorAll('.watch-links.copy');
        watchLinkCopyDivs.forEach(function (copy) {
            copy.addEventListener('click', function () {
                const href = copy.getAttribute('data-url');
                navigator.clipboard.writeText(href).then(function () {
                    copy.classList.add('copied');
                    setTimeout(function () {
                        copy.classList.remove('copied');
                    }, 2000);
                });
            });
        });
    }

    backToSeason(e) {
        e.preventDefault();
        const backLink = document.querySelector('.episode-show > header > .navigation #back-to-season');
        window.location.href = backLink.getAttribute("href") + '#episode-' + this.globs.seasonNumber + '-' + this.globs.episodeNumber;
    }

    initEditOverview() {
        const overviewDialog = document.querySelector('.episode-overview-dialog');
        const form = overviewDialog.querySelector('form');
        const buttonCancel = overviewDialog.querySelector('button[name="cancel"]');

        buttonCancel.addEventListener('click', function () {
            overviewDialog.hidePopover();
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            self.saveOverview(e);
        });
    }

    saveOverview(e) {
        const overview = document.querySelector('.overview');
        const overviewDialog = document.querySelector('.episode-overview-dialog');
        const textarea = overviewDialog.querySelector('textarea');

        fetch('/api/episode/update/info/' + self.episodeId,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    content: textarea.value,
                    type: 'overview'
                })
            }
        )
            .then(function (response) {
                if (response.ok) {
                    overview.innerHTML = textarea.value;
                    overviewDialog.hidePopover();
                }
            });
    }
}
