import './bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.scss';

import {Menu} from "./js/Menu.js";

const menu = new Menu();
menu.init();

import {ToolTips} from "./js/ToolTips.js";

const toolTips = new ToolTips();
toolTips.init();

import {ProviderSelect} from "./js/home/ProviderSelect.js";
import {HighlightSeries} from "./js/home/HighlightSeries.js";

if (document.querySelector(".home")) {
    const providerSelect = new ProviderSelect();
    const highlightSeries = new HighlightSeries();
    const globs = JSON.parse(document.querySelector("#global-data").textContent);
    providerSelect.init();
    highlightSeries.init(globs);
}

import {PosterHover} from "./js/images/PosterHover.js";

const posterHover = new PosterHover();
posterHover.init();

import {AverageColor} from "./js/images/AverageColor.js";

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
}

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
