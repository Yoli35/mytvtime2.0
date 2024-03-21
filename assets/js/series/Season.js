import {ToolTips} from '../ToolTips.js';

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

        this.toolTips = new ToolTips();
    }

    init() {
        const seasonsEpisodes = document.querySelector('.seasons-episodes');
        const infos = seasonsEpisodes.querySelectorAll('.infos');
        infos.forEach(info => {
            info.addEventListener('mouseleave', () => {
                info.scrollTop = 0;
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

        const userEpisodeProviders = document.querySelectorAll('.select-provider');
        userEpisodeProviders.forEach(provider => {
            provider.addEventListener('mouseenter', gThis.selectProvider);
            provider.addEventListener('mouseleave', gThis.removeList);
        });

        const userEpisodeDevices = document.querySelectorAll('.select-device');
        userEpisodeDevices.forEach(device => {
            device.addEventListener('mouseenter', gThis.selectDevice);
            device.addEventListener('mouseleave', gThis.removeList);
        });

        const userEpisodeVotes = document.querySelectorAll('.select-vote');
        userEpisodeVotes.forEach(vote => {
            vote.addEventListener('mouseenter', gThis.selectVote);
            vote.addEventListener('mouseleave', gThis.removeList);
        });
    }

    addEpisode(e, episodeId = null) {
        const selector = episodeId ? '.remove-this-episode[data-id="' + episodeId + '"]' : null;
        const episode = episodeId ? document.querySelector(selector) : e.currentTarget;
        const sId = episode.getAttribute('data-show-id');
        const id = episode.getAttribute('data-id');
        const episodeNumber = episode.getAttribute('data-e-number');
        const seasonNumber = episode.getAttribute('data-s-number');
        const views = parseInt(episode.getAttribute('data-views'));
        fetch('/' + gThis.lang + '/series/add/episode/' + id, {
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
                episode.removeEventListener('click', gThis.addEpisode);
                episode.addEventListener('click', gThis.removeOrReviewEpisode);
                const number = episode.closest('.season-episode').querySelector('.number');
                number.setAttribute('data-title', "x" + (views + 1));

                episode.setAttribute('data-title', gThis.text.now);
                if (episodeId) {
                    return;
                }
                episode.innerHTML = '<i class="fas fa-eye"></i>';
                episode.classList.remove('add-this-episode');
                episode.classList.add('remove-this-episode');
                const now = new Date();
                episode.setAttribute('data-time', now.toISOString());
                episode.addEventListener('mouseenter', gThis.updateRelativeTime);

                const quickEpisodeLink = document.querySelector('.quick-episode[data-number="' + episodeNumber + '"]');
                quickEpisodeLink.classList.add('watched');

                const numberDiv = episode.closest('.season-episode').querySelector('.number');
                numberDiv.classList.add('watched');

                const previousEpisode = episode.closest('.seasons-episodes').querySelector('.remove-this-episode[data-e-number="' + (episodeNumber - 1) + '"]');
                const previousProvider = previousEpisode?.parentElement.querySelector('.select-provider');
                if (parseInt(episodeNumber) >= 1 && previousProvider) {
                    const clone = previousProvider.cloneNode(true);
                    clone.setAttribute('data-id', id);
                    clone.addEventListener('mouseenter', gThis.selectProvider);
                    clone.addEventListener('mouseleave', gThis.removeList);
                    episode.parentElement.appendChild(clone);
                } else {
                    const providerDiv = document.createElement('div');
                    providerDiv.classList.add('select-provider');
                    providerDiv.setAttribute('data-id', id);
                    providerDiv.setAttribute('data-title', gThis.text.provider);
                    providerDiv.innerHTML = '<i class="fas fa-plus"></i>';
                    providerDiv.addEventListener('mouseenter', gThis.selectProvider);
                    providerDiv.addEventListener('mouseleave', gThis.removeList);
                    episode.parentElement.appendChild(providerDiv);
                }

                const previousDevice = previousEpisode.parentElement.querySelector('.select-device');
                if (parseInt(episodeNumber) >= 1 && previousDevice) {
                    const clone = previousDevice.cloneNode(true);
                    clone.setAttribute('data-id', id);
                    clone.addEventListener('mouseenter', gThis.selectDevice);
                    clone.addEventListener('mouseleave', gThis.removeList);
                    episode.parentElement.appendChild(clone);
                } else {
                    const device = document.createElement('div');
                    device.classList.add('select-device');
                    device.setAttribute('data-id', id);
                    device.setAttribute('data-title', gThis.text.device);
                    device.innerHTML = '<i class="fas fa-plus"></i>';
                    device.addEventListener('mouseenter', gThis.selectDevice);
                    device.addEventListener('mouseleave', gThis.removeList);
                    episode.parentElement.appendChild(device);
                }

                const vote = document.createElement('div');
                vote.classList.add('select-device');
                vote.setAttribute('data-id', id);
                vote.setAttribute('data-title', gThis.text.rating);
                vote.innerHTML = '<i class="fas fa-plus"></i>';
                vote.addEventListener('mouseenter', gThis.selectVote);
                vote.addEventListener('mouseleave', gThis.removeList);
                episode.parentElement.appendChild(vote);

                const backToTopLink = episode.parentElement.querySelector('.back-to-top').closest('a');
                episode.parentElement.appendChild(backToTopLink);
            }
        });
    }

    updateRelativeTime(e) {
        const div = e.currentTarget;

            const time = div.getAttribute('data-time');
            const date = new Date(time);
            const now = new Date();
            const diff = now - date;
            const days = Math.floor(diff / (1000 * 60 * 60 * 24));
            const hours = Math.floor(diff / (1000 * 60 * 60));
            const minutes = Math.floor(diff / (1000 * 60));
            const seconds = Math.floor(diff / 1000);
            if (days > 0) {
                div.setAttribute('data-title',  days + ' ' + (days>1 ? gThis.text.days : gThis.text.day));
            } else if (hours > 0) {
                div.setAttribute('data-title',  hours + ' ' + (hours>1 ? gThis.text.hours : gThis.text.hour));
            } else if (minutes > 0) {
                div.setAttribute('data-title',  minutes + ' ' + (minutes>1 ? gThis.text.minutes : gThis.text.minute));
            } else {
                div.setAttribute('data-title',  seconds + ' ' + (seconds>1 ? gThis.text.seconds : gThis.text.second));
            }
    }

    removeOrReviewEpisode(e) {
        const dialog = document.querySelector("#review-dialog");
        const episode = e.currentTarget;
        const id = episode.getAttribute('data-id');
        const showId = episode.getAttribute('data-show-id');
        const episodeNumber = episode.getAttribute('data-e-number');
        const seasonNumber = episode.getAttribute('data-s-number');
        const buttons = dialog.querySelectorAll('button');
        const removeButton = dialog.querySelector('button[value="remove"]');
        const watchButton = dialog.querySelector('button[value="watch"]');
        buttons.forEach(button => {
            button.setAttribute('data-id', id);
            button.setAttribute('data-show-id', showId);
            button.setAttribute('data-e-number', episodeNumber);
            button.setAttribute('data-s-number', seasonNumber);
        });
        removeButton.addEventListener('click', () => {
            dialog.close();
            gThis.removeEpisode(id);
        });
        watchButton.addEventListener('click', () => {
            dialog.close();
            gThis.addEpisode(e, id);
        });
        dialog.showModal();
    }

    removeEpisode(episodeId) {
        const selector = '.remove-this-episode[data-id="' + episodeId + '"]';
        const episode = document.querySelector(selector);
        const sId = episode.getAttribute('data-show-id');
        const episodeNumber = episode.getAttribute('data-e-number');
        const seasonNumber = episode.getAttribute('data-s-number');
        let views = parseInt(episode.getAttribute('data-views'));
        fetch('/' + gThis.lang + '/series/remove/episode/' + episodeId, {
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
                const number = episode.closest('.season-episode').querySelector('.number');
                number.setAttribute('data-title', "x" + views);
                gThis.toolTips.init(number);
                if (views > 0) {
                    return;
                }

                const quickEpisodeLink = document.querySelector('.quick-episode[data-number="' + episodeNumber + '"]');
                quickEpisodeLink.classList.remove('watched');

                const numberDiv = episode.closest('.season-episode').querySelector('.number');
                numberDiv.classList.remove('watched');

                episode.removeEventListener('click', gThis.removeEpisode);
                episode.addEventListener('click', gThis.addEpisode);
                episode.innerHTML = '<i class="fas fa-plus"></i>';
                episode.classList.remove('remove-this-episode');
                episode.classList.add('add-this-episode');
                episode.setAttribute('data-title', gThis.text.add);
                const provider = episode.parentElement.querySelector('.select-provider');
                if (provider) {
                    provider.remove();
                }
                const device = episode.parentElement.querySelector('.select-device');
                if (device) {
                    device.remove();
                }
                const vote = episode.parentElement.querySelector('.select-vote');
                if (vote) {
                    vote.remove();
                }
            }
        });
    }

    selectProvider(e) {
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
        } else {
            for (const provider of gThis.providerArray) {
                gThis.addProviderItem(provider, episodeId, providerList, selectProviderDiv);
            }
        }
        gThis.listInput(providerList);
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
        const selectDeviceDiv = e.currentTarget;
        const episodeId = selectDeviceDiv.getAttribute('data-id');
        const deviceList = document.createElement('div');
        deviceList.classList.add('list');
        selectDeviceDiv.appendChild(deviceList);
        for (const device of gThis.devices) {
            gThis.addDeviceItem(device, episodeId, deviceList, selectDeviceDiv);
        }
        gThis.listInput(deviceList);
        gThis.toolTips.init(deviceList);
    }

    addDeviceItem(device, episodeId, deviceList, selectDeviceDiv) {
        const deviceDiv = document.createElement('div');
        deviceDiv.classList.add('item');
        deviceDiv.setAttribute('data-id', device['id']);
        deviceDiv.setAttribute('data-title', gThis.text[device['name']]);
        deviceDiv.innerHTML = '<img src="/series/devices' + device['logo_path'] + '" alt="' + device['name'] + '">';
        deviceDiv.addEventListener('click', () => {
            const deviceId = deviceDiv.getAttribute('data-id');
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
                    selectDeviceDiv.innerHTML = '<img src="/series/devices' + device['logo_path'] + '" alt="' + device['name'] + '">';
                    selectDeviceDiv.setAttribute('data-title', gThis.text[device['name']]);
                    gThis.toolTips.init(selectDeviceDiv);
                    deviceList.remove();
                }
            });
        });
        deviceList.appendChild(deviceDiv);
    }

    selectVote(e) {
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
                        selectVoteDiv.innerHTML = voteValue;
                        voteList.remove();
                    }
                });
            });
            voteList.appendChild(vote);
        }
        gThis.listInput(voteList);
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

    removeList(e) {
        const list = e.currentTarget.querySelector('.list');
        if (list) {
            list.remove();
        }
    }
}