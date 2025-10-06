let gThis;

export class MovieIndex {
    constructor(globs) {
        gThis = this;
        this.app_series_settings_save = globs.app_series_settings_save;
        this.txt_no_poster = globs.txt_no_poster;
        this.init();
    }

    init() {
        /** @typedef Movie
         * @type {Object}
         * @property {number} userMovieId
         * @property {string} title
         * @property {date} releaseDate
         * @property {string} releaseDateString
         * @property {string} posterPath
         * @property {boolean} favorite
         * @property {date} lastViewedAt
         * @property {string} lastViewedAtString
         */

        /** @typedef Param
         * @type {Object}
         * @property {string} key
         * @property {string|number} value
         */
        /** @typedef Params
         * @type {Param[]}
         */
        const svgPathCollapse = '<path fill="currentColor" d="M117.863 88.282c-8.681 10.017-7.598 25.174 2.419 33.855l120 104c9.02 7.818 22.416 7.818 31.436 0l120-104c10.017-8.681 11.1-23.838 2.419-33.855s-23.839-11.099-33.855-2.418L256 176.241L151.718 85.864c-10.016-8.681-25.174-7.598-33.855 2.418m0 335.436c-8.681-10.017-7.598-25.174 2.419-33.855l120-104c9.02-7.818 22.416-7.818 31.436 0l120 104c10.017 8.681 11.1 23.838 2.419 33.855s-23.839 11.099-33.855 2.418L256 335.759l-104.282 90.377c-10.016 8.681-25.174 7.598-33.855-2.418"></path>';
        const svgPathExpand = '<path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="48" d="m136 208l120-104l120 104m-240 96l120 104l120-104"></path>';
        const svgFavorite = '<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 512 512"><path fill="currentColor" d="m47.6 300.4l180.7 168.7c7.5 7 17.4 10.9 27.7 10.9s20.2-3.9 27.7-10.9l180.7-168.7c30.4-28.3 47.6-68 47.6-109.5v-5.8c0-69.9-50.5-129.5-119.4-141c-45.6-7.6-92 7.3-124.6 39.9l-12 12l-12-12c-32.6-32.6-79-47.5-124.6-39.9C50.5 55.6 0 115.2 0 185.1v5.8c0 41.5 17.2 81.2 47.6 109.5"/></svg>'

        const lang = document.documentElement.lang;
        const collapseBoxes = document.querySelectorAll('.collapse');
        /** @type {HTMLSelectElement} */
        const sortSelect = document.getElementById('sort-by');
        /** @type {HTMLSelectElement} */
        const orderSelect = document.getElementById('order-by');
        /** @type {HTMLSelectElement} */
        const perPage = document.getElementById('per-page');
        /** @type {HTMLInputElement} */
        const titleFilter = document.getElementById('title-filter');
        let t0 = performance.now();

        collapseBoxes.forEach(box => {
            box.addEventListener('click', function () {
                const icon = box.querySelector('svg');
                const article = box.closest('article');
                const forBox = box.getAttribute('data-for');
                const selector = '.' + forBox;
                const div = article.querySelector(selector);
                console.log({div});
                console.log({forBox});
                div.classList.toggle('collapse');
                if (div.classList.contains('collapse')) {
                    icon.innerHTML = svgPathExpand;
                    saveBoxSettings(forBox, false);
                } else {
                    icon.innerHTML = svgPathCollapse;
                    saveBoxSettings(forBox, true);
                }
            });
        });

        titleFilter.addEventListener('input', function () {
            // if (titleFilter.value.length > 2) {
            const t1 = performance.now();
            if (t1 - t0 > 500) {
                getMovies(getParams().concat({key: 'page', value: 1}, {key: 'title', value: titleFilter.value}));
                t0 = t1;
            }
        });

        sortSelect.addEventListener('change', function () {
            const sort = sortSelect.value;
            const order = orderSelect.value;
            document.querySelector("#sort-value span").textContent = sort + ' / ' + order;
            getMovies(getParams().concat({key: 'page', value: 1}));
        });
        orderSelect.addEventListener('change', function () {
            const sort = sortSelect.value;
            const order = orderSelect.value;
            document.querySelector("#sort-value span").textContent = sort + ' / ' + order;
            getMovies(getParams().concat({key: 'page', value: 1}));
        });
        perPage.addEventListener('change', function () {
            document.querySelector("#per-value span").textContent = perPage.value;
            getMovies(getParams().concat({key: 'page', value: 1}));
        });

        function getMovies(params) {
            fetch('/' + lang + '/movie/filter', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(params)
            })
                .then(response => response.json())
                .then(data => {
                    console.log(data);
                    const movies = data.body['userMovies'];
                    const count = data.body['userMovieCount'];
                    const paginationSections = data.body['paginationSections'];
                    const movieCount = document.getElementById('movie-count');
                    movieCount.textContent = count;
                    const wrapper = document.querySelector('.wrapper');
                    wrapper.innerHTML = '';
                    movies.forEach(movie => {
                        wrapper.appendChild(card(movie));
                    });

                    const currentPaginationSections = document.querySelectorAll('section[id^=pagination-section]');
                    currentPaginationSections.forEach((section, index) => {
                        const aside = section.closest('aside');
                        const tempDiv = document.createElement('div');
                        tempDiv.innerHTML = paginationSections[index];
                        aside.replaceChild(tempDiv.firstChild, section);
                    });
                })
                .catch(error => console.error(error));
        }

        /**
         * @returns {Params}
         */
        function getParams() {
            return [
                {key: 'sort', value: sortSelect.value},
                {key: 'order', value: orderSelect.value},
                {key: 'perPage', value: perPage.value},
                {key: 'title', value: titleFilter.value}
            ];
        }

        function saveBoxSettings(box, open) {
            const data = {
                'name': 'my movies boxes',
                'box': {
                    'key': box,
                    'value': open
                }
            };
            fetch(gThis.app_series_settings_save, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(data)
            })
                .then(response => response.json())
                .then(data => console.log(data))
                .catch(error => console.error(error));
        }

        function card(movie) {
            const card = document.createElement('div');
            card.classList.add('card');
            const a = document.createElement('a');
            a.href = '/' + lang + '/movie/show/' + movie.userMovieId;
            const poster = document.createElement('div');
            poster.classList.add('poster');
            if (movie.posterPath) {
                const img = document.createElement('img');
                img.src = '/movies/posters/' + movie.posterPath;
                img.alt = movie.title;
                poster.appendChild(img);
            } else {
                const div = document.createElement('div');
                div.textContent = gThis.txt_no_poster;
                poster.appendChild(div);
            }
            if (movie.favorite) {
                const favorite = document.createElement('div');
                favorite.classList.add('favorite');
                favorite.innerHTML = svgFavorite;
                poster.appendChild(favorite);
            }
            a.appendChild(poster);
            const infos = document.createElement('div');
            infos.classList.add('infos');
            const name = document.createElement('div');
            name.classList.add('name');
            name.textContent = movie.title;
            infos.appendChild(name);
            const date = document.createElement('div');
            date.classList.add('date');
            date.textContent = movie.releaseDateString;
            infos.appendChild(date);
            if (movie.lastViewedAtString) {
                const viewed = document.createElement('div');
                viewed.classList.add('viewed');
                viewed.textContent = movie.lastViewedAtString;
                infos.appendChild(viewed);
            }
            a.appendChild(infos);
            card.appendChild(a);

            return card;
        }
    }
}