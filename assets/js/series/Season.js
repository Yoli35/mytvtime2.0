import {AverageColor} from 'AverageColor';
import {FlashMessage} from "FlashMessage";
import {Map} from "Map";
import {ToolTips} from 'ToolTips';

let gThis;

export class Season {

    constructor() {
        gThis = this;
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
         * @typedef Globs
         * @type {Object}
         * @property {SeasonProvider} seasonProvider
         * @property {Providers} providers
         * @property {Devices} devices
         * @property {Translations} text
         */

        /** @var {Globs} jsonGlobsObject */
        const jsonGlobsObject = JSON.parse(document.querySelector('div#globs').textContent);
        this.seasonProvider = jsonGlobsObject.seasonProvider;
        this.providers = jsonGlobsObject.providers;
        this.providerArray = jsonGlobsObject.providers.list;
        this.devices = jsonGlobsObject.devices;
        this.text = jsonGlobsObject.text;
        this.lang = document.documentElement.lang;
        this.intervals = [];
        // this.initialDay = new Date().getDate();
        // this.saving = null;
        // this.lastMinute = 0;
        // this.lastHour = 0;
        // this.lastDay = 0;

        this.flashMessage = new FlashMessage();
        this.toolTips = new ToolTips();
    }

    init(menu) {
        this.menu = menu;
        /******************************************************************************
         * Adjust Vote section colors according to the brightness of the background   *
         ******************************************************************************/
        this.adjustVoteColors();

        /******************************************************************************
         * Animation for the progress bar                                             *
         ******************************************************************************/
        this.setProgress();

        // Test
        console.log(this.getLightnessFromHex('#7f7f7f'));

        const watchLinks = document.querySelectorAll('.watch-link');
        watchLinks.forEach(function (watchLink) {
            const tools = watchLink.querySelector('.watch-link-tools');
            const href = watchLink.querySelector('a').getAttribute('href');
            const copy = tools.querySelector('.watch-link-tool.copy');
            const linkNameDiv = tools.querySelector('.watch-link-name');
            const name = linkNameDiv.innerText;

            copy.addEventListener('click', function () {
                navigator.clipboard.writeText(href).then(function () {
                    copy.classList.add('copied');
                    linkNameDiv.innerText = gThis.text['copied'];
                    setTimeout(function () {
                        copy.classList.remove('copied');
                        linkNameDiv.innerText = name;
                    }, 2000);
                });
            });
        });

        const sizesDiv = document.querySelector('.user-actions:has(.size-item)');
        const arsDiv = document.querySelector('.user-actions:has(.ar-item)');
        const userSeriesId = sizesDiv.getAttribute('data-user-series-id');
        const sizesItemDivs = sizesDiv.querySelectorAll('.size-item');
        const arsItemDivs = arsDiv.querySelectorAll('.ar-item');
        const itemDivs = [...sizesItemDivs, ...arsItemDivs];
        const initialActiveSizeItemDiv = sizesDiv.querySelector('.size-item.active');
        const initialActiveArItemDiv = arsDiv.querySelector('.ar-item.active');
        const initialSize = initialActiveSizeItemDiv.getAttribute('data-size');
        const initialAr = initialActiveArItemDiv.getAttribute('data-ar');
        const episodesDiv = document.querySelector('.episodes');
        episodesDiv.style.setProperty('--episode-height', initialSize);
        episodesDiv.style.setProperty('--episode-aspect-ratio', initialAr);

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

        const quickEpisodesDiv = document.querySelector('.quick-episodes');
        const seasonNumber = quickEpisodesDiv.getAttribute('data-season-number');
        const quickEpisodeLinks = document.querySelectorAll('.quick-episode');
        quickEpisodeLinks.forEach(episode => {
            episode.addEventListener('click', e => {
                e.preventDefault();
                const episodeNumber = e.currentTarget.getAttribute('data-number');
                if (!episodeNumber) { return; }
                const selector = '#episode-' + seasonNumber + '-' + episodeNumber;
                const target = document.querySelector(selector);
                target.scrollIntoView({behavior: 'smooth', block: 'center'});
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
        const infos = episodes.querySelectorAll('.infos');
        infos.forEach(info => {
            info.addEventListener('mouseleave', () => {
                info.scrollTop = 0;
            });
            const episodeNameEdit = info.querySelector('.episode-name>.edit');
            episodeNameEdit.addEventListener('click', this.openTitleForm);
            const episodeOverviewEdit = info.querySelector('.episode-overview>.edit');
            episodeOverviewEdit.addEventListener('click', this.openTitleForm);
        });

        const addThisEpisode = document.querySelectorAll('.add-this-episode');
        addThisEpisode.forEach(episode => {
            episode.addEventListener('click', this.addEpisode);
        });

        const removeThisEpisode = document.querySelectorAll('.remove-this-episode');
        removeThisEpisode.forEach(episode => {
            episode.addEventListener('click', this.removeOrReviewEpisode);
        });

        const watchedAtDivs = document.querySelectorAll('.watched-at');
        watchedAtDivs.forEach(watchedAtDiv => {
            watchedAtDiv.addEventListener('click', this.modifyWatchedAtOpen);
        });

        const userEpisodeProviders = document.querySelectorAll('.select-provider');
        userEpisodeProviders.forEach(provider => {
            provider.addEventListener('click', gThis.selectProvider);
        });

        const userEpisodeDevices = document.querySelectorAll('.select-device');
        userEpisodeDevices.forEach(device => {
            device.addEventListener('click', gThis.selectDevice);
        });

        const userEpisodeVotes = document.querySelectorAll('.select-vote');
        userEpisodeVotes.forEach(vote => {
            vote.addEventListener('click', gThis.selectVote);
            // vote.addEventListener('wheel', gThis.wheelVote);
        });

        const customStillsTextDivs = document.querySelectorAll('.custom-stills-text');
        customStillsTextDivs.forEach(customStillsTextDiv => {
            customStillsTextDiv.addEventListener('click', () => {
                const customStillsDiv = customStillsTextDiv.parentElement.querySelector('.custom-stills');
                customStillsTextDiv.innerText = gThis.text['paste'] + ' - 4';
                customStillsDiv.classList.add('active');
                customStillsTextDiv.classList.add('active');
                customStillsTextDiv.setAttribute('contenteditable', 'true');
                customStillsTextDiv.focus();
                customStillsTextDiv.addEventListener('paste', gThis.pasteStill);
                let countDown = 4;
                let intervalId = setInterval(() => {
                    customStillsTextDiv.innerText = gThis.text['paste'] + ' - ' + --countDown;
                    console.log(countDown);
                    if (countDown === 1) {
                        clearInterval(intervalId);
                    }
                }, 1000);
                setTimeout(() => {
                    customStillsTextDiv.innerText = gThis.text['click'];
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
                let wrapperDiv;
                if (!containerDiv) {
                    containerDiv = document.createElement('div');
                    containerDiv.classList.add('series-to-watch');
                    wrapperDiv = document.createElement('div');
                    wrapperDiv.classList.add('wrapper');
                    containerDiv.appendChild(wrapperDiv);
                    whatToWatchNextDiv.appendChild(containerDiv);
                } else {
                    wrapperDiv = containerDiv.querySelector('.wrapper')
                    wrapperDiv.innerHTML = '';
                }
                blocks.forEach((block) => {
                    wrapperDiv.insertAdjacentHTML('beforeend', block);
                });
                whatToWatchNextButton.classList.remove('disabled');
            });
        });

        const getFilmingLocationsDiv = document.querySelector('.get-filming-locations');
        const getFilmingLocationsButton = document.querySelector('.get-filming-locations-button');
        getFilmingLocationsButton?.addEventListener('click', () => {
            getFilmingLocationsButton.innerHTML = gThis.text['loading'];
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
                const svgsDiv = document.querySelector('.svgs');
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
         * Add a person to the cast - Search input                                    *
         ******************************************************************************/
        this.addCastInit();

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

    setProgress() {
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
        const cancelButton = submitRow.querySelector('button[type="button"]');
        cancelButton.addEventListener('click', () => {
            editEpisodeInfosDialog.classList.remove('open');
        });
        const submitButton = submitRow.querySelector('button[type="submit"]');
        submitButton.addEventListener('click', (e) => {
            e.preventDefault();
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
        });

        editEpisodeInfosDialog.classList.add('open');
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
                            substituteDiv.innerText = gThis.text['additional'];
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

    addCastInit() {
        const peopleSearchBlockDiv = document.querySelector('.cast-search-block');
        if (peopleSearchBlockDiv) {
            const addCastButton = document.querySelector('.add-cast-button');
            const peopleSearchInput = document.getElementById('cast-search');

            peopleSearchInput.addEventListener("input", this.menu.searchFetch);
            peopleSearchInput.addEventListener("keydown", this.menu.searchMenuNavigate);

            addCastButton.addEventListener('click', () => {
                peopleSearchBlockDiv.classList.toggle('active');
                if (peopleSearchBlockDiv.classList.contains('active')) {
                    peopleSearchInput.focus();
                }
            });
        }
    }

    addEpisode(e, episodeId = null) {
        gThis.toolTips.hide();
        const selector = episodeId ? '.remove-this-episode[data-id="' + episodeId + '"]' : null;
        const episode = episodeId ? document.querySelector(selector) : e.currentTarget;
        const userEpisode = episode.closest('.user-episode');
        const sId = episode.getAttribute('data-show-id');
        const seriesId = episode.getAttribute('data-series-id');
        const id = episode.getAttribute('data-id');
        const ueId = episode.getAttribute('data-ue-id');
        const episodeNumber = episode.getAttribute('data-e-number');
        const seasonNumber = episode.getAttribute('data-s-number');
        const lastEpisode = episode.getAttribute('data-last-episode');
        const views = parseInt(episode.getAttribute('data-views') ?? "0");
        const backToTopLink = episode.parentElement.querySelector('.back-to-top');
        /*const backToSeriesLink = episode.parentElement.querySelector('.back-to-series').closest('a');*/
        const quickEpisodeLink = document.querySelector('.quick-episode[data-number="' + episodeNumber + '"]');
        const substituteNameDiv = episode.closest('.episode').querySelector('.substitute');
        const episodeWatchLinks = episode.closest('.episode').querySelector('.watch-links');
        const finaleDivs = episode.closest('.episode').querySelectorAll('.finale');

        fetch('/api/episode/add/' + id, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                showId: sId,
                lastEpisode: lastEpisode,
                seasonNumber: seasonNumber,
                episodeNumber: episodeNumber,
                ueId: ueId
            })
        }).then((response) => response.json())
            .then(data => {
                // TODO: Vérifier "data"
                console.log(data);
                const airDateDiv = episode.closest('.episode').querySelector('.air-date');
                const block = document.createElement('div');
                block.innerHTML = data['airDateBlock'];
                const newAirDateDiv = block.querySelector('.air-date');
                const newWatchedAtDivs = block.querySelectorAll('.watched-at');
                newWatchedAtDivs.forEach(newWatchedAtDiv => {
                    newWatchedAtDiv.addEventListener('click', gThis.modifyWatchedAtOpen);
                });
                airDateDiv.replaceWith(newAirDateDiv);

                const numberDiv = episode.closest('.episode').querySelector('.number');
                numberDiv.setAttribute('data-title', data['views']);

                episode.setAttribute('data-views', '' + (views + 1));
                episode.setAttribute('data-title', gThis.text.now);
                const now = new Date();
                episode.setAttribute('data-time', now.toISOString());
                episode.addEventListener('mouseenter', gThis.updateRelativeTime);
                if (episodeId) {
                    return;
                }

                const messages = data['messages'];
                if (messages.length) {
                    messages.forEach(message => {
                        gThis.flashMessage.add('success', message);
                    });
                }

                const progressDiv = document.querySelector('.progress');
                if (progressDiv) {
                    progressDiv.setAttribute('data-value', data['season_progress']);
                    gThis.setProgress();
                }

                const newEpisode = document.createElement('div');
                newEpisode.classList.add('remove-this-episode');
                newEpisode.setAttribute('data-id', id);
                newEpisode.setAttribute('data-ue-id', ueId);
                newEpisode.setAttribute('data-series-id', seriesId);
                newEpisode.setAttribute('data-show-id', sId);
                newEpisode.setAttribute('data-e-number', episodeNumber);
                newEpisode.setAttribute('data-s-number', seasonNumber);
                newEpisode.setAttribute('data-last-episode', lastEpisode);
                newEpisode.setAttribute('data-views', '' + (views + 1));
                newEpisode.setAttribute('data-title', gThis.text.now);
                newEpisode.setAttribute('data-time', now.toISOString());
                gThis.intervals[id] = setInterval(gThis.updateRelativeTime, 1000, {currentTarget: newEpisode});
                newEpisode.addEventListener('click', gThis.removeOrReviewEpisode);
                newEpisode.appendChild(gThis.getSvg('eye'));
                episode.replaceWith(newEpisode);
                gThis.toolTips.init(newEpisode);/*episode.querySelector('.remove-this-episode'));*/

                quickEpisodeLink.classList.add('watched');

                numberDiv.classList.add('watched');

                substituteNameDiv?.classList.add('watched');

                episodeWatchLinks?.closest('.user-actions').classList.add('d-none');

                finaleDivs.forEach(f => {
                    f.classList.add('watched');
                });

                // Mise à jour du menu
                let episodesOfTheDayInMenu = document.querySelectorAll('a[id^="eotd-menu-item-"]');
                episodesOfTheDayInMenu.forEach(eotd => {
                    // if (eotd.getAttribute('id').includes(seriesId)) {
                    if (eotd.getAttribute('data-episode-ids').includes(episodeId ? episodeId : id)) {
                        const episodeCount = parseInt(eotd.getAttribute('data-episode-count'));
                        const firstEpisodeNumber = parseInt(eotd.getAttribute('data-first-episode-number'));
                        const number = parseInt(episodeNumber);
                        if (episodeCount === 1) {
                            if (number === firstEpisodeNumber) {
                                eotd.setAttribute('style', 'background: linear-gradient(90deg, var(--green-50) 100%, transparent 100%)');
                            }
                        } else {
                            if (number >= firstEpisodeNumber && number < firstEpisodeNumber + episodeCount) {
                                const progress = Math.round(100 * (episodeNumber - firstEpisodeNumber + 1) / episodeCount);
                                const style = "background: linear-gradient(90deg, var(--green-50) " + progress + "%, transparent " + progress + "%)";
                                eotd.setAttribute('style', style);
                            }
                        }
                    }
                });

                const previousEpisode = userEpisode.closest('.episodes').querySelector('.remove-this-episode[data-e-number="' + (episodeNumber - 1) + '"]');
                const previousProvider = previousEpisode?.parentElement.querySelector('.select-provider');
                if (previousProvider) {
                    const clone = previousProvider.cloneNode(true);
                    clone.setAttribute('data-id', id);
                    clone.setAttribute('data-ue-id', ueId);
                    clone.addEventListener('click', gThis.selectProvider);
                    userEpisode.insertBefore(clone, backToTopLink);
                } else {
                    const bestProviderIds = data['bestProviderIds'];
                    if (bestProviderIds.length > 1) {
                        const dialog = document.querySelector("#select-provider-dialog");
                        const form = dialog.querySelector('form');
                        const cancelButton = dialog.querySelector('button[value="cancel"]');
                        cancelButton.addEventListener('click', () => {
                            dialog.close();
                        });
                        bestProviderIds.forEach(providerId => {
                            const providerDiv = document.createElement('div');
                            providerDiv.classList.add('select-provider');
                            providerDiv.setAttribute('data-id', id);
                            providerDiv.setAttribute('data-ue-id', ueId);
                            providerDiv.setAttribute('data-provider-id', providerId);
                            providerDiv.innerHTML = '<img src="' + gThis.providers.logos[providerId] + '" alt="' + gThis.providers.names[providerId] + '">';
                            providerDiv.setAttribute('data-title', gThis.providers.names[providerId]);
                            providerDiv.addEventListener('click', () => {
                                gThis.saveProvider(ueId, providerId);
                                const deviceDiv = userEpisode.querySelector('.select-device');
                                userEpisode.insertBefore(providerDiv, deviceDiv);
                                const providerDivs = form.querySelectorAll('.select-provider');
                                providerDivs.forEach(providerDiv => {
                                    providerDiv.remove();
                                });
                                dialog.close();
                            });
                            gThis.toolTips.init(providerDiv);
                            form.insertBefore(providerDiv, cancelButton);
                        });
                        dialog.showModal();
                    } else {
                        const providerId = data['providerId'];
                        const providerDiv = document.createElement('div');
                        providerDiv.classList.add('select-provider');
                        providerDiv.setAttribute('data-id', id);
                        providerDiv.setAttribute('data-ue-id', ueId);
                        providerDiv.setAttribute('data-provider-id', providerId);
                        if (providerId) {
                            providerDiv.innerHTML = '<img src="' + gThis.providers.logos[providerId] + '" alt="' + gThis.providers.names[providerId] + '">';
                            providerDiv.setAttribute('data-title', gThis.providers.names[providerId]);
                            gThis.toolTips.init(providerDiv);
                        } else {
                            providerDiv.setAttribute('data-title', gThis.text.provider);
                            providerDiv.appendChild(gThis.getSvg('plus'));
                        }
                        providerDiv.addEventListener('click', gThis.selectProvider);
                        userEpisode.insertBefore(providerDiv, backToTopLink);
                    }
                }

                const previousDevice = previousEpisode?.parentElement.querySelector('.select-device');
                const deviceId = data['deviceId'];
                if (previousDevice) {
                    const clone = previousDevice.cloneNode(true);
                    clone.setAttribute('data-id', id);
                    clone.setAttribute('data-ue-id', ueId);
                    clone.addEventListener('click', gThis.selectDevice);
                    userEpisode.insertBefore(clone, backToTopLink);
                } else {
                    const deviceDiv = document.createElement('div');
                    deviceDiv.classList.add('select-device');
                    deviceDiv.setAttribute('data-id', id);
                    deviceDiv.setAttribute('data-ue-id', ueId);
                    deviceDiv.setAttribute('data-device-id', deviceId);
                    if (deviceId) {
                        const deviceName = gThis.getDeviceName(deviceId);
                        deviceDiv.innerHTML = '';
                        deviceDiv.appendChild(gThis.getSvg('device-' + deviceId));
                        deviceDiv.setAttribute('data-title', gThis.text[deviceName]);
                        gThis.toolTips.init(deviceDiv);
                    } else {
                        deviceDiv.setAttribute('data-title', gThis.text.device);
                        deviceDiv.appendChild(gThis.getSvg('plus'));
                    }
                    deviceDiv.addEventListener('click', gThis.selectDevice);
                    userEpisode.insertBefore(deviceDiv, backToTopLink);
                }

                const vote = document.createElement('div');
                vote.classList.add('select-vote');
                vote.setAttribute('data-id', id);
                vote.setAttribute('data-ue-id', ueId);
                vote.setAttribute('data-title', gThis.text.rating);
                vote.appendChild(gThis.getSvg('plus'));
                vote.addEventListener('click', gThis.selectVote);
                // vote.addEventListener('wheel', gThis.wheelVote);
                userEpisode.insertBefore(vote, backToTopLink);
            });
    }

    nowEpisode(e, episodeId = null) { // Ajuste la date de visionnage à maintenant
        gThis.toolTips.hide();
        const selector = episodeId ? '.remove-this-episode[data-ue-id="' + episodeId + '"]' : null;
        const episode = episodeId ? document.querySelector(selector) : e.currentTarget;
        const sId = episode.getAttribute('data-show-id');
        /*const id = episode.getAttribute('data-id');*/
        const episodeNumber = episode.getAttribute('data-e-number');
        const seasonNumber = episode.getAttribute('data-s-number');
        if (!episodeId) episode.getAttribute('data-ue-id');

        fetch('/api/episode/touch/' + episodeId, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                showId: sId,
                seasonNumber: seasonNumber,
                episodeNumber: episodeNumber
            })
        }).then((response) => response.json())
            .then(data => {
                // TODO: Vérifier "data"
                console.log(data);
                const airDateDiv = episode.closest('.episode').querySelector('.air-date');
                const watchedAtDiv = airDateDiv.querySelector('.watched-at');
                watchedAtDiv.innerHTML = data['viewedAt'];
                watchedAtDiv.setAttribute('data-watched-at', data['dataViewedAt']);
                episode.setAttribute('data-title', gThis.text.now);
                const now = new Date();
                episode.setAttribute('data-time', now.toISOString());
            });
    }

    updateRelativeTime(e) {
        const div = e.currentTarget;
        const id = div.getAttribute('data-id');

        const time = div.getAttribute('data-time');
        const date = new Date(time);
        const now = new Date();
        const diff = now - date;
        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        const hours = Math.floor(diff / (1000 * 60 * 60));
        const minutes = Math.floor(diff / (1000 * 60));
        const seconds = Math.floor(diff / 1000);
        if (days > 0) {
            div.setAttribute('data-title', days + ' ' + (days > 1 ? gThis.text.days : gThis.text.day));
            if (gThis.lastDay !== days) {
                gThis.lastDay = days;
                clearInterval(gThis.intervals[id]);
                gThis.intervals[id] = setInterval(gThis.updateRelativeTime, 86400000, e);
            }
        } else if (hours > 0) {
            div.setAttribute('data-title', hours + ' ' + (hours > 1 ? gThis.text.hours : gThis.text.hour));
            if (gThis.lastHour !== hours) {
                gThis.lastHour = hours;
                clearInterval(gThis.intervals[id]);
                gThis.intervals[id] = setInterval(gThis.updateRelativeTime, 3600000, e);
            }
        } else if (minutes > 0) {
            div.setAttribute('data-title', minutes + ' ' + (minutes > 1 ? gThis.text.minutes : gThis.text.minute));
            if (gThis.lastMinute !== minutes) {
                gThis.lastMinute = minutes;
                clearInterval(gThis.intervals[id]);
                gThis.intervals[id] = setInterval(gThis.updateRelativeTime, 60000, e);
            }
        } else {
            div.setAttribute('data-title', seconds + ' ' + (seconds > 1 ? gThis.text.seconds : gThis.text.second));
        }
    }

    modifyWatchedAtOpen(e) {
        const watchedAtDiv = e.currentTarget;
        const episodeId = watchedAtDiv.getAttribute('data-id');
        const userEpisodeId = watchedAtDiv.getAttribute('data-ue-id');
        const watchedAt = watchedAtDiv.getAttribute('data-watched-at');
        const airDateDiv = watchedAtDiv.closest('.air-date');
        const watchedAtModifyDiv = document.createElement('div');
        watchedAtModifyDiv.classList.add('watched-at-modify');
        const datetimeInput = document.createElement('input');
        datetimeInput.setAttribute('type', 'datetime-local');
        datetimeInput.setAttribute('value', watchedAt);
        const datetimeSaveButton = document.createElement('button');
        const svgSave = gThis.getSvg('save');
        datetimeSaveButton.appendChild(svgSave);
        datetimeSaveButton.setAttribute('data-ue-id', userEpisodeId);
        const datetimeDeleteButton = document.createElement('button');
        const svgDelete = gThis.getSvg('delete');
        datetimeDeleteButton.appendChild(svgDelete);
        datetimeSaveButton.setAttribute('data-ue-id', userEpisodeId);
        const datetimeCancelButton = document.createElement('button');
        const svgCancel = gThis.getSvg('cancel');
        datetimeCancelButton.appendChild(svgCancel);
        watchedAtModifyDiv.appendChild(datetimeInput);
        watchedAtModifyDiv.appendChild(datetimeSaveButton);
        watchedAtModifyDiv.appendChild(datetimeDeleteButton);
        watchedAtModifyDiv.appendChild(datetimeCancelButton);
        airDateDiv.appendChild(watchedAtModifyDiv);
        watchedAtDiv.classList.add('editing');

        datetimeInput.focus();
        datetimeInput.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                watchedAtModifyDiv.remove();
                watchedAtDiv.style.display = 'flex';
            }
            if (e.key === 'Enter') {
                e.preventDefault();
                datetimeSaveButton.click();
            }
        });
        datetimeSaveButton.addEventListener('click', gThis.touchEpisode);
        datetimeDeleteButton.addEventListener('click', () => {
            gThis.removeEpisode(episodeId, userEpisodeId);
            watchedAtModifyDiv.remove();
            watchedAtDiv.remove();
        });
        datetimeCancelButton.addEventListener('click', () => {
            watchedAtModifyDiv.remove();
            watchedAtDiv.classList.remove('editing');
        });
    }

    touchEpisode(e) { // Ajuste la date de visionnage à la valeur de l'input datetime-local
        const datetimeSaveButton = e.currentTarget;
        const id = datetimeSaveButton.getAttribute('data-ue-id');
        const airDateDiv = datetimeSaveButton.closest('.air-date');
        const watchedAtDiv = airDateDiv.querySelector('.watched-at[data-ue-id="' + id + '"]');
        const watchedAtModifyDiv = datetimeSaveButton.parentElement;
        const datetimeInput = watchedAtModifyDiv.querySelector('input');
        const newDatetime = datetimeInput.value;
        console.log(newDatetime);
        const episode = datetimeSaveButton.closest('.episode').querySelector('.remove-this-episode');
        const sId = episode.getAttribute('data-show-id');
        const episodeNumber = episode.getAttribute('data-e-number');
        const seasonNumber = episode.getAttribute('data-s-number');

        fetch('/api/episode/touch/' + id, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                showId: sId,
                date: newDatetime,
                seasonNumber: seasonNumber,
                episodeNumber: episodeNumber
            })
        }).then((response) => response.json())
            .then(data => {
                // TODO: Vérifier "data"
                console.log(data);
                const block = document.createElement('div');
                block.innerHTML = data['watchedAtBlock'];
                const newWatchedAtDiv = block.querySelector('.watched-at');
                newWatchedAtDiv.addEventListener('click', gThis.modifyWatchedAtOpen);
                watchedAtDiv.replaceWith(newWatchedAtDiv);
                episode.setAttribute('data-title', gThis.text.now);
                const now = new Date();
                episode.setAttribute('data-time', now.toISOString());
            });
        watchedAtModifyDiv.remove();
        // watchedAtDiv.style.display = 'flex';
        watchedAtDiv.classList.remove('editing');
    }

    removeOrReviewEpisode(e) {
        gThis.toolTips.hide();
        const dialog = document.querySelector("#review-dialog");
        const episode = e.currentTarget;
        const id = episode.getAttribute('data-id');
        const ueId = episode.getAttribute('data-ue-id');
        const showId = episode.getAttribute('data-show-id');
        const episodeNumber = episode.getAttribute('data-e-number');
        const seasonNumber = episode.getAttribute('data-s-number');
        const buttons = dialog.querySelectorAll('button');
        const removeButton = dialog.querySelector('button[value="remove"]');
        const watchButton = dialog.querySelector('button[value="watch"]');
        const nowButton = dialog.querySelector('button[value="now"]');
        const cancelButton = dialog.querySelector('button[value="cancel"]');
        buttons.forEach(button => {
            button.setAttribute('data-id', id);
            button.setAttribute('data-ue-id', ueId);
            button.setAttribute('data-show-id', showId);
            button.setAttribute('data-e-number', episodeNumber);
            button.setAttribute('data-s-number', seasonNumber);
        });
        removeButton.addEventListener('click', gThis.doRemoveEpisode);
        watchButton.addEventListener('click', gThis.doAddEpisode);
        nowButton.addEventListener('click', gThis.doNowEpisode);
        cancelButton.addEventListener('click', gThis.doCancelEpisode);
        dialog.showModal();
    }

    doRemoveEpisode(e) {
        const dialog = document.querySelector("#review-dialog");
        const episodeId = e.currentTarget.getAttribute('data-id');
        const ueId = e.currentTarget.getAttribute('data-ue-id');
        dialog.close();
        gThis.doRemoveEventListeners();
        gThis.removeEpisode(episodeId, ueId);
    }

    doNowEpisode(e) {
        const dialog = document.querySelector("#review-dialog");
        const episodeId = e.currentTarget.getAttribute('data-ue-id');
        dialog.close();
        gThis.doRemoveEventListeners();
        gThis.nowEpisode(e, episodeId);
    }

    doAddEpisode(e) {
        const dialog = document.querySelector("#review-dialog");
        const episodeId = e.currentTarget.getAttribute('data-id');
        dialog.close();
        gThis.doRemoveEventListeners();
        gThis.addEpisode(e, episodeId);
    }

    doCancelEpisode() {
        const dialog = document.querySelector("#review-dialog");
        dialog.close();
        gThis.doRemoveEventListeners();
    }

    doRemoveEventListeners() {
        const dialog = document.querySelector("#review-dialog");
        const removeButton = dialog.querySelector('button[value="remove"]');
        const watchButton = dialog.querySelector('button[value="watch"]');
        const nowButton = dialog.querySelector('button[value="now"]');
        const cancelButton = dialog.querySelector('button[value="cancel"]');
        removeButton.removeEventListener('click', gThis.doRemoveEpisode);
        watchButton.removeEventListener('click', gThis.doAddEpisode);
        nowButton.removeEventListener('click', gThis.doNowEpisode);
        cancelButton.removeEventListener('click', gThis.doCancelEpisode);
    }

    removeEpisode(episodeId, ueId) {
        const selector = '.remove-this-episode[data-id="' + episodeId + '"]';
        const episode = document.querySelector(selector);
        const sId = episode.getAttribute('data-show-id');
        const episodeNumber = episode.getAttribute('data-e-number');
        const seasonNumber = episode.getAttribute('data-s-number');
        const lastEpisode = episode.getAttribute('data-last-episode');
        const seriesId = episode.getAttribute('data-series-id');
        let views = parseInt(episode.getAttribute('data-views'));
        fetch('/api/episode/remove', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                showId: sId,
                userEpisodeId: ueId,
                seasonNumber: seasonNumber,
                episodeNumber: episodeNumber
            })
        }).then((response) => response.json())
            .then(data => {
                views--;

                episode.setAttribute('data-views', '' + views);
                const numberDiv = episode.closest('.episode').querySelector('.number');
                numberDiv.setAttribute('data-title', "x" + views);
                gThis.toolTips.init(numberDiv);
                if (views > 0) {
                    return;
                }
                const progressDiv = document.querySelector('.progress');
                progressDiv.setAttribute('data-value', data['progress']);
                gThis.setProgress();

                const airDateDiv = episode.closest('.episode').querySelector('.air-date');
                const watchedAtDiv = airDateDiv.querySelector(`.watched-at[data-ue-id="${ueId}"]`);
                watchedAtDiv.remove();

                if (gThis.intervals[episodeId] > 0) {
                    clearInterval(gThis.intervals[episodeId]);
                    gThis.intervals[episodeId] = 0;
                }

                const quickEpisodeLink = document.querySelector('.quick-episode[data-number="' + episodeNumber + '"]');
                quickEpisodeLink.classList.remove('watched');

                numberDiv.classList.remove('watched');

                const substituteNameDiv = episode.closest('.episode').querySelector('.substitute');
                substituteNameDiv?.classList.add('watched');

                const episodeWatchLinks = episode.closest('.episode').querySelector('.watch-links');
                episodeWatchLinks?.closest('.user-actions').classList.remove('d-none');

                const newEpisode = document.createElement('div');
                newEpisode.classList.add('add-this-episode');
                newEpisode.setAttribute('data-id', episodeId);
                newEpisode.setAttribute('data-show-id', sId);
                newEpisode.setAttribute('data-series-id', seriesId);
                newEpisode.setAttribute('data-ue-id', ueId);
                newEpisode.setAttribute('data-e-number', episodeNumber);
                newEpisode.setAttribute('data-s-number', seasonNumber);
                newEpisode.setAttribute('data-last-episode', lastEpisode);
                newEpisode.setAttribute('data-views', '0');
                newEpisode.setAttribute('data-title', gThis.text.markAsWatched);
                newEpisode.appendChild(gThis.getSvg('plus'));
                newEpisode.addEventListener('click', gThis.addEpisode);
                episode.parentElement.appendChild(newEpisode);
                gThis.toolTips.init(newEpisode);

                const episodeProps = episode.parentElement.querySelectorAll('div[class^=select]');
                episodeProps.forEach(prop => {
                    prop.remove();
                });

                const backToTopLink = episode.parentElement.querySelector('.back-to-top');
                episode.parentElement.appendChild(backToTopLink);

                const backToTopSeries = episode.parentElement.querySelector('.back-to-series').closest('a');
                episode.parentElement.appendChild(backToTopSeries);

                episode.remove();
            });
    }

    selectProvider(e) {
        if (gThis.handleClick(e)) {
            return;
        }
        const selectProviderDiv = e.currentTarget
        const episodeId = selectProviderDiv.getAttribute('data-ue-id');
        const flatrate = gThis.seasonProvider['flatrate'];
        const providerList = document.createElement('div');
        providerList.classList.add('list');
        providerList.setAttribute('data-id', 'provider-' + episodeId);
        providerList.setAttribute('data-save', 'saveProvider');
        selectProviderDiv.appendChild(providerList);
        if (flatrate.length > 0) {
            for (const provider of flatrate) {
                gThis.addProviderItem(provider, episodeId, providerList, selectProviderDiv);
            }
            const separator = document.createElement('div');
            separator.classList.add('separator');
            providerList.appendChild(separator);
        }/* else {*/
        for (const provider of gThis.providerArray) {
            gThis.addProviderItem(provider, episodeId, providerList, selectProviderDiv);
        }
        /*}*/
        gThis.listInput(providerList);
        gThis.listTrashButton(providerList, selectProviderDiv);
        gThis.toolTips.hide();
        gThis.toolTips.init(providerList);
    }

    addProviderItem(provider, episodeId, providerList, selectProviderDiv) {
        const providerDiv = document.createElement('div');
        providerDiv.classList.add('item');
        providerDiv.setAttribute('data-provider-id', provider['provider_id'].toString());
        providerDiv.setAttribute('data-title', provider['provider_name']);
        providerDiv.innerHTML = '<img src="' + provider['logo_path'] + '" alt="' + provider['provider_name'] + '">';
        providerDiv.addEventListener('click', () => {
            const providerId = providerDiv.getAttribute('data-provider-id');
            gThis.saveProvider(episodeId, providerId, selectProviderDiv);
        });
        providerList.appendChild(providerDiv);
    }

    selectDevice(e) {
        if (gThis.handleClick(e)) {
            return;
        }
        const selectDeviceDiv = e.currentTarget;
        const episodeId = selectDeviceDiv.getAttribute('data-ue-id');
        const deviceList = document.createElement('div');
        deviceList.classList.add('list');
        deviceList.setAttribute('data-id', 'device-' + episodeId);
        deviceList.setAttribute('data-save', 'saveDevice');
        selectDeviceDiv.appendChild(deviceList);
        for (const device of gThis.devices) {
            gThis.addDeviceItem(device, episodeId, deviceList, selectDeviceDiv);
        }
        gThis.listTrashButton(deviceList, selectDeviceDiv);
        gThis.toolTips.hide();
        gThis.toolTips.init(deviceList);
    }

    addDeviceItem(device, episodeId, deviceList, selectDeviceDiv) {
        const deviceSvg = document.createElement('div');
        deviceSvg.classList.add('item');
        deviceSvg.setAttribute('data-id', device['id']);
        deviceSvg.setAttribute('data-title', gThis.text[device['name']]);
        deviceSvg.appendChild(gThis.getSvg('device-' + device['id']));
        deviceSvg.addEventListener('click', () => {
            const deviceId = deviceSvg.getAttribute('data-id');
            gThis.saveDevice(episodeId, deviceId, selectDeviceDiv);
        });
        deviceList.appendChild(deviceSvg);
    }

    getDeviceName(id) {
        const devices = gThis.devices;
        for (const device of devices) {
            if (device['id'] === id) {
                return device.name;
            }
        }
        return null;
    }

    getSvg(id) {
        const clone = document.querySelector('.svgs').querySelector('svg[id="' + id + '"]').cloneNode(true);
        clone.removeAttribute('id');
        return clone;
    }

    selectVote(e) {
        if (gThis.handleClick(e)) {
            return;
        }
        const selectVoteDiv = e.currentTarget;
        const episodeId = selectVoteDiv.getAttribute('data-ue-id');
        const voteList = document.createElement('div');
        voteList.classList.add('list');
        voteList.setAttribute('data-id', 'vote-' + episodeId);
        voteList.setAttribute('data-save', 'saveVote');
        selectVoteDiv.appendChild(voteList);
        for (let i = 1; i <= 12; i++) {
            const vote = document.createElement('div');
            vote.classList.add('item');
            vote.setAttribute('data-vote', i.toString());
            vote.setAttribute('data-title', i.toString());
            vote.innerHTML = i.toString();
            vote.addEventListener('click', () => {
                const voteValue = vote.getAttribute('data-vote');
                gThis.saveVote(episodeId, voteValue, selectVoteDiv);
            });
            voteList.appendChild(vote);
        }
        gThis.listTrashButton(voteList, selectVoteDiv);
        gThis.toolTips.hide();
    }

    saveProvider(episodeId, providerId, selectProviderDiv = null) {
        fetch('/api/episode/provider/' + episodeId, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                providerId: providerId
            })
        }).then(function (response) {
            if (response.ok) {
                if (selectProviderDiv) {
                    if (providerId === -1) {
                        const svgPlus = gThis.getSvg('plus');
                        selectProviderDiv.innerHTML = '';
                        selectProviderDiv.setAttribute('data-title', gThis.text.provider);
                        selectProviderDiv.appendChild(svgPlus);
                        gThis.toolTips.init(selectProviderDiv);
                    } else {
                        selectProviderDiv.innerHTML = '<img src="' + gThis.providers.logos[providerId] + '" alt="' + gThis.providers.names[providerId] + '">';
                    }
                }
            }
        });
    }

    saveDevice(episodeId, deviceId, selectDeviceDiv = null) {
        fetch('/api/episode/device/' + episodeId, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                deviceId: deviceId
            })
        }).then(function (response) {
            if (response.ok) {
                if (selectDeviceDiv) {
                    if (deviceId === -1) {
                        const svgPlus = gThis.getSvg('plus');
                        selectDeviceDiv.innerHTML = '';
                        selectDeviceDiv.setAttribute('data-title', gThis.text.device);
                        selectDeviceDiv.appendChild(svgPlus);
                        gThis.toolTips.init(selectDeviceDiv);
                    } else {
                        selectDeviceDiv.innerHTML = '';
                        selectDeviceDiv.appendChild(gThis.getSvg('device-' + deviceId));
                    }
                }
            }
        });
    }

    saveVote(episodeId, voteValue, selectVoteDiv = null) {
        fetch('/api/episode/vote/' + episodeId, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                vote: voteValue
            })
        }).then(function (response) {
            if (response.ok) {
                if (selectVoteDiv) {
                    if (voteValue === -1) {
                        const svgPlus = gThis.getSvg('plus');
                        selectVoteDiv.innerHTML = '';
                        selectVoteDiv.setAttribute('data-title', gThis.text.rating);
                        selectVoteDiv.appendChild(svgPlus);
                        gThis.toolTips.init(selectVoteDiv);
                    } else {
                        selectVoteDiv.innerHTML = voteValue;

                        const voteGraphDiv = document.querySelector('.vote-graph');
                        const voteDiv = voteGraphDiv.querySelector('.vote[data-ep-id="' + episodeId + '"]');
                        const div = voteDiv.querySelector('div');
                        const episodeVoteDiv = voteDiv.closest('.episode-vote');
                        div.classList.remove('dashed-vote');
                        div.classList.add('user-vote');
                        div.style.height = (voteValue * 16) + 'px';
                        div.innerText = voteValue;
                        episodeVoteDiv.setAttribute('data-vote', voteValue);

                        const voteAverageDiv = voteGraphDiv.querySelector('.vote-average');
                        const voteDivs = voteGraphDiv.querySelectorAll('.episode-vote');
                        let sum = 0, count = 0;
                        voteDivs.forEach((element) => {
                            const vote = 1 * element.getAttribute('data-vote');
                            if (vote) {
                                sum += vote;
                                count++;
                            }
                        });
                        if (count) {
                            let result = (sum / count);
                            if (result > 10) result = result.toFixed(0) + "+"; else result = result.toFixed(1);
                            voteAverageDiv.innerHTML = result + " / 10";
                        } else {
                            voteAverageDiv.innerHTML = gThis.text['No votes'];
                        }
                    }
                }
            }
        });
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

    listInput(list, type = 'text', size = '10') {
        const listId = list.getAttribute('data-id');
        const input = document.createElement('input');
        input.setAttribute('id', listId);
        input.setAttribute('type', type);
        input.setAttribute('size', size);
        input.setAttribute('placeholder', gThis.text.Search);
        list.appendChild(input);
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                list.remove();
            }
        });
        input.addEventListener('input', (e) => {
            const value = e.target.value.toLowerCase();
            const items = list.querySelectorAll('.item');
            items.forEach(item => {
                const title = item.getAttribute('data-title').toLowerCase();
                if (title.includes(value)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        });
        input.focus({'preventScroll': true});
    }

    listTrashButton(list, selectDiv) {
        const deleteButton = document.createElement('button');
        const svgDelete = gThis.getSvg('delete');
        const id = list.getAttribute('data-id').split('-')[1];
        const saveFunction = list.getAttribute('data-save');

        deleteButton.appendChild(svgDelete);
        deleteButton.addEventListener('click', () => {
            switch (saveFunction) {
                case 'saveProvider':
                    gThis.saveProvider(id, -1, selectDiv);
                    break;
                case 'saveDevice':
                    gThis.saveDevice(id, -1, selectDiv);
                    break;
                case 'saveVote':
                    gThis.saveVote(id, -1, selectDiv);
                    break;
            }
        });
        list.appendChild(deleteButton);
    }

    handleClick(e) {
        e.preventDefault();
        e.stopPropagation();
        const list = document.querySelector('.list');
        if (list) {
            list.remove();
            gThis.toolTips.hide();
            return true;
        }
        return false;
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
        if (Math.floor(color.lightness) > 160) {
            const voteGraphDiv = document.querySelector('.vote-graph');
            voteGraphDiv.classList.add('dark');
        }
    }
}
