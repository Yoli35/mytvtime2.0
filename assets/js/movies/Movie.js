import {Keyword} from "Keyword";

let gThis;

export class Movie {
    /**
     * @typedef Globs
     * @type {Object}
     * @property {number} userMovieId
     * @property {number} tmdbId
     * @property {Array} providers
     * @property {Array} translations
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

        /** @var {Globs} */
        const jsonGlobsObject = JSON.parse(document.querySelector('div#globs').textContent);
        this.providers = jsonGlobsObject.providers;
        this.userMovieId = jsonGlobsObject.userMovieId;
        this.tmdbId = jsonGlobsObject.tmdbId;
        this.translations = jsonGlobsObject.translations;
        this.lang = document.documentElement.lang;
        gThis.init();
    }

    init() {

        const userActions = document.querySelector('.user-actions');

        if (!userActions) {
            return;
        }
        const stars = userActions.querySelectorAll('.star');
        stars.forEach(function (star) {
            star.addEventListener('click', function () {
                const active = this.classList.contains('active');
                const value = active ? 0 : this.getAttribute('data-value');
                fetch('/' + gThis.lang + '/movie/rating/' + gThis.userMovieId, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({rating: value})
                    }
                ).then(function (response) {
                    if (response.ok) {
                        const transRating = stars[0].parentElement.getAttribute('data-trans-rating').split('|')[gThis.lang === 'en' ? 0 : 1];
                        const transStar = stars[0].parentElement.getAttribute('data-trans-star').split('|')[gThis.lang === 'en' ? 0 : 1];
                        const transStars = stars[0].parentElement.getAttribute('data-trans-stars').split('|')[gThis.lang === 'en' ? 0 : 1];
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

        // const pinned = userActions.querySelector('.toggle-pinned-movie');
        // pinned?.addEventListener('click', function () {
        //     const isPinned = this.classList.contains('pinned');
        //     fetch('/' + gThis.lang + '/movie/pinned/' + gThis.userMovieId,
        //         {
        //             method: 'POST',
        //             headers: {
        //                 'Content-Type': 'application/json'
        //             },
        //             body: JSON.stringify({newStatus: isPinned ? 0 : 1})
        //         }
        //     ).then(function (response) {
        //         if (response.ok) {
        //             window.location.reload();
        //         }
        //     });
        // });

        const favorite = userActions.querySelector('.toggle-favorite-movie');
        favorite.addEventListener('click', function () {
            const isFavorite = this.classList.contains('favorite');
            fetch('/' + gThis.lang + '/movie/favorite/' + gThis.userMovieId,
                {
                    method: 'POST',
                    headers:
                        {
                            'Content-Type': 'application/json'
                        },
                    body: JSON.stringify({favorite: isFavorite ? 0 : 1})
                }
            ).then(function (response) {
                if (response.ok) {
                    favorite.classList.toggle('favorite');
                    if (favorite.classList.contains('favorite')) {
                        favorite.setAttribute('data-title', gThis.translations['Remove from favorites']);
                    } else {
                        favorite.setAttribute('data-title', gThis.translations['Add to favorites']);
                    }
                }
            });
        });

        const removeThisMovie = userActions.querySelector('.remove-this-movie');
        removeThisMovie.addEventListener('click', function () {
            fetch('/' + gThis.lang + '/movie/remove/' + gThis.userMovieId,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                }
            ).then(function (response) {
                if (response.ok) {
                    window.location.href = '/' + gThis.lang + '/movie/tmdb/' + gThis.tmdbId;
                }
            });
        });

        const addWatchLink = document.querySelector('.add-watch-link');
        const watchLinkForm = document.querySelector('.watch-link-form');
        const form = document.querySelector('#watch-link-form');
        const cancel = form.querySelector('button[type="button"]');
        const submit = form.querySelector('button[type="submit"]');

        addWatchLink.addEventListener('click', function () {
            watchLinkForm.classList.add('display');
            setTimeout(function () {
                watchLinkForm.classList.add('active');
            }, 0);
        });
        cancel.addEventListener('click', function () {
            watchLinkForm.classList.remove('active');
            setTimeout(function () {
                watchLinkForm.classList.remove('display');
            }, 300);
        });
        submit.addEventListener('click', function (event) {
            event.preventDefault();

            const provider = form.querySelector('#provider');
            const name = form.querySelector('#name');
            const url = form.querySelector('#url');
            const errors = form.querySelectorAll('.error');
            errors.forEach(function (error) {
                error.textContent = '';
            });
            if (!provider.value) {
                provider.value = null;
            }
            if (!name.value) {
                name.nextElementSibling.textContent = gThis.translations['This field is required'];
            }
            if (!url.value) {
                url.nextElementSibling.textContent = gThis.translations['This field is required'];
            }
            if (name.value && url.value) {
                fetch('/' + gThis.lang + '/movie/add/direct/link/' + gThis.userMovieId, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({provider: provider.value, title: name.value, url: url.value})
                    }
                ).then(function (response) {
                    if (response.ok) {
                        watchLinkForm.classList.remove('active');
                        setTimeout(function () {
                            watchLinkForm.classList.remove('display');
                        }, 300);
                        const watchLinks = document.querySelector('.watch-links');
                        const newLink = document.createElement('a');
                        newLink.href = url.value;
                        newLink.target = '_blank';
                        newLink.rel = 'noopener noreferrer';
                        if (provider.value) {
                            const watchLink = document.createElement('div');
                            watchLink.classList.add('watch-link');
                            const img = document.createElement('img');
                            img.src = gThis.providers.logos[provider.value];
                            img.alt = gThis.providers.names[provider.value];
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
                        watchLinks.insertBefore(newLink, watchLinks.lastElementChild);
                        provider.value = '';
                        name.value = '';
                        url.value = '';
                    }
                });
            }
        });

        const viewedAtDiv = userActions.querySelector('.viewed-at');

        viewedAtDiv.addEventListener('click', function () {
            const viewed = this.classList.contains('viewed');
            fetch('/' + gThis.lang + '/movie/viewed/' + gThis.userMovieId,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({viewed: viewed})
                }
            ).then(response => response.json())
                /** @param {{viewed: boolean, lastViewedAt: string}} data */
                .then(data => {
                    if (!data.body.viewed) {
                        viewedAtDiv.classList.add('viewed');
                    }
                    const textNode = document.createTextNode(' ' + data.body.lastViewedAt);
                    viewedAtDiv.appendChild(textNode);
                });
        });

        const seriesToolsClick = document.querySelector('.movie-tools-click');
        const seriesToolsMenu = document.querySelector('.movie-tools-menu');
        seriesToolsClick.addEventListener('click', function () {
            seriesToolsMenu.classList.toggle('active');
        });

        const movieToolsLocalizedName = document.querySelector('#localized-name');
        const movieToolsLocalizedOverview = document.querySelector('#localized-overview');
        const movieToolsAdditionalOverview = document.querySelector('#additional-overview');
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

        movieToolsLocalizedName.addEventListener('click', function () {
            localizedNameForm.classList.add('display');
            setTimeout(function () {
                localizedNameForm.classList.add('active');
            }, 0);
        });
        lnCancel.addEventListener('click', function () {
            localizedNameForm.classList.remove('active');
            setTimeout(function () {
                localizedNameForm.classList.remove('display');
            }, 300);
        });
        lnDelete?.addEventListener('click', function (event) {
            event.preventDefault();

            fetch('/' + gThis.lang + '/movie/delete/localized/name/' + gThis.userMovieId, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({locale: gThis.lang})
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
                fetch('/' + gThis.lang + '/series/add/localized/name/' + gThis.userMovieId, {
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

        movieToolsLocalizedOverview.addEventListener('click', function () {
            const firstRow = ovForm.querySelector('.form-row:first-child');
            const hiddenInputTool = ovForm.querySelector('#tool');
            hiddenInputTool.setAttribute('data-type', 'localized');
            hiddenInputTool.setAttribute('data-crud', 'add');
            firstRow.classList.add('hide');
            gThis.displayForm(overviewForm);
        });

        movieToolsAdditionalOverview.addEventListener('click', function () {
            const firstRow = ovForm.querySelector('.form-row:first-child');
            const hiddenInputTool = ovForm.querySelector('#tool');
            hiddenInputTool.setAttribute('data-type', 'additional');
            hiddenInputTool.setAttribute('data-crud', 'add');
            firstRow.classList.remove('hide');
            gThis.displayForm(overviewForm);
        });

        ovCancel.addEventListener('click', function () {
            gThis.hideForm(overviewForm);
        });

        if (overviews) {
            overviews.forEach(function (overview) {
                const type = overview.classList.contains('localized') ? 'localized' : 'additional';
                const tools = overview.querySelector('.tools');
                const edit = tools.querySelector('.edit');
                const del = tools.querySelector('.delete');
                edit.addEventListener('click', function () {
                    const id = this.getAttribute('data-id');
                    const content = overview.querySelector('.content').textContent;
                    const form = document.querySelector('.overview-form');
                    const hiddenInputTool = form.querySelector('#tool');
                    const overviewField = form.querySelector('#overview-field');
                    hiddenInputTool.value = id;
                    hiddenInputTool.setAttribute('data-type', type);
                    hiddenInputTool.setAttribute('data-crud', 'edit');
                    overviewField.value = content.trim();
                    const firstRow = form.querySelector('.form-row:first-child');
                    if (type === 'localized') {
                        firstRow.classList.add('hide');
                    } else {
                        firstRow.classList.remove('hide');
                    }
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
            const additional = type === 'additional';
            if (additional && !source.value) {
                sourceError.textContent = gThis.translations['This field is required'];
                return;
            }
            if (!overviewField.value) {
                overviewError.textContent = gThis.translations['This field is required'];
                return;
            }
            let data = {source: source.value, overview: overviewField.value, type: type, crud: hiddenInputTool.getAttribute('data-crud'), locale: gThis.lang};

            fetch('/' + gThis.lang + '/series/add/edit/overview/' + gThis.userMovieId, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            }).then(function (response) {
                if (response.ok) {
                    gThis.hideForm(overviewForm);

                    const infos = document.querySelector('.infos');
                    let h4 = infos.querySelector('.' + type + '-h4'), overviewsDiv;
                    if (!h4) {
                        h4 = document.createElement('h4');
                        h4.classList.add(type + '-h4');
                        h4.textContent = gThis.translations[type === 'localized' ? 'Localized overviews' : 'Additional overviews'];
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
                    contentDiv.textContent = overviewField.value;
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
                    localeDiv.textContent = gThis.lang.toUpperCase();
                    toolsDiv.appendChild(localeDiv);

                    const editDiv = document.createElement('div');
                    editDiv.classList.add('edit');
                    editDiv.setAttribute('data-id', newId);
                    editDiv.setAttribute('data-title', gThis.translations['Edit']);
                    const editI = document.createElement('i');
                    editI.classList.add('fas');
                    editI.classList.add('fa-pen');
                    editDiv.appendChild(editI);
                    toolsDiv.appendChild(editDiv);

                    const deleteDiv = document.createElement('div');
                    deleteDiv.classList.add('delete');
                    deleteDiv.setAttribute('data-id', newId);
                    deleteDiv.setAttribute('data-title', gThis.translations['Delete']);
                    const deleteI = document.createElement('i');
                    deleteI.classList.add('fas');
                    deleteI.classList.add('fa-trash');
                    deleteDiv.appendChild(deleteI);
                    toolsDiv.appendChild(deleteDiv);

                    overviewDiv.appendChild(toolsDiv);

                    overviewsDiv.appendChild(overviewDiv);

                    overviewField.value = '';
                }
            });
        });

        deleteOvDelete.addEventListener('click', function (event) {
            event.preventDefault();

            const overviewType = deleteOverviewForm.querySelector('#overview-type').value;
            const overviewId = deleteOverviewForm.querySelector('#overview-id').value;
            fetch('/' + gThis.lang + '/series/delete/overview/' + overviewId, {
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

        new Keyword('movie');
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