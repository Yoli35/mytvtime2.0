import {AddCast} from 'AddCast';
import {CopyName} from "CopyName";
import {Diaporama} from 'Diaporama';
import {FlashMessage} from "FlashMessage";
import {Keyword} from 'Keyword';
import {Location} from "Location";
import {ToolTips} from 'ToolTips';
import {TranslationsForms} from "TranslationsForms";
import {WatchLinkCrud} from "WatchLinkCrud";

let self = null;

export class Show {
    /**
     * @typedef Crud
     * @type {Object}
     * @property {string} create
     * @property {string} read
     * @property {string} update
     * @property {string} delete
     */

    /**
     * @typedef Api
     * @type {Object}
     * @property {Crud} directLinkCrud
     */

    /** @typedef FilmingLocationImage
     * @type {Object}
     * @property {number} id
     * @property {number} filming_location_id
     * @property {string} path
     */

    /** @typedef FilmingLocation
     * @type {Object}
     * @property {number} id
     * @property {number} is_series
     * @property {number} tmdb_id
     * @property {string} title
     * @property {number} season_number
     * @property {number} episode_number
     * @property {string} location
     * @property {string} description
     * @property {number} latitude
     * @property {number} longitude
     * @property {number} radius
     * @property {number} still_id
     * @property {string} source_name
     * @property {string} source_url
     * @property {string} uuid
     * @property {string} still_path
     * @property {Array.<FilmingLocationImage>} filmingLocationImages
     */

    /**
     * @typedef Globs
     * @type {Object}
     * @property {number} seriesId
     * @property {number} userSeriesId
     * @property {Array} providers
     * @property {Array} translations
     * @property {Api} api
     */

    /**
     * @typedef GlobsMap
     * @type {Object}
     * @property {Array.<FilmingLocation>} locations
     * @property {Array} bounds
     * @property {Array} emptyLocation
     * @property {Array} fieldList
     * @property {string} locationImagePath
     * @property {string} poiImagePath
     */
    /**
     * @typedef Source
     * @type {Object}
     * @property {string} name
     * @property {string} path
     * @property {string} logoPath
     */

    /**
     * @typedef Provider
     * @type {Object}
     * @property {string} id
     * @property {string} name
     * @property {string} logoPath
     */
    /**
     * @typedef Link
     * @type {Object}
     * @property {string} id
     * @property {string} name
     * @property {Provider} provider
     * @property {string} url
     */

    constructor() {
        self = this;
        this.lang = document.documentElement.lang;
        this.toolTips = new ToolTips();
        this.flashMessage = new FlashMessage();
    }

    init(menu) {
        this.menu = menu;
        /** @var {Globs} */
        const jsonGlobsObject = JSON.parse(document.querySelector('div#globs').textContent);
        // const svgs = document.querySelector('div#svgs');
        const providers = jsonGlobsObject.providers;
        const seriesId = jsonGlobsObject.seriesId;
        const seriesName = document.querySelector('span.localization-span, span.name-span').textContent;//jsonGlobsObject.seriesName;
        const userSeriesId = jsonGlobsObject.userSeriesId;
        const translations = jsonGlobsObject.translations;
        const api = jsonGlobsObject.api;

        /** var{GlobsMap} */
        const jsonGlobsMap = JSON.parse(document.querySelector('div#globs-map').textContent);
        const fieldList = jsonGlobsMap.fieldList;
        const emptyLocation = jsonGlobsMap.emptyLocation;
        const filmingLocations = jsonGlobsMap.locations;
        const locationImagePath = jsonGlobsMap.locationImagePath;
        console.log(jsonGlobsMap);

        const previousSeries = document.querySelector('.previous-series');
        const nextSeries = document.querySelector('.next-series');
        document.addEventListener('keyup', function (event) {
            // "<" Arrow: Previous series
            if (event.key === "<" && previousSeries) {
                previousSeries.click();
            }
            // ">" Arrow: Next series
            if (event.key === ">" && nextSeries) {
                nextSeries.click();
            }
        });

        /******************************************************************************
         * Animation for the progress bar.                                            *
         ******************************************************************************/
        const progressDiv = document.querySelector('.progress');
        if (progressDiv) {
            const progressBarDiv = document.querySelector('.progress-bar');
            const progress = progressDiv.getAttribute('data-value');
            progressBarDiv.classList.add('set');
            progressBarDiv.style.width = progress + '%';
            progressBarDiv.setAttribute('aria-valuenow', progress);
            if (progress === "100") {
                setTimeout(() => {
                    progressDiv.classList.add('full');
                }, 1000);
            }
        }

        /******************************************************************************
         * Add a copy badge to the name and localized name.                           *
         ******************************************************************************/
        new CopyName(document.querySelector('.header .name h1'));

        /******************************************************************************
         * Fetch localized season overviews.                                          *
         ******************************************************************************/
        const seasonDivs = document.querySelectorAll('.seasons .season');
        this.fetchSeasonOverviews(seriesId, seasonDivs, 0, seasonDivs.length);

        /******************************************************************************
         * Fetch episode stills for each season.                                      *
         ******************************************************************************/
        const episodeCardDivs = document.querySelectorAll('.episode__cards');
        this.fetchEpisodeCards(episodeCardDivs, 0, episodeCardDivs.length);

        /******************************************************************************
         * Old series added?                                                          *
         ******************************************************************************/
        const oldSeriesAddedPanel = document.querySelector('.old-series-added-panel');
        if (oldSeriesAddedPanel) {
            const noThanksButton = oldSeriesAddedPanel.querySelector('button[name="no-thanks"]');
            const yesButton = oldSeriesAddedPanel.querySelector('button[name="yes"]');
            noThanksButton.addEventListener('click', function () {
                oldSeriesAddedPanel.classList.remove("open");
                setTimeout(function () {
                    oldSeriesAddedPanel.remove();
                }, 300);
            });
            yesButton.addEventListener('click', function () {
                fetch('/' + lang + '/series/old/' + seriesId,
                    {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        }
                    }
                ).then(function (response) {
                    if (response.ok) {
                        oldSeriesAddedPanel.classList.remove("open");
                        setTimeout(function () {
                            // Get the url without ? and everything after
                            window.location.href = window.location.href.split('?')[0];
                        }, 1000);
                    }
                });
            });
        }

        /******************************************************************************
         * Diaporama for posters, backdrops and logos                                 *
         ******************************************************************************/
        const diaporama = new Diaporama();
        const posters = document.querySelector('.posters')?.querySelectorAll('img');
        const backdrops = document.querySelector('.backdrops')?.querySelectorAll('img');
        const logos = document.querySelector('.logos')?.querySelectorAll('img');
        // const locationImages = document.querySelector('.locations')?.querySelectorAll('img');
        const locationDBImages = document.querySelectorAll('.image.db');
        diaporama.start(posters);
        diaporama.start(backdrops);
        diaporama.start(logos);
        // diaporama.start(locationImages);
        locationDBImages.forEach(function (locationDBImage) {
            const list = locationDBImage.querySelector('.list');
            if (list) {
                const images = list.querySelectorAll('img');
                diaporama.start(images);
                const image = locationDBImage.querySelector('img');
                diaporama.enable(image);
            } else {
                diaporama.start(locationDBImage.querySelectorAll('img'));
            }
        });

        /******************************************************************************
         * Hide votes when the mouse leaves season div.                               *
         ******************************************************************************/
        if (seasonDivs) {
            seasonDivs.forEach(function (seasonDiv) {
                seasonDiv.addEventListener('mouseleave', function () {
                    const userVotesDiv = seasonDiv.querySelector('.user-votes');
                    if (userVotesDiv) {
                        userVotesDiv.classList.remove("show");
                    }
                });
            });
        }

        /******************************************************************************
         * Remaining time when the schedule is present.                                   *
         ******************************************************************************/
        const remainingDivs = document.querySelectorAll('.remaining');
        remainingDivs.forEach(function (remaining) {
            const span1 = remaining.querySelector('span:first-child');
            const span1Value = span1.textContent;
            const span2 = remaining.querySelector('span:last-child');
            const targetTS = remaining.getAttribute('data-target-ts') * 1000;
            const seasonCompleted = remaining.getAttribute('data-season-completed');
            const upToDate = remaining.getAttribute('data-up-to-date');
            /*const interval = */
            setInterval(() => {
                const now = (new Date().getTime());
                const distance = targetTS - now;
                const distanceAbs = Math.abs(distance);
                const d = Math.floor(distanceAbs / (1000 * 60 * 60 * 24));
                const h = /*(d === 1 ? 24 : 0) +*/ Math.floor((distanceAbs % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const m = Math.floor((distanceAbs % (1000 * 60 * 60)) / (1000 * 60));
                const s = Math.floor((distanceAbs % (1000 * 60)) / 1000);
                const days = d ? (" " + d + " " + translations[d > 1 ? 'days' : 'day'] + " " + translations['and'] + " ") : "";
                const hours = (h < 10 ? "0" : "") + h + ":";
                const minutes = (m < 10 ? "0" : "") + m + ":";
                const secondes = (s < 10 ? "0" : "") + s;
                const elapsedTime = '<code>' + hours + minutes + secondes + '</code>';

                const airDate = new Date(targetTS);
                const currentDate = new Date();

                const airDay = airDate.getTime();
                const currentDay = currentDate.getTime();
                const airDayOfMonth = airDate.getDate();
                const currentDayOfMonth = currentDate.getDate();

                // Si la date est dépassée de moins d'une heure, on arrête le compte à rebours
                if (distance < 0) {
                    if (distanceAbs < 1000 * 3600) {
                        span1.innerHTML = translations["Now"];
                    } else {
                        if (seasonCompleted) {
                            span1.innerHTML = translations["Season completed"];
                        }
                        if (upToDate) {
                            span1.innerHTML = translations["Up to date"];
                        }
                        if (!seasonCompleted && !upToDate) {
                            span1.innerHTML = span1Value + ', ' + translations["available"];
                        }
                    }
                    span2.innerHTML = translations["since"] + " " + (d ? (days + "<br>") : "") + elapsedTime;
                } else {
                    let dayPart; // today, tomorrow, after tomorrow, x days
                    let day = Math.floor((airDay - currentDay) / (1000 * 3600 * 24));
                    if (day === 0) {
                        if (airDayOfMonth === currentDayOfMonth) {
                            dayPart = translations["Today"] + "<br>";
                        } else {
                            dayPart = translations["Tomorrow"] + "<br>";
                        }
                    } else if (day === 1) {
                        if (currentDayOfMonth - airDayOfMonth === 1) {
                            dayPart = translations["Tomorrow"];
                        } else {
                            dayPart = translations["After tomorrow"] + "<br>";
                        }
                    } else if (day === 2) {
                        if (currentDayOfMonth - airDayOfMonth === 2) {
                            dayPart = translations["After tomorrow"] + "<br>";
                        } else {
                            dayPart = "";/*d + " " + translations['days'];*/
                        }
                    } else {
                        dayPart = "";/*d + " " + translations['days'];*/
                    }
                    span2.innerHTML = dayPart + (d ? (days + "<br>") : "") + elapsedTime;
                }
            }, 1000);
        });

        /******************************************************************************
         * User's actions: rating, pinned, favorite, remove this series.              *
         ******************************************************************************/
        const userActions = document.querySelector('.user-actions');
        const lang = document.documentElement.lang;
        if (userActions) {
            const stars = userActions.querySelectorAll('.star');
            stars.forEach(function (star) {
                star.addEventListener('click', function () {
                    const active = this.classList.contains('active');
                    const value = active ? 0 : this.getAttribute('data-value');
                    fetch('/api/series/user/rating/' + userSeriesId,
                        {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({rating: value})
                        }
                    ).then(function (response) {
                        if (response.ok) {
                            const transRating = stars[0].parentElement.getAttribute('data-trans-rating').split('|')[lang === 'en' ? 0 : 1];
                            const transStar = stars[0].parentElement.getAttribute('data-trans-star').split('|')[lang === 'en' ? 0 : 1];
                            const transStars = stars[0].parentElement.getAttribute('data-trans-stars').split('|')[lang === 'en' ? 0 : 1];
                            stars.forEach(function (star) {
                                star.classList.remove('active');
                                if (star.getAttribute('data-value') <= value) {
                                    star.setAttribute('data-title', transRating);
                                } else {
                                    const v = star.getAttribute('data-value');
                                    star.setAttribute('data-title', v + ' ' + (v > 1 ? transStars : transStar));
                                }
                            });
                            for (let i = 0; i < value; i++) {
                                stars[i].classList.add('active');
                            }
                        }
                    });
                });
            });

            const pinned = userActions.querySelector('.toggle-pinned-series');
            pinned.addEventListener('click', function () {
                const isPinned = this.classList.contains('pinned');
                fetch('/api/series/user/pinned/' + userSeriesId,
                    {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({newStatus: isPinned ? 0 : 1})
                    }
                ).then(function (response) {
                    if (response.ok) {
                        window.location.reload();
                    }
                });
            });

            const favorite = userActions.querySelector('.toggle-favorite-series');
            favorite.addEventListener('click', function () {
                const isFavorite = this.classList.contains('favorite');
                fetch('/api/series/user/favorite/' + userSeriesId,
                    {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({favorite: isFavorite ? 0 : 1})
                    }
                ).then(function (response) {
                    if (response.ok) {
                        favorite.classList.toggle('favorite');
                        if (favorite.classList.contains('favorite')) {
                            favorite.setAttribute('data-title', translations['Remove from favorites']);
                        } else {
                            favorite.setAttribute('data-title', translations['Add to favorites']);
                        }
                    }
                });
            });

            const removeThisSeries = userActions.querySelector('.remove-this-series');
            removeThisSeries.addEventListener('click', function () {
                fetch('/api/series/user/remove/' + userSeriesId,
                    {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        }
                    }
                ).then(function (response) {
                    if (response.ok) {
                        const tmdbId = removeThisSeries.getAttribute('data-tmdb-id');
                        const slug = removeThisSeries.getAttribute('data-slug');
                        window.location.href = '/' + lang + '/series/tmdb/' + tmdbId + '-' + slug;
                    }
                });
            });
        }

        /******************************************************************************
         * Watch links: add.                                                          *
         ******************************************************************************/
        if (userActions) {
            new WatchLinkCrud({'mediaType': 'series', 'mediaId': seriesId, 'api': api, 'providers': providers, 'translations': translations})
        }

        /******************************************************************************
         * Menu to add a localized name or an overview and additional overview.       *
         ******************************************************************************/
        new TranslationsForms(seriesId, 'series', translations);

        /******************************************************************************
         * Keyword translation.                                                       *
         ******************************************************************************/
        new Keyword('series');

        /******************************************************************************
         * User votes on season divs.                                                 *
         ******************************************************************************/
        const showUserTabs = document.querySelectorAll('.show-tab');
        showUserTabs.forEach(div => {
            div.addEventListener('click', e => {
                e.preventDefault();
                const currentTab = e.currentTarget;
                const parent = currentTab.parentElement;
                if (parent.classList.contains('show')) {
                    showUserTabs.forEach(tab => {
                        tab.classList.remove('d-none');
                    });
                    parent.classList.remove('show');
                } else {
                    showUserTabs.forEach(tab => {
                        tab.parentElement.classList.remove('show');
                        tab.classList.add('d-none');
                    });
                    parent.classList.add('show');
                    currentTab.classList.remove('d-none');
                }
            });
        });

        /******************************************************************************
         * mapbox gl                                                                  *
         ******************************************************************************/
        const data = {
            translations: translations,
            locations: filmingLocations,
            emptyLocation: emptyLocation,
            imagePath: locationImagePath,
            seriesId: seriesId,
            seriesName: seriesName
        };
        new Location('loc', data, fieldList);

        /******************************************************************************
         + Add all backdrops and posters from TMDB to the series                      *
         ******************************************************************************/
        const addAllBackdropsButton = document.querySelector('.add-all-backdrops');
        const addAllBackdropsDialog = document.querySelector('.add-all-backdrops-dialog');
        const h3 = addAllBackdropsDialog.querySelector('h3');
        const addAllBackdropsCancelButton = addAllBackdropsDialog.querySelector('button[name="cancel"]');
        const addAllBackdropsAddButton = addAllBackdropsDialog.querySelector('button[name="add"]');
        const tmdbId = addAllBackdropsButton.dataset.seriesId;
        const addBackdropSeriesName = addAllBackdropsButton.dataset.seriesName;
        const addBackdropDialog = document.querySelector('.add-backdrop-dialog');
        const addBackdropButton = document.querySelector('.add-backdrop');
        const addBackdropCancelButton = addBackdropDialog.querySelector('button[name="cancel"]');
        const wrapper = addAllBackdropsDialog.querySelector('.all-images');
        let addAllBackdrops = [];
        let addAllPosters = [];

        addAllBackdropsButton.addEventListener('click', () => {
            fetch('/' + lang + '/series/backdrops/get/' + tmdbId, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        addAllBackdrops = data['backdrops'];
                        const backdropUrl = data['backdropUrl'];
                        addAllBackdrops.forEach((backdrop, index) => {
                            const backdropElement = document.createElement('div');
                            backdropElement.classList.add('backdrop-item');
                            const imgElement = document.createElement('img');
                            imgElement.src = backdropUrl + backdrop['file_path'];
                            imgElement.alt = `Backdrop #${index + 1} - ${addBackdropSeriesName}`;
                            backdropElement.setAttribute('data-title', `Poster #${index + 1} - ${addBackdropSeriesName}`);
                            backdropElement.appendChild(imgElement);
                            wrapper.appendChild(backdropElement);
                        });
                        addAllPosters = data['posters'];
                        const posterUrl = data['posterUrl'];
                        addAllPosters.forEach((poster, index) => {
                            const posterElement = document.createElement('div');
                            posterElement.classList.add('poster-item');
                            const imgElement = document.createElement('img');
                            imgElement.src = posterUrl + poster['file_path'];
                            imgElement.alt = `Poster #${index + 1} - ${addBackdropSeriesName}`;
                            posterElement.setAttribute('title', `Poster #${index + 1} - ${addBackdropSeriesName}`);
                            posterElement.appendChild(imgElement);
                            wrapper.appendChild(posterElement);
                        });
                        h3.querySelector('.poster-count').innerText = addAllPosters.length;
                        h3.querySelector('.backdrop-count').innerText = addAllBackdrops.length;
                        addAllBackdropsDialog.showModal();
                    }
                });
        });
        addAllBackdropsCancelButton.addEventListener('click', () => {
            addAllBackdrops = [];
            addAllPosters = [];
            wrapper.innerHTML = '';
            addAllBackdropsDialog.close();
        });
        addAllBackdropsAddButton.addEventListener('click', () => {
            self.fetchSeriesImages(addAllBackdropsDialog, tmdbId, addAllBackdrops, addAllPosters);
        });

        addBackdropButton.addEventListener('click', () => {
            addBackdropDialog.showModal();
        });
        addBackdropCancelButton.addEventListener('click', () => {
            addBackdropDialog.close();
        });

        /******************************************************************************
         + Add a YouTube video to the series                                          *
         ******************************************************************************/
        const addVideoButton = document.querySelector('.add-video');
        const addVideoDialog = document.querySelector('.add-video-dialog');
        const addVideoCancelButton = addVideoDialog.querySelector('button[name="cancel"]');

        addVideoButton.addEventListener('click', () => {
            addVideoDialog.showModal();
        });
        addVideoCancelButton.addEventListener('click', () => {
            addVideoDialog.close();
        });

        /******************************************************************************
         * Add a person to the cast - Search input                                    *
         ******************************************************************************/
        const addCast = new AddCast();
        addCast.init(menu, this.toolTips, this.flashMessage);
    }

    fetchSeasonOverviews(seriesId, seasonDivs, index, length) {
        if (!length) return;
        const seasonDiv = seasonDivs.item(index);
        const seasonNumber = seasonDiv.getAttribute('data-season-number');

        fetch('/api/season/overview/get/' + seriesId + '/' + seasonNumber, {method: 'GET'})
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const infosDiv = seasonDiv.querySelector(".infos");
                const seasonEpisodesDiv = seasonDiv.querySelector(".season__episodes");
                const overviewDiv = document.createElement("div");
                overviewDiv.classList.add('season__overview');
                overviewDiv.innerText = data['overview'];
                infosDiv.insertBefore(overviewDiv, seasonEpisodesDiv);
            }
            index++;
            if (index<length) self.fetchSeasonOverviews(seriesId, seasonDivs, index, length);
        })
    }

    fetchEpisodeCards(cards, index, length) {
        if (!length) return;
        const episodeCardDiv = cards.item(index);
        const id = episodeCardDiv.getAttribute('data-id');
        const tmdbId = episodeCardDiv.getAttribute('data-tmdb-id');
        const seasonNumber = episodeCardDiv.getAttribute('data-season-number');
        const seriesSlug = episodeCardDiv.getAttribute('data-series-slug');
        fetch('/api/series/season/episode/stills/' + id + '/' + tmdbId + '/' + seasonNumber + '/' + seriesSlug, {method: 'GET'})
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                episodeCardDiv.innerHTML = data['episodeCards'];
                self.toolTips.init(episodeCardDiv);
                index++;
                if (index < length) self.fetchEpisodeCards(cards, index, length);
            })
            .catch(err => console.log(err));
    }

    fetchSeriesImages(dialog, tmdbId, backdrops, posters) {
        if (backdrops.length + posters.length < 20) {
            const data = {
                seriesId: tmdbId,
                method: 'all',
                backdrops: backdrops,
                posters: posters
            };
            fetch('/' + self.lang + '/series/backdrops/add', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(data)
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        dialog.close();
                        window.location.reload();
                    }
                });
        } else {
            const progressBarDiv = dialog.querySelector('.progress-bar');
            const progressDiv = document.createElement('div');
            const progressSpan = document.createElement('span');
            progressDiv.classList.add('progress');
            progressBarDiv.appendChild(progressDiv);
            progressDiv.appendChild(progressSpan);
            const images = [];
            backdrops.forEach(backdrop => {
                images.push({type: 'backdrop', image: backdrop});
            });
            posters.forEach(poster => {
                images.push({type: 'poster', image: poster});
            });
            self.totalFetches = images.length;
            self.fetchSeriesImage(tmdbId, dialog, images, progressDiv, progressSpan);

        }
    }

    fetchSeriesImage(tmdbId, dialog, images, progressDiv, progressSpan) {
        if (!images.length) {
            dialog.close();
            window.location.reload();
            return
        }
        const item = images.shift();
        const image = item.image;
        const type = item.type;
        const data = {
            seriesId: tmdbId,
            method: 'image',
            image: image,
            type: type
        };
        fetch('/' + self.lang + '/series/backdrops/add', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(data)
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const progress = 100 * (self.totalFetches - images.length) / self.totalFetches;
                    progressDiv.style.width = progress + '%';
                    progressSpan.innerText = Math.ceil(progress) + '%';
                    self.fetchSeriesImage(tmdbId, dialog, images, progressDiv, progressSpan);
                }
            });
    }

    displayForm(form) {
        form.classList.add('display');
        setTimeout(function () {
            form.classList.add('active');
        }, 0);
    }

    hideForm(form) {
        form.classList.remove('active');
        setTimeout(function () {
            form.classList.remove('display');
        }, 300);
    }
}
