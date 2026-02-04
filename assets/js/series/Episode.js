import {AddCast} from 'AddCast';
import {EpisodeActions} from "EpisodeActions";
import {Location} from "Location";
import {SeasonComments} from "SeasonComments";

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

let gThis = null;

export class Episode {

    constructor(flashMessage, toolTips, menu) {
        gThis = this;
        /** @var {Globs} */
        this.globs = JSON.parse(document.querySelector('div#globs').textContent);
        /** @var {GlobsMap} */
        this.globsMap = JSON.parse(document.querySelector('div#globs-map').textContent);
        this.lang = document.documentElement.lang;
        this.toolTips = toolTips;
        this.flashMessage = flashMessage;
        this.menu = menu;
        this.episodeActions = new EpisodeActions({...this.globs, ...this.globsMap}, flashMessage, toolTips, menu);

        this.backToSeason = this.backToSeason.bind(this);
    }

    init() {
        const user = this.globs.user;
        const seriesId = this.globs.seriesId;
        const seriesName = this.globs.seriesName;
        const seasonNumber = this.globs.seasonNumber;
        const episodeNumber = this.globs.episodeNumber;
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
         * Comments                                                                   *
         ******************************************************************************/
        const seasonComments = new SeasonComments(user, seriesId, seasonNumber, translations);
        seasonComments.init();

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
    }

    backToSeason(e) {
        e.preventDefault();
        const backLink = document.querySelector('.episode-show > header > .navigation #back-to-season');
        window.location.href = backLink.getAttribute("href") + '#episode-' + this.globs.seasonNumber + '-' + this.globs.episodeNumber;
    }
}
