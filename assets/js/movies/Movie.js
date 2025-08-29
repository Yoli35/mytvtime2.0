import {Keyword} from "Keyword";
import {TranslationsForms} from "TranslationsForms";

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
        this.providers = jsonGlobsObject?.providers;
        this.userMovieId = jsonGlobsObject?.userMovieId;
        this.tmdbId = jsonGlobsObject?.tmdbId;
        this.translations = jsonGlobsObject?.translations;
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
            const viewed = viewedAtDiv.getAttribute('data-viewed');
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
                    const viewDateDiv = document.createElement('div');
                    viewDateDiv.classList.add('view-date');
                    const textNode = document.createTextNode(' ' + data.body.lastViewedAt);
                    viewDateDiv.appendChild(textNode);
                    viewedAtDiv.appendChild(viewDateDiv);
                });
        });

        /******************************************************************************
         * Menu to add a localized name or an overview and additional overview        *
         ******************************************************************************/
        new TranslationsForms(this.userMovieId, 'movie', this.translations);

        /******************************************************************************
         * Keyword translation                                                        *
         ******************************************************************************/
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