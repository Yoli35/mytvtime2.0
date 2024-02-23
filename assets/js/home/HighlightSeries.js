import {AverageColor} from "../images/AverageColor.js";
import {ToolTips} from "../ToolTips.js";

/**
 *  @typedef Globs
 * @type {Object}
 * @property {Array} highlightedSeries
 * @property {String} app_series_tmdb
 */

export class HighlightSeries {
    constructor() {
        this.series = [];
        this.count = 0;
        this.highlightDiv = null;
        this.intervalDuration = 20000;
        this.toolTips = new ToolTips();
    }

    /** @param {Globs} globs */
    init(globs) {
        this.series = globs.highlightedSeries;
        this.app_series_tmdb = globs.app_series_tmdb;
        this.count = this.series.length;
        this.highlightDiv = document.querySelector(".highlighted-series");
        this.averageColor = new AverageColor();

        this.displaySeries();
    }

    displaySeries() {
        let lastSeriesIndex = Math.floor(Math.random() * this.count);

        this.setSeries(lastSeriesIndex);
        setInterval(() => {
            this.highlightDiv.querySelector('.poster').classList.remove('show');
            this.highlightDiv.querySelector('.details').classList.remove('show');
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
        const aPoster = poster.querySelector("a");
        const nameDiv = this.highlightDiv.querySelector(".name");
        const aDetails = this.highlightDiv.querySelector(".details").querySelector("a");
        const overviewDiv = this.highlightDiv.querySelector(".overview");
        const providerDiv = this.highlightDiv.querySelector(".providers").querySelector(".wrapper");
        const series = this.series[index];
        const link = this.app_series_tmdb + series['id'] + "-" + series['slug'];
        posterImg.src = series['poster_path'];
        aPoster.href = link;
        posterImg.alt = series['name'];
        aDetails.href = link;
        posterImg.title = series['name'];
        nameDiv.textContent = series['name'] + " (" + series['year'] + ")";
        nameDiv.setAttribute('data-title', series['date']);
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
        this.toolTips.init(this.highlightDiv, "highlight");
        posterImg.onload = () => {
            const color = this.averageColor.getColor(posterImg);
            const hsl = this.averageColor.rgbToHsl(color);
            const toolTips = document.querySelector(".tool-tips.highlight");
            if (toolTips) {
                toolTips.style.setProperty("--tooltips-bg", "hsl(" + hsl.h + ", " + hsl.s + "%, 40%)");
                toolTips.style.setProperty("--tooltips-color", "hsl(" + hsl.h + ", " + hsl.s + "%, 80%)");
            }
            this.highlightDiv.style.setProperty("--highlight-bg", "hsl(" + ((hsl.h + 180) % 360) + ", " + hsl.s + "%, " + hsl.l + "%)");
            this.highlightDiv.style.setProperty("--highlight-color", "hsl(" + ((hsl.h + 180) % 360) + ", " + hsl.s + "%, " + (hsl.l > 60 ? "10" : "90") + "%)");
            this.highlightDiv.querySelector('.poster').classList.add('show');
            this.highlightDiv.querySelector('.details').classList.add('show');
        };
    }
}