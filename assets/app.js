import './bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.scss';

import {PosterHover} from "./js/images/PosterHover.js";
const posterHover = new PosterHover();
posterHover.init();

import {AverageColor} from "./js/images/AverageColor.js";
const seriesShow = document.querySelector(".series-show");
if (seriesShow) {
    const infos = seriesShow.querySelector(".infos");
    const averageColor = new AverageColor();
    const img = seriesShow.querySelector(".backdrop").querySelector("img");
    const color = averageColor.getColor(img);
    if (color.lightness > 150) {
        infos.style.color = "#101010";
    } else {
        infos.style.color= "#f5f5f5";
    }
    // infos.innerHTML += "<div class='color' style='background-color: rgb(" + color.r + ", " + color.g + ", " + color.b + ")'></div>";
}