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
        this.initialDay = new Date().getDate();
        console.log(this.initialDay);
        // this.saving = null;
        // this.lastMinute = 0;
        // this.lastHour = 0;
        // this.lastDay = 0;

        this.toolTips = new ToolTips();
    }

    init() {
        // TODO: recharger la page au changement de jour (mettre à jour les dates relatives)
        setInterval(this.checkDayChange, 60000);// chaque minute

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

        const quickEpisodeLinks = document.querySelectorAll('.quick-episode');
        quickEpisodeLinks.forEach(episode => {
            episode.addEventListener('click', e => {
                e.preventDefault();
                const episodeNumber = e.currentTarget.getAttribute('data-number');
                const selector = '#episode-' + episodeNumber;
                const target = document.querySelector(selector);
                target.scrollIntoView({behavior: 'smooth'});
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
            const edit = info.querySelector('.edit');
            edit.addEventListener('click', this.openTitleForm);
        });

        const usOverviews = document.querySelectorAll('.overview.us');
        usOverviews.forEach(overview => {
            overview.addEventListener('paste', this.pasteTranslation);

            // const text = overview.innerText;
            // const svgPlus = overview.querySelector('svg');
            overview.addEventListener('click', () => {
                // Select the text
                const range = document.createRange();
                range.selectNodeContents(overview);
                const selection = window.getSelection();
                selection.removeAllRanges();
                selection.addRange(range);
            });
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

        document.addEventListener('paste', async (e) => {
            // Récupérer l'élément sous la souris
            const target = e.target;
            const targetStillDiv = target.classList.contains('.still') ? target : target.closest('.still');

            if (!targetStillDiv) {
                return;
            }
            e.preventDefault();
            const seriesId = targetStillDiv.getAttribute('data-series-id');
            const seasonId = targetStillDiv.getAttribute('data-season-id');
            const episodeId = targetStillDiv.getAttribute('data-episode-id');
            const fileName = seriesId + '-' + seasonId + '-' + episodeId + '.';

            for (const clipboardItem of e.clipboardData.files) {
                if (clipboardItem.type.startsWith('image/')) {
                    // Save the image in %kernel.dir%/public/series/stills/season-xx/episode-xx.jpg
                    const formData = new FormData();
                    formData.append('file', clipboardItem, fileName + clipboardItem.type.split('/')[1]);
                    const response = await fetch('/' + gThis.lang + '/series/episode/still/' + episodeId, {
                        method: 'POST',
                        body: formData
                    });
                    if (response.ok) {
                        let still;
                        still = targetStillDiv.querySelector('img');
                        if (still) {
                            still.src = URL.createObjectURL(clipboardItem);
                        } else {
                            const noPoster = targetStillDiv.querySelector('.no-poster');
                            noPoster?.remove();
                            still = document.createElement('img');
                            still.src = URL.createObjectURL(clipboardItem);
                            targetStillDiv.appendChild(still);
                        }
                    }
                }
            }
        });
    }

    checkDayChange() {
        const currentDay = new Date().getDate();
        console.log({'initial day': gThis.initialDay, 'current day': currentDay});
        if (currentDay !== gThis.initialDay) {
            window.location.reload();
        }
    }

    openTitleForm(e) {
        const editDiv = e.currentTarget;
        const nameDiv = editDiv.closest('.episode-name');
        const contentDiv = nameDiv.querySelector('.name');
        let substituteDiv = nameDiv.querySelector('.substitute');
        const name = substituteDiv?.innerText.length ? substituteDiv.innerText : contentDiv.innerText;
        const form = document.createElement('form');
        form.setAttribute('method', 'post');
        form.setAttribute('action', '');
        form.setAttribute('autocomplete', 'off');
        const input = document.createElement('input');
        input.setAttribute('type', 'text');
        input.setAttribute('name', 'title');
        input.setAttribute('value', name);
        input.setAttribute('maxlength', '255');
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
            const substituteName = input.value;
            fetch('/' + gThis.lang + '/series/episode/update/name/' + editDiv.getAttribute('data-id'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    name: substituteName
                })
            }).then(function (response) {
                if (response.ok) {
                    const needToCreateSubstitute = substituteName.length && !substituteDiv;
                    if (needToCreateSubstitute) {
                        substituteDiv = document.createElement('div');
                        substituteDiv.classList.add('substitute');
                        nameDiv.insertBefore(substituteDiv, editDiv);
                        const episodeWatched = nameDiv.closest('.episode').querySelector('.remove-this-episode');
                        if (episodeWatched) {
                            substituteDiv.classList.add('watched');
                        }
                    }
                    if (substituteName.length) {
                        substituteDiv.innerText = substituteName;
                    } else {
                        substituteDiv.remove();
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
        nameDiv.appendChild(form);

        input.focus();
        input.select();
    }

    pasteTranslation(e) {
        e.preventDefault();
        const overviewDiv = e.currentTarget;
        const localizedText = e.clipboardData.getData('text/plain');
        fetch('/' + gThis.lang + '/series/episode/localize/overview/' + overviewDiv.getAttribute('data-id'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                overview: localizedText
            })
        }).then(function (response) {
            return response.json();
        }).then(function (json) {
            // if (response.ok) {
            gThis.toolTips.hide();
            overviewDiv.innerText = json.overview;
            overviewDiv.classList.remove('us');
            overviewDiv.removeEventListener('paste', gThis.pasteTranslation);
            // }
        });
    }

    addEpisode(e, episodeId = null) {
        gThis.toolTips.hide();
        const selector = episodeId ? '.remove-this-episode[data-id="' + episodeId + '"]' : null;
        const episode = episodeId ? document.querySelector(selector) : e.currentTarget;
        const sId = episode.getAttribute('data-show-id');
        const id = episode.getAttribute('data-id');
        const episodeNumber = episode.getAttribute('data-e-number');
        const seasonNumber = episode.getAttribute('data-s-number');
        const lastEpisode = episode.getAttribute('data-last-episode');
        const views = parseInt(episode.getAttribute('data-views') ?? "0");
        fetch('/' + gThis.lang + '/series/episode/add/' + id, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                showId: sId,
                lastEpisode: lastEpisode,
                seasonNumber: seasonNumber,
                episodeNumber: episodeNumber
            })
        }).then((response) => response.json())
            .then(data => {
                // TODO: Vérifier "data"
                console.log(data);
                const airDateDiv = episode.closest('.episode').querySelector('.air-date');
                let watchedAtDiv = airDateDiv.querySelector(".watched-at");
                if (!watchedAtDiv) {
                    watchedAtDiv = document.createElement('div');
                    watchedAtDiv.classList.add('watched-at');
                    watchedAtDiv.addEventListener('click', gThis.modifyWatchedAtOpen);
                    airDateDiv.appendChild(watchedAtDiv);
                }
                watchedAtDiv.innerHTML = data['viewedAt'];
                watchedAtDiv.setAttribute('data-watched-at', data['dataViewedAt']);

                const numberDiv = episode.closest('.episode').querySelector('.number');
                numberDiv.setAttribute('data-title', "x" + (views + 1));

                episode.setAttribute('data-views', '' + (views + 1));
                episode.setAttribute('data-title', gThis.text.now);
                const now = new Date();
                episode.setAttribute('data-time', now.toISOString());
                episode.addEventListener('mouseenter', gThis.updateRelativeTime);
                if (episodeId) {
                    return;
                }

                const newEpisode = document.createElement('div');
                newEpisode.classList.add('remove-this-episode');
                newEpisode.setAttribute('data-id', id);
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
                episode.parentElement.appendChild(newEpisode);
                gThis.toolTips.init(episode.querySelector('.remove-this-episode'));

                const quickEpisodeLink = document.querySelector('.quick-episode[data-number="' + episodeNumber + '"]');
                quickEpisodeLink.classList.add('watched');

                numberDiv.classList.add('watched');

                const substituteNameDiv = episode.closest('.episode').querySelector('.substitute');
                substituteNameDiv?.classList.add('watched');

                const episodeWatchLinks = episode.closest('.episode').querySelector('.watch-links');
                episodeWatchLinks?.classList.add('hidden');

                // Mise à jour du menu
                const seriesId = episode.getAttribute('data-series-id');
                let episodesOfTheDayInMenu = document.querySelectorAll('a[id^="eotd-menu-item-"]');

                episodesOfTheDayInMenu.forEach(eotd => {
                    if (eotd.getAttribute('id').includes(seriesId)) {
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

                const previousEpisode = episode.closest('.episodes').querySelector('.remove-this-episode[data-e-number="' + (episodeNumber - 1) + '"]');
                const previousProvider = previousEpisode?.parentElement.querySelector('.select-provider');
                if (previousProvider) {
                    const clone = previousProvider.cloneNode(true);
                    clone.setAttribute('data-id', id);
                    clone.addEventListener('click', gThis.selectProvider);
                    episode.parentElement.appendChild(clone);
                } else {
                    const providerDiv = document.createElement('div');
                    providerDiv.classList.add('select-provider');
                    providerDiv.setAttribute('data-id', id);
                    providerDiv.setAttribute('data-title', gThis.text.provider);
                    providerDiv.appendChild(gThis.getSvg('plus'));
                    providerDiv.addEventListener('click', gThis.selectProvider);
                    episode.parentElement.appendChild(providerDiv);
                }

                const previousDevice = previousEpisode?.parentElement.querySelector('.select-device');
                if (previousDevice) {
                    const clone = previousDevice.cloneNode(true);
                    clone.setAttribute('data-id', id);
                    clone.addEventListener('click', gThis.selectDevice);
                    episode.parentElement.appendChild(clone);
                } else {
                    const device = document.createElement('div');
                    device.classList.add('select-device');
                    device.setAttribute('data-id', id);
                    device.setAttribute('data-title', gThis.text.device);
                    device.appendChild(gThis.getSvg('plus'));
                    device.addEventListener('click', gThis.selectDevice);
                    episode.parentElement.appendChild(device);
                }

                const vote = document.createElement('div');
                vote.classList.add('select-vote');
                vote.setAttribute('data-id', id);
                vote.setAttribute('data-title', gThis.text.rating);
                vote.appendChild(gThis.getSvg('plus'));
                vote.addEventListener('click', gThis.selectVote);
                // vote.addEventListener('wheel', gThis.wheelVote);
                episode.parentElement.appendChild(vote);

                const backToTopLink = episode.parentElement.querySelector('.back-to-top');
                episode.parentElement.appendChild(backToTopLink);

                const backToSeriesLink = episode.parentElement.querySelector('.back-to-series').closest('a');
                episode.parentElement.appendChild(backToSeriesLink);

                episode.remove();
            });
    }

    nowEpisode(e, episodeId = null) { // Ajuste la date de visionnage à maintenant
        gThis.toolTips.hide();
        const selector = episodeId ? '.remove-this-episode[data-id="' + episodeId + '"]' : null;
        const episode = episodeId ? document.querySelector(selector) : e.currentTarget;
        const sId = episode.getAttribute('data-show-id');
        const id = episode.getAttribute('data-id');
        const episodeNumber = episode.getAttribute('data-e-number');
        const seasonNumber = episode.getAttribute('data-s-number');

        fetch('/' + gThis.lang + '/series/episode/touch/' + id, {
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
        const watchedAt = watchedAtDiv.getAttribute('data-watched-at');
        const airDateDiv = watchedAtDiv.closest('.air-date');
        const watchedAtModifyDiv = document.createElement('div');
        watchedAtModifyDiv.classList.add('watched-at-modify');
        const datetimeInput = document.createElement('input');
        datetimeInput.setAttribute('type', 'datetime-local');
        datetimeInput.setAttribute('value', watchedAt);
        const datetimeSaveButton = document.createElement('button');
        datetimeSaveButton.textContent = 'OK';
        const datetimeCancelButton = document.createElement('button');
        datetimeCancelButton.textContent = 'X';
        watchedAtModifyDiv.appendChild(datetimeInput);
        watchedAtModifyDiv.appendChild(datetimeSaveButton);
        watchedAtModifyDiv.appendChild(datetimeCancelButton);
        airDateDiv.appendChild(watchedAtModifyDiv);
        watchedAtDiv.style.display = 'none';

        datetimeSaveButton.addEventListener('click', gThis.touchEpisode);
        datetimeCancelButton.addEventListener('click', () => {
            watchedAtModifyDiv.remove();
            watchedAtDiv.style.display = 'flex';
        });
    }

    touchEpisode(e) { // Ajuste la date de visionnage à la valeur de l'input datetime-local
        const datetimeSaveButton = e.currentTarget;
        const airDateDiv = datetimeSaveButton.closest('.air-date');
        const watchedAtDiv = airDateDiv.querySelector('.watched-at');
        const watchedAtModifyDiv = datetimeSaveButton.parentElement;
        const datetimeInput = watchedAtModifyDiv.querySelector('input');
        const newDatetime = datetimeInput.value;
        console.log(newDatetime);
        const episode = datetimeSaveButton.closest('.episode').querySelector('.remove-this-episode');
        const sId = episode.getAttribute('data-show-id');
        const id = episode.getAttribute('data-id');
        const episodeNumber = episode.getAttribute('data-e-number');
        const seasonNumber = episode.getAttribute('data-s-number');

        fetch('/' + gThis.lang + '/series/episode/touch/' + id, {
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
                watchedAtDiv.innerHTML = data['viewedAt'];
                watchedAtDiv.setAttribute('data-watched-at', data['dataViewedAt']);
                episode.setAttribute('data-title', gThis.text.now);
                const now = new Date();
                episode.setAttribute('data-time', now.toISOString());
            });
        watchedAtModifyDiv.remove();
        watchedAtDiv.style.display = 'flex';
    }

    removeOrReviewEpisode(e) {
        gThis.toolTips.hide();
        const dialog = document.querySelector("#review-dialog");
        const episode = e.currentTarget;
        const id = episode.getAttribute('data-id');
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
        dialog.close();
        gThis.doRemoveEventListeners();
        gThis.removeEpisode(episodeId);
    }

    doNowEpisode(e) {
        const dialog = document.querySelector("#review-dialog");
        const episodeId = e.currentTarget.getAttribute('data-id');
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

    removeEpisode(episodeId) {
        const selector = '.remove-this-episode[data-id="' + episodeId + '"]';
        const episode = document.querySelector(selector);
        const sId = episode.getAttribute('data-show-id');
        const episodeNumber = episode.getAttribute('data-e-number');
        const seasonNumber = episode.getAttribute('data-s-number');
        const lastEpisode = episode.getAttribute('data-last-episode');
        let views = parseInt(episode.getAttribute('data-views'));
        fetch('/' + gThis.lang + '/series/episode/remove/' + episodeId, {
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
        }).then(function (response) {
            if (response.ok) {
                views--;

                episode.setAttribute('data-views', '' + views);
                const numberDiv = episode.closest('.episode').querySelector('.number');
                numberDiv.setAttribute('data-title', "x" + views);
                gThis.toolTips.init(numberDiv);
                if (views > 0) {
                    return;
                }
                const airDateDiv = episode.closest('.episode').querySelector('.air-date');
                const watchedAtDiv = airDateDiv.querySelector('.watched-at');
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
                episodeWatchLinks?.classList.remove('hidden');

                const newEpisode = document.createElement('div');
                newEpisode.classList.add('add-this-episode');
                newEpisode.setAttribute('data-id', episodeId);
                newEpisode.setAttribute('data-show-id', sId);
                newEpisode.setAttribute('data-e-number', episodeNumber);
                newEpisode.setAttribute('data-s-number', seasonNumber);
                newEpisode.setAttribute('data-last-episode', lastEpisode);
                newEpisode.setAttribute('data-views', '0');
                newEpisode.setAttribute('data-title', gThis.text.add);
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
            }
        });
    }

    selectProvider(e) {
        if (gThis.handleClick(e)) {
            return;
        }
        const selectProviderDiv = e.currentTarget
        const episodeId = selectProviderDiv.getAttribute('data-id');
        const flatrate = gThis.seasonProvider['flatrate'];
        const providerList = document.createElement('div');
        providerList.classList.add('list');
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
            fetch('/' + gThis.lang + '/series/episode/provider/' + episodeId, {
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
                    selectProviderDiv.innerHTML = '<img src="' + gThis.providers.logos[providerId] + '" alt="' + gThis.providers.names[providerId] + '">';
                    selectProviderDiv.setAttribute('data-title', gThis.providers.names[providerId]);
                    gThis.toolTips.init(selectProviderDiv);
                    providerList.remove();
                }
            });
        });
        providerList.appendChild(providerDiv);
    }

    selectDevice(e) {
        if (gThis.handleClick(e)) {
            return;
        }
        const selectDeviceDiv = e.currentTarget;
        const episodeId = selectDeviceDiv.getAttribute('data-id');
        const deviceList = document.createElement('div');
        deviceList.classList.add('list');
        selectDeviceDiv.appendChild(deviceList);
        for (const device of gThis.devices) {
            gThis.addDeviceItem(device, episodeId, deviceList, selectDeviceDiv);
        }
        gThis.listInput(deviceList);
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
            fetch('/' + gThis.lang + '/series/episode/device/' + episodeId, {
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
                    selectDeviceDiv.innerHTML = '';
                    selectDeviceDiv.appendChild(gThis.getSvg('device-' + device['id']));
                    selectDeviceDiv.setAttribute('data-title', gThis.text[device['name']]);
                    gThis.toolTips.init(selectDeviceDiv);
                    deviceList.remove();
                }
            });
        });
        deviceList.appendChild(deviceSvg);
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
        const episodeId = selectVoteDiv.getAttribute('data-id');
        const voteList = document.createElement('div');
        voteList.classList.add('list');
        selectVoteDiv.appendChild(voteList);
        for (let i = 1; i <= 10; i++) {
            const vote = document.createElement('div');
            vote.classList.add('item');
            vote.setAttribute('data-vote', i.toString());
            vote.setAttribute('data-title', i.toString());
            vote.innerHTML = i.toString();
            vote.addEventListener('click', () => {
                const voteValue = vote.getAttribute('data-vote');
                gThis.saveVote(episodeId, voteValue, selectVoteDiv, voteList);
                // fetch('/' + gThis.lang + '/series/episode/vote/' + episodeId, {
                //     method: 'POST',
                //     headers: {
                //         'Content-Type': 'application/json',
                //         'X-Requested-With': 'XMLHttpRequest'
                //     },
                //     body: JSON.stringify({
                //         vote: voteValue
                //     })
                // }).then(function (response) {
                //     if (response.ok) {
                //         selectVoteDiv.innerHTML = voteValue;
                //         voteList.remove();
                //     }
                // });
            });
            voteList.appendChild(vote);
        }
        gThis.toolTips.hide();
        gThis.listInput(voteList);
    }

    // wheelVote(e) {
    //     e.preventDefault();
    //     // Save the new value every 500ms
    //     if (gThis.saving) {
    //         return;
    //     }
    //     gThis.saving = setTimeout(() => {
    //         if (e.deltaY > 0) {
    //             gThis.incVote(e);
    //         } else {
    //             gThis.decVote(e);
    //         }
    //     }, 500);
    // }

    // incVote(e) {
    //     const selectVoteDiv = e.target;
    //     const innerText = selectVoteDiv.innerText;
    //     const episodeId = selectVoteDiv.getAttribute('data-id');
    //     const voteValue = innerText === '+' ? 0 : parseInt(selectVoteDiv.innerText);
    //     if (voteValue < 10) {
    //         selectVoteDiv.innerText = (voteValue + 1).toString();
    //         gThis.saveVote(episodeId, voteValue + 1);
    //     }
    // }
    //
    // decVote(e) {
    //     const selectVoteDiv = e.target;
    //     const innerText = selectVoteDiv.innerText;
    //     const episodeId = selectVoteDiv.getAttribute('data-id');
    //     const voteValue = innerText === '+' ? 11 : parseInt(selectVoteDiv.innerText);
    //     if (voteValue > 1) {
    //         selectVoteDiv.innerText = (voteValue - 1).toString();
    //         gThis.saveVote(episodeId, voteValue - 1);
    //     }
    // }

    saveVote(episodeId, voteValue, selectVoteDiv = null, voteList = null) {
        fetch('/' + gThis.lang + '/series/episode/vote/' + episodeId, {
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
                if (selectVoteDiv) selectVoteDiv.innerHTML = voteValue;
                if (voteList) voteList.remove();
                if (gThis.saving) {
                    clearTimeout(gThis.saving);
                    gThis.saving = null;
                }
            }
        });
    }

    listInput(list) {

        const input = document.createElement('input');
        input.setAttribute('type', 'text');
        input.setAttribute('size', '10');
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

    handleClick(e) {
        e.preventDefault();
        e.stopPropagation();
        const list = document.querySelector('.list');
        if (list) {
            list.remove();
            return true;
        }
        return false;
    }
}
