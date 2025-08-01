import {Diaporama} from 'Diaporama';
import {FlashMessage} from "FlashMessage";
import {Keyword} from 'Keyword';
import {Map} from "Map";
import {ToolTips} from 'ToolTips';

let gThis = null;

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
     * @property {Array.<FilmingLocation>} locations
     * @property {Api} api
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
        gThis = this;
        this.toolTips = new ToolTips();
        this.flashMessage = new FlashMessage();
        this.init();
    }

    init() {
        /** @var {Globs} */
        const jsonGlobsObject = JSON.parse(document.querySelector('div#globs').textContent);
        const svgs = document.querySelector('div#svgs');
        const providers = jsonGlobsObject.providers;
        const seriesId = jsonGlobsObject.seriesId;
        const seriesName = document.querySelector('span.localized-name-span, span.name-span').textContent;//jsonGlobsObject.seriesName;
        const userSeriesId = jsonGlobsObject.userSeriesId;
        const translations = jsonGlobsObject.translations;
        const api = jsonGlobsObject.api;
        /*console.log({api});*/

        this.filmingLocations = jsonGlobsObject.locations;
        console.log({filmingLocations: this.filmingLocations});
        const jsonGlobsMap = JSON.parse(document.querySelector('div#globs-map').textContent);
        this.fieldList = jsonGlobsMap.fieldList;
        console.log({fieldList: this.fieldList});

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
         * Animation for the progress bar                                             *
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
         * Remaining time when schedule is present                                    *
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
         * Alternate schedule : l'épisode avec la date de diffusion la plus proche de *
         * maintenant doit être visible.                                              *
         ******************************************************************************/
        /*const alternateSchedules = document.querySelectorAll('.alternate-schedule');
        alternateSchedules.forEach(function (alternateSchedule) {
            const firstFutureAirDay = alternateSchedule.querySelector('.future.air-day');
            if (firstFutureAirDay){
                firstFutureAirDay.scrollIntoView({behavior: 'smooth', block: 'center'});
            } else {
                const lastWatchedAirDay = alternateSchedule.querySelector('.air-day.watched:last-of-type');
                if (lastWatchedAirDay) {
                    lastWatchedAirDay.scrollIntoView({behavior: 'smooth', block: 'center'});
                }
            }
        });*/

        /******************************************************************************
         * User's actions: rating, pinned, favorite, remove this series               *
         ******************************************************************************/
        const userActions = document.querySelector('.user-actions');
        const lang = document.documentElement.lang;
        if (userActions) {
            const stars = userActions.querySelectorAll('.star');
            stars.forEach(function (star) {
                star.addEventListener('click', function () {
                    const active = this.classList.contains('active');
                    const value = active ? 0 : this.getAttribute('data-value');
                    fetch('/' + lang + '/series/rating/' + userSeriesId,
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
                fetch('/' + lang + '/series/pinned/' + userSeriesId,
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
                fetch('/' + lang + '/series/favorite/' + userSeriesId,
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
                fetch('/' + lang + '/series/remove/' + userSeriesId,
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
        const watchLinks = document.querySelectorAll('.watch-link');
        const addWatchLink = document.querySelector('.add-watch-link');
        const watchLinkForm = document.querySelector('.watch-link-form');
        const watchLinkFormProvider = watchLinkForm.querySelector('#provider');
        const watchLinkFormName = watchLinkForm.querySelector('#name');
        const watchLinkFormUrl = watchLinkForm.querySelector('#url');
        const watchLinkFormSaisonNumber = watchLinkForm.querySelector('#season-number');
        const watchLinkFormType = watchLinkForm.querySelector('#crud-type');
        const watchLinkFormId = watchLinkForm.querySelector('#crud-id');
        const form = document.querySelector('#watch-link-form');
        const providerSelect = form.querySelector('#provider');
        const watchLinkFormCancel = form.querySelector('button[type="button"]');
        const watchLinkFormSubmit = form.querySelector('button[type="submit"]');

        addWatchLink.addEventListener('click', function () {
            watchLinkFormType.value = 'create';
            watchLinkFormSubmit.classList.remove('delete');
            watchLinkFormSubmit.textContent = translations['Add'];
            watchLinkFormId.value = "";
            watchLinkFormProvider.value = "";
            watchLinkFormName.value = "";
            watchLinkFormUrl.value = "";
            watchLinkFormSaisonNumber.value = "-1";
            gThis.displayForm(watchLinkForm);
        });

        watchLinks.forEach(function (watchLink) {
            const tools = watchLink.querySelector('.watch-link-tools');
            const href = watchLink.querySelector('a').getAttribute('href');
            const edit = tools.querySelector('.watch-link-tool.edit');
            const copy = tools.querySelector('.watch-link-tool.copy');
            const del = tools.querySelector('.watch-link-tool.delete');
            const id = tools.getAttribute('data-id');
            const provider = tools.getAttribute('data-provider');
            const name = tools.getAttribute('data-name');
            const seasonNumber = tools.getAttribute('data-season-number');

            edit.addEventListener('click', function () {
                watchLinkFormType.value = 'update';
                watchLinkFormSubmit.classList.remove('delete');
                watchLinkFormSubmit.textContent = translations['Edit'];
                watchLinkFormId.value = id;
                watchLinkFormProvider.value = provider;
                watchLinkFormName.value = name;
                watchLinkFormUrl.value = href;
                watchLinkFormSaisonNumber.value = seasonNumber;
                gThis.displayForm(watchLinkForm);
            });

            copy.addEventListener('click', function () {
                navigator.clipboard.writeText(href).then(function () {
                    copy.classList.add('copied');
                    setTimeout(function () {
                        copy.classList.remove('copied');
                    }, 1000);
                });
            });

            del.addEventListener('click', function () {
                watchLinkFormType.value = 'delete';
                watchLinkFormSubmit.classList.add('delete');
                watchLinkFormSubmit.textContent = translations['Delete'];
                watchLinkFormId.value = id;
                watchLinkFormProvider.value = provider;
                watchLinkFormName.value = name;
                watchLinkFormUrl.value = href;
                gThis.displayForm(watchLinkForm);
            });
        });

        providerSelect.addEventListener('change', function () {
            const provider = this.value;
            if (provider) {
                const name = form.querySelector('#name');
                name.value = translations['Watch on'] + ' ' + providers.names[provider];
            }
        });
        watchLinkFormCancel.addEventListener('click', function () {
            gThis.hideForm(watchLinkForm);
        });
        watchLinkFormSubmit.addEventListener('click', function (event) {
            event.preventDefault();

            const provider = form.querySelector('#provider');
            const name = form.querySelector('#name');
            const url = form.querySelector('#url');
            const seasonNumber = form.querySelector('#season-number');
            const type = form.querySelector('#crud-type');
            const errors = form.querySelectorAll('.error');
            errors.forEach(function (error) {
                error.textContent = '';
            });
            if (!provider.value) {
                provider.value = null;
            }
            if (!name.value) {
                name.nextElementSibling.textContent = gThis.translations['This field is required'];
                return;
            }
            if (!url.value) {
                url.nextElementSibling.textContent = gThis.translations['This field is required'];
                return;
            }
            if (name.value && url.value) {
                let apiUrl;
                if (type.value === 'create') {
                    apiUrl = api.directLinkCrud.create;
                }
                if (type.value === 'update') {
                    apiUrl = api.directLinkCrud.update + watchLinkFormId.value;
                }
                if (type.value === 'delete') {
                    apiUrl = api.directLinkCrud.delete + watchLinkFormId.value;
                }
                fetch(apiUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({seriesId: seriesId, provider: provider.value, name: name.value, url: url.value, seasonNumber: seasonNumber.value})
                    }
                ).then(async function (response) {
                    if (response.ok) {
                        const data = await response.json();
                        console.log({data});
                        gThis.hideForm(watchLinkForm);
                        const watchLinksDiv = document.querySelector('.watch-links');
                        if (type.value === 'create') {
                            /** @var {Link} link */
                            const link = data.link;
                            console.log({link});
                            const newWatchLinkDiv = document.createElement('div');
                            newWatchLinkDiv.classList.add('watch-link');
                            newWatchLinkDiv.setAttribute('data-id', link.id);
                            const newLink = document.createElement('a');
                            newLink.href = link.url;
                            newLink.target = '_blank';
                            newLink.rel = 'noopener noreferrer';
                            if (link.provider.logoPath) {
                                const watchLink = document.createElement('div');
                                watchLink.classList.add('watch-link');
                                const img = document.createElement('img');
                                img.src = link.provider.logoPath; //providers.logos[provider.value];
                                img.alt = link.provider.name; //providers.names[provider.value];
                                img.setAttribute('data-title', link.name);
                                watchLink.appendChild(img);
                                newLink.appendChild(watchLink);
                            } else {
                                const watchLink = document.createElement('div');
                                watchLink.classList.add('watch-link');
                                const span = document.createElement('span');
                                span.textContent = link.name;
                                watchLink.appendChild(span);
                                newLink.appendChild(watchLink);
                            }
                            const watchLinkTools = document.createElement('div');
                            watchLinkTools.classList.add('watch-link-tools');
                            watchLinkTools.setAttribute('data-id', link.id);
                            watchLinkTools.setAttribute('data-provider', link.provider.id);
                            watchLinkTools.setAttribute('data-name', link.name);
                            const edit = document.createElement('div');
                            edit.classList.add('watch-link-tool');
                            edit.classList.add('edit');
                            edit.setAttribute('data-title', translations['Edit this watch link']);
                            const editIcon = svgs.querySelector('.svg#pen').querySelector('svg').cloneNode(true);
                            edit.addEventListener('click', function () {
                                watchLinkFormType.value = 'update';
                                watchLinkFormSubmit.classList.remove('delete');
                                watchLinkFormSubmit.textContent = translations['Edit'];
                                watchLinkFormId.value = link.id;
                                watchLinkFormProvider.value = link.provider.id;
                                watchLinkFormName.value = link.name;
                                watchLinkFormUrl.value = link.url;
                                gThis.displayForm(watchLinkForm);
                            });
                            edit.appendChild(editIcon);
                            watchLinkTools.appendChild(edit);
                            const nameDiv = document.createElement('div');
                            nameDiv.classList.add('watch-link-name');
                            nameDiv.textContent = link.name;
                            watchLinkTools.appendChild(nameDiv);
                            const del = document.createElement('div');
                            del.classList.add('watch-link-tool');
                            del.classList.add('delete');
                            del.setAttribute('data-title', translations['Delete this watch link']);
                            const delIcon = svgs.querySelector('.svg#trash').querySelector('svg').cloneNode(true);
                            del.appendChild(delIcon);
                            del.addEventListener('click', function () {
                                watchLinkFormType.value = 'delete';
                                watchLinkFormSubmit.classList.add('delete');
                                watchLinkFormSubmit.textContent = translations['Delete'];
                                watchLinkFormId.value = link.id;
                                watchLinkFormProvider.value = link.provider.id;
                                watchLinkFormName.value = link.name;
                                watchLinkFormUrl.value = link.url;
                                gThis.displayForm(watchLinkForm);
                            });
                            watchLinkTools.appendChild(del);
                            gThis.toolTips.init(watchLinkTools);

                            newWatchLinkDiv.appendChild(newLink);
                            newWatchLinkDiv.appendChild(watchLinkTools);
                            watchLinksDiv.insertBefore(newWatchLinkDiv, watchLinksDiv.lastElementChild);
                        }
                        if (type.value === 'update') {
                            const watchLink = document.querySelector('.watch-link[data-id="' + watchLinkFormId.value + '"]');
                            const hasImg = watchLink.querySelector('img');
                            const hasSpan = watchLink.querySelector('span');
                            if (provider.value) {
                                if (hasImg) {
                                    const img = watchLink.querySelector('img');
                                    img.src = providers.logos[provider.value];
                                    img.alt = providers.names[provider.value];
                                    img.setAttribute('data-title', name.value);
                                }
                                if (hasSpan) {
                                    const img = document.createElement('img');
                                    img.src = providers.logos[provider.value];
                                    img.alt = providers.names[provider.value];
                                    img.setAttribute('data-title', name.value);
                                    watchLink.appendChild(img);
                                    if (hasSpan) {
                                        hasSpan.remove();
                                    }
                                }
                            } else {
                                if (hasSpan) {
                                    hasSpan.textContent = name.value;
                                }
                                if (hasImg) {
                                    hasImg.remove();
                                }
                            }
                        }
                        if (type.value === 'delete') {
                            const watchLink = document.querySelector('.watch-link[data-id="' + watchLinkFormId.value + '"]');
                            watchLink.remove();
                        }

                        provider.value = '';
                        name.value = '';
                        url.value = '';
                    }
                });
            }
        });

        /******************************************************************************
         * Menu to add a localized name or an overview and additional overview        *
         ******************************************************************************/
        const seriesToolsClick = document.querySelector('.series-tools-click');
        const seriesToolsMenu = document.querySelector('.series-tools-menu');
        seriesToolsClick.addEventListener('click', function () {
            seriesToolsMenu.classList.toggle('active');
        });

        const seriesToolsLocalizedName = document.querySelector('#localized-name');
        const seriesToolsLocalizedOverview = document.querySelector('#localized-overview');
        const seriesToolsAdditionalOverview = document.querySelector('#additional-overview');
        const overviews = document.querySelectorAll('.overview');
        const localizedNameForm = document.querySelector('.localized-name-form');
        const overviewForm = document.querySelector('.overview-form');
        const deleteOverviewForm = document.querySelector('.delete-overview-form');
        const lnForm = document.querySelector('#localized-name-form');
        const lnCancel = lnForm.querySelector('button[type="button"]');
        const lnDelete = lnForm.querySelector('button[value="delete"]');
        const lnAdd = lnForm.querySelector('button[value="add"]');
        const ovForm = document.querySelector('#overview-form');
        const ovCancel = ovForm.querySelector('button[type="button"]');
        const ovAdd = ovForm.querySelector('button[value="add"]');
        const deleteOvForm = document.querySelector('#delete-overview-form');
        const deleteOvCancel = deleteOvForm.querySelector('button[type="button"]');
        const deleteOvDelete = deleteOvForm.querySelector('button[value="delete"]');

        seriesToolsLocalizedName.addEventListener('click', function () {
            // localizedNameForm.classList.add('display');
            // setTimeout(function () {
            //     localizedNameForm.classList.add('active');
            // }, 0);
            gThis.displayForm(localizedNameForm);
        });
        lnCancel.addEventListener('click', function () {
            localizedNameForm.classList.remove('active');
            setTimeout(function () {
                localizedNameForm.classList.remove('display');
            }, 300);
        });
        lnDelete?.addEventListener('click', function (event) {
            event.preventDefault();

            fetch('/' + lang + '/series/localized/name/delete/' + seriesId,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({locale: lang})
                }
            ).then(function (response) {
                if (response.ok) {
                    gThis.hideForm(localizedNameForm);
                    const localizedNameSpan = document.querySelector('.localized-name-span');
                    localizedNameSpan.remove();
                }
            });
        });
        lnAdd.addEventListener('click', function (event) {
            event.preventDefault();

            const name = lnForm.querySelector('#name');
            const errors = lnForm.querySelectorAll('.error');
            errors.forEach(function (error) {
                error.textContent = '';
            });
            if (!name.value) {
                name.nextElementSibling.textContent = gThis.translations['This field is required'];
            } else {
                fetch('/' + lang + '/series/localized/name/add/' + seriesId,
                    {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({name: name.value})
                    }
                ).then(function (response) {
                    if (response.ok) {
                        gThis.hideForm(localizedNameForm);
                        const localizedNameSpan = document.querySelector('.localized-name-span');
                        if (localizedNameSpan) {
                            localizedNameSpan.textContent = name.value;
                        } else {
                            const h1 = document.querySelector('h1');
                            const nameSpan = document.querySelector('.name-span');
                            const localizedNameSpan = document.createElement('span');
                            localizedNameSpan.classList.add('localized-name-span');
                            localizedNameSpan.textContent = name.value;
                            h1.insertBefore(localizedNameSpan, nameSpan);
                        }
                    }
                });
            }
        });

        seriesToolsLocalizedOverview.addEventListener('click', function () {
            const firstRow = ovForm.querySelector('.form-row:first-child');
            const hiddenInputTool = ovForm.querySelector('#tool');
            hiddenInputTool.setAttribute('data-type', 'localized');
            hiddenInputTool.setAttribute('data-crud', 'add');
            hiddenInputTool.setAttribute('data-overview-id', '');
            firstRow.classList.add('hide');
            const submitButton = ovForm.querySelector('button[type="submit"]');
            submitButton.textContent = translations['Add'];
            gThis.displayForm(overviewForm);
        });
        seriesToolsAdditionalOverview.addEventListener('click', function () {
            const firstRow = ovForm.querySelector('.form-row:first-child');
            const hiddenInputTool = ovForm.querySelector('#tool');
            hiddenInputTool.setAttribute('data-type', 'additional');
            hiddenInputTool.setAttribute('data-crud', 'add');
            firstRow.classList.remove('hide');
            const submitButton = ovForm.querySelector('button[type="submit"]');
            submitButton.textContent = translations['Add'];
            gThis.displayForm(overviewForm);
        });
        ovCancel.addEventListener('click', function () {
            gThis.hideForm(overviewForm);
        });

        /* Tools for every added overview */
        if (overviews) {
            overviews.forEach(function (overview) {
                const type = overview.classList.contains('localized') ? 'localized' : 'additional';
                const tools = overview.querySelector('.tools');
                const edit = tools.querySelector('.edit');
                const del = tools.querySelector('.delete');
                edit.addEventListener('click', function () {
                    const id = this.getAttribute('data-id');
                    const content = overview.querySelector('.content').getAttribute('data-overview');
                    const form = document.querySelector('.overview-form');
                    const hiddenInputTool = form.querySelector('#tool');
                    const overviewField = form.querySelector('#overview-field');
                    hiddenInputTool.value = id;
                    hiddenInputTool.setAttribute('data-type', type);
                    hiddenInputTool.setAttribute('data-crud', 'edit');
                    hiddenInputTool.setAttribute('data-overview-id', id);
                    overviewField.value = content.trim();
                    const firstRow = form.querySelector('.form-row:first-child');
                    if (type === 'localized') {
                        firstRow.classList.add('hide');
                    } else {
                        firstRow.classList.remove('hide');
                        const select = form.querySelector('#overview-source');
                        const sourceId = overview.getAttribute('data-source-id');
                        if (sourceId) {
                            select.value = sourceId;
                        }
                    }
                    const submitButton = form.querySelector('button[type="submit"]');
                    submitButton.textContent = translations['Edit'];
                    gThis.displayForm(overviewForm);
                });
                del.addEventListener('click', function () {
                    const id = this.getAttribute('data-id');
                    const overviewType = deleteOverviewForm.querySelector('#overview-type');
                    const overviewId = deleteOverviewForm.querySelector('#overview-id');
                    overviewType.value = type;
                    overviewId.value = id;
                    gThis.displayForm(deleteOverviewForm);
                });
            });
        }

        deleteOvCancel.addEventListener('click', function () {
            gThis.hideForm(deleteOverviewForm);
        });
        ovAdd.addEventListener('click', function (event) {
            event.preventDefault();

            const source = ovForm.querySelector('#overview-source');
            const sourceError = source.closest('label').querySelector('.error');
            const overviewField = ovForm.querySelector('#overview-field');
            const overviewError = overviewField.closest('label').querySelector('.error');
            const hiddenInputTool = ovForm.querySelector('#tool');
            const errors = ovForm.querySelectorAll('.error');
            errors.forEach(function (error) {
                error.textContent = '';
            });
            const type = hiddenInputTool.getAttribute('data-type');
            const overviewId = parseInt(hiddenInputTool.getAttribute('data-overview-id'));
            const crud = hiddenInputTool.getAttribute('data-crud');
            const additional = type === 'additional';
            if (additional && !source.value) {
                sourceError.textContent = gThis.translations['This field is required'];
            }
            if (!overviewField.value) {
                overviewError.textContent = gThis.translations['This field is required'];
            }
            let data = {
                overviewId: overviewId,
                source: source.value,
                overview: overviewField.value,
                type: type,
                crud: crud,
                locale: lang
            };

            fetch('/' + lang + '/series/overview/add/edit/' + seriesId, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            }).then(function (response) {
                if (response.ok) {
                    gThis.hideForm(overviewForm);

                    if (crud === 'edit') {
                        const overviewDiv = document.querySelector('.' + type + '.overview[data-id="' + overviewId + '"]');
                        const contentDiv = overviewDiv.querySelector('.content');
                        const newContentText = overviewField.value;
                        contentDiv.setAttribute('data-overview', newContentText);
                        // replace \n by <br>
                        //contentDiv.innerHTML = newContentText.replace(/\n/g, '<br>');
                        contentDiv.textContent = newContentText;

                        /*const toolsDiv = overviewDiv.querySelector('.tools');
                        const sourceDiv = toolsDiv.querySelector('.source');
                        if (source.value) {
                            if (sourceDiv) {
                                const sourceA = sourceDiv.querySelector('a');
                                sourceA.href = source.value;
                                sourceA.setAttribute('data-title', source.value);
                                sourceA.textContent = source.value;
                            } else {
                                const sourceDiv = document.createElement('div');
                                sourceDiv.classList.add('source');
                                const sourceA = document.createElement('a');
                                sourceA.href = source.value;
                                sourceA.setAttribute('data-title', source.value);
                                sourceA.target = '_blank';
                                sourceA.rel = 'noopener noreferrer';
                                sourceA.textContent = source.value;
                                sourceDiv.appendChild(sourceA);
                                toolsDiv.insertBefore(sourceDiv, toolsDiv.firstChild);
                            }
                        } else {
                            if (sourceDiv) {
                                sourceDiv.remove();
                            }
                        }*/
                        return;
                    }

                    // crud: add
                    const infos = document.querySelector('.infos');
                    let h4 = infos.querySelector('.' + type + '-h4'), overviewsDiv;
                    if (!h4) {
                        h4 = document.createElement('h4');
                        h4.classList.add(type + '-h4');
                        h4.textContent = type === 'localized' ? translations['Localized overviews'] : translations['Additional overviews'];
                        infos.appendChild(h4);

                        overviewsDiv = document.createElement('div');
                        overviewsDiv.classList.add(type);
                        overviewsDiv.classList.add('overviews');
                        infos.appendChild(overviewsDiv);
                    }
                    overviewsDiv = infos.querySelector('.' + type + '.overviews');

                    const newId = response.id;
                    /** @type {Source} */
                    const sourceRecord = response.source;

                    const overviewDiv = document.createElement('div');
                    overviewDiv.classList.add(type);
                    overviewDiv.classList.add('overview');
                    const contentDiv = document.createElement('div');
                    contentDiv.classList.add('content');
                    contentDiv.setAttribute('data-overview', overviewField.value);
                    contentDiv.innerHTML = overviewField.value.replace(/\n/g, '<br>');
                    overviewDiv.appendChild(contentDiv);

                    const toolsDiv = document.createElement('div');
                    toolsDiv.classList.add('tools');
                    if (sourceRecord) {
                        const sourceDiv = document.createElement('div');
                        sourceDiv.classList.add('source');
                        if (sourceRecord.path) {
                            const sourceA = document.createElement('a');
                            sourceA.href = sourceRecord.path;
                            sourceA.setAttribute('data-title', sourceRecord.name);
                            sourceA.target = '_blank';
                            sourceA.rel = 'noopener noreferrer';
                            sourceDiv.appendChild(sourceA);
                            if (sourceRecord.logoPath) {
                                const sourceImg = document.createElement('img');
                                sourceImg.src = sourceRecord.logoPath;
                                sourceImg.alt = sourceRecord.name;
                                sourceA.appendChild(sourceImg);
                            } else {
                                sourceA.textContent = sourceRecord.name;
                            }
                        } else {
                            sourceDiv.textContent = sourceRecord.name;
                        }
                        toolsDiv.appendChild(sourceDiv);
                    }
                    const localeDiv = document.createElement('div');
                    localeDiv.classList.add('locale');
                    localeDiv.textContent = lang.toUpperCase();
                    toolsDiv.appendChild(localeDiv);

                    const editDiv = document.createElement('div');
                    editDiv.classList.add('edit');
                    editDiv.setAttribute('data-id', newId);
                    editDiv.setAttribute('data-title', translations['Edit']);
                    const editI = document.createElement('i');
                    editI.classList.add('fas');
                    editI.classList.add('fa-pen');
                    editDiv.appendChild(editI);
                    toolsDiv.appendChild(editDiv);

                    const deleteDiv = document.createElement('div');
                    deleteDiv.classList.add('delete');
                    deleteDiv.setAttribute('data-id', newId);
                    deleteDiv.setAttribute('data-title', translations['Delete']);
                    const deleteI = document.createElement('i');
                    deleteI.classList.add('fas');
                    deleteI.classList.add('fa-trash');
                    deleteDiv.appendChild(deleteI);
                    toolsDiv.appendChild(deleteDiv);

                    overviewDiv.appendChild(toolsDiv);

                    overviewsDiv.appendChild(overviewDiv);
                    gThis.toolTips.init(overviewDiv);

                    overviewField.value = '';
                }
            });
        });
        deleteOvDelete.addEventListener('click', function (event) {
            event.preventDefault();

            const overviewType = deleteOverviewForm.querySelector('#overview-type').value;
            const overviewId = deleteOverviewForm.querySelector('#overview-id').value;
            fetch('/' + lang + '/series/overview/delete/' + overviewId, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({overviewType: overviewType})
            }).then(function (response) {
                if (response.ok) {
                    gThis.hideForm(deleteOverviewForm);
                    const overviewDiv = document.querySelector('.' + overviewType + '.overview[data-id="' + overviewId + '"]');
                    overviewDiv.remove();
                    const localizedOverviewDivs = document.querySelectorAll('.' + overviewType + '.overview');
                    if (localizedOverviewDivs.length === 0) {
                        document.querySelector('.' + overviewType + '-h4').remove();
                        document.querySelector('.' + overviewType + '.overviews').remove();
                    }
                }
            });
        });

        /******************************************************************************
         * Keyword translation                                                        *
         ******************************************************************************/
        new Keyword('series');

        /******************************************************************************
         * mapbox gl                                                                  *
         ******************************************************************************/
        const mapDiv = document.querySelector('.map-controller');
        if (mapDiv) {
            this.map = new Map();
        }

        /******************************************************************************
         * Filming location form                                                      *
         * When call location.js:                                                     *
         *     → new Location(data, fieldList);                                       *
         *     → data: div#globs                                                      *
         *     → fieldList: [                                                         *
         *                   "series-id", "tmdb-id", "crud-type", "crud-id","title",  *
         *                   "episode-number", "season-number",                       *
         *                   "location", "description",                               *
         *                   "latitude", "longitude",                                 *
         *                  ]                                                         *
         ******************************************************************************/
        const seriesMap = document.querySelector('#map');
        const addLocationButton = document.querySelector('.add-location-button');
        const addLocationDialog = document.querySelector('.side-panel.add-location-dialog');
        const addLocationForm = addLocationDialog.querySelector('form');
        const inputGoogleMapsUrl = addLocationForm.querySelector('input[name="google-map-url"]');
        const inputLatitude = addLocationForm.querySelector('input[name="latitude"]');
        const inputLongitude = addLocationForm.querySelector('input[name="longitude"]');
        const addLocationCancel = addLocationForm.querySelector('button[type="button"]');
        const addLocationSubmit = addLocationForm.querySelector('button[type="submit"]');
        const imageInputs = addLocationForm.querySelectorAll('input[type="url"]');
        const submitRow = addLocationForm.querySelector('.form-row.submit-row');
        const scrollDownToSubmitDiv = addLocationDialog.querySelector('.scroll-down-to-submit');
        const scrollDownToSubmitButton = scrollDownToSubmitDiv.querySelector('button');
        console.log({imageInputs});
        // Dev test
        /*const locationInput = addLocationForm.querySelector('input[name="location"]');*/
        /*locationInput.addEventListener('input', function () {
            const location = this.value;
            if (location.length === 4 && location === 'test') {
                const descriptionInput = addLocationForm.querySelector('input[name="description"]');
                descriptionInput.value = 'bla bla bla';
                const latitudeInput = addLocationForm.querySelector('input[name="latitude"]');
                latitudeInput.value = 48.8566;
                const longitudeInput = addLocationForm.querySelector('input[name="longitude"]');
                longitudeInput.value = 2.3522;
            }
        });*/

        // Lorsque le panneau devient trop haut la div "submit-row" disparait.
        // Si la div "submit-row" est hors du cadre, la div "scroll-down-to-submit" apparaît.
        // Si la div "submit-row" est visible, la div "scroll-down-to-submit" disparaît.
        const observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                console.log(entry)
                if (entry.isIntersecting) {
                    scrollDownToSubmitDiv.style.display = 'none';
                } else {
                    scrollDownToSubmitDiv.style.display = 'flex';
                }
            });
        });
        observer.observe(submitRow);
        scrollDownToSubmitButton.addEventListener('click', function () {
            // addLocationDialog > frame > form > submit-row
            // frame overflow-y: auto;
            // faire apparaitre la div "submit-row" dans le cadre
            addLocationDialog.querySelector('.frame').scrollTo(0, submitRow.offsetTop);
        });

        if (seriesMap) {
            const mapViewValue = JSON.parse(seriesMap.getAttribute('data-symfony--ux-leaflet-map--map-view-value'));
            console.log({mapViewValue});

            const locationsDiv = document.querySelector('.temp-locations');
            const imageDivs = locationsDiv.querySelectorAll('.image');
            let imageSrcLists = [];
            let currentImages = [];
            imageDivs.forEach(function (imageDiv, imageDivIndex) {
                const isDB = imageDiv.classList.contains('db');
                const listDiv = imageDiv.querySelector('.list');
                let imageList;
                if (listDiv) {
                    if (isDB) {
                        imageList = listDiv.querySelectorAll('img');
                    } else {
                        imageList = imageDiv.querySelectorAll('img');
                    }
                    imageList = Array.from(imageList);
                    if (imageList.length > 1) {
                        imageSrcLists[imageDivIndex] = imageList.map(function (image) {
                            return {src: image.src};
                        });
                        const imageImg = imageDiv.querySelector('img');
                        const leftArrow = imageDiv.querySelector('.arrow.left');
                        const rightArrow = imageDiv.querySelector('.arrow.right');
                        currentImages[imageDivIndex] = 0;

                        leftArrow.addEventListener('click', function () {
                            const lastIndex = imageSrcLists[imageDivIndex].length - 1;
                            let i = currentImages[imageDivIndex];
                            i = i === 0 ? lastIndex : (i - 1);
                            currentImages[imageDivIndex] = i;
                            imageImg.src = imageSrcLists[imageDivIndex][i].src;
                        });
                        rightArrow.addEventListener('click', function () {
                            const lastIndex = imageSrcLists[imageDivIndex].length - 1;
                            let i = currentImages[imageDivIndex];
                            i = i === lastIndex ? 0 : (i + 1);
                            currentImages[imageDivIndex] = i;
                            imageImg.src = imageSrcLists[imageDivIndex][i].src;
                        });
                    }
                }
                const editButton = imageDiv.querySelector('.edit');
                editButton.addEventListener('click', function () {
                    const locationId = this.getAttribute('data-loc-id');
                    const location = gThis.filmingLocations.find(location => location.id === parseInt(locationId));
                    gThis.openLocationPanel('update', location, translations['Update']);
                });
            });
        }

        addLocationButton.addEventListener('click', function () {
            gThis.openLocationPanel('create', {'title': seriesName}, translations['Add']);
        });
        inputGoogleMapsUrl.addEventListener('paste', function (e) {
            const url = e.clipboardData.getData('text');
            const isGoogleMapsUrl = url.match(/https:\/\/www.google.com\/maps\//);
            let urlParts;
            if (isGoogleMapsUrl) {
                urlParts = url.split('@')[1].split(',');
            } else { // 48.8566,2.3522
                urlParts = url.split(',');
            }
            inputLatitude.value = parseFloat(urlParts[0].trim());
            inputLongitude.value = parseFloat(urlParts[1].trim());
        });
        addLocationCancel.addEventListener('click', function () {
            gThis.closeLocationPanel();
        });
        addLocationSubmit.addEventListener('click', function (event) {
            event.preventDefault();

            const inputs = addLocationForm.querySelectorAll('input[required]');
            const crudTypeInput = addLocationForm.querySelector('input[name="crud-type"]');
            let emptyInput = false;
            if (crudTypeInput.value === 'create') {
                inputs.forEach(function (input) {
                    // la première image ("image-url") est requise, mais peut être remplacée par un fichier (image-file)
                    // en mode création
                    if (input.name === 'image-url') {
                        if (!input.value && !input.closest('.form-row').querySelector('input[name="image-file"]').value) {
                            input.nextElementSibling.textContent = translations['This field is required'];
                            emptyInput = true;
                        } else {
                            input.nextElementSibling.textContent = '';
                        }
                    } else {
                        if (input.required && !input.value) {
                            input.nextElementSibling.textContent = translations['This field is required'];
                            emptyInput = true;
                        } else {
                            input.nextElementSibling.textContent = '';
                        }
                    }
                });
            }
            if (!emptyInput) {
                const formData = gThis.getFormData(addLocationForm, gThis.fieldList);
                fetch('/' + lang + '/series/location/add/' + seriesId,
                    {
                        method: 'POST',
                        body: formData
                    }
                ).then(async function (response) {
                    const data = await response.json();
                    console.log({data});
                    if (response.ok) {
                        window.location.reload();
                    } else {
                        gThis.flashMessage.add('error', data.message);
                    }
                    gThis.closeLocationPanel();
                });
            }
        });

        imageInputs.forEach(function (imageInput) {
            // Les champs de type "url" peuvent être modifiés pour afficher une image
            imageInput.addEventListener('input', function () {
                let validValue = false;
                const path = this.value;
                const img = this.closest('.form-field').querySelector('img');
                // is it a valid url?
                const isUrl = path.match(/https?:\/\/.+\.(jpg|jpeg|png|gif|webp)/);
                if (isUrl) {
                    img.src = path;
                    validValue = true;
                }
                if (this.value.includes('~/')) { // for dev test
                    const filename = path.split('/').pop();
                    // is a valid filename?
                    const isFilename = filename.match(/.+\.jpg|jpeg|png|webp/);
                    if (isFilename) {
                        img.src = this.value.replace('~/', '/images/map/');
                        validValue = true;
                    }
                }
                if (!validValue) {
                    img.src = '';
                }
            });
            // Les champs de type "url" peuvent recevoir un fichier de type image par glisser-déposer
            imageInput.addEventListener('drop', function (e) {
                e.preventDefault();
                const file = e.dataTransfer.files[0];
                const img = this.closest('.form-field').querySelector('img');
                img.src = URL.createObjectURL(file);
                this.value = img.src;
                console.log({file});
                console.log(img.src)
                const blobPreviewDiv = this.closest('.form-field').querySelector('.blob-preview');
                const blobPreview = blobPreviewDiv.querySelector('img');
                previewFile(file, blobPreview);
            });
        });

        // https://developer.mozilla.org/en-US/docs/Web/HTML/Element/input/file
        const imageFile = addLocationForm.querySelector('input[name="image-file"]');
        imageFile.addEventListener("change", updateImageDisplay);
        const imageFiles = addLocationForm.querySelector('input[name="image-files"]');
        imageFiles.addEventListener("change", updateImageDisplay);

        function updateImageDisplay(e) {
            const input = e.target;
            const inputName = input.name;
            const preview = addLocationForm.querySelector('.preview-' + inputName);
            while (preview.firstChild) {
                preview.removeChild(preview.firstChild);
            }
            const curFiles = input.files;
            if (curFiles.length === 0) {
                const div = document.createElement("div");
                div.textContent = "No files currently selected for upload";
                preview.appendChild(div);
                return;
            }

            const list = document.createElement("ol");
            preview.appendChild(list);

            for (const file of curFiles) {
                const listItem = document.createElement("li");
                const div = document.createElement("div");
                if (validFileType(file)) {
                    div.textContent = `${file.name}, ${returnFileSize(file.size)}`;
                    const image = document.createElement("img");
                    image.src = URL.createObjectURL(file);
                    image.alt = image.title = file.name;

                    listItem.appendChild(div);
                    listItem.appendChild(image);
                } else {
                    div.innerHTML = `${file.name}<span class="error">${translations['Not a valid file type. Update your selection']}.</span>`;
                    listItem.appendChild(div);
                }

                list.appendChild(listItem);
            }

        }

        // https://developer.mozilla.org/en-US/docs/Web/Media/Formats/Image_types
        const fileTypes = [
            /* "image/apng",*/
            /* "image/bmp",*/
            /* "image/gif",*/
            "image/jpeg",
            /* "image/pjpeg",*/
            "image/png",
            /* "image/svg+xml",*/
            /* "image/tiff",*/
            "image/webp",
            /* "image/x-icon",*/
            /*"image/heic",*/
        ];

        function validFileType(file) {
            return fileTypes.includes(file.type);
        }

        function returnFileSize(number) {
            if (number < 1e3) {
                return `${number} bytes`;
            } else if (number >= 1e3 && number < 1e6) {
                return `${(number / 1e3).toFixed(1)} KB`;
            } else {
                return `${(number / 1e6).toFixed(1)} MB`;
            }
        }

        function previewFile(file, preview) {
            const reader = new FileReader();

            reader.addEventListener("load", () => {
                preview.src = reader.result;
                console.log({reader});
            }, false);
            if (file) {
                reader.readAsDataURL(file);
            }
        }

        /******************************************************************************
         + Add all backdrop & posters from TMDB to the series                         *
         ******************************************************************************/
        const addAllBackdropsButton = document.querySelector('.add-all-backdrops');
        const addAllBackdropsDialog = document.querySelector('.add-all-backdrops-dialog');
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
                            posterElement.setAttribute('data-title', `Poster #${index + 1} - ${addBackdropSeriesName}`);
                            posterElement.appendChild(imgElement);
                            wrapper.appendChild(posterElement);
                        });
                        gThis.toolTips.init(wrapper);
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
            const data = {
                seriesId: tmdbId,
                backdrops: addAllBackdrops,
                posters: addAllPosters
            };
            fetch('/' + lang + '/series/backdrops/add', {
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
                        addAllBackdropsDialog.close();
                        window.location.reload();
                    }
                });
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
    }

    getFormData(form, list) {
        // const seriesIdInput = form.querySelector('input[name="series-id"]');
        // const tmdbIdInput = form.querySelector('input[name="tmdb-id"]');
        // const crudTypeInput = form.querySelector('input[name="crud-type"]');
        // const crudIdInput = form.querySelector('input[name="crud-id"]');
        // const titleInput = form.querySelector('input[name="title"]');
        // const episodeNumberInput = form.querySelector('input[name="episode-number"]');
        // const seasonNumberInput = form.querySelector('input[name="season-number"]');
        // const locationInput = form.querySelector('input[name="location"]');
        // const descriptionInput = form.querySelector('input[name="description"]');
        const imageUrlInputs = form.querySelectorAll('input[name*="image-url"]');
        const imageFileInput = form.querySelector('input[name="image-file"]');
        const imageFilesInput = form.querySelector('input[name*="image-files"]');
        // const latitudeInput = form.querySelector('input[name="latitude"]');
        // const longitudeInput = form.querySelector('input[name="longitude"]');

        const formData = new FormData();
        list.forEach(function (field) {
            const fieldInput = form.querySelector('input[name="' + field + '"]');
            if (fieldInput) {
                formData.append(field, fieldInput.value);
            }
            const fieldSelect = form.querySelector('select[name="' + field + '"]');
            if (fieldSelect) {
                formData.append(field, fieldSelect.value);
            }
            const fieldTextarea = form.querySelector('textarea[name="' + field + '"]');
            if (fieldTextarea) {
                formData.append(field, fieldTextarea.value);
            }
        });

        imageUrlInputs.forEach(function (input) {
            formData.append(input.name, input.value);
            if (input.value.includes('blob:')) {
                const blobPreviewDiv = input.closest('.form-field').querySelector('.blob-preview');
                const blobPreview = blobPreviewDiv.querySelector('img');
                const file = blobPreview.src;
                formData.append(input.name + '-blob', file);
            }
        });
        if (imageFileInput.files.length)
            formData.append(imageFileInput.name, imageFileInput.files[0]);
        Array.from(imageFilesInput.files).forEach(function (file, index) {
            formData.append('additional-image-' + index, file);
        });

        return formData;
    }

    openLocationPanel(crud, location, buttonText) {
        const addLocationForm = document.querySelector('#add-location-form');
        const addLocationDialog = document.querySelector('.side-panel.add-location-dialog');
        const inputs = addLocationForm.querySelectorAll('input');
        const crudTypeInput = addLocationForm.querySelector('input[name="crud-type"]');
        const crudIdInput = addLocationForm.querySelector('input[name="crud-id"]');
        const titleInput = addLocationForm.querySelector('input[name="title"]');
        const episodeNumberInput = addLocationForm.querySelector('input[name="episode-number"]');
        const seasonNumberInput = addLocationForm.querySelector('input[name="season-number"]');
        const locationInput = addLocationForm.querySelector('input[name="location"]');
        const descriptionTextarea = addLocationForm.querySelector('textarea[name="description"]');
        const latitudeInput = addLocationForm.querySelector('input[name="latitude"]');
        const longitudeInput = addLocationForm.querySelector('input[name="longitude"]');
        const radiusInput = addLocationForm.querySelector('input[name="radius"]');
        const sourceNameInput = addLocationForm.querySelector('input[name="source-name"]');
        const sourceUrlInput = addLocationForm.querySelector('input[name="source-url"]');
        const locationImages = addLocationForm.querySelector(".location-images");
        const additionalImagesDiv = addLocationForm.querySelector('.additional-images');
        const submitButton = addLocationForm.querySelector('button[type="submit"]');

        inputs.forEach(function (input) {
            if (input.getAttribute('type') !== 'hidden') {
                input.value = '';
            }
        });
        titleInput.value = location.title;
        submitButton.textContent = buttonText;
        crudTypeInput.value = crud;
        if (crud === 'create') {
            crudIdInput.value = 0;
            episodeNumberInput.value = '0';
            seasonNumberInput.value = '0';
            locationImages.style.display = 'none';
        } else {
            crudIdInput.value = location.id;
            episodeNumberInput.value = location.episode_number;
            seasonNumberInput.value = location.season_number;
            locationInput.value = location.location;
            latitudeInput.value = location.latitude;
            longitudeInput.value = location.longitude;
            radiusInput.value = location.radius;
            descriptionTextarea.value = location.description;
            sourceNameInput.value = location.source_name;
            sourceUrlInput.value = location.source_url;

            locationImages.style.display = 'flex';
            const stillDiv = locationImages.querySelector('.still');
            const imageDiv = stillDiv.querySelector('.image');
            imageDiv.innerHTML = '';
            const img = document.createElement('img');
            img.src = '/images/map' + location.still_path;
            img.alt = location.title;
            imageDiv.appendChild(img);

            const wrapper = additionalImagesDiv.querySelector('.wrapper');
            wrapper.innerHTML = '';
            const additionalImagesArray = location.filmingLocationImages.filter(fl => fl.id !== location.still_id);
            additionalImagesArray.forEach(function (image) {
                const img = document.createElement('img');
                const imageDiv = document.createElement('div');
                imageDiv.classList.add('image');
                img.src = '/images/map' + image.path;
                img.alt = image.title;
                imageDiv.appendChild(img);
                wrapper.appendChild(imageDiv);
            });
        }
        addLocationDialog.classList.add('open');
        locationInput.focus();
        locationInput.select();
    }

    closeLocationPanel() {
        const addLocationDialog = document.querySelector('.side-panel.add-location-dialog');
        addLocationDialog.classList.remove('open');
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
