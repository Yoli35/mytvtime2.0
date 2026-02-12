import {AddCast} from "AddCast";
import {AverageColor} from 'AverageColor';
import {CopyName} from "CopyName";
import {EpisodeActions} from "EpisodeActions";
import {FlashMessage} from "FlashMessage";
import {Map} from "Map";
import {SeasonComments} from "SeasonComments";
import {ToolTips} from 'ToolTips';
import {TranslationsForms} from "TranslationsForms";
import {UserList} from "UserList";

let gThis;

/**
 * @typedef Provider
 * @type {Object}
 * @property {number} provider_id
 * @property {string} name
 * @property {string} logo_path
 */
/**
 * @typedef Device
 * @type {Object}
 * @property {number} id
 * @property {string} name
 * @property {string} logo_path
 */
/**
 * @typedef FlatRate
 * @type {Array.<Provider>}
 */
/**
 * @typedef Rent
 * @type {Array.<Provider>}
 */
/**
 * @typedef Buy
 * @type {Array.<Provider>}
 */
/**
 * @typedef SeasonProvider
 * @type {Object}
 * @property {FlatRate} flatrate
 * @property {Rent} rent
 * @property {Buy} buy
 */
/**
 * @typedef wpSelect
 * @type {Array.<key: value>}
 */
/**
 * @typedef wpLogos
 * @type {Array.<key: value>}
 */
/**
 * @typedef wpNames
 * @type {Array.<key: value>}
 */
/**
 * @typedef wpList
 * @type {Array.<Provider>}
 */
/**
 * @typedef Providers
 * @type {Object}
 * @property {wpSelect} watchProviderSelect
 * @property {wpLogos} logos
 * @property {wpNames} names
 * @property {wpList} list
 */
/**
 * @typedef Devices
 * @type {Array.<Device>}
 */
/**
 * @typedef EpisodeFilmingLocationResult
 * @type {Object}
 * @property {number} episode_number
 * @property {string} block
 */
/**
 * @typedef Translations
 * @type {Object}
 * @property {string} provider
 * @property {string} device
 * @property {string} rating
 * @property {string} now
 * @property {string} add
 * @property {string} markAsWatched
 * @property {string} Television
 * @property {string} Mobile
 * @property {string} Tablet
 * @property {string} Laptop
 * @property {string} Desktop
 * @property {string} Search
 * @property {string} days
 * @property {string} hours
 * @property {string} minutes
 * @property {string} seconds
 * @property {string} day
 * @property {string} hour
 * @property {string} minute
 * @property {string} second
 * @property {string} additional
 * @property {string} loading
 * @property {string} poiToggler
 */
/**
 * @typedef User
 * @type {Object}
 * @property {string} avatar
 * @property {string} username
 */
/**
 * @typedef Globs
 * @type {Object}
 * @property {SeasonProvider} seasonProvider
 * @property {number} showId
 * @property {number} seasonNumber
 * @property {User} user
 * @property {Providers} providers
 * @property {Devices} devices
 * @property {Translations} text
 */

export class Season {

    constructor(menu) {
        gThis = this;

        /** @var {Globs} globs */
        const globs = JSON.parse(document.querySelector('div#globs').textContent);
        this.devices = globs.devices;
        this.providers = globs.providers;
        this.seasonNumber = globs.seasonNumber;
        this.seriesId = globs.seriesId;
        this.showId = globs.showId;
        this.user = globs.user;
        this.translations = globs.translations;
        this.lang = document.documentElement.lang;
        this.menu = menu;

        this.flashMessage = new FlashMessage();
        this.toolTips = new ToolTips();
        this.seasonComments = new SeasonComments(this.user, this.seriesId, this.seasonNumber, this.translations);
        this.episodeActions = new EpisodeActions(globs, this.flashMessage, this.toolTips, this.menu);
    }

    init() {
        /******************************************************************************
         * Adjust Vote section colors according to the brightness of the background   *
         ******************************************************************************/
        this.adjustVoteColors();

        /******************************************************************************
         * Animation for the progress bar                                             *
         ******************************************************************************/
        this.episodeActions.setProgress();

        /******************************************************************************
         * Add a copy badge to the name and localized name                            *
         ******************************************************************************/
        new CopyName(document.querySelector('.header .name h1'));

        /******************************************************************************
         * Menu to add a localized name or an overview and additional overview        *
         ******************************************************************************/
        new TranslationsForms(this.seriesId, 'season', this.translations, this.seasonNumber);

        /******************************************************************************
         * Comments                                                                   *
         ******************************************************************************/
        this.seasonComments.init();

        // Test
        console.log(this.getLightnessFromHex('#7f7f7f'));

        const watchLinks = document.querySelectorAll('.watch-link');
        watchLinks.forEach(function (watchLink) {
            const tools = watchLink.querySelector('.watch-link-tools');
            if (tools) {
                const a = watchLink.querySelector('a');
                const href = a.getAttribute('href');
                const copy = tools.querySelector('.watch-link-tool.copy');
                const linkNameDiv = tools.querySelector('.watch-link-name');
                const name = linkNameDiv.innerText;

                copy.addEventListener('click', function () {
                    navigator.clipboard.writeText(href).then(function () {
                        copy.classList.add('copied');
                        linkNameDiv.innerText = gThis.translations['copied'];
                        setTimeout(function () {
                            copy.classList.remove('copied');
                            linkNameDiv.innerText = name;
                        }, 2000);
                    });
                });
            }
        });

        const watchLinksCopyBadge = document.querySelectorAll(".watch-links.copy");
        watchLinksCopyBadge.forEach(function (badge) {
            badge.addEventListener('click', function () {
                const href = badge.getAttribute("data-url");
                navigator.clipboard.writeText(href).then(function () {
                    badge.classList.add('copied');
                    setTimeout(function () {
                        badge.classList.remove('copied');
                    }, 1000);
                });
            });
        });

        const sizesDiv = document.querySelector('.user-actions:has(.size-item)');
        const arsDiv = document.querySelector('.user-actions:has(.ar-item)');
        const userSeriesId = sizesDiv.getAttribute('data-user-series-id');
        const sizesItemDivs = sizesDiv.querySelectorAll('.size-item');
        const arsItemDivs = arsDiv.querySelectorAll('.ar-item');
        const itemDivs = [...sizesItemDivs, ...arsItemDivs];
        const episodesDiv = document.querySelector('.episodes');

        this.windowSizeListener();
        window.addEventListener("resize", this.windowSizeListener);

        itemDivs.forEach(function (itemDiv) {
            itemDiv.addEventListener('click', function (e) {
                const target = e.currentTarget;
                const type = target.getAttribute('data-type');
                if (target.classList.contains('active')) {
                    return;
                }
                if (type === 'size') {
                    const activeSizeItemDiv = sizesDiv.querySelector('.size-item.active');
                    activeSizeItemDiv.classList.remove('active');
                    const newValue = itemDiv.getAttribute('data-size');
                    episodesDiv.style.setProperty('--episode-height', newValue);
                }
                if (type === 'aspect-ratio') {
                    const activeArItemDiv = arsDiv.querySelector('.ar-item.active');
                    activeArItemDiv.classList.remove('active');
                    const newValue = itemDiv.getAttribute('data-ar');
                    episodesDiv.style.setProperty('--episode-aspect-ratio', newValue);
                }
                itemDiv.classList.add('active');

                const size = sizesDiv.querySelector('.size-item.active').getAttribute('data-size');
                const ar = arsDiv.querySelector('.ar-item.active').getAttribute('data-ar');

                fetch('/api/episode/height/' + userSeriesId, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        height: size,
                        aspectRatio: ar
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        console.log(data);
                    });
            });
        });

        const editEpisodeInfosButton = document.querySelector('.edit-episode-infos');
        editEpisodeInfosButton.addEventListener('click', this.openEditEpisodeInfosPanel);
        const editEpisodeInfosDialog = document.querySelector('.side-panel.edit-episode-infos-dialog');
        const editEpisodeInfosForm = editEpisodeInfosDialog.querySelector('form');
        const submitRow = editEpisodeInfosForm.querySelector('.form-row.submit-row');
        const scrollDownToSubmitDiv = editEpisodeInfosDialog.querySelector('.scroll-down-to-submit');
        const scrollDownToSubmitButton = scrollDownToSubmitDiv.querySelector('button');
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
            editEpisodeInfosDialog.querySelector('.frame').scrollTo(0, submitRow.offsetTop);
        });

        const quickEpisodesDivs = document.querySelectorAll('.quick-episodes');
        quickEpisodesDivs.forEach((quickEpisodesDiv) => {
            const seasonNumber = quickEpisodesDiv.getAttribute('data-season-number');
            const quickEpisodeLinks = quickEpisodesDiv.querySelectorAll('.quick-episode.enabled');
            quickEpisodeLinks.forEach(episode => {
                episode.addEventListener('click', e => {
                    e.preventDefault();
                    const episodeNumber = e.currentTarget.getAttribute('data-number');
                    if (!episodeNumber) {
                        return;
                    }
                    const selector = '#episode-' + seasonNumber + '-' + episodeNumber;
                    const target = document.querySelector(selector);
                    target.scrollIntoView({behavior: 'smooth', block: 'center'});
                });
            });
        });

        const backToTops = document.querySelectorAll('.back-to-top');
        const top = document.querySelector('#top');
        backToTops.forEach(backToTop => {
            backToTop.addEventListener('click', e => {
                e.preventDefault();
                top.scrollIntoView({behavior: 'smooth'});
            });
        });

        const episodes = document.querySelector('.episodes');
        const infos = episodes.querySelectorAll('.episode > .infos');
        infos.forEach(info => {
            info.addEventListener('mouseleave', () => {
                const infosContentDiv = info.querySelector(".infos-content");
                infosContentDiv.scrollTop = 0;
            });
            const episodeNameEdit = info.querySelector('.episode-name>.edit');
            episodeNameEdit.addEventListener('click', this.openTitleForm);
            const episodeOverviewEdit = info.querySelector('.episode-overview>.edit');
            episodeOverviewEdit.addEventListener('click', this.openTitleForm);
        });

        /******************************************************************************
         * Episode actions (add, remove, provider, device, vote)                      *                                                           *
         ******************************************************************************/
        this.episodeActions.init();

        const customStillsTextDivs = document.querySelectorAll('.custom-stills-text');
        customStillsTextDivs.forEach(customStillsTextDiv => {
            customStillsTextDiv.addEventListener('click', (e) => {
                e.preventDefault();
                const customStillsDiv = customStillsTextDiv.parentElement.querySelector('.custom-stills');
                customStillsTextDiv.innerText = gThis.translations['paste'] + ' - 4';
                customStillsDiv.classList.add('active');
                customStillsTextDiv.classList.add('active');
                customStillsTextDiv.setAttribute('contenteditable', 'true');
                customStillsTextDiv.focus();
                customStillsTextDiv.addEventListener('paste', gThis.pasteStill);
                let countDown = 4;
                let intervalId = setInterval(() => {
                    customStillsTextDiv.innerText = gThis.translations['paste'] + ' - ' + --countDown;
                    console.log(countDown);
                    if (countDown === 1) {
                        clearInterval(intervalId);
                    }
                }, 1000);
                setTimeout(() => {
                    customStillsTextDiv.innerText = gThis.translations['click'];
                    customStillsDiv.classList.remove('active');
                    customStillsTextDiv.classList.remove('active');
                    customStillsTextDiv.removeAttribute('contenteditable');
                    customStillsTextDiv.removeEventListener('paste', gThis.pasteStill);
                }, 4000);
            });
        });

        const whatToWatchNextDiv = document.querySelector('.what-to-watch-next');
        const whatToWatchNextButton = whatToWatchNextDiv.querySelector('.next-button');
        whatToWatchNextButton.addEventListener('click', () => {
            whatToWatchNextButton.classList.add('disabled');
            const id = whatToWatchNextButton.getAttribute('data-id');
            const language = whatToWatchNextButton.getAttribute('data-language');

            fetch("/api/series/what/next?id=" + id + "&language=" + language,
                {
                    'method': 'GET',
                    'headers': {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(res => res.json())
                .then(data => {
                    const blocks = data['blocks'];
                    let containerDiv = whatToWatchNextDiv.querySelector('.series-to-watch');
                    let wrapperDiv, infosDiv;
                    if (!containerDiv) {
                        infosDiv = document.createElement('div');
                        infosDiv.classList.add('series-to-watch-infos');
                        whatToWatchNextDiv.appendChild(infosDiv);
                        containerDiv = document.createElement('div');
                        containerDiv.classList.add('series-to-watch');
                        wrapperDiv = document.createElement('div');
                        wrapperDiv.classList.add('wrapper');
                        containerDiv.appendChild(wrapperDiv);
                        whatToWatchNextDiv.appendChild(containerDiv);
                    } else {
                        infosDiv = whatToWatchNextDiv.querySelector(".series-to-watch-infos");
                        wrapperDiv = containerDiv.querySelector('.wrapper')
                        wrapperDiv.innerHTML = '';
                    }
                    infosDiv.innerText = data['sortOption'] + " / " + data['orderOption'] + " x " + data['limitOption'];
                    blocks.forEach((block, index) => {
                        wrapperDiv.insertAdjacentHTML('beforeend', block);
                        const posterDiv = wrapperDiv.querySelector(".card:last-child").querySelector(".poster");
                        const numberDiv = document.createElement("div");
                        numberDiv.classList.add("number");
                        numberDiv.innerText = (index + 1).toString()
                        posterDiv.appendChild(numberDiv);
                    });
                    new UserList(gThis.flashMessage, gThis.toolTips);
                    whatToWatchNextButton.classList.remove('disabled');
                });
        });

        const getFilmingLocationsDiv = document.querySelector('.get-filming-locations');
        const getFilmingLocationsButton = document.querySelector('.get-filming-locations-button');
        getFilmingLocationsButton?.addEventListener('click', () => {
            getFilmingLocationsButton.innerHTML = gThis.translations['loading'];
            getFilmingLocationsButton.classList.add('disabled');
            const id = getFilmingLocationsButton.getAttribute('data-id');
            fetch('/api/series/get/filming/locations/' + id,
                {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(res => res.json())
                .then(data => {
                    const body = document.querySelector('body');
                    const svgsDiv = document.querySelector('#svgs');
                    const globsMapDiv = document.createElement('div');
                    globsMapDiv.setAttribute('id', 'globs-map');
                    globsMapDiv.style.display = 'none';
                    globsMapDiv.innerText = '{';
                    globsMapDiv.innerText += '"locations": ' + JSON.stringify(data["locations"]) + ', ';
                    globsMapDiv.innerText += '"bounds": ' + JSON.stringify(data["locationsBounds"]) + ', ';
                    globsMapDiv.innerText += '"emptyLocation": ' + JSON.stringify(data["emptyLocation"]) + ', ';
                    globsMapDiv.innerText += '"fieldList": ' + JSON.stringify(data["fieldList"]) + ', ';
                    globsMapDiv.innerText += '"locationImagePath": "' + data["locationImagePath"] + '", ';
                    globsMapDiv.innerText += '"poiImagePath": "' + data["poiImagePath"] + '"';
                    globsMapDiv.innerText += '}';
                    body.insertBefore(globsMapDiv, svgsDiv);
                    getFilmingLocationsDiv.innerHTML = data["mapBlock"];
                    getFilmingLocationsButton.remove();
                    gThis.map = new Map({cooperativeGesturesOption: true});
                })
        });

        /******************************************************************************
         * Force text color to light or dark                                          *
         ******************************************************************************/
        const lightDarkTogglers = document.querySelectorAll('.light-dark-toggler');
        lightDarkTogglers.forEach(el => {
            el.addEventListener('click', (e) => {
                const toggler = e.currentTarget;
                const infosContentDiv = toggler.closest('.infos-content');
                if (!infosContentDiv.classList.contains('dark')) {
                    infosContentDiv.classList.add('dark');
                    infosContentDiv.classList.remove('light');
                } else {
                    infosContentDiv.classList.remove('dark');
                    infosContentDiv.classList.add('light');
                }
            });
        });

        /******************************************************************************
         * Add a person to the cast - Search input                                    *
         ******************************************************************************/
        const addCast = new AddCast();
        addCast.init(this.menu, this.toolTips, this.flashMessage);

        this.getEpisodeFilmingLocations();

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const list = document.querySelector('.list');
                if (list) {
                    list.remove();
                    e.preventDefault();
                }
            }
        });
        document.addEventListener('click', (e) => {
            const list = document.querySelector('.list');
            if (list) {
                if (!list.contains(e.target)) {
                    list.remove();
                    e.preventDefault();
                }
            }
        });
    }

    windowSizeListener() {
        const sizesDiv = document.querySelector('.user-actions:has(.size-item)');
        const arsDiv = document.querySelector('.user-actions:has(.ar-item)');
        const initialActiveSizeItemDiv = sizesDiv.querySelector('.size-item.active');
        const initialActiveArItemDiv = arsDiv.querySelector('.ar-item.active');
        const initialSize = initialActiveSizeItemDiv.getAttribute('data-size');
        const initialAr = initialActiveArItemDiv.getAttribute('data-ar');
        const windowWidth = window.innerWidth;
        const episodesDiv = document.querySelector('.episodes');

        if (windowWidth > 1024) {
            episodesDiv.style.setProperty('--episode-height', initialSize);
            episodesDiv.style.setProperty('--episode-aspect-ratio', initialAr);
        } else {
            episodesDiv.removeAttribute("style");
        }
    }

    getEpisodeFilmingLocations() {
        fetch('/api/season/filming/location/' + this.showId, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                seasonNumber: this.seasonNumber
            })
        })
            .then((response) => response.json())
            .then(data => {
                console.log(data);
                /** @type Array<EpisodeFilmingLocationResult> results */
                const results = data['results'];
                results.forEach(result => {
                    const episodeSelector = "#episode-" + this.seasonNumber + "-" + result['episode_number'] + " .infos";
                    console.log(episodeSelector)
                    const episodeInfosDiv = document.querySelector(episodeSelector);
                    const episodeInfosContentDiv = episodeInfosDiv.querySelector(".infos-content");
                    console.log(episodeInfosDiv)
                    const block = result.block;
                    const tempDiv = document.createElement("div");
                    tempDiv.innerHTML = block;
                    /*episodeInfosContentDiv.insertAdjacentHTML('beforeend', block);*/
                    const filmingLocationsDiv = tempDiv.querySelector(".season-filming-locations");
                    gThis.toolTips.init(filmingLocationsDiv);
                    episodeInfosContentDiv.appendChild(filmingLocationsDiv);
                });
                gThis.initScrollInfosButtons();
            })
            .catch(err => console.log(err));
    }

    initScrollInfosButtons() {
        const scrollButtons = document.querySelectorAll(".episodes .episode-wrapper .scroll-infos-button");
        scrollButtons.forEach(button => {
            button.addEventListener("click", gThis.buttonScrollAction)
        });
        // Mettre à jour la visibilité au chargement, redimensionnement et scroll
        window.addEventListener('load', gThis.updateButtonVisibility);
        window.addEventListener('resize', gThis.updateButtonVisibility);
        window.addEventListener('scroll', gThis.updateButtonVisibility, true); // true pour capter le scroll des conteneurs
        // et observer dynamiquement les changements dans la zone episodes
        const observer = new MutationObserver(gThis.updateButtonVisibility);
        observer.observe(document.body, {childList: true, subtree: true});
    }

    isVisibleInContainer(el, container) {
        const e = el.getBoundingClientRect();
        const c = container.getBoundingClientRect();
        return e.top >= c.top && e.bottom <= c.bottom;
    }

    findFirstHiddenTarget(infosDiv) {
        if (!infosDiv) { return null; }
        const targets = infosDiv.querySelectorAll('.infos .season-filming-location');
        for (const t of targets) {
            // si l'élément n'est pas entièrement visible dans son conteneur
            if (!gThis.isVisibleInContainer(t, infosDiv)) return {target: t, container: infosDiv};
        }
        return null;
    }

    updateButtonVisibility() {
        const infosDivs = document.querySelectorAll(".episodes .episode-wrapper .infos");
        infosDivs.forEach(infosDiv => {
            const infosContentDiv = infosDiv.querySelector(".infos-content");
            const btn = infosDiv.querySelector(".scroll-infos-button");
            if (!btn) return;
            const found = gThis.findFirstHiddenTarget(infosContentDiv);
            btn.style.display = found ? 'flex' : 'none';
        });
    }

    buttonScrollAction(evt) {
        const infosContentDiv = evt.currentTarget.closest(".infos").querySelector(".infos-content");
        const found = gThis.findFirstHiddenTarget(infosContentDiv);
        if (!found) return;
        const {target, container} = found;

        const e = target.getBoundingClientRect();
        const c = container.getBoundingClientRect();
        // calculer le delta pour aligner le bas de l'élément avec le bas du conteneur
        const delta = e.bottom - c.bottom;
        // si l'élément est au-dessus, on aligne son haut
        const deltaTop = e.top - c.top;
        const scrollTo = delta > 0 ? container.scrollTop + delta + 8 : container.scrollTop + deltaTop - 8;
        container.scrollTo({top: scrollTo, behavior: 'smooth'});
    }

    openEditEpisodeInfosPanel() {
        const editEpisodeInfosForm = document.querySelector('#edit-episode-infos-form');
        const editEpisodeInfosDialog = document.querySelector('.side-panel.edit-episode-infos-dialog');
        const stillDivs = editEpisodeInfosForm.querySelectorAll('.still');
        stillDivs.forEach(stillDiv => {
            stillDiv.addEventListener('click', () => {
                stillDiv.classList.add('paste');
                stillDiv.setAttribute('contenteditable', 'true');
                stillDiv.focus();
                stillDiv.addEventListener('paste', gThis.pasteStill);
                setTimeout(() => {
                    stillDiv.classList.remove('paste');
                    stillDiv.removeAttribute('contenteditable');
                    stillDiv.removeEventListener('paste', gThis.pasteStill);
                }, 4000);
            });
        });
        const textareas = editEpisodeInfosForm.querySelectorAll('textarea');
        textareas.forEach(textarea => {
            // ajuster la hauteur du textarea pour que le contenu soit entièrement visible s'i 'il y a un contenu
            if (textarea.scrollHeight > textarea.clientHeight) {
                textarea.style.height = textarea.scrollHeight + 'px';
            }
            textarea.addEventListener('keyup', (e) => {
                const field = e.currentTarget;
                if (field.scrollHeight > field.clientHeight) {
                    field.style.height = `${field.scrollHeight}px`;
                }
            });
        });
        const submitRow = editEpisodeInfosForm.querySelector('.form-row.submit-row');
        const copySwitch = editEpisodeInfosForm.querySelector('input[id="edit-episode-infos-copy-all"]');
        copySwitch.addEventListener('click', gThis.editEpisodeInfosCopySwitch);
        const copyButton = submitRow.querySelector('button[id="edit-episode-infos-copy"]');
        copyButton.addEventListener('click', gThis.editEpisodeInfosCopy);
        const cancelButton = submitRow.querySelector('button[id="edit-episode-infos-cancel"]');
        cancelButton.addEventListener('click', gThis.editEpisodeInfosCancel);
        const submitButton = submitRow.querySelector('button[id="edit-episode-infos-save"]');
        submitButton.addEventListener('click', gThis.editEpisodeInfosSubmit);

        editEpisodeInfosDialog.classList.add('open');
    }

    editEpisodeInfosCopySwitch(e) {
        const copySwitch = e.currentTarget;
        const editEpisodeInfosForm = document.querySelector('#edit-episode-infos-form');
        const switchStatus = copySwitch.checked;
        const switchInputs = editEpisodeInfosForm.querySelectorAll('input[id^=copy]')
        switchInputs.forEach(switchInput => {
            switchInput.checked = switchStatus;
        });
    }

    editEpisodeInfosCopy() {
        const editEpisodeInfosForm = document.querySelector('#edit-episode-infos-form');
        const data = {};
        const switchInputs = editEpisodeInfosForm.querySelectorAll('input[id^=copy]')
        switchInputs.forEach(switchInput => {
            if (switchInput.checked) {
                const id = switchInput.getAttribute('data-id');
                const formColumnDiv = switchInput.closest('.form-row').querySelector('.form-column');
                const title = formColumnDiv.querySelector('input[id^=title]').value;
                const overview = formColumnDiv.querySelector('textarea[id^=overview]').value;
                data[id] = {title: title, overview: overview};
            }
        });
        // Copy data to the clipboard
        navigator.clipboard.writeText(JSON.stringify(data)).then(r => console.log(r));
    }

    editEpisodeInfosCancel() {
        const editEpisodeInfosDialog = document.querySelector('.side-panel.edit-episode-infos-dialog');
        editEpisodeInfosDialog.classList.remove('open');
    }

    editEpisodeInfosSubmit(e) {
        e.preventDefault();
        const editEpisodeInfosForm = document.querySelector('#edit-episode-infos-form');
        const editEpisodeInfosDialog = document.querySelector('.side-panel.edit-episode-infos-dialog');
        const formData = new FormData(editEpisodeInfosForm);
        const data = {};
        formData.forEach((value, key) => {
            data[key] = value;
        });
        fetch('/api/episode/update/infos', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(data)
        }).then(function (response) {
            if (response.ok) {
                editEpisodeInfosDialog.classList.remove('open');
                window.location.reload();
            }
        });

    }

    openTitleForm(e) {
        const editDiv = e.currentTarget;
        const type = editDiv.getAttribute('data-type');
        const selector = '.episode-' + type;
        const fieldDiv = editDiv.closest(selector);
        let contentDiv, substituteDiv, fieldContent;
        if (type === 'name') {
            contentDiv = fieldDiv.querySelector('.name');
            substituteDiv = fieldDiv.querySelector('.substitute');
            fieldContent = substituteDiv?.innerText.length ? substituteDiv.innerText : contentDiv.innerText;
        } else {
            contentDiv = fieldDiv.querySelector('.overview');
            substituteDiv = fieldDiv.querySelector('.additional');
            fieldContent = substituteDiv.innerText;
        }
        const form = document.createElement('form');
        form.setAttribute('method', 'post');
        form.setAttribute('action', '');
        form.setAttribute('autocomplete', 'off');
        const input = document.createElement(type === 'name' ? 'input' : 'textarea');
        input.setAttribute('type', 'text');
        input.setAttribute('name', type);
        if (type === 'name') {
            input.setAttribute('value', fieldContent);
            input.setAttribute('maxlength', '255');
        } else {
            input.textContent = fieldContent;
            input.setAttribute('rows', '5');
            input.addEventListener('keyup', (e) => {
                const field = e.currentTarget;
                if (field.scrollHeight > field.clientHeight) {
                    field.style.height = `${field.scrollHeight}px`;
                }
            });
        }
        input.setAttribute('required', '');
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                form.remove();
            }
        });
        form.appendChild(input);
        const submit = document.createElement('button');
        submit.setAttribute('type', 'submit');
        submit.setAttribute('name', 'submit');
        submit.setAttribute('value', 'submit');
        submit.textContent = 'OK';
        submit.addEventListener('click', (e) => {
            e.preventDefault();
            fieldContent = input.value;
            fetch('/api/episode/update/info/' + editDiv.getAttribute('data-id'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    content: fieldContent,
                    type: type
                })
            }).then(function (response) {
                if (response.ok) {
                    const needToCreateSubstitute = type === 'name' && fieldContent.length && !substituteDiv;
                    if (needToCreateSubstitute) {
                        substituteDiv = document.createElement('div');
                        substituteDiv.classList.add('substitute');
                        fieldDiv.insertBefore(substituteDiv, editDiv);
                        const episodeWatched = contentDiv.closest('.episode').querySelector('.remove-this-episode');
                        if (episodeWatched) {
                            substituteDiv.classList.add('watched');
                        }
                    }
                    if (fieldContent.length) {
                        substituteDiv.innerText = fieldContent;
                    } else {
                        if (type === 'name') {
                            substituteDiv.remove();
                        } else {
                            substituteDiv.innerText = gThis.translations['additional'];
                        }
                    }
                }
                form.remove();
            });
        });
        form.appendChild(submit);
        const cancel = document.createElement('button');
        cancel.setAttribute('type', 'button');
        cancel.setAttribute('name', 'cancel');
        cancel.setAttribute('value', 'cancel');
        cancel.textContent = 'X';
        cancel.addEventListener('click', () => {
            form.remove();
        });
        form.appendChild(cancel);
        contentDiv.appendChild(form);

        input.focus();
        input.select();

        /*if (type === 'overview') {
            form.scrollIntoView({block: "end", inline: "nearest", behavior: 'smooth'});
        }*/
    }

    async pasteStill(e) {
        // Récupérer l'élément sous la souris
        const target = e.target;
        const targetStillDiv = target.classList.contains('.custom-stills-text') ? target : target.closest('.still');

        if (!targetStillDiv) {
            return;
        }
        e.preventDefault();
        const seriesNameSpan = document.querySelector('span.localization-span') || document.querySelector('span.name-span');
        const seriesId = targetStillDiv.getAttribute('data-series-id');
        const seasonId = targetStillDiv.getAttribute('data-season-id');
        const episodeId = targetStillDiv.getAttribute('data-episode-id');
        const episodeDivId = targetStillDiv.closest('.episode').getAttribute('id');
        const seasonNumber = episodeDivId.split('-')[1];
        const episodeNumber = episodeDivId.split('-')[2];
        const fileName = seriesId + '-' + seasonId + '-' + episodeId;

        for (const clipboardItem of e.clipboardData.files) {
            if (clipboardItem.type.startsWith('image/')) {
                // Save the image in %kernel.dir%/public/series/stills/season-xx/episode-xx.jpg
                console.log('Saving still for episode ' + episodeId + ' with file name: ' + fileName);
                console.log(clipboardItem);
                // Create a FormData object to send the image
                if (clipboardItem.size > 5000000) { // 5MB
                    alert("Image size exceeds 5MB. Please use a smaller image.");
                    return;
                }
                const formData = new FormData();
                formData.append('file', clipboardItem, fileName);/*+ clipboardItem.type.split('/')[1]*/
                formData.append('name', seriesNameSpan ? seriesNameSpan.textContent : 'Unknown Series');
                formData.append('seasonNumber', seasonNumber);
                formData.append('episodeNumber', episodeNumber);
                const response = await fetch('/api/episode/still/' + episodeId, {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                console.log(data);
                if (response.ok) {
                    const isEditing = targetStillDiv.getAttribute('data-editing');
                    const url = "/series/stills" + data['image'];
                    let still;
                    still = targetStillDiv.querySelector('img');
                    if (still) {
                        still.src = url;
                    } else {
                        const noPoster = targetStillDiv.querySelector('.no-poster');
                        noPoster?.remove();
                        still = document.createElement('img');
                        still.src = url;
                        targetStillDiv.appendChild(still);
                    }
                    if (isEditing) {
                        // Find the episode still (.series-season > .content.column > .episodes > .episode > .still[data-episode-id=" + episodeId + "]")
                        // Replace the still, if exists, with the new one
                        const episodesDiv = document.querySelector('.episodes');
                        const episodeStill = episodesDiv.querySelector('.still[data-episode-id="' + episodeId + '"]');
                        if (episodeStill) {
                            const still = episodeStill.querySelector('img');
                            if (still) {
                                still.src = url;
                            } else {
                                const noPoster = episodeStill.querySelector('.no-poster');
                                noPoster?.remove();
                                const still = document.createElement('img');
                                still.src = url;
                                episodeStill.appendChild(still);
                            }
                        }
                    }
                } else {
                    console.log(response);
                    alert('Error: see console');
                }
            }
        }
    }

    getLightnessFromHex(hex) {
        hex = hex.replace(/^#/, '');
        const r = parseInt(hex.slice(0, 2), 16);
        const g = parseInt(hex.slice(2, 4), 16);
        const b = parseInt(hex.slice(4, 6), 16);
        // Luminance formula (perceived brightness)
        const brightness = (0.2126 * r + 0.7152 * g + 0.0722 * b) / 255;
        return +(brightness * 100).toFixed(2);
    }

    adjustVoteColors() {
        const body = document.querySelector("body");
        const url = body.style.backgroundImage.slice(5, -2);
        const img = document.createElement("img");
        img.src = url;
        const averageColor = new AverageColor();
        const color = averageColor.getColor(img);
        console.log({color});
        /* Luminosité de l'espace de couleur LCH */
        if (Math.floor(color.lch.l) > 50) {
            const voteGraphDiv = document.querySelector('.vote-graph');
            voteGraphDiv.classList.add('dark');
        }
    }
}
