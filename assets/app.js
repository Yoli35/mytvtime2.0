import './bootstrap.js';

import {Menu} from './js/Menu.js';
import {ToolTips} from './js/ToolTips.js';
import {ProviderSelect} from './js/home/ProviderSelect.js';
import {HighlightSeries} from './js/home/HighlightSeries.js';
import {PosterHover} from './js/images/PosterHover.js';
import {AverageColor} from './js/images/AverageColor.js';
import {Season} from './js/series/Season.js';

const menu = new Menu();
menu.init();

// Tooltips
const toolTips = new ToolTips();
toolTips.init();

// Poster hover option
const posterHover = new PosterHover();
posterHover.init();

// Home page
if (document.querySelector(".home")) {
    const providerSelect = new ProviderSelect();
    const highlightSeries = new HighlightSeries();
    const globs = JSON.parse(document.querySelector("#global-data").textContent);
    providerSelect.init();
    highlightSeries.init(globs);
}

// Series & season page
const seriesShow = document.querySelector(".series-show");
if (seriesShow) {
    // const navbar = document.querySelector(".navbar");
    const navbarLinks = document.querySelectorAll(".navbar a");
    const footer = document.querySelector(".home-footer");
    const infos = seriesShow.querySelector(".infos");
    const averageColor = new AverageColor();
    const img = seriesShow.querySelector(".poster").querySelector("img") ?? seriesShow.querySelector(".backdrop").querySelector("img");
    const color = averageColor.getColor(img);
    if (color.lightness > 150) {
        infos.style.color = "#101010";
    } else {
        infos.style.color = "#f5f5f5";
    }
    const hsl = averageColor.rgbToHsl(color);
    hsl.l *= .8;
    hsl.s *= 1.25;
    if (hsl.l > 100) {
        hsl.l = 100;
    }

    const root = document.documentElement;
    root.style.setProperty("--navbar-bg", "hsl(" + hsl.h + ", " + hsl.s + "%, " + (hsl.l - 10) + "%)");
    root.style.setProperty("--navbar-bg-50", "hsla(" + hsl.h + ", " + hsl.s + "%, " + hsl.l + "%, .5)");
    root.style.setProperty("--navbar-bg-75", "hsla(" + hsl.h + ", " + hsl.s + "%, " + hsl.l + "%, .75)");

    if (hsl.l > 50) {
        navbarLinks.forEach(link => {
            link.classList.add("dark");
        });
        footer.classList.add("dark");
    }

    const additionalOverviews = document.querySelector(".additional.overviews");
    if (additionalOverviews) {
        const imgs = additionalOverviews.querySelectorAll("img");
        imgs.forEach(img => {
            const color = averageColor.getColor(img);
            const sourceDiv = img.closest(".source");
            if (color.lightness > 150) {
                // hsl(202,18%,10%)
                sourceDiv.style.backgroundColor = "#151B1E";
            } else {
                // hsl(202,18%,90%);
                sourceDiv.style.backgroundColor = "#E1E7EA";
            }
        });
    }

    const seasonPage = document.querySelector("#series-season");
    if (seasonPage) {
        const season = new Season();
        season.init();
    }
}

// Flash messages
window.addEventListener("DOMContentLoaded", () => {
    const flashes = document.querySelectorAll(".flash-message");

    flashes.forEach(flash => {
        flash.querySelector(".close").addEventListener("click", () => {
            closeFlash(flash);
        });
    });

    function closeFlash(flash) {
        setTimeout(() => {
            flash.classList.add("hide");
        }, 0);
        setTimeout(() => {
            flash.classList.add("d-none");
            flash.parentElement.removeChild(flash);
        }, 500);
    }
});
