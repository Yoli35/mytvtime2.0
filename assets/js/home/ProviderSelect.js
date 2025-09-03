/**
 * @typedef Globs
 * @type {Object}
 * @property {Array} highlightedSeries
 * @property {Array} watchProviders
 * @property {Array} app_home_load_provider_series
 * @property {String} app_series_tmdb
 */

/**
 *  @typedef ProviderSeriesResult
 * @type {Object}
 * @property {string} status
 * @property {string} message
 * @property {string} wrapperContent
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

export class ProviderSelect {
    constructor() {
        this.select = document.querySelector("#watch-providers");
    }

    init(globs) {
        this.providerSeriesUrl = globs.app_home_load_provider_series;
        this.providers = globs.watchProviders;

        this.select?.addEventListener("change", () => {
            const provider = this.select.value;
            this.setProviderCookie(provider);

            fetch(this.providerSeriesUrl, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({provider: provider})
            })
                .then(res => res.json())
                /* @param ProviderSeriesResult */
                .then(data => {
                    if (data.status === 'success') {
                        const providerSeriesDiv = document.querySelector("#provider-series");
                        const contentDiv = providerSeriesDiv.querySelector('.content');
                        contentDiv.innerHTML = data.wrapperContent;
                        /** @type ProviderData */
                        const providerData = this.providers.find((p) => p.provider_id === parseInt(provider));
                        const headerDiv = providerSeriesDiv.parentElement.querySelector('.header');
                        const logoImg = headerDiv.querySelector('.logo').querySelector('img');
                        logoImg.src = providerData.logoPath;
                        logoImg.alt = providerData.name;
                        logoImg.title = providerData.name;
                        const h2Tag = headerDiv.querySelector('h2');
                        h2Tag.innerText = providerData.name;
                    } else {
                        console.log(data);
                    }
                })
                .catch(err => console.log(err));
        });
    }


    getProvider(id) {
        return this.providers.find((provider) => provider.id === id);
    }

    // Save the selected provider in a cookie to keep it on the next visit
    setProviderCookie(provider) {
        const date = new Date();
        date.setTime(date.getTime() + 365 * 24 * 60 * 60 * 1000);
        document.cookie = "mytvtime_2_provider=" + provider + ";expires=" + date.toUTCString() + ";path=/";
    }
}