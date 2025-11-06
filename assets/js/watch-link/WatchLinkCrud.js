import {ToolTips} from "ToolTips";

let gThis;

export class WatchLinkCrud {
    constructor(options) {
        gThis = this;
        // {'mediaType': 'series', 'mediaId': seriesId, 'api': api, 'providers': providers, 'translations': translations}
        this.mediaType = options.mediaType;
        this.mediaId = options.mediaId;
        this.api = options.api;
        this.providers = options.providers;
        this.translations = options.translations;
        this.svgs = document.querySelector('div#svgs');

        this.toolTips = new ToolTips();

        this.init();
    }

    init() {
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
            watchLinkFormSubmit.textContent = gThis.translations['Add'];
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
                watchLinkFormSubmit.textContent = gThis.translations['Edit'];
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
                watchLinkFormSubmit.textContent = gThis.translations['Delete'];
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
                name.value = gThis.translations['Watch on'] + ' ' + gThis.providers.names[provider];
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
            let apiUrl;
            errors.forEach(function (error) {
                error.textContent = '';
            });
            if (type.value !== 'delete') {
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
                    if (type.value === 'create') {
                        apiUrl = gThis.api.directLinkCrud.create;
                    }
                    if (type.value === 'update') {
                        apiUrl = gThis.api.directLinkCrud.update + watchLinkFormId.value;
                    }
                }
            } else {
                apiUrl = gThis.api.directLinkCrud.delete + watchLinkFormId.value;
            }
            fetch(apiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({mediaId: gThis.mediaId, provider: provider.value, name: name.value, url: url.value, seasonNumber: seasonNumber?.value})
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
                        edit.setAttribute('data-title', gThis.translations['Edit this watch link']);
                        const editIcon = gThis.svgs.querySelector('.svg#pen').querySelector('svg').cloneNode(true);
                        edit.addEventListener('click', function () {
                            watchLinkFormType.value = 'update';
                            watchLinkFormSubmit.classList.remove('delete');
                            watchLinkFormSubmit.textContent = gThis.translations['Edit'];
                            watchLinkFormId.value = link.id;
                            watchLinkFormProvider.value = link.provider.id;
                            watchLinkFormName.value = link.name;
                            watchLinkFormUrl.value = link.url;
                            gThis.displayForm(watchLinkForm);
                        });
                        edit.appendChild(editIcon);
                        watchLinkTools.appendChild(edit);
                        // <div className="watch-link-tool copy" data-title="{{ 'Copy this watch link'|trans }}">
                        //     {{ux_icon('mdi:content-paste')}}
                        // </div>
                        const copy = document.createElement('div');
                        copy.classList.add('watch-link-tool');
                        copy.classList.add('copy');
                        copy.setAttribute('data-title', gThis.translations['Copy this watch link']);
                        const copyIcon = gThis.svgs.querySelector('.svg#copy').querySelector('svg').cloneNode(true);
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
                        del.setAttribute('data-title', gThis.translations['Delete this watch link']);
                        const delIcon = gThis.svgs.querySelector('.svg#trash').querySelector('svg').cloneNode(true);
                        del.appendChild(delIcon);
                        del.addEventListener('click', function () {
                            watchLinkFormType.value = 'delete';
                            watchLinkFormSubmit.classList.add('delete');
                            watchLinkFormSubmit.textContent = gThis.translations['Delete'];
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
                                img.src = gThis.providers.logos[provider.value];
                                img.alt = gThis.providers.names[provider.value];
                                img.setAttribute('data-title', name.value);
                            }
                            if (hasSpan) {
                                const img = document.createElement('img');
                                img.src = gThis.providers.logos[provider.value];
                                img.alt = gThis.providers.names[provider.value];
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

    displayForm(form) {
        if (form.getAttribute('popover') === "") {
            form.showPopover();
        } else {
            form.classList.add('display');
            setTimeout(function () {
                form.classList.add('active');
            }, 0);
        }
        const url = form.querySelector("#url");
        url.focus();
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