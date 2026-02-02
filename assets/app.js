import './bootstrap.js';

import {AdminMovieEdit} from "AdminMovieEdit";
import {AdminPointsOfInterest} from "AdminPointsOfInterest";
import {AdminSeriesUpdates} from "AdminSeriesUpdates";
import {AlbumShow} from "AlbumShow";
import {AverageColor} from 'AverageColor';
import {EpisodeHistory} from "EpisodeHistory";
import {Episode} from "Episode";
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
// import {PosterHover} from 'PosterHover';
import {Profile} from 'Profile';
import {ProviderSelect} from 'ProviderSelect';
import {Season} from 'Season';
import {SeriesStatistics} from "SeriesStatistics";
import {Show} from 'Show';
import {ToolTips} from 'ToolTips';
import {UserList} from "UserList";
import {VideoList} from "VideoList";
import {Video} from 'Video';

window.addEventListener("DOMContentLoaded", () => {

    const navBar = new NavBar();
    const menu = new Menu();
    menu.init();

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
    const previewToggler = document.querySelector(".preview-toggler");
    const toggler = function () {
        if (menu.getPreview()) {
            previewToggler.classList.add("active");
        } else {
            previewToggler.classList.remove("active");
        }
    }
    toggler();
    if (previewToggler) {
        previewToggler.addEventListener("click", (e) => {
            e.preventDefault();
            menu.togglePreview();
            toggler();
        });
    }

    // Flash messages
    const flashMessage = new FlashMessage();

    // Tooltips
    const toolTips = new ToolTips();

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
        const seriesStatistics = new SeriesStatistics();
        const globs = JSON.parse(document.querySelector("#global-data").textContent);
        episodeHistory.init(globs);
        providerSelect.init(globs, toolTips);
        highlightSeries.init(globs);
        seriesStatistics.init();

        new UserList(flashMessage, toolTips, null);
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
    const allMySeries = document.querySelector(".all-my-series");
    if (seriesIndex || seriesToStart || seriesByCountry || allMySeries) {
        const index = new Index();
        const globs = JSON.parse(document.querySelector("#global-data").textContent);
        index.init(globs, menu);
    }
    const seriesShow = document.querySelector(".series-show");
    if (seriesShow) {
        const isSeasonPage = document.querySelector("#series-season");
        let img;
        if (isSeasonPage) {
            img = seriesShow.querySelector(".backdrop")?.querySelector("img");
            if (!img) {
                img = seriesShow.querySelector(".series-back")?.querySelector("img");
            }
            if (!img) {
                img = seriesShow.querySelector(".header-back")?.querySelector("img");
            }
        } else {
            img = seriesShow.querySelector(".poster")?.querySelector("img") ?? seriesShow.querySelector(".backdrop")?.querySelector("img");
        }
        if (img) {
            const averageColor = new AverageColor();
            const color = averageColor.getColor(img);
            const seasonInfosDivs = document.querySelectorAll(".seasons .season .infos");
            const commentDiv = document.querySelector(".episodes-comments");
            const keywordDivs = document.querySelectorAll(".series-show .block-infos .keywords .keyword");
            const hasPoster = seriesShow.querySelector(".poster")?.querySelector("img");
            if (!hasPoster) {
                const body = document.querySelector("body");
                body.style.backgroundImage = "unset";
                body.style.backgroundColor = "oklch(" + color.lch.l / 100 + " " + color.lch.c / 100 + " " + ((color.lch.h+180) % 360) + ")"
            }
            if (color.lch.l > 50) {
                seriesShow.style.color = "hsl(202, 18%, 10%)";
                if (keywordDivs) {
                    keywordDivs.forEach(div => {
                        div.classList.add("dark");
                    });
                }
                if (commentDiv) commentDiv.style.backgroundColor = "hsl(0 0 90% /.25)";
                if (seasonInfosDivs) seasonInfosDivs.forEach(seasonInfosDiv => {
                    seasonInfosDiv.style.color = "hsl(202, 18%, 10%)";
                });
            } else {
                seriesShow.style.color = "hsl(202, 18%, 90%)";
                if (keywordDivs) {
                    keywordDivs.forEach(div => {
                        div.classList.add("light");
                    });
                }
                if (commentDiv) commentDiv.style.backgroundColor = "hsl(0 0 10% /.25)";
                if (seasonInfosDivs) seasonInfosDivs.forEach(seasonInfosDiv => {
                    seasonInfosDiv.style.color = "hsl(202, 18%, 90%)";
                });
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

        const seasonOrderBadge = document.querySelector(".season-order-badge");
        seasonOrderBadge?.addEventListener("click", () => {
            const seasonList = seasonOrderBadge.closest(".content").querySelector(".seasons");
            seasonList.classList.toggle("reverse");
            seasonOrderBadge.classList.toggle("reversed");
            /*if (seasonList.classList.contains("reverse")) {
                const arrowUp = document.querySelector(".svgs #arrow-up").querySelector("svg").cloneNode(true);
                seasonOrderBadge.innerHTML = arrowUp.outerHTML;
            } else {
                const arrowDown = document.querySelector(".svgs #arrow-down").querySelector("svg").cloneNode(true);
                seasonOrderBadge.innerHTML = arrowDown.outerHTML;
            }*/
        });

        if (isSeasonPage) {
            const season = new Season();
            season.init(menu);
        } else {
            new UserList(flashMessage, toolTips, document.querySelectorAll(".action.toggle-bookmark-series"))
        }
    }

    const userLists = document.querySelector(".user-lists");
    if (userLists) {
        new UserList(flashMessage, toolTips, null);
    }

    const userSeriesShow = document.querySelector(".user-series-show");
    if (userSeriesShow) {
        const show = new Show();
        show.init(menu);
    }

    const episodeShow = document.querySelector(".episode-show");
    if (episodeShow) {
        const episode = new Episode(flashMessage, toolTips, menu);
        episode.init();
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
        new MovieIndex(globs, menu);
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
        const video = new Video();
        video.init();
    }

    const videosPage = document.querySelector(".videos-page");
    if (videosPage) {
        const videoList = new VideoList();
        videoList.init();
    }
});
