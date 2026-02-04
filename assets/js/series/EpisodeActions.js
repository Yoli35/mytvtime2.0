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
import JSConfetti from "js-confetti";
import {SeasonComments} from "SeasonComments";


export class EpisodeActions {
    constructor(globs, flashMessage, toolTips, menu) {
        this.lang = document.documentElement.lang;
        this.toolTips = toolTips;
        this.flashMessage = flashMessage;
        this.devices = globs.devices;
        this.providers = globs.providers;
        this.translations = globs.translations;
        this.providerArray = globs.providers.list;
        this.seasonProvider = globs.seasonProvider;
        this.seasonNumber = globs.seasonNumber;
        this.seriesId = globs.seriesId;
        this.user = globs.user;
        this.menu = menu;
        this.intervals = [];

        this.seasonComments = new SeasonComments(this.user, this.seriesId, this.seasonNumber, this.translations);

        this.setProgress = this.setProgress.bind(this);
        this.addEpisode = this.addEpisode.bind(this);
        this.removeEpisode = this.removeEpisode.bind(this);
        this.removeOrReviewEpisode = this.removeOrReviewEpisode.bind(this);
        this.nowEpisode = this.nowEpisode.bind(this);
        this.touchEpisode = this.touchEpisode.bind(this);
        this.doRemoveEpisode = this.doRemoveEpisode.bind(this);
        this.doNowEpisode = this.doNowEpisode.bind(this);
        this.doAddEpisode = this.doAddEpisode.bind(this);
        this.doCancelEpisode = this.doCancelEpisode.bind(this);
        this.doRemoveEventListeners = this.doRemoveEventListeners.bind(this);
        this.updateRelativeTime = this.updateRelativeTime.bind(this);
        this.modifyWatchedAtOpen = this.modifyWatchedAtOpen.bind(this);
        this.selectProvider = this.selectProvider.bind(this);
        this.selectDevice = this.selectDevice.bind(this);
        this.selectVote = this.selectVote.bind(this);
        this.saveProvider = this.saveProvider.bind(this);
        this.saveDevice = this.saveDevice.bind(this);
        this.saveVote = this.saveVote.bind(this);
        this.getSvg = this.getSvg.bind(this);
    }

    init() {
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
            provider.addEventListener('click', this.selectProvider);
        });

        const userEpisodeDevices = document.querySelectorAll('.select-device');
        userEpisodeDevices.forEach(device => {
            device.addEventListener('click', this.selectDevice);
        });

        const userEpisodeVotes = document.querySelectorAll('.select-vote');
        userEpisodeVotes.forEach(vote => {
            vote.addEventListener('click', this.selectVote);
            // vote.addEventListener('wheel', gThis.wheelVote);
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

    addEpisode(e, episodeId = null) {
        this.toolTips.hide();
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
        const episodeDiv = episode.closest('.episode')
        const substituteNameDiv = episodeDiv ? episodeDiv.querySelector('.substitute') : null;
        const episodeWatchLinks = episodeDiv ? episodeDiv.querySelector('.watch-links') : null;
        const numberDiv = episodeDiv ? episodeDiv.querySelector('.number') : null;
        const finaleDivs = episodeDiv ? episodeDiv.querySelectorAll('.finale') : null;

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
                if (episodeDiv) {
                    const airDateDiv = episodeDiv.querySelector('.air-date');
                    const block = document.createElement('div');
                    block.innerHTML = data['airDateBlock'];
                    const newAirDateDiv = block.querySelector('.air-date');
                    const newWatchedAtDivs = block.querySelectorAll('.watched-at');
                    newWatchedAtDivs.forEach(newWatchedAtDiv => {
                        newWatchedAtDiv.addEventListener('click', this.modifyWatchedAtOpen);
                    });
                    airDateDiv.replaceWith(newAirDateDiv);

                    numberDiv.setAttribute('data-title', data['views']);
                }

                const now = new Date();
                if (episodeId) {
                    episode.setAttribute('data-views', '' + (views + 1));
                    episode.setAttribute('data-title', this.translations.now);
                    episode.setAttribute('data-time', now.toISOString());
                    episode.addEventListener('mouseenter', this.updateRelativeTime);
                    return;
                }

                const messages = data['messages'];
                if (messages.length) {
                    messages.forEach(message => {
                        this.flashMessage.add('success', message);
                    });
                }

                const progressDiv = document.querySelector('.progress');
                if (progressDiv) {
                    progressDiv.setAttribute('data-value', data['season_progress']);
                    this.setProgress();
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
                newEpisode.setAttribute('data-title', this.translations.now);
                newEpisode.setAttribute('data-time', now.toISOString());
                this.intervals[id] = setInterval(this.updateRelativeTime, 1000, {currentTarget: newEpisode});
                newEpisode.addEventListener('click', this.removeOrReviewEpisode);
                newEpisode.appendChild(this.getSvg('eye'));
                episode.replaceWith(newEpisode);
                this.toolTips.init(newEpisode);/*episode.querySelector('.remove-this-episode'));*/

                quickEpisodeLink?.classList.add('watched');

                numberDiv?.classList.add('watched');

                substituteNameDiv?.classList.add('watched');

                episodeWatchLinks?.closest('.user-actions').classList.add('d-none');

                finaleDivs?.forEach(f => {
                    f.classList.add('watched');
                });

                // <div className="comment-badge" data-id="{{ episode.id }}" data-title="{{ 'Add a comment or reply to it for the episode'|trans }} {{ episode.episode_number }}">
                //     {{ux_icon('mdi:comment-text-outline')}}
                // </div>;
                const episodesCommentsDiv = document.querySelector(".episodes-comments");
                const commentBadge = document.createElement("div");
                commentBadge.classList.add("comment-badge");
                commentBadge.setAttribute("data-id", id);
                commentBadge.setAttribute("data-title", this.translations['Add a comment or reply to it for the episode'] + " " + episodeNumber);
                const commentSvg = document.querySelector("#svgs #comment-badge-outline").cloneNode(true);
                commentSvg.removeAttribute("id");
                commentBadge.appendChild(commentSvg);
                userEpisode.insertBefore(commentBadge, backToTopLink);
                const episodeGroup = this.seasonComments.createEpisodeGroup(seasonNumber, episodeNumber);
                episodesCommentsDiv.appendChild(episodeGroup);

                const previousEpisode = userEpisode.closest('.episodes').querySelector('.remove-this-episode[data-e-number="' + (episodeNumber - 1) + '"]');
                const previousProvider = previousEpisode?.parentElement.querySelector('.select-provider');
                if (previousProvider) {
                    const clone = previousProvider.cloneNode(true);
                    clone.setAttribute('data-id', id);
                    clone.setAttribute('data-ue-id', ueId);
                    clone.addEventListener('click', this.selectProvider);
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
                            providerDiv.innerHTML = '<img src="' + this.providers.logos[providerId] + '" alt="' + this.providers.names[providerId] + '">';
                            providerDiv.setAttribute('data-title', this.providers.names[providerId]);
                            providerDiv.addEventListener('click', () => {
                                this.saveProvider(ueId, providerId);
                                const deviceDiv = userEpisode.querySelector('.select-device');
                                userEpisode.insertBefore(providerDiv, deviceDiv);
                                const providerDivs = form.querySelectorAll('.select-provider');
                                providerDivs.forEach(providerDiv => {
                                    providerDiv.remove();
                                });
                                dialog.close();
                            });
                            this.toolTips.init(providerDiv);
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
                            providerDiv.innerHTML = '<img src="' + this.providers.logos[providerId] + '" alt="' + this.providers.names[providerId] + '">';
                            providerDiv.setAttribute('data-title', this.providers.names[providerId]);
                            this.toolTips.init(providerDiv);
                        } else {
                            providerDiv.setAttribute('data-title', this.translations.provider);
                            providerDiv.appendChild(this.getSvg('plus'));
                        }
                        providerDiv.addEventListener('click', this.selectProvider);
                        userEpisode.insertBefore(providerDiv, backToTopLink);
                    }
                }

                const previousDevice = previousEpisode?.parentElement.querySelector('.select-device');
                const deviceId = data['deviceId'];
                if (previousDevice) {
                    const clone = previousDevice.cloneNode(true);
                    clone.setAttribute('data-id', id);
                    clone.setAttribute('data-ue-id', ueId);
                    clone.addEventListener('click', this.selectDevice);
                    userEpisode.insertBefore(clone, backToTopLink);
                } else {
                    const deviceDiv = document.createElement('div');
                    deviceDiv.classList.add('select-device');
                    deviceDiv.setAttribute('data-id', id);
                    deviceDiv.setAttribute('data-ue-id', ueId);
                    deviceDiv.setAttribute('data-device-id', deviceId);
                    if (deviceId) {
                        const deviceName = this.getDeviceName(deviceId);
                        deviceDiv.innerHTML = '';
                        deviceDiv.appendChild(this.getSvg('device-' + deviceId));
                        deviceDiv.setAttribute('data-title', this.translations[deviceName]);
                        this.toolTips.init(deviceDiv);
                    } else {
                        deviceDiv.setAttribute('data-title', this.translations.device);
                        deviceDiv.appendChild(this.getSvg('plus'));
                    }
                    deviceDiv.addEventListener('click', this.selectDevice);
                    userEpisode.insertBefore(deviceDiv, backToTopLink);
                }

                const vote = document.createElement('div');
                vote.classList.add('select-vote');
                vote.setAttribute('data-id', id);
                vote.setAttribute('data-ue-id', ueId);
                vote.setAttribute('data-title', this.translations.rating);
                vote.appendChild(this.getSvg('plus'));
                vote.addEventListener('click', this.selectVote);
                // vote.addEventListener('wheel', this.wheelVote);
                userEpisode.insertBefore(vote, backToTopLink);
            });
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
                this.toolTips.init(numberDiv);
                if (views > 0) {
                    return;
                }
                const progressDiv = document.querySelector('.progress');
                progressDiv.setAttribute('data-value', data['progress']);
                this.setProgress();

                const airDateDiv = episode.closest('.episode').querySelector('.air-date');
                const watchedAtDiv = airDateDiv.querySelector(`.watched-at[data-ue-id="${ueId}"]`);
                watchedAtDiv.remove();

                if (this.intervals[episodeId] > 0) {
                    clearInterval(this.intervals[episodeId]);
                    this.intervals[episodeId] = 0;
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
                newEpisode.setAttribute('data-title', this.translations.markAsWatched);
                newEpisode.appendChild(this.getSvg('plus'));
                newEpisode.addEventListener('click', this.addEpisode);
                episode.parentElement.appendChild(newEpisode);
                this.toolTips.init(newEpisode);

                const commentBadge = episode.parentElement.querySelector(".comment-badge");
                commentBadge.remove();
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

    removeOrReviewEpisode(e) {
        this.toolTips.hide();
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
        removeButton.addEventListener('click', this.doRemoveEpisode);
        watchButton.addEventListener('click', this.doAddEpisode);
        nowButton.addEventListener('click', this.doNowEpisode);
        cancelButton.addEventListener('click', this.doCancelEpisode);
        dialog.showModal();
    }

    nowEpisode(e, episodeId = null) { // Ajuste la date de visionnage à maintenant
        this.toolTips.hide();
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
                episode.setAttribute('data-title', this.translations.now);
                const now = new Date();
                episode.setAttribute('data-time', now.toISOString());
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
                newWatchedAtDiv.addEventListener('click', this.modifyWatchedAtOpen);
                watchedAtDiv.replaceWith(newWatchedAtDiv);
                episode.setAttribute('data-title', this.translations.now);
                const now = new Date();
                episode.setAttribute('data-time', now.toISOString());
            });
        watchedAtModifyDiv.remove();
        // watchedAtDiv.style.display = 'flex';
        watchedAtDiv.classList.remove('editing');
    }

    doRemoveEpisode(e) {
        const dialog = document.querySelector("#review-dialog");
        const episodeId = e.currentTarget.getAttribute('data-id');
        const ueId = e.currentTarget.getAttribute('data-ue-id');
        dialog.close();
        this.doRemoveEventListeners();
        this.removeEpisode(episodeId, ueId);
    }

    doNowEpisode(e) {
        const dialog = document.querySelector("#review-dialog");
        const episodeId = e.currentTarget.getAttribute('data-ue-id');
        dialog.close();
        this.doRemoveEventListeners();
        this.nowEpisode(e, episodeId);
    }

    doAddEpisode(e) {
        const dialog = document.querySelector("#review-dialog");
        const episodeId = e.currentTarget.getAttribute('data-id');
        dialog.close();
        this.doRemoveEventListeners();
        this.addEpisode(e, episodeId);
    }

    doCancelEpisode() {
        const dialog = document.querySelector("#review-dialog");
        dialog.close();
        this.doRemoveEventListeners();
    }

    doRemoveEventListeners() {
        const dialog = document.querySelector("#review-dialog");
        const removeButton = dialog.querySelector('button[value="remove"]');
        const watchButton = dialog.querySelector('button[value="watch"]');
        const nowButton = dialog.querySelector('button[value="now"]');
        const cancelButton = dialog.querySelector('button[value="cancel"]');
        removeButton.removeEventListener('click', this.doRemoveEpisode);
        watchButton.removeEventListener('click', this.doAddEpisode);
        nowButton.removeEventListener('click', this.doNowEpisode);
        cancelButton.removeEventListener('click', this.doCancelEpisode);
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
            div.setAttribute('data-title', days + ' ' + (days > 1 ? this.translations.days : this.translations.day));
            if (this.lastDay !== days) {
                this.lastDay = days;
                clearInterval(this.intervals[id]);
                this.intervals[id] = setInterval(this.updateRelativeTime, 86400000, e);
            }
        } else if (hours > 0) {
            div.setAttribute('data-title', hours + ' ' + (hours > 1 ? this.translations.hours : this.translations.hour));
            if (this.lastHour !== hours) {
                this.lastHour = hours;
                clearInterval(this.intervals[id]);
                this.intervals[id] = setInterval(this.updateRelativeTime, 3600000, e);
            }
        } else if (minutes > 0) {
            div.setAttribute('data-title', minutes + ' ' + (minutes > 1 ? this.translations.minutes : this.translations.minute));
            if (this.lastMinute !== minutes) {
                this.lastMinute = minutes;
                clearInterval(this.intervals[id]);
                this.intervals[id] = setInterval(this.updateRelativeTime, 60000, e);
            }
        } else {
            div.setAttribute('data-title', seconds + ' ' + (seconds > 1 ? this.translations.seconds : this.translations.second));
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
        const svgSave = this.getSvg('save');
        datetimeSaveButton.appendChild(svgSave);
        datetimeSaveButton.setAttribute('data-ue-id', userEpisodeId);
        const datetimeDeleteButton = document.createElement('button');
        const svgDelete = this.getSvg('trash');
        datetimeDeleteButton.appendChild(svgDelete);
        datetimeSaveButton.setAttribute('data-ue-id', userEpisodeId);
        const datetimeCancelButton = document.createElement('button');
        const svgCancel = this.getSvg('cancel');
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
        datetimeSaveButton.addEventListener('click', this.touchEpisode);
        datetimeDeleteButton.addEventListener('click', () => {
            this.removeEpisode(episodeId, userEpisodeId);
            watchedAtModifyDiv.remove();
            watchedAtDiv.remove();
        });
        datetimeCancelButton.addEventListener('click', () => {
            watchedAtModifyDiv.remove();
            watchedAtDiv.classList.remove('editing');
        });
    }

    selectProvider(e) {
        if (this.handleClick(e)) {
            return;
        }
        const selectProviderDiv = e.currentTarget
        const episodeId = selectProviderDiv.getAttribute('data-ue-id');
        const flatrate = this.seasonProvider['flatrate'];
        const providerList = document.createElement('div');
        providerList.classList.add('list');
        providerList.setAttribute('data-id', 'provider-' + episodeId);
        providerList.setAttribute('data-save', 'saveProvider');
        selectProviderDiv.appendChild(providerList);
        if (flatrate.length > 0) {
            for (const provider of flatrate) {
                this.addProviderItem(provider, episodeId, providerList, selectProviderDiv);
            }
            const separator = document.createElement('div');
            separator.classList.add('separator');
            providerList.appendChild(separator);
        }/* else {*/
        for (const provider of this.providerArray) {
            this.addProviderItem(provider, episodeId, providerList, selectProviderDiv);
        }
        /*}*/
        this.listInput(providerList);
        this.listTrashButton(providerList, selectProviderDiv);
        this.toolTips.hide();
        this.toolTips.init(providerList);
    }

    addProviderItem(provider, episodeId, providerList, selectProviderDiv) {
        const providerDiv = document.createElement('div');
        providerDiv.classList.add('item');
        providerDiv.setAttribute('data-provider-id', provider['provider_id'].toString());
        providerDiv.setAttribute('data-title', provider['provider_name']);
        providerDiv.innerHTML = '<img src="' + provider['logo_path'] + '" alt="' + provider['provider_name'] + '">';
        providerDiv.addEventListener('click', () => {
            const providerId = providerDiv.getAttribute('data-provider-id');
            this.saveProvider(episodeId, providerId, selectProviderDiv);
        });
        providerList.appendChild(providerDiv);
    }

    selectDevice(e) {
        if (this.handleClick(e)) {
            return;
        }
        const selectDeviceDiv = e.currentTarget;
        const episodeId = selectDeviceDiv.getAttribute('data-ue-id');
        const deviceList = document.createElement('div');
        deviceList.classList.add('list');
        deviceList.setAttribute('data-id', 'device-' + episodeId);
        deviceList.setAttribute('data-save', 'saveDevice');
        selectDeviceDiv.appendChild(deviceList);
        for (const device of this.devices) {
            this.addDeviceItem(device, episodeId, deviceList, selectDeviceDiv);
        }
        this.listTrashButton(deviceList, selectDeviceDiv);
        this.toolTips.hide();
        this.toolTips.init(deviceList);
    }

    addDeviceItem(device, episodeId, deviceList, selectDeviceDiv) {
        const deviceSvg = document.createElement('div');
        deviceSvg.classList.add('item');
        deviceSvg.setAttribute('data-id', device['id']);
        deviceSvg.setAttribute('data-title', this.translations[device['name']]);
        deviceSvg.appendChild(this.getSvg('device-' + device['id']));
        deviceSvg.addEventListener('click', () => {
            const deviceId = deviceSvg.getAttribute('data-id');
            this.saveDevice(episodeId, deviceId, selectDeviceDiv);
        });
        deviceList.appendChild(deviceSvg);
    }

    selectVote(e) {
        if (this.handleClick(e)) {
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
                this.saveVote(episodeId, voteValue, selectVoteDiv);
            });
            voteList.appendChild(vote);
        }
        this.listTrashButton(voteList, selectVoteDiv);
        this.toolTips.hide();
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
        }).then((response) => {
            if (response.ok) {
                if (selectProviderDiv) {
                    if (providerId === -1) {
                        const svgPlus = this.getSvg('plus');
                        selectProviderDiv.innerHTML = '';
                        selectProviderDiv.setAttribute('data-title', this.translations.provider);
                        selectProviderDiv.appendChild(svgPlus);
                        this.toolTips.init(selectProviderDiv);
                    } else {
                        selectProviderDiv.innerHTML = '<img src="' + this.providers.logos[providerId] + '" alt="' + this.providers.names[providerId] + '">';
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
        }).then((response) => {
            if (response.ok) {
                if (selectDeviceDiv) {
                    if (deviceId === -1) {
                        const svgPlus = this.getSvg('plus');
                        selectDeviceDiv.innerHTML = '';
                        selectDeviceDiv.setAttribute('data-title', this.translations.device);
                        selectDeviceDiv.appendChild(svgPlus);
                        this.toolTips.init(selectDeviceDiv);
                    } else {
                        selectDeviceDiv.innerHTML = '';
                        selectDeviceDiv.appendChild(this.getSvg('device-' + deviceId));
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
        }).then((response) => {
            if (response.ok) {
                if (selectVoteDiv) {
                    if (voteValue === -1) {
                        const svgPlus = this.getSvg('plus');
                        selectVoteDiv.innerHTML = '';
                        selectVoteDiv.setAttribute('data-title', this.translations.rating);
                        selectVoteDiv.appendChild(svgPlus);
                        this.toolTips.init(selectVoteDiv);
                    } else {
                        // Display the vote value
                        selectVoteDiv.innerHTML = voteValue;
                        // Add the vote to the graph
                        const voteGraphDiv = document.querySelector('.vote-graph');
                        if (voteGraphDiv) {
                            const voteDiv = voteGraphDiv.querySelector('.vote[data-ep-id="' + episodeId + '"]');
                            const div = voteDiv.querySelector('div');
                            const episodeVoteDiv = voteDiv.closest('.episode-vote');
                            div.classList.remove('dashed-vote');
                            div.classList.add('user-vote');
                            div.style.height = (voteValue * 16) + 'px';
                            div.innerText = voteValue;
                            episodeVoteDiv.setAttribute('data-vote', voteValue);
                            // Average vote for the season
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
                                if (result > 10) result = "10+"; else result = result.toFixed(1);
                                voteAverageDiv.innerHTML = result + " / 10";
                            } else {
                                voteAverageDiv.innerHTML = this.translations['No votes'];
                            }
                        }
                        // If finale, confetti!!
                        const episodeDiv = selectVoteDiv.closest(".episode");
                        if (episodeDiv.querySelector(".still .finale.season-finale")) {
                            {
                                const jsConfetti = new JSConfetti();
                                jsConfetti.addConfetti({
                                    confettiNumber: 500,
                                    confettiColors: [
                                        'hsl(28deg 100% 48%)',
                                        'hsl(34deg 100% 50%)',
                                        'hsl(41deg 100% 50%)',
                                        'hsl(48deg 100% 50%)',
                                        'hsl(55deg 100% 50%)',
                                        'hsl(55deg 99% 66%)',
                                        'hsl(56deg 98% 75%)',
                                        'hsl(56deg 98% 83%)',
                                        'hsl(58deg 100% 90%)',
                                        'hsl(58deg 100% 93%)',
                                        'hsl(58deg 100% 95%)',
                                        'hsl(57deg 100% 98%)',
                                        'hsl(0deg 0% 100%)',
                                    ],
                                }).then(r => {
                                    console.log(r)
                                });
                            }
                        }
                    }
                }
            }
        });
    }

    listInput(list, type = 'text', size = '10') {
        const listId = list.getAttribute('data-id');
        const input = document.createElement('input');
        input.setAttribute('id', listId);
        input.setAttribute('type', type);
        input.setAttribute('size', size);
        input.setAttribute('placeholder', this.translations.Search);
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
        const svgDelete = this.getSvg('trash')
        const id = list.getAttribute('data-id').split('-')[1];
        const saveFunction = list.getAttribute('data-save');

        deleteButton.appendChild(svgDelete);
        deleteButton.addEventListener('click', () => {
            switch (saveFunction) {
                case 'saveProvider':
                    this.saveProvider(id, -1, selectDiv);
                    break;
                case 'saveDevice':
                    this.saveDevice(id, -1, selectDiv);
                    break;
                case 'saveVote':
                    this.saveVote(id, -1, selectDiv);
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
            this.toolTips.hide();
            return true;
        }
        return false;
    }

    getDeviceName(id) {
        const devices = this.devices;
        for (const device of devices) {
            if (device['id'] === id) {
                return device.name;
            }
        }
        return null;
    }

    getSvg(id) {
        const clone = document.querySelector('#svgs').querySelector('svg[id="' + id + '"]').cloneNode(true);
        clone.removeAttribute('id');
        return clone;
    }
}