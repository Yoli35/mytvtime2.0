import './bootstrap.js';

import {AdminMovieEdit} from "AdminMovieEdit";
import {AdminPointsOfInterest} from "AdminPointsOfInterest";
import {AdminSeriesUpdates} from "AdminSeriesUpdates";
import {AlbumShow} from "AlbumShow";
import {AverageColor} from 'AverageColor';
import {EpisodeHistory} from "EpisodeHistory";
import {FlashMessage} from 'FlashMessage';
import {HighlightSeries} from 'HighlightSeries';
import {Index} from 'Index';
import {Menu} from 'Menu';
import {Map} from 'Map';
import {Movie} from 'Movie';
import {MovieIndex} from 'MovieIndex';
import {NavBar} from 'NavBar';
import {NetworkAndProvider} from "NetworkAndProvider";
import {PeopleShow} from "PeopleShow";
import {PeopleStar} from "PeopleStar";
import {Photos} from 'Photos';
import {PosterHover} from 'PosterHover';
import {Profile} from 'Profile';
import {ProviderSelect} from 'ProviderSelect';
import {Season} from 'Season';
import {Show} from 'Show';
import {ToolTips} from 'ToolTips';
// import {TranslationsForms} from "TranslationsForms";
import {Videos} from 'Videos';

// new ResizeObserver(entries => {
//     entries.forEach( (entry) =>{
//     console.log({entry});
//     });
//     drawBodyBackground(canvas);
// }).observe(document.body);

// const canvas = initBodyBackground();
// new ResizeObserver(() => {
//     drawBodyBackground(canvas);
// }).observe(document.body);
//
// function initBodyBackground()
// {
//     const body = document.querySelector("body");
//     const canvas = document.createElement("canvas");
//     canvas.height = window.innerHeight;
//     canvas.width = window.innerWidth;
//     canvas.style.position = "fixed";
//     canvas.style.top = "0";
//     canvas.style.left = "0";
//     canvas.style.zIndex = "-1";
//     canvas.style.pointerEvents = "none";
//     body.appendChild(canvas);
//
//     return canvas;
// }
// function drawBodyBackground(canvas)
// {
//     let ctx = canvas.getContext("2d");
//     const scaleFactor = backingScale(ctx);
//     const height = window.innerHeight;
//     const width = window.innerWidth;
//     const bodyVisibleHeight = height * scaleFactor;
//     const bodyVisibleWidth = width * scaleFactor;
//     if (scaleFactor > 1) {
//         ctx = canvas.getContext("2d", { alpha: false });
//     }
//
//     canvas.height = height;
//     canvas.width = width;
//     ctx.fillStyle = "#1E1E22";
//     ctx.fillRect(0, 0, bodyVisibleWidth, bodyVisibleHeight);
//
//     ctx.beginPath();
//     const grad1 = ctx.createRadialGradient(3 * width / 4, height / 4, 0, 3 * width / 4, height / 4, width / 3);
//     grad1.addColorStop(0, "#5f6668");
//     grad1.addColorStop(1, "#1E1E22");
//     ctx.fillStyle = grad1;
//     ctx.arc(3 * width / 4, height / 4, width / 3, 0, 2 * Math.PI);
//     ctx.fill();
//     ctx.beginPath();
//     const grad2 = ctx.createRadialGradient(width / 4, 3 * height / 4, 0, width / 4, 3 * height / 4, width / 3);
//     grad2.addColorStop(0, "#5f6668");
//     grad2.addColorStop(1, "#1E1E22");
//     ctx.fillStyle = grad2;
//     ctx.arc(width / 4, 3 * height / 4, width / 3, 0, 2 * Math.PI);
//     ctx.fill();
//     // ctx.beginPath();
//     // const grad = ctx.createRadialGradient(50, 50, 5, 50, 50, 50);
//     // grad.addColorStop(0, '#5f6668');
//     // grad.addColorStop(1, '#1e1e22');
//     // ctx.fillStyle = grad;
//     // ctx.arc(50, 50, 50, 0, 2 * Math.PI);
//     // ctx.fill();
// }
//
// function backingScale() {
//     if ('devicePixelRatio' in window) {
//         if (window.devicePixelRatio > 1) {
//             return window.devicePixelRatio;
//         }
//     }
//     return 1;
// }
window.addEventListener("DOMContentLoaded", () => {

    const toTop = document.querySelector(".to-top");
    if (toTop) {
        toTop.addEventListener("click", () => {
            window.scrollTo({
                top: 0,
                behavior: "smooth"
            });
        });
        window.addEventListener("scroll", () => {
            if (window.scrollY > 100) {
                toTop.classList.add("show");
            } else {
                toTop.classList.remove("show");
            }
        });
    }

    const navBar = new NavBar();
    const menu = new Menu();
    menu.init();

// Flash messages
    new FlashMessage();

    // Tooltips
    new ToolTips();

// Poster hover option
    const posterHover = new PosterHover();
    posterHover.init();

// Admin page
    const admin = document.querySelector(".admin");
    const adminMovieEditDiv = admin?.querySelector(".admin__movie__edit");
    const adminSeriesEditDiv = admin?.querySelector(".admin__series__edit");
    const adminSeriesUpdatesDiv = admin?.querySelector(".admin__series__updates");
    const adminPointsOfInterest = admin?.querySelector(".admin__points_of_interest");
    if (adminMovieEditDiv || adminSeriesEditDiv) {
        new AdminMovieEdit();
    }
    if (adminSeriesUpdatesDiv) {
        new AdminSeriesUpdates();
    }
    if (adminPointsOfInterest) {
        new AdminPointsOfInterest();
    }

// Home page
    if (document.querySelector(".home")) {
        const episodeHistory = new EpisodeHistory();
        const providerSelect = new ProviderSelect();
        const highlightSeries = new HighlightSeries();
        const globs = JSON.parse(document.querySelector("#global-data").textContent);
        episodeHistory.init(globs);
        providerSelect.init(globs);
        highlightSeries.init(globs);

    }

// Profile page
    const userProfile = document.querySelector(".user-profile");
    if (userProfile) {
        new Profile();
    }

    const importMap = document.querySelector(".import-map");
    if (importMap) {
        console.log("Import map");
        new Map({cooperativeGesturesOption: false});
    }

// Series & season page
    const seriesIndex = document.querySelector(".series-index");
    const seriesToStart = document.querySelector(".series-to-start");
    const seriesByCountry = document.querySelector(".series-by-country");
    if (seriesIndex || seriesToStart || seriesByCountry) {
        const index = new Index();
        const globs = JSON.parse(document.querySelector("#global-data").textContent);
        index.init(globs);
    }
    const seriesShow = document.querySelector(".series-show");
    if (seriesShow) {
        const isSeasonPage = document.querySelector("#series-season");
        let img;
        if (isSeasonPage) {
            img = seriesShow.querySelector(".backdrop").querySelector("img");
            if (!img) {
                img = seriesShow.querySelector(".series-back").querySelector("img");
            }
            if (!img) {
                img = seriesShow.querySelector(".header-back").querySelector("img");
            }
        } else {
            img = seriesShow.querySelector(".poster")?.querySelector("img") ?? seriesShow.querySelector(".backdrop")?.querySelector("img");
        }
        if (img) {
            const averageColor = new AverageColor();
            const color = averageColor.getColor(img);
            console.log({color})
            if (color.lightness > 180) {
                seriesShow.style.color = "hsl(202, 18%, 10%)"
            } else {
                seriesShow.style.color = "hsl(202, 18%, 90%)"
            }
            const hsl = averageColor.rgbToHsl(color);
            hsl.l *= .8;
            // hsl.s *= 1.25;
            hsl.s = 20;
            if (hsl.l > 100) {
                hsl.l = 100;
            }
            navBar.navBarColor(hsl);
        }

        const seasonOrder = document.querySelector(".season-order");
        seasonOrder?.addEventListener("click", () => {
            const seasonList = seasonOrder.closest(".content").querySelector(".seasons");
            seasonList.classList.toggle("reverse");
            if (seasonList.classList.contains("reverse")) {
                const arrowUp = document.querySelector(".svgs #arrow-up").querySelector("svg").cloneNode(true);
                seasonOrder.innerHTML = arrowUp.outerHTML;
            } else {
                const arrowDown = document.querySelector(".svgs #arrow-down").querySelector("svg").cloneNode(true);
                seasonOrder.innerHTML = arrowDown.outerHTML;
            }
        });

        const seasonPage = document.querySelector("#series-season");
        if (seasonPage) {
            const season = new Season();
            season.init();
        }
    }

    const userSeriesShow = document.querySelector(".user-series-show");
    if (userSeriesShow) {
        new Show();
    }

    const userAlbumShow = document.querySelector(".album-page");
    if (userAlbumShow) {
        new AlbumShow();
    }

    const photosPage = document.querySelector(".photos-page");
    if (photosPage) {
        new Photos();
    }

    const movieIndex = document.querySelector(".movie-index");
    if (movieIndex) {
        const globs = JSON.parse(document.querySelector("#globs").textContent);
        new MovieIndex(globs);
    }

    const movieShow = document.querySelector(".movie-show");
    if (movieShow) {
        new Movie();

        const img = movieShow.querySelector(".poster").querySelector("img") ?? movieShow.querySelector(".backdrop").querySelector("img");
        if (img) {
            const averageColor = new AverageColor();
            const color = averageColor.getColor(img);
            const hsl = averageColor.rgbToHsl(color);
            hsl.l *= .8;
            hsl.s *= 1.25;
            if (hsl.l > 100) {
                hsl.l = 100;
            }
            navBar.navBarColor(hsl);
        }
    }

    const person = document.querySelector(".person");
    if (person) {
        new PeopleShow();
    }

    const peopleStar = document.querySelector(".people-star");
    if (peopleStar) {
        new PeopleStar();
    }

    const seriesMap = document.querySelector(".series-map");
    if (seriesMap && !seriesShow) {
        navBar.navBarColor({h: 32, s: 76, l: 30});
    }

    const networkAndProvider = document.querySelector(".user-providers");
    if (networkAndProvider) {
        const networkPage = document.querySelector(".user-networks");
        new NetworkAndProvider(networkPage != null);
    }

    const videoPage = document.querySelector(".video-page");
    if (videoPage) {
        const videos = new Videos();
        videos.init();
    }
});
