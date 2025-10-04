/**
 * @typedef Globs
 * @type {Object}
 * @property {Array} highlightedSeries
 * @property {Array} watchProviders
 * @property {Array} app_home_load_provider_series
 * @property {Array} app_home_load_episode_history
 * @property {String} app_series_tmdb
 */

/**
 *  @typedef EpisodeHistoryResult
 * @type {Object}
 * @property {string} status
 * @property {string} message
 * @property {string} wrapperContent
 * @property {string} h2Text
 */

/**
 * @typedef ProviderData
 * @type {Object}
 * @property {Array} display_priorities
 * @property {number} display_priority
 * @property {string} logo_path
 * @property {string} provider_name
 * @property {number} provider_id
 * @property {string} logoPath
 * @property {number} id
 * @property {string} name
 */

export class EpisodeHistory {
    constructor() {
        this.select = document.querySelector("#day-count");
    }

    init(globs) {
        this.episodeHistoryUrl = globs.app_home_load_episode_history;

        this.select?.addEventListener("change", () => {
            const dayCount = this.select.value;
            this.setDayCountCookie(dayCount);

            fetch(this.episodeHistoryUrl, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({count: dayCount})
            })
                .then(res => res.json())
                /** @param EpisodeHistoryResult */
                .then(data => {
                    if (data.status === 'success') {
                        const episodeHistoryDiv = document.querySelector("#episode-history");
                        const contentDiv = episodeHistoryDiv.querySelector('.content');
                        contentDiv.innerHTML = data.wrapperContent;
                        const headerDiv = episodeHistoryDiv.parentElement.querySelector('.header');
                        const h2Tag = headerDiv.querySelector('h2');
                        h2Tag.innerText = data.h2Text;
                    } else {
                        console.log(data);
                    }
                })
                .catch(err => console.log(err));
        });
    }

    setDayCountCookie(dayCount) {
        const date = new Date();
        date.setTime(date.getTime() + 365 * 24 * 60 * 60 * 1000);
        document.cookie = "mytvtime_2_day_count=" + dayCount + ";expires=" + date.toUTCString() + ";path=/";
    }
}