import {Diaporama} from 'Diaporama';
import {Keyword} from 'Keyword';
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

    /**
     * @typedef Globs
     * @type {Object}
     * @property {string} seriesName
     * @property {number} seriesId
     * @property {number} userSeriesId
     * @property {Array} providers
     * @property {Array} translations
     * @property {Api} api
     */
    /**
     * @typedef Source
     * @type {Object}
     * @property {string} name
     * @property {string} path
     * @property {string} logoPath
     */

    constructor() {
        gThis = this;
        this.toolTips = new ToolTips();
        this.init();
    }

    init() {
        /** @var {Globs} */
        const jsonGlobsObject = JSON.parse(document.querySelector('div#globs').textContent);
        const providers = jsonGlobsObject.providers;
        const seriesId = jsonGlobsObject.seriesId;
        const seriesName = jsonGlobsObject.seriesName;
        const userSeriesId = jsonGlobsObject.userSeriesId;
        const translations = jsonGlobsObject.translations;
        const api = jsonGlobsObject.api;
        console.log({api});

        /******************************************************************************
         * Animation for the progress bar                                             *
         ******************************************************************************/
        const progressDiv = document.querySelector('.progress');
        const progressBarDiv = document.querySelector('.progress-bar');
        const progress = progressDiv.getAttribute('data-value');
        progressBarDiv.classList.add('set');
        progressBarDiv.style.width = progress + '%';
        progressBarDiv.setAttribute('aria-valuenow', progress);

        /******************************************************************************
         * Diaporama for posters, backdrops and logos                                 *
         ******************************************************************************/
        const diaporama = new Diaporama();
        const posters = document.querySelector('.posters')?.querySelectorAll('img');
        const backdrops = document.querySelector('.backdrops')?.querySelectorAll('img');
        const logos = document.querySelector('.logos')?.querySelectorAll('img');
        diaporama.start(posters);
        diaporama.start(backdrops);
        diaporama.start(logos);

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
                        window.location.href = '/' + lang + '/series/tmdb/{{ series.tmdbId }}-{{ series.slug }}';
                    }
                });
            });
        }

        /******************************************************************************
         * Watch links: add.                                                          *
         * ****************************************************************************
         * <div class="watch-link-tools"                                              *
         *     data-id="142"                                                          *
         *     data-provider="1733"                                                   *
         *     data-name="Regarder sur Action Max Amazon Channel"                     *
         * >                                                                          *
         *     <div class="watch-link-tool edit" data-title="...">                    *
         *         <svg viewBox="0 0 512 512" fill="currentColor" [...] </svg>        *
         *     </div>                                                                 *
         *     <div class="watch-link-tool delete" data-title="...">                  *
         *         <svg viewBox="0 0 448 512" fill="currentColor" [...] </svg>        *
         *     </div>                                                                 *
         * </div>                                                                     *
         ******************************************************************************/
        const watchLinks = document.querySelectorAll('.watch-link');
        const addWatchLink = document.querySelector('.add-watch-link');
        const watchLinkForm = document.querySelector('.watch-link-form');
        const watchLinkFormProvider = watchLinkForm.querySelector('#provider');
        const watchLinkFormName = watchLinkForm.querySelector('#name');
        const watchLinkFormUrl = watchLinkForm.querySelector('#url');
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
            gThis.displayForm(watchLinkForm);
        });

        watchLinks.forEach(function (watchLink) {
            const tools = watchLink.querySelector('.watch-link-tools');
            const href = watchLink.querySelector('a').getAttribute('href');
            const edit = tools.querySelector('.watch-link-tool.edit');
            const del = tools.querySelector('.watch-link-tool.delete');
            const id = tools.getAttribute('data-id');
            const provider = tools.getAttribute('data-provider');
            const name = tools.getAttribute('data-name');

            edit.addEventListener('click', function () {
                watchLinkFormType.value = 'update';
                watchLinkFormSubmit.classList.remove('delete');
                watchLinkFormSubmit.textContent = translations['Edit'];
                watchLinkFormId.value = id;
                watchLinkFormProvider.value = provider;
                watchLinkFormName.value = name;
                watchLinkFormUrl.value = href;
                gThis.displayForm(watchLinkForm);
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
                        body: JSON.stringify({seriesId: seriesId, provider: provider.value, name: name.value, url: url.value})
                    }
                ).then(function (response) {
                    if (response.ok) {
                        gThis.hideForm(watchLinkForm);
                        const watchLinksDiv = document.querySelector('.watch-links');
                        if (type.value === 'create') {
                            const newLink = document.createElement('a');
                            newLink.href = url.value;
                            newLink.target = '_blank';
                            newLink.rel = 'noopener noreferrer';
                            if (provider.value) {
                                const watchLink = document.createElement('div');
                                watchLink.classList.add('watch-link');
                                const img = document.createElement('img');
                                img.setAttribute('data-title', name.value);
                                watchLink.appendChild(img);
                                newLink.appendChild(watchLink);
                            } else {
                                const watchLink = document.createElement('div');
                                watchLink.classList.add('watch-link');
                                const span = document.createElement('span');
                                span.textContent = name.value;
                                watchLink.appendChild(span);
                                newLink.appendChild(watchLink);
                            }
                            watchLinks.insertBefore(newLink, watchLinksDiv.lastElementChild);
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

            fetch('/' + lang + '/series/delete/localized/name/' + seriesId,
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
                fetch('/' + lang + '/series/add/localized/name/' + seriesId,
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

            fetch('/' + lang + '/series/add/edit/overview/' + seriesId, {
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
                        contentDiv.innerHTML = newContentText.replace(/\n/g, '<br>');

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
                    contentDiv.textContent = overviewField.value.replace(/\n/g, '<br>');
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
            fetch('/' + lang + '/series/delete/overview/' + overviewId, {
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
         * Filming location form                                                      *
         ******************************************************************************/
        const seriesMap = document.querySelector('div[data-controller^="series-map"]');
        const addLocationForm = document.querySelector('#add-location-form');
        const addLocationDialog = document.querySelector('dialog.add-location-dialog');
        const addLocationButton = document.querySelector('.add-location-button');
        const inputGoogleMapsUrl = addLocationForm.querySelector('input[name="google-map-url"]');
        const inputLatitude = addLocationForm.querySelector('input[name="latitude"]');
        const inputLongitude = addLocationForm.querySelector('input[name="longitude"]');
        const addLocationCancel = addLocationForm.querySelector('button[type="button"]');
        const addLocationSubmit = addLocationForm.querySelector('button[type="submit"]');

        if (seriesMap) {
            const mapViewValue = JSON.parse(seriesMap.getAttribute('data-symfony--ux-leaflet-map--map-view-value'));
            console.log({mapViewValue});
        }

        addLocationButton.addEventListener('click', function () {
            const inputs = addLocationForm.querySelectorAll('input');
            const titleInput = addLocationForm.querySelector('input[name="title"]');
            const locationInput = addLocationForm.querySelector('input[name="location"]');
            inputs.forEach(function (input) {
                input.value = '';
            });
            titleInput.value = seriesName;
            addLocationDialog.showModal();
            locationInput.focus();
            locationInput.select();
        });
        inputGoogleMapsUrl.addEventListener('paste', function (e) {
            const url = e.clipboardData.getData('text');
            const urlParts = url.split('@')[1].split(',');
            inputLatitude.value = urlParts[0];
            inputLongitude.value = urlParts[1];
        });
        addLocationCancel.addEventListener('click', function () {
            addLocationDialog.close();
        });
        addLocationSubmit.addEventListener('click', function (event) {
            event.preventDefault();

            const inputs = addLocationForm.querySelectorAll('input[required]');
            let emptyInput = false;
            inputs.forEach(function (input) {
                if (!input.value) {
                    input.nextElementSibling.textContent = translations['This field is required'];
                    emptyInput = true;
                } else {
                    input.nextElementSibling.textContent = '';
                }
            });
            if (!emptyInput) {
                const formDatas = new FormData(addLocationForm);
                fetch('/' + lang + '/series/add/location/' + seriesId,
                    {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(Object.fromEntries(formDatas))
                    }
                ).then(function (response) {
                    if (response.ok) {
                        addLocationDialog.close();
                        window.location.reload();
                    }
                });
            }
        });

        /******************************************************************************
         * Broadcast delay                                                            *
         ******************************************************************************/
        const broadcastInput = document.querySelector('input[name="broadcast-delay"]');
        const broadcastSubmit = document.querySelector('button[value="broadcast-delay"]');
        broadcastSubmit.addEventListener('click', function () {
            fetch('/' + lang + '/series/broadcast/delay/' + seriesId,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({delay: broadcastInput.value})
                }
            ).then(function (response) {
                if (response.ok) {
                    window.location.reload();
                }
            });
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
