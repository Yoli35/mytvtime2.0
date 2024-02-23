/**
 *  @typedef Globs
 * @type {Object}
 * @property {Array} highlightedSeries
 */

export class HighlightSeries {
    constructor() {
        this.series = [];
        this.count = 0;
        this.highlightDiv = null;
        this.intervalDuration = 20000;
    }

    /** @param {Globs} globs */
    init(globs) {
        this.series = globs.highlightedSeries;
        this.count = this.series.length;
        this.highlightDiv = document.querySelector(".highlighted-series");

        this.displaySeries();
    }

    displaySeries() {
        let lastSeriesIndex = Math.floor(Math.random() * this.count);

        this.setSeries(lastSeriesIndex);
        setInterval(() => {
            this.highlightDiv.classList.remove('show');
            setTimeout(() => {
                let seriesIndex = Math.floor(Math.random() * this.count);
                while (seriesIndex === lastSeriesIndex) {
                    seriesIndex = Math.floor(Math.random() * this.count);
                }
                lastSeriesIndex = seriesIndex;
                this.setSeries(lastSeriesIndex);
            }, 600);
        }, this.intervalDuration);
    }

    setSeries(index) {
        const poster = this.highlightDiv.querySelector(".poster");
        const posterImg = poster.querySelector("img");
        const nameDiv = this.highlightDiv.querySelector(".name");
        const overviewDiv = this.highlightDiv.querySelector(".overview");
        const providerDiv = this.highlightDiv.querySelector(".providers").querySelector(".wrapper");
        const series = this.series[index];
        posterImg.src = series['poster_path'];
        posterImg.alt = series['name'];
        posterImg.title = series['name'];
        nameDiv.textContent = series['name'];
        overviewDiv.textContent = series['overview'];
        providerDiv.innerHTML = "";
        series['watch_providers'].forEach(provider => {
            const div = document.createElement("div");
            const img = document.createElement("img");
            const name = document.createElement("div");
            div.classList.add('provider');
            img.src = provider['logo_path'];
            img.alt = provider['provider_name'];
            img.title = provider['provider_name'];
            name.innerText = provider['provider_name'];
            name.classList.add('name');
            div.appendChild(img);
            div.appendChild(name);
            providerDiv.appendChild(div);
        });
        posterImg.onload = () => {
            this.highlightDiv.classList.add('show');
        };
    }
}