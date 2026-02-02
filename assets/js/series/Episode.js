import {AddCast} from 'AddCast';
import {Location} from "Location";
import {SeasonComments} from "SeasonComments";

let gThis = null;

export class Episode {

    /** @typedef FilmingLocationImage
     * @type {Object}
     * @property {number} id
     * @property {number} filming_location_id
     * @property {string} path
     */

    /** @typedef FilmingLocation
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

    constructor(flashMessage, toolTips, menu) {
        gThis = this;
        this.lang = document.documentElement.lang;
        this.toolTips = toolTips;
        this.flashMessage = flashMessage;
        this.menu = menu;
    }

    init() {
        /** @var {Globs} */
        const jsonGlobsObject = JSON.parse(document.querySelector('div#globs').textContent);
        const user = jsonGlobsObject.user;
        const seriesId = jsonGlobsObject.seriesId;
        const seriesName = jsonGlobsObject.seriesName;
        const seasonNumber = jsonGlobsObject.seasonNumber;
        const translations = jsonGlobsObject.translations;

        /** var{GlobsMap} */
        const jsonGlobsMap = JSON.parse(document.querySelector('div#globs-map').textContent);
        const fieldList = jsonGlobsMap.fieldList;
        const emptyLocation = jsonGlobsMap.emptyLocation;
        const filmingLocations = jsonGlobsMap.locations;
        const locationImagePath = jsonGlobsMap.locationImagePath;
        console.log(jsonGlobsMap);

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
    }
}
