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
        this.root = null;
        this.highlightDiv = null;
        this.highlightProgressDiv = null;
        this.intervalDuration = 20000;
        this.transition = 300;
        this.toolTips = new ToolTips();
    }

    /** @param {Globs} globs */
    init(globs) {
        this.series = globs.highlightedSeries;
        this.app_series_tmdb = globs.app_series_tmdb;
        this.count = this.series.length;
        this.root = document.documentElement;
        this.home = document.querySelector(".home");
        this.highlightDiv = document.querySelector(".highlighted-series");
        this.highlightProgressDiv = document.querySelector(".highlight-progress");
        this.averageColor = new AverageColor();
        let duration = this.root.style.getPropertyValue("--highlight-duration");
        let transition = this.root.style.getPropertyValue("--highlight-transition");
        if (duration.length) { // s
            this.intervalDuration = duration.slice(0, -1) * 1000;
        }
        if (transition.length) { // ms
            this.transition = transition.slice(0, -2) * 1;
        }

        this.displaySeries();
    }

    displaySeries() {
        this.series.forEach((series, index) => {
            series['thumb'] = this.highlightDiv.querySelector("#thumb-" + series['id']);
        });

        let lastSeriesIndex = Math.floor(Math.random() * this.count);
        this.setSeries(lastSeriesIndex);
        setInterval(() => {
            this.highlightDiv.querySelector('.poster').classList.remove('show');
            this.highlightDiv.querySelector('.details').classList.remove('show');
            this.highlightProgressDiv.classList.remove('show');
            setTimeout(() => {
                let seriesIndex = Math.floor(Math.random() * this.count);
                while (seriesIndex === lastSeriesIndex) {
                    seriesIndex = Math.floor(Math.random() * this.count);
                }
                this.series[lastSeriesIndex]['thumb'].classList.remove('active');
                lastSeriesIndex = seriesIndex;
                this.setSeries(lastSeriesIndex);
            }, this.transition);
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
                this.root.style.setProperty("--tooltips-bg", "hsl(" + hsl.h + ", " + hsl.s + "%, 50%)");
                this.root.style.setProperty("--tooltips-color", "hsl(" + hsl.h + ", " + hsl.s + "%, 80%)");
            }
            this.root.style.setProperty("--highlight-progress", "hsl(" + ((hsl.h + 180) % 360) + ", " + hsl.s + "%, " + (hsl.l < 95 ? hsl.l + 5 : 100) + "%)");
            this.root.style.setProperty("--highlight-head", "hsl(" + ((hsl.h + 180) % 360) + ", " + hsl.s + "%, " + (hsl.l < 75 ? hsl.l + 25 : 100) + "%)");
            this.root.style.setProperty("--highlight-bg", "hsl(" + ((hsl.h + 180) % 360) + ", " + hsl.s + "%, " + hsl.l + "%)");
            this.root.style.setProperty("--highlight-color", "hsl(" + ((hsl.h + 180) % 360) + ", " + hsl.s + "%, " + (hsl.l > 60 ? "10" : "90") + "%)");
            this.highlightDiv.querySelector('.poster').classList.add('show');
            this.highlightDiv.querySelector('.details').classList.add('show');
            this.highlightProgressDiv.classList.add('show');
        };

        this.series[index]['thumb'].classList.add('active');
    }
}