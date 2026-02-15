import {ToolTips} from "ToolTips";

let self;

export class WatchLinkCrud {
    constructor(options) {
        /**
         * @typedef Provider
         * @type {Object}
         * @property {number} id
         * @property {string} logoPath
         * @property {string} name
         */
        /**
         * @typedef Link
         * @type {Object}
         * @property {number} id
         * @property {string} name
         * @property {Provider} provider
         * @property {number} seasonNumber
         * @property {string} url
         */
        self = this;
        // {'mediaType': 'series', 'mediaId': seriesId, 'api': api, 'providers': providers, 'translations': translations}
        this.mediaType = options.mediaType;
        this.mediaId = options.mediaId;
        this.api = options.api;
        this.providers = options.providers;
        this.translations = options.translations;
        this.svgs = document.querySelector('div#svgs');

        this.toolTips = new ToolTips();

        // this.list();

        this.associations = [
            {needle: 'tv.apple.com', providerId: 350},
            {needle: 'www.disneyplus.com', providerId: 337},
            {needle: 'www.gagaoolala.com', providerId: 3266},
            {needle: 'www.france.tv', providerId: 236},
            {needle: 'kisskh.co', providerId: 3267},
            {needle: 'www.iq.com', providerId: 581},
            {needle: 'www.netflix.com', providerId: 8},
            {needle: 'novo19.ouest-france.fr', providerId: 3268},
            {needle: 'www.paramountplus.com', providerId: 531},
            {needle: 'www.primevideo.com', providerId: 119},
            {needle: 'www.viki.com', providerId: 1390},
            {needle: 'wetv.vip', providerId: 623},
            {needle: 'www.youtube.com', providerId: 192},
        ];

        this.init();
    }

    init() {
        const watchLinks = document.querySelectorAll('.watch-link');
        const addWatchLink = document.querySelector('.add-watch-link');
        const watchLinkFormContainer = document.querySelector('.watch-link-form');
        const watchLinkForm = document.querySelector('#watch_link_form');
        const watchLinkFormUrl = watchLinkFormContainer.querySelector('#watch_link_form_url');
        const watchLinkFormProvider = watchLinkForm.querySelector('#watch_link_form_provider');
        const watchLinkFormName = watchLinkForm.querySelector('#watch_link_form_name');
        const watchLinkFormSaisonNumber = watchLinkForm.querySelector('#watch_link_form_season_number');
        const watchLinkFormType = watchLinkForm.querySelector('#watch_link_form_crud_type');
        const watchLinkFormId = watchLinkForm.querySelector('#watch_link_form_crud_id');
        const watchLinkFormCancel = watchLinkForm.querySelector('button[type="button"]');
        const watchLinkFormSubmit = watchLinkForm.querySelector('button[type="submit"]');

        addWatchLink.addEventListener('click', function () {
            watchLinkFormType.value = 'create';
            watchLinkFormSubmit.classList.remove('delete');
            watchLinkFormSubmit.textContent = self.translations['Add'];
            watchLinkFormId.value = "";
            watchLinkFormProvider.value = "";
            watchLinkFormName.value = "";
            watchLinkFormUrl.value = "";
            watchLinkFormSaisonNumber.value = "-1";
            self.displayForm(watchLinkFormContainer);
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
                watchLinkFormSubmit.textContent = self.translations['Edit'];
                watchLinkFormId.value = id;
                watchLinkFormProvider.value = provider;
                watchLinkFormName.value = name;
                watchLinkFormUrl.value = href;
                watchLinkFormSaisonNumber.value = seasonNumber;
                self.displayForm(watchLinkFormContainer);
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
                watchLinkFormSubmit.textContent = self.translations['Delete'];
                watchLinkFormId.value = id;
                watchLinkFormProvider.value = provider;
                watchLinkFormName.value = name;
                watchLinkFormUrl.value = href;
                self.displayForm(watchLinkFormContainer);
            });
        });

        watchLinkFormUrl.addEventListener('input', function () {
            // Parcourir le tableau associations pour trouver le providerId correspondant à l'URL collée. Needle contient la chaine à rechercher dans l'URL.
            let needle = watchLinkFormUrl.value;
            for (let i = 0; i < self.associations.length; i++) {
                if (needle.includes(self.associations[i].needle)) {
                    watchLinkFormProvider.value = self.associations[i].providerId;
                    watchLinkFormName.value = self.buildWatchLabel(self.selectValue(watchLinkFormProvider), self.selectValue(watchLinkFormSaisonNumber));
                    break;
                }
            }
        });

        watchLinkFormSaisonNumber.addEventListener('change', function () {
            watchLinkFormName.value = self.buildWatchLabel(self.selectValue(watchLinkFormProvider), self.selectValue(watchLinkFormSaisonNumber));
        });

        watchLinkFormProvider.addEventListener('change', function () {
            watchLinkFormName.value = self.buildWatchLabel(self.selectValue(watchLinkFormProvider), self.selectValue(watchLinkFormSaisonNumber));
        });
        watchLinkFormCancel.addEventListener('click', function () {
            self.hideForm(watchLinkFormContainer);
        });
        watchLinkFormSubmit.addEventListener('click', function (event) {
            event.preventDefault();

            const provider = watchLinkFormProvider;
            const name = watchLinkFormName;
            const url = watchLinkFormUrl;
            const seasonNumber = watchLinkFormSaisonNumber;
            const type = watchLinkFormType;
            const errors = watchLinkForm.querySelectorAll('.error');
            let apiUrl;
            errors.forEach(function (error) {
                error.textContent = '';
            });
            if (type.value !== 'delete') {
                if (!provider.value) {
                    provider.value = null;
                }
                if (!name.value) {
                    name.nextElementSibling.textContent = self.translations['This field is required'];
                    return;
                }
                if (!url.value) {
                    url.nextElementSibling.textContent = self.translations['This field is required'];
                    return;
                }
                if (name.value && url.value) {
                    if (type.value === 'create') {
                        apiUrl = self.api.directLinkCrud.create;
                    }
                    if (type.value === 'update') {
                        apiUrl = self.api.directLinkCrud.update + watchLinkFormId.value;
                    }
                }
            } else {
                apiUrl = self.api.directLinkCrud.delete + watchLinkFormId.value;
            }
            fetch(apiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({mediaId: self.mediaId, provider: provider.value, name: name.value, url: url.value, seasonNumber: seasonNumber?.value})
                }
            ).then(async function (response) {
                if (response.ok) {
                    const data = await response.json();
                    // console.log({data});
                    self.hideForm(watchLinkFormContainer);
                    const watchLinksDiv = document.querySelector('.watch-links');
                    if (type.value === 'create') {
                        /** @var {Link} link */
                        const link = data.link;
                        // console.log({link});
                        const newWatchLinkDiv = document.createElement('div');
                        newWatchLinkDiv.classList.add('watch-link');
                        newWatchLinkDiv.setAttribute('data-id', link.id.toString());
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
                        // <div class="season-number-badge">S01</div>
                        const seasonNumber = link.seasonNumber;
                        if (seasonNumber > 0) {
                            const seasonNumberBadge = document.createElement('div');
                            seasonNumberBadge.classList.add('season-number-badge');
                            if (seasonNumber < 10)
                                seasonNumberBadge.textContent = 'S0' + seasonNumber.toString();
                            else
                                seasonNumberBadge.textContent = 'S' + seasonNumber.toString();
                            newWatchLinkDiv.appendChild(seasonNumberBadge);
                        }
                        const watchLinkTools = document.createElement('div');
                        watchLinkTools.classList.add('watch-link-tools');
                        watchLinkTools.setAttribute('data-id', link.id.toString());
                        watchLinkTools.setAttribute('data-provider', link.provider.id.toString());
                        watchLinkTools.setAttribute('data-name', link.name);
                        const edit = document.createElement('div');
                        edit.classList.add('watch-link-tool');
                        edit.classList.add('edit');
                        edit.setAttribute('data-title', self.translations['Edit this watch link']);
                        const editIcon = self.svgs.querySelector('.svg#pen').querySelector('svg').cloneNode(true);
                        edit.addEventListener('click', function () {
                            watchLinkFormType.value = 'update';
                            watchLinkFormSubmit.classList.remove('delete');
                            watchLinkFormSubmit.textContent = self.translations['Edit'];
                            watchLinkFormId.value = link.id;
                            watchLinkFormProvider.value = link.provider.id;
                            watchLinkFormName.value = link.name;
                            watchLinkFormUrl.value = link.url;
                            self.displayForm(watchLinkFormContainer);
                        });
                        edit.appendChild(editIcon);
                        watchLinkTools.appendChild(edit);
                        // <div className="watch-link-tool copy" data-title="{{ 'Copy this watch link'|trans }}">
                        //     {{ux_icon('mdi:content-paste')}}
                        // </div>
                        const copy = document.createElement('div');
                        copy.classList.add('watch-link-tool');
                        copy.classList.add('copy');
                        copy.setAttribute('data-title', self.translations['Copy this watch link']);
                        const copyIcon = self.svgs.querySelector('.svg#copy').querySelector('svg').cloneNode(true);
                        copyIcon.addEventListener('click', function () {
                            navigator.clipboard.writeText(link.url).then(function () {
                                copy.classList.add('copied');
                                setTimeout(function () {
                                    copy.classList.remove('copied');
                                }, 1000);
                            });
                        });
                        copy.appendChild(copyIcon);
                        watchLinkTools.appendChild(edit);
                        const nameDiv = document.createElement('div');
                        nameDiv.classList.add('watch-link-name');
                        nameDiv.textContent = link.name;
                        watchLinkTools.appendChild(nameDiv);
                        const del = document.createElement('div');
                        del.classList.add('watch-link-tool');
                        del.classList.add('delete');
                        del.setAttribute('data-title', self.translations['Delete this watch link']);
                        const delIcon = self.svgs.querySelector('.svg#trash').querySelector('svg').cloneNode(true);
                        del.appendChild(delIcon);
                        del.addEventListener('click', function () {
                            watchLinkFormType.value = 'delete';
                            watchLinkFormSubmit.classList.add('delete');
                            watchLinkFormSubmit.textContent = self.translations['Delete'];
                            watchLinkFormId.value = link.id;
                            watchLinkFormProvider.value = link.provider.id;
                            watchLinkFormName.value = link.name;
                            watchLinkFormUrl.value = link.url;
                            self.displayForm(watchLinkFormContainer);
                        });
                        watchLinkTools.appendChild(del);
                        self.toolTips.init(watchLinkTools);

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
                                img.src = self.providers.logos[provider.value];
                                img.alt = self.providers.names[provider.value];
                                img.setAttribute('data-title', name.value);
                            }
                            if (hasSpan) {
                                const img = document.createElement('img');
                                img.src = self.providers.logos[provider.value];
                                img.alt = self.providers.names[provider.value];
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
        });
    }

    buildWatchLabel(providerIndex, seasonNumber) {
        const providersNames = self.providers.names;
        const translations = self.translations;
        const noProvider = providerIndex === -1 || providerIndex === null || typeof providerIndex === 'undefined';
        const noSeason = seasonNumber === -1 || seasonNumber === null || typeof seasonNumber === 'undefined';

        if (noProvider && noSeason) return '';

        const providerName = !noProvider ? (providersNames?.[providerIndex] ?? '') : '';

        if (!noSeason && seasonNumber === 0) {
            return noProvider
                ? translations['Watch specials']
                : translations['Watch specials'] + ' ' + translations['on'] + ' ' + providerName;
        }

        if (!noSeason) {
            return noProvider
                ? translations['Watch season'] + ' ' + seasonNumber
                : translations['Watch season'] + ' ' + seasonNumber + ' ' + translations['on'] + ' ' + providerName;
        }

        // pas de saison, mais provider présent
        return translations['Watch on'] + ' ' + providerName;
    }

    selectValue(select) {
        const raw = select?.value ?? '';
        let num = parseInt(raw, 10);
        if (Number.isNaN(num)) {
            num = -1;
        }
        return num;
    }

    // list() {
    //     const watchLinksDiv = document.querySelector(".watch-links");
    //     const watchLinkDivs = watchLinksDiv.querySelectorAll(".watch-link");
    //     watchLinkDivs.forEach(function (item) {
    //         const id = item.getAttribute("data-id")
    //         fetch(self.api.directLinkCrud.read + id)
    //             .then(response => response.json())
    //             .then(data => {
    //                 const link = data.link;
    //                 console.log(link);
    //             });
    //     });
    // }

    displayForm(form) {
        if (form.getAttribute('popover') === "") {
            form.showPopover();
        } else {
            form.classList.add('display');
            setTimeout(function () {
                form.classList.add('active');
            }, 0);
        }
        /** @type HTMLInputElement */
        const url = form.querySelector("#watch_link_form_url");
        url.focus();
        url.select();
    }

    hideForm(form) {
        if (form.getAttribute('popover') === "") {
            form.hidePopover();
        } else {
            form.classList.remove('active');
            setTimeout(function () {
                form.classList.remove('display');
            }, 300);
        }
    }
}