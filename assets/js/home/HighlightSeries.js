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
        this.seriesIndexes = [];
        this.seriesIndex = 0;
        this.maxDisplayPerPoster = 1;
        this.timeToChangeSeries = false;
        this.interval = null;
        this.count = 0;
        this.fetchCount = 0;
        this.root = null;
        this.highlightDiv = null;
        this.highlightProgressDiv = null;
        this.posterListDiv = null;
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
        this.posterListDiv = document.querySelector(".poster-list");
        this.loadingDiv = document.querySelector(".loading");
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
        this.series.forEach((series) => {
            series['thumb'] = this.highlightDiv.querySelector("#thumb-" + series['id']);
            series['countDiv'] = series['thumb'].parentElement.querySelector(".count");
            series['count'] = 0;
        });
        for (let i = 0; i < this.count; i++) {
            this.seriesIndexes.push(i);
        }

        this.seriesIndex = Math.floor(Math.random() * this.count);
        this.setSeries(this.seriesIndex);
        this.interval = setInterval(this.cycle.bind(this), this.intervalDuration);
    }

    cycle() {
        this.highlightDiv.querySelector('.poster').classList.remove('show');
        this.highlightDiv.querySelector('.details').classList.remove('show');
        this.highlightProgressDiv.classList.remove('show');
        if (this.timeToChangeSeries) {
            clearInterval(this.interval);
            this.loadNewSeries();
            return;
        }
        setTimeout(() => {
            this.series[this.seriesIndex]['thumb'].classList.remove('active');
            this.seriesIndex = this.seriesIndexes[Math.floor(Math.random() * this.seriesIndexes.length)];
            this.setSeries(this.seriesIndex);
        }, this.transition);
    }

    setSeries() {
        const poster = this.highlightDiv.querySelector(".poster");
        const posterImg = poster.querySelector("img");
        const aPoster = poster.querySelector("a");
        const nameDiv = this.highlightDiv.querySelector(".name");
        const aDetails = this.highlightDiv.querySelector(".details").querySelector("a");
        const overviewDiv = this.highlightDiv.querySelector(".overview");
        const providerDiv = this.highlightDiv.querySelector(".providers").querySelector(".wrapper");
        const series = this.series[this.seriesIndex];
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

        this.series[this.seriesIndex]['thumb'].classList.add('active');
        const div = document.createElement("div");
        this.series[this.seriesIndex]['countDiv'].appendChild(div);
        this.series[this.seriesIndex]['count']++;

        if (this.series[this.seriesIndex]['count'] >= this.maxDisplayPerPoster) {
            let index = this.seriesIndexes.indexOf(this.seriesIndex);
            this.seriesIndexes.splice(index, 1);
            console.log(this.seriesIndexes);
            this.timeToChangeSeries = this.seriesIndexes.length === 0;
        }
    }

    loadNewSeries() {
        this.loadingDiv.classList.add('show');
        fetch('/load-new-series', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            }
        }).then(response => {
            return response.json();
        }).then(data => {
            if (data['status'] === 'success') {
                this.posterListDiv.replaceChildren();
                this.series = data['series'];
                this.count = this.series.length;
                for (let i = 0; i < this.count; i++) {
                    this.seriesIndexes.push(i);
                }
                this.series.forEach((series) => {
                    const a = document.createElement("a");
                    const div = document.createElement("div");
                    const thumb = document.createElement("div");
                    const img = document.createElement("img");
                    const count = document.createElement("div");
                    a.href = this.app_series_tmdb + series['id'] + "-" + series['slug'];
                    div.classList.add('item');
                    thumb.classList.add('poster-item');
                    thumb.id = "thumb-" + series['id'];
                    img.src = series['poster_path'];
                    img.alt = series['name'];
                    count.classList.add('count');
                    thumb.appendChild(img);
                    div.appendChild(thumb);
                    div.appendChild(count);
                    a.appendChild(div);
                    this.posterListDiv.appendChild(a);
                    series['thumb'] = this.highlightDiv.querySelector("#thumb-" + series['id']);
                    series['countDiv'] = series['thumb'].parentElement.querySelector(".count");
                    series['count'] = 0;
                });
                this.fetchCount++;
                const div = document.createElement("div");
                div.classList.add('item');
                div.classList.add('counter');
                const counter = document.createElement("div");
                counter.innerText = this.fetchCount;
                div.appendChild(counter);
                this.posterListDiv.insertBefore(div, this.posterListDiv.firstChild);
                this.loadingDiv.classList.remove('show');

                this.seriesIndex = Math.floor(Math.random() * this.count);
                this.setSeries(this.seriesIndex);
                this.interval = setInterval(this.cycle.bind(this), this.intervalDuration);
            }
        });
    }
}