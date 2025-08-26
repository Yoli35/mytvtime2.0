import {ToolTips} from "ToolTips";

let gThis = null;

export class Menu {

    /**
     * @typedef SearchResults
     * @type {Object}
     * @property {Array} results
     * @property {number} page
     */

    /**
     * @typedef Movie
     * @type {Object}
     * @property {boolean} adult
     * @property {string} backdrop_path
     * @property {Array} genre_ids
     * @property {number} id
     * @property {string} original_language
     * @property {string} original_title
     * @property {string} overview
     * @property {number} popularity
     * @property {string} poster_path
     * @property {string} release_date
     * @property {string} title
     * @property {boolean} video
     * @property {number} vote_average
     * @property {number} vote_count
     */

    /**
     * @typedef TV
     * @type {Object}
     * @property {string} backdrop_path
     * @property {Array} genre_ids
     * @property {number} id
     * @property {Array} origin_country
     * @property {string} original_language
     * @property {string} original_name
     * @property {string} overview
     * @property {number} popularity
     * @property {string} poster_path
     * @property {string} first_air_date
     * @property {string} name
     * @property {number} vote_average
     * @property {number} vote_count
     */

    /**
     * @typedef Person
     * @type {Object}
     * @property {boolean} adult
     * @property {number} gender
     * @property {number} id
     * @property {string} known_for_department
     * @property {string} name
     * @property {string} original_name
     * @property {number} popularity
     * @property {string} profile_path
     * @property {Array} known_for
     */

    /**
     * @typedef DbSeries
     * @type {Object}
     * @property {string} display_name
     * @property {string} display_slug
     * @property {number} series_id
     * @property {string} poster_path
     */

    /**
     * @typedef HistoryItem
     * @type {Object}
     * @property {Date} lastWatchAt
     * @property {number} episodeId
     * @property {number} episodeNumber
     * @property {number} id
     * @property {number} seasonNumber
     * @property {string} name
     * @property {string} posterPath
     * @property {number} progress
     * @property {string} url
     * @property {number} vote
     * @property {string} deviceSvg
     * @property {string} providerLogoPath
     * @property {string} providerName
     */

    /**
     * @typedef LogData
     * @type {Object}
     * @property {boolean} ok
     * @property {Array} logs
     * @property {number} count
     * @property {Array} dates
     */

    /** @typedef LogItem
     * @type {Object}
     *  @property {number} id
     *  @property {string} title
     *  @property {string} time
     *  @property {string} link
     *  @property {Date} date
     *  @property {string} dateKey
     */
    constructor() {
        gThis = this;
        this.userMenu = document.querySelector(".menu.user");
        this.menuPreview = document.querySelector(".menu-preview");
        this.menuThemes = document.querySelectorAll(".menu-theme");
        this.init = this.init.bind(this);
        this.togglePreview = this.togglePreview.bind(this);
        this.setPreview = this.setPreview.bind(this);
        this.initPreview = this.initPreview.bind(this);
        this.setTheme = this.setTheme.bind(this);
        this.checkTheme = this.checkTheme.bind(this);
        this.lang = document.documentElement.lang;
        this.posterUrl = null;
        this.profileUrl = null;
        this.svgs = {
            "mdi:tv": "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"1em\" height=\"1em\" viewBox=\"0 0 24 24\"><path fill=\"currentColor\" d=\"M21 17H3V5h18m0-2H3a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h5v2h8v-2h5a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2\"/></svg>",
            "mdi:mobile-phone": "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"1em\" height=\"1em\" viewBox=\"0 0 24 24\"><path fill=\"currentColor\" d=\"M17 19H7V5h10m0-4H7c-1.11 0-2 .89-2 2v18a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2\"/></svg>",
            "mdi:tablet": "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"1em\" height=\"1em\" viewBox=\"0 0 24 24\"><path fill=\"currentColor\" d=\"M19 18H5V6h14m2-2H3c-1.11 0-2 .89-2 2v12a2 2 0 0 0 2 2h18a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2\"/></svg>",
            "mdi:laptop": "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"1em\" height=\"1em\" viewBox=\"0 0 24 24\"><path fill=\"currentColor\" d=\"M4 6h16v10H4m16 2a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2H4c-1.11 0-2 .89-2 2v10a2 2 0 0 0 2 2H0v2h24v-2z\"/></svg>",
            "mdi:desktop": "<svg viewBox=\"0 0 576 512\" height=\"18px\" width=\"18px\" aria-hidden=\"true\"><path fill=\"currentColor\" d=\"M64 0C28.7 0 0 28.7 0 64v288c0 35.3 28.7 64 64 64h176l-10.7 32H160c-17.7 0-32 14.3-32 32s14.3 32 32 32h256c17.7 0 32-14.3 32-32s-14.3-32-32-32h-69.3L336 416h176c35.3 0 64-28.7 64-64V64c0-35.3-28.7-64-64-64zm448 64v224H64V64z\"></path></svg>"
        }
    }

    init() {
        const navbar = document.querySelector(".navbar");
        if (!navbar) {
            console.log("Navbar not found");
            return;
        }
        this.reloadOnDayChange();
        this.getTMDBConfig();
        this.initOptions();

        this.tooltips = new ToolTips();

        const navbarItems = navbar.querySelectorAll(".navbar-item");
        // const burger = navbar.querySelector(".burger");
        // const avatar = navbar.querySelector(".avatar");
        // const mainMenu = navbar.querySelector(".menu.main");
        // const userMenu = navbar.querySelector(".menu.user");
        const eotdMenuItems = document.querySelectorAll("a[id^='eotd-menu-item-']");
        const pinnedMenuItems = document.querySelectorAll("a[id^='pinned-menu-item-']");
        const seriesInProgress = document.querySelector("a[id^='sip-menu-item-']");
        const notifications = document.querySelector(".notifications");
        const movieSearch = navbar.querySelector("#movie-search");
        const tvSearch = navbar.querySelector("#tv-search");
        const tvSearchDb = navbar.querySelector("#tv-search-db");
        const personSearch = navbar.querySelector("#person-search");
        const multiSearch = navbar.querySelector("#multi-search");
        const multiSearchOptions = navbar.querySelector(".search-options");

        const historyNavbarItem = navbar.querySelector("#history-menu");
        this.adjustHistoryList();

        const searchResults = navbar.querySelectorAll(".search-results");
        console.log({searchResults});
        const multiSearchResults = navbar.querySelector(".search-results.__multi");
        console.log({multiSearchResults});
        const menus = navbar.querySelectorAll(".menu");
        /*console.log({menus});*/

        document.addEventListener("click", (e) => {
            const target = e.target;
            /*console.log(target);*/
            if (target === multiSearchResults || multiSearchResults.contains(target)) {
                if (multiSearchOptions.contains(target)) {
                    multiSearch.focus();
                }
            } else {
                searchResults.forEach((searchResult) => {
                    const ul = searchResult.querySelector("ul");
                    if (ul && !searchResult.parentElement.contains(e.target)) {
                        if (searchResult.contains(multiSearchOptions)) {
                            const lis = ul.querySelectorAll('li');
                            lis.forEach((li) => {
                                li.remove();
                            });
                        } else {
                            ul.remove();
                        }
                        searchResult.classList.remove("showing-something");
                        // e.preventDefault();
                    }
                });
                menus.forEach((menu) => {
                    if (!menu.parentElement.contains(e.target)) {
                        this.closeMenu(menu.closest(".navbar-item"), menu);
                    }
                });
            }
        });
        document.addEventListener("auxclick", (e) => {
            searchResults.forEach((searchResult) => {
                if (searchResult.parentElement.contains(e.target)) {
                    searchResult.innerHTML = '';
                    searchResult.classList.remove("showing-something");
                }
            });
            menus.forEach((menu) => {
                if (menu.parentElement.contains(e.target)) {
                    this.closeMenu(menu.closest(".navbar-item"), menu);
                }
            });
        });

        navbarItems.forEach((item) => {
            item.addEventListener("click", (e) => {
                if (e.target.closest(".menu")) {
                    return;
                }
                const menu = item.querySelector(".menu");
                if (item.classList.contains("open")) {
                    gThis.closeMenu(item, menu);
                    return;
                }
                navbarItems.forEach((i) => {
                    const m = i.querySelector(".menu");
                    gThis.closeMenu(i, m);
                });
                gThis.openMenu(item, menu);
                if (menu.classList.contains("history")) {
                    gThis.checkHistory();
                }
                if (menu.classList.contains("log")) {
                    gThis.checkLog();
                }
            });
        });

        eotdMenuItems.forEach((item) => {
            const group = item.id.split("-")[3]; // eotd-menu-item-{group}-{id}
            const id = item.id.split("-")[4];
            const eotdPreview = document.querySelector(`#eotd-preview-${group}-${id}`);
            item.addEventListener("mouseenter", () => {
                eotdPreview.classList.add("open");
                setTimeout(() => {
                    eotdPreview.classList.add("show");
                }, 0);
            });
            item.addEventListener("mouseleave", () => {
                setTimeout(() => {
                    eotdPreview.classList.remove("show");
                    setTimeout(() => {
                        eotdPreview.classList.remove("open");
                    }, 250);
                }, 0);
            });
        });

        pinnedMenuItems.forEach((item) => {
            const id = item.id.split("-")[3];
            const pinnedPreview = document.querySelector(`#pinned-preview-${id}`);
            item.addEventListener("mouseenter", () => {
                pinnedPreview.classList.add("open");
                setTimeout(() => {
                    pinnedPreview.classList.add("show");
                }, 0);
            });
            item.addEventListener("mouseleave", () => {
                setTimeout(() => {
                    pinnedPreview.classList.remove("show");
                    setTimeout(() => {
                        pinnedPreview.classList.remove("open");
                    }, 250);
                }, 0);
            });
        });

        if (seriesInProgress) {
            const id = seriesInProgress.id.split("-")[3];
            const sipPreview = document.querySelector(`#sip-preview-${id}`);
            seriesInProgress.addEventListener("mouseenter", () => {
                sipPreview.classList.add("open");
                setTimeout(() => {
                    sipPreview.classList.add("show");
                }, 0);
            });
            seriesInProgress.addEventListener("mouseleave", () => {
                setTimeout(() => {
                    sipPreview.classList.remove("show");
                    setTimeout(() => {
                        sipPreview.classList.remove("open");
                    }, 250);
                }, 0);
            });
        }

        notifications?.addEventListener("click", () => {
            this.markNotificationsAsRead();
        });

        if (movieSearch) {
            movieSearch.addEventListener("input", (e) => {
                const searchResults = movieSearch.closest(".menu-item").querySelector(".search-results");
                const value = e.target.value;
                console.log({e});
                if (value.length > 2) {
                    const url = `/${gThis.lang}/movie/fetch/search/movies`;
                    const options = {
                        method: 'POST',
                        headers: {
                            accept: 'application/json'
                        },
                        body: JSON.stringify({query: value})
                    };

                    fetch(url, options)
                        .then(res => res.json())
                        /** @type {SearchResults} */
                        .then(json => {
                            console.log(json);
                            searchResults.innerHTML = '';
                            const ul = document.createElement("ul");
                            ul.setAttribute("data-type", "movie");
                            if (json.results.length) {
                                searchResults.classList.add("showing-something");
                            }
                            /** @type {Movie} */
                            json.results.forEach((result) => {
                                const a = document.createElement("a");
                                a.href = '/' + gThis.lang + '/movie/tmdb/' + result.id;
                                const li = document.createElement("li");
                                li.setAttribute("data-id", result.id);
                                const posterDiv = document.createElement("div");
                                posterDiv.classList.add("poster");
                                if (result.poster_path) {
                                    const img = document.createElement("img");
                                    img.src = result.poster_path ? gThis.posterUrl + result.poster_path : '/assets/img/no-poster.png';
                                    img.alt = result.title;
                                    posterDiv.appendChild(img);
                                } else {
                                    posterDiv.innerHTML = 'No poster';
                                }
                                a.appendChild(posterDiv);
                                const titleDiv = document.createElement("div");
                                titleDiv.classList.add("title");
                                titleDiv.innerHTML = result.title + (result.release_date ? ' (' + result.release_date.slice(0, 4) + ')' : '');
                                a.appendChild(titleDiv);
                                // Si le lien est ouvert dans un autre onglet (bouton du milieu : auxclick), il faut supprimer le menu.
                                a.addEventListener("auxclick", () => {
                                    const menuDiv = a.closest(".menu");
                                    const navbarItem = a.closest(".navbar-item");
                                    const resultsDiv = a.closest(".search-results");
                                    resultsDiv.classList.remove("showing-something");
                                    ul.remove();
                                    movieSearch.value = '';
                                    gThis.closeMenu(navbarItem, menuDiv);
                                });
                                li.appendChild(a);
                                ul.appendChild(li);
                            });
                            searchResults.appendChild(ul);
                        })
                        .catch(err => console.error('error:' + err));
                } else {
                    movieSearch.closest(".menu-item").querySelector(".search-results").innerHTML = '';
                    searchResults.classList.remove("showing-something");
                }
            });
            movieSearch.addEventListener("keydown", gThis.searchMenuNavigate);

            tvSearch.addEventListener("input", (e) => {
                const searchResults = tvSearch.closest(".menu-item").querySelector(".search-results");
                const value = e.target.value;
                if (value.length > 2) {
                    const url = `/${gThis.lang}/series/fetch/search/series`;
                    const options = {
                        method: 'POST',
                        headers: {
                            accept: 'application/json'
                        },
                        body: JSON.stringify({query: value})
                    };

                    fetch(url, options)
                        .then(res => res.json())
                        .then(json => {
                            console.log(json);
                            searchResults.innerHTML = '';
                            const ul = document.createElement("ul");
                            ul.setAttribute("data-type", "tv");
                            if (json.results.length) {
                                searchResults.classList.add("showing-something");
                            }
                            json.results.forEach((result) => {
                                const a = document.createElement("a");
                                const slug = gThis.toSlug(result.name);
                                a.href = '/' + gThis.lang + '/series/tmdb/' + result.id + '-' + slug;
                                const li = document.createElement("li");
                                li.setAttribute("data-id", result.id);
                                li.setAttribute("data-slug", slug);
                                const posterDiv = document.createElement("div");
                                posterDiv.classList.add("poster");
                                if (result.poster_path) {
                                    const img = document.createElement("img");
                                    img.src = gThis.posterUrl + result.poster_path;
                                    img.alt = result.title;
                                    posterDiv.appendChild(img);
                                } else {
                                    posterDiv.innerHTML = 'No poster';
                                }
                                a.appendChild(posterDiv);
                                const titleDiv = document.createElement("div");
                                titleDiv.classList.add("title");
                                titleDiv.innerHTML = result.name;
                                a.appendChild(titleDiv);
                                gThis.addAuxClickListener(a);
                                li.appendChild(a);
                                ul.appendChild(li);
                            });
                            searchResults.appendChild(ul);
                        })
                        .catch(err => console.error('error:' + err));
                } else {
                    tvSearch.closest(".menu-item").querySelector(".search-results").innerHTML = '';
                    searchResults.classList.remove("showing-something");
                }
            });
            tvSearch.addEventListener("keydown", gThis.searchMenuNavigate);

            tvSearchDb.addEventListener("input", (e) => {
                const searchResults = tvSearchDb.closest(".menu-item").querySelector(".search-results");
                const value = e.target.value;
                if (value.length > 2) {
                    const url = `/${gThis.lang}/series/fetch/search/db/tv`;
                    const options = {
                        method: 'POST',
                        headers: {
                            accept: 'application/json'
                        },
                        body: JSON.stringify({query: value})
                    };
                    console.log({url, options});

                    fetch(url, options)
                        .then(res => res.json())
                        .then(json => {
                            searchResults.innerHTML = '';
                            const ul = document.createElement("ul");
                            ul.setAttribute("data-type", "dbtv");
                            if (json.results.length) {
                                searchResults.classList.add("showing-something");
                            }
                            /** @type {DbSeries} */
                            json.results.forEach((result) => {
                                const a = document.createElement("a");
                                a.href = '/' + gThis.lang + '/series/show/' + result.series_id + '-' + result.display_slug;
                                const li = document.createElement("li");
                                li.setAttribute("data-id", result.series_id);
                                li.setAttribute("data-slug", result.display_slug);
                                const posterDiv = document.createElement("div");
                                posterDiv.classList.add("poster");
                                if (result.poster_path) {
                                    const img = document.createElement("img");
                                    img.src = '/series/posters' + result.poster_path;
                                    img.alt = result.display_name;
                                    posterDiv.appendChild(img);
                                } else {
                                    posterDiv.innerHTML = 'No poster';
                                }
                                a.appendChild(posterDiv);
                                const titleDiv = document.createElement("div");
                                titleDiv.classList.add("title");
                                titleDiv.innerHTML = result.display_name;
                                a.appendChild(titleDiv);
                                gThis.addAuxClickListener(a);
                                li.appendChild(a);
                                ul.appendChild(li);
                            });
                            searchResults.appendChild(ul);
                        })
                        .catch(err => console.error('error:' + err));
                } else {
                    tvSearchDb.closest(".menu-item").querySelector(".search-results").innerHTML = '';
                    searchResults.classList.remove("showing-something");
                }
            });
            tvSearchDb.addEventListener("keydown", gThis.searchMenuNavigate);

            personSearch.addEventListener("input", (e) => {
                const searchResults = personSearch.closest(".menu-item").querySelector(".search-results");
                const value = e.target.value;
                if (value.length > 2) {
                    const url = `/${gThis.lang}/people/fetch/search/person`;
                    const options = {
                        method: 'POST',
                        headers: {
                            accept: 'application/json'
                        },
                        body: JSON.stringify({query: value})
                    };

                    fetch(url, options)
                        .then(res => res.json())
                        .then(json => {
                            console.log(json);
                            searchResults.innerHTML = '';
                            const ul = document.createElement("ul");
                            ul.setAttribute("data-type", "person");
                            if (json.results.length) {
                                searchResults.classList.add("showing-something");
                            }
                            /** @var {Person} */
                            json.results.forEach((result) => {
                                const a = document.createElement("a");
                                a.href = '/' + gThis.lang + '/people/show/' + result.id + '-' + gThis.toSlug(result.name);
                                const li = document.createElement("li");
                                li.setAttribute("data-id", result.id);
                                li.setAttribute("data-slug", gThis.toSlug(result.name));
                                const posterDiv = document.createElement("div");
                                posterDiv.classList.add("poster");
                                if (result.profile_path) {
                                    const img = document.createElement("img");
                                    img.src = gThis.profileUrl + result.profile_path;
                                    img.alt = result.name;
                                    posterDiv.appendChild(img);
                                } else {
                                    posterDiv.innerHTML = 'No poster';
                                }
                                a.appendChild(posterDiv);
                                const titleDiv = document.createElement("div");
                                titleDiv.classList.add("title");
                                titleDiv.innerHTML = result.name;
                                a.appendChild(titleDiv);
                                // Si le lien est ouvert dans un autre onglet, il faut supprimer le menu
                                a.addEventListener("auxclick", () => {
                                    const menuDiv = a.closest(".menu");
                                    const navbarItem = a.closest(".navbar-item");
                                    const resultsDiv = a.closest(".search-results");
                                    resultsDiv.classList.remove("showing-something");
                                    ul.remove();
                                    personSearch.value = '';
                                    gThis.closeMenu(navbarItem, menuDiv);
                                });
                                li.appendChild(a);
                                ul.appendChild(li);
                            });
                            searchResults.appendChild(ul);
                        })
                        .catch(err => console.error('error:' + err));
                } else {
                    personSearch.closest(".menu-item").querySelector(".search-results").innerHTML = '';
                    searchResults.classList.remove("showing-something");
                }
            });
            personSearch.addEventListener("keydown", gThis.searchMenuNavigate);

            multiSearch.addEventListener("input", gThis.searchFetch);
            multiSearch.addEventListener("keydown", gThis.searchMenuNavigate);
        }

        if (historyNavbarItem) {
            const historyMenu = historyNavbarItem.querySelector(".menu");
            const historyOptions = historyMenu.querySelector("#history-options").querySelectorAll("input");
            historyOptions.forEach((historyOption) => {
                historyOption.addEventListener("change", this.reloadHistory);
            });
        }
    }

    addAuxClickListener(a) {
        // Si le lien est ouvert dans un autre onglet (bouton du milieu : auxclick), il faut supprimer le menu.
        a.addEventListener("auxclick", () => {
            const menuDiv = a.closest(".menu");
            const navbarItem = a.closest(".navbar-item");
            const resultsDiv = a.closest(".search-results");
            const ul = resultsDiv.querySelector("ul");
            const input = resultsDiv.closest(".menu-item").querySelector("input");
            resultsDiv.classList.remove("showing-something");
            ul.remove();
            input.value = '';
            gThis.closeMenu(navbarItem, menuDiv);
        });
    }

    openMenu(button, menu) {
        button.classList.add("open");
        document.body.classList.add("frozen");
        menu.classList.add("open");
        setTimeout(() => {
            menu.classList.add("show");
        }, 0);
    }

    closeMenu(button, menu) {
        if (button.classList.contains("open")) {
            button.classList.remove("open");
            document.body.classList.remove("frozen");
            setTimeout(() => {
                menu.classList.remove("show");
                setTimeout(() => {
                    menu.classList.remove("open");
                }, 250);
            }, 0);
        }
    }

    reloadOnDayChange() {
        const now = new Date();
        const midnightMinusOneSecond = new Date(now.getFullYear(), now.getMonth(), now.getDate(), 23, 59, 59);
        const timeToMidnightMinusOneSecond = midnightMinusOneSecond - now;
        const randomSecondAmount = (1 + this.getRandomInt(60)) * 1000;
        console.log('Reload in ' + Math.floor(timeToMidnightMinusOneSecond / 3600000) + ':' + Math.floor(timeToMidnightMinusOneSecond % 3600000 / 60000) + ':' + Math.floor(timeToMidnightMinusOneSecond % 60000 / 1000));
        console.log('Reload at ' + new Date(now.getTime() + timeToMidnightMinusOneSecond + randomSecondAmount));
        setTimeout(() => {
            window.location.reload();
        }, timeToMidnightMinusOneSecond + randomSecondAmount); // reload at noon + 1-60 seconds
    }

    getRandomInt(max) {
        return Math.floor(Math.random() * max);
    }

    initOptions() {
        if (this.userMenu) {
            this.menuPreview.addEventListener("click", this.togglePreview);
            this.menuThemes.forEach((theme) => {
                theme.addEventListener("click", this.setTheme);
            });
            this.initTheme();
            this.initPreview();
        }
    }

    searchFetch(e) {
        const searchInput = e.target;
        const value = searchInput.value;
        const searchResults = searchInput.parentElement.parentElement.querySelector(".search-results");
        const ul = searchResults.querySelector('ul');//document.createElement("ul");
        const lis = ul.querySelectorAll('li');
        if (value.length < 3) {
            if (searchResults.innerHTML.length) {
                lis.forEach(item => {
                    item.remove();
                }); //searchResults.innerHTML = '';
                searchResults.classList.remove("showing-something");
            }
            return;
        }
        const searchType = searchInput.getAttribute("data-type");
        const tvdbAPI = `/${gThis.lang}/series/fetch/search/db/tv`;
        const multiAPI = `/${gThis.lang}/series/fetch/search/multi`;
        const baseHref = `/${gThis.lang}/`;
        const resultNames = {
            'movie': 'title',
            'collection': 'title',
            'tv': 'name',
            'dbtv': 'display_name',
            'person': 'name'
        }
        const hRefs = {
            'movie': 'movie/tmdb/',
            'collection': 'movie/collection/',
            'tv': 'series/tmdb/',
            'dbtv': 'series/show/',
            'person': 'people/show/',
            'multi': 'search/all?q='
        };
        const imagePaths = {
            'movie': gThis.posterUrl,
            'collection': gThis.posterUrl,
            'tv': gThis.posterUrl,
            'dbtv': '/series/posters',
            'person': gThis.profileUrl
        };
        const resultPaths = {
            'movie': 'poster_path',
            'collection': 'poster_path',
            'tv': 'poster_path',
            'dbtv': 'poster_path',
            'person': 'profile_path'
        };

        let url, options;
        if (searchType === 'tvdb') {
            url = tvdbAPI;
        } else {
            url = multiAPI;
        }
        options = {
            method: 'POST',
            headers: {
                accept: 'application/json'
            },
            body: JSON.stringify({query: value})
        };

        fetch(url, options)
            .then(res => res.json())
            .then(json => {
                console.log(json);
                const lis = ul.querySelectorAll('li');
                lis.forEach(item => {
                    item.remove();
                }); //searchResults.innerHTML = '';
                ul.setAttribute("data-type", searchType);

                if (json.results.length) {
                    searchResults.classList.add("showing-something");
                }

                json.results.forEach((result, index) => {
                    const type = result['media_type'] || searchType; // Pour les résultats de recherche multi
                    if (type === 'collection') {
                        console.log({index});
                        console.log({result});
                        //return; // On ne veut pas de collection
                    }
                    const a = document.createElement("a");
                    let url = baseHref + hRefs[type] + result['id'];
                    if (type !== 'movie' && type !== 'collection') url += '-' + gThis.toSlug(result[resultNames[type]]);
                    a.href = url;
                    a.target = "_blank";
                    const li = document.createElement("li");
                    li.setAttribute("data-id", result['id'].toString());
                    li.setAttribute("data-slug", gThis.toSlug(result[resultNames[type]]));
                    li.setAttribute("data-type", type);
                    li.setAttribute('data-title', result[resultNames[type]]);
                    if (!index) li.classList.add("active");
                    const posterDiv = document.createElement("div");
                    posterDiv.classList.add("poster");
                    const imageResult = resultPaths[type];
                    if (result[imageResult]) {
                        const img = document.createElement("img");
                        img.src = imagePaths[type] + result[imageResult];
                        img.alt = result[resultNames[type]];
                        posterDiv.appendChild(img);
                    } else {
                        posterDiv.innerHTML = 'No poster';
                    }
                    a.appendChild(posterDiv);
                    const titleDiv = document.createElement("div");
                    titleDiv.classList.add("title");
                    titleDiv.innerHTML = result[resultNames[type]];
                    a.appendChild(titleDiv);
                    a.addEventListener("click", (e) => {
                        const menuDiv = e.currentTarget.closest(".multi-search");
                        const multiSearchInput = menuDiv.querySelector("input");
                        const resultsDiv = menuDiv.querySelector(".search-results");
                        const ul = menuDiv.querySelectorAll("li");
                        ul.forEach(item => {
                            item.remove();
                        });
                        resultsDiv.classList.remove("showing-something");
                        multiSearchInput.value = '';
                        gThis.tooltips.hide();
                        window.location.href = url;
                    });
                    li.appendChild(a);
                    ul.appendChild(li);
                });
                gThis.tooltips.init(ul);
                searchResults.appendChild(ul);
            })
            .catch(err => console.error('error:' + err));
    }

    searchMenuNavigate(e) {
        // movieSearch, tvSearch, tvSearchDb, personSearch or multiSearch
        const searchMenu = e.target;
        // console.log({e});
        const value = e.target.value;
        if (value.length > 2) {
            const ul = searchMenu.parentElement.parentElement.querySelector(".search-results ul");
            if (!ul) return;
            let type = ul.getAttribute("data-type");
            // console.log({ul});
            // console.log(e.key);
            if (e.key === 'Enter') {
                e.preventDefault();
                const li = ul.querySelector("li.active") ?? ul.querySelector("li");
                if (type === 'multi') {
                    e.preventDefault();
                    li.querySelector("a").click();
                    return;
                }
                //const multiSearchMenuResultType = (type === 'multi') ? li?.getAttribute("data-type") : null;

                if (!li) {
                    if (type === 'tv') {
                        window.location.href = '/' + gThis.lang + '/series/search/all?q=' + value;
                    }
                    if (type === 'dbtv') {
                        window.location.href = '/' + gThis.lang + '/series/db/search/?name=' + value;
                    }
                    return;
                }

                const searchResults = li.closest(".search-results");
                const a = li.querySelector("a");
                /* >> Fermeture du menu au cas où le lien serait ouvert dans un autre onglet ou une autre fenêtre */
                ul.remove();
                e.target.value = '';
                searchResults.classList.remove("showing-something");
                const menuDiv = searchResults.closest(".menu");
                const navbarItem = menuDiv.closest(".navbar-item");
                gThis.closeMenu(navbarItem, menuDiv);
                /* << */
                a.click();

                /*if (multiSearchMenuResultType) {
                    type = multiSearchMenuResultType;
                }

                const id = li.getAttribute("data-id");

                if (type === 'movie') {
                    window.location.href = '/' + gThis.lang + '/movie/tmdb/' + id;
                }
                if (type === 'tv') {
                    const slug = li.getAttribute("data-slug");
                    window.location.href = '/' + gThis.lang + '/series/tmdb/' + id + '-' + slug;
                }
                if (type === 'dbtv') {
                    const slug = li.getAttribute("data-slug");
                    window.location.href = '/' + gThis.lang + '/series/show/' + id + '-' + slug;
                }
                if (type === 'person') {
                    const slug = li.getAttribute("data-slug");
                    window.location.href = '/' + gThis.lang + '/people/show/' + id + '-' + slug;
                }*/
            }
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                const active = ul.querySelector("li.active");
                if (active) {
                    active.classList.remove("active");
                    if (active.nextElementSibling) {
                        active.nextElementSibling.classList.add("active");
                    } else {
                        ul.querySelector("li").classList.add("active");
                    }
                } else {
                    ul.querySelector("li").classList.add("active");
                }
                const newActive = ul.querySelector("li.active");
                newActive.scrollIntoView({block: "center", inline: "center"});
            }
            if (e.key === 'ArrowUp') {
                e.preventDefault();
                const active = ul.querySelector("li.active");
                if (active) {
                    active.classList.remove("active");
                    if (active.previousElementSibling) {
                        active.previousElementSibling.classList.add("active");
                    } else {
                        ul.querySelector("li:last-child").classList.add("active");
                    }
                } else {
                    ul.querySelector("li:last-child").classList.add("active");
                }
                const newActive = ul.querySelector("li.active");
                newActive.scrollIntoView({block: "center", inline: "center"});
            }
        }
    }

    getTMDBConfig() {
        fetch('/' + gThis.lang + '/series/fetch/search/multi', {
            method: 'POST',
            headers: {
                accept: 'application/json'
            },
            body: JSON.stringify({query: 'init'})
        })
            .then(response => response.json())
            /**
             *  @type {Object}
             * @property {string} poster_url
             * @property {string} profile_url
             */
            .then(data => {
                /*console.log(data);*/
                gThis.posterUrl = data.posterUrl;
                gThis.profileUrl = data.profileUrl;
            })
            .catch((error) => {
                console.error({error});
            });
    }

    initPreview() {
        this.setPreview(localStorage.getItem("mytvtime_2_preview"));
    }

    togglePreview() {
        const preview = localStorage.getItem("mytvtime_2_preview");

        if (preview === null) {
            localStorage.setItem("mytvtime_2_preview", "true");
        } else {
            localStorage.removeItem("mytvtime_2_preview");
        }
        this.setPreview(localStorage.getItem("mytvtime_2_preview"));
    }

    setPreview(preview) {
        if (preview !== null) {
            this.menuPreview.innerHTML = this.menuPreview.getAttribute("data-on");
        } else {
            this.menuPreview.innerHTML = this.menuPreview.getAttribute("data-off");
        }
    }

    initTheme() {
        let theme = localStorage.getItem("mytvtime_2_theme");
        if (theme !== null && theme !== 'auto') {
            document.body.classList.add(theme);
        }
        if (theme === null) {
            const dark = window.matchMedia("(prefers-color-scheme: dark)");
            const light = window.matchMedia("(prefers-color-scheme: light)");
            if (dark.matches) {
                theme = "dark";
                localStorage.setItem("mytvtime_2_theme", "dark");
            } else {
                if (light.matches) {
                    theme = "light";
                    localStorage.setItem("mytvtime_2_theme", "light");
                } else {
                    theme = "auto";
                    localStorage.setItem("mytvtime_2_theme", "auto");
                }
            }
        }
        this.checkTheme(theme);
    }

    setTheme(e) {
        const theme = e.currentTarget.getAttribute("data-theme");

        if (!document.startViewTransition) {
            gThis.updateTheme(theme);
        } else {
            document.startViewTransition(() => {
                gThis.updateTheme(theme);
            });
        }

        localStorage.setItem("mytvtime_2_theme", theme);
        this.checkTheme(theme);
        // Créer un événement "theme-change" pour que les autres modules puissent l'écouter
        const event = new Event("theme-changed");
        document.dispatchEvent(event);
    }

    updateTheme(theme) {
        document.body.classList.remove("dark", "light");
        if (theme !== 'auto') document.body.classList.add(theme);
    }

    checkTheme(theme) {
        this.menuThemes.forEach((t) => {
            t.classList.remove("active");
        });
        const newTheme = document.querySelector(`.menu-theme[data-theme="${theme}"]`);
        newTheme.classList.add("active");
    }

    markNotificationsAsRead() {
        fetch('/' + gThis.lang + '/user/notifications/mark-as-read', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            },
        })
            .then(response => response.json())
            .then(data => {
                if (data.ok) {
                    const notifications = document.querySelector(".notifications");
                    const badge = notifications.querySelector("span");
                    badge?.remove();
                }
            })
            .catch((error) => {
                console.error('Error:', error);
            });

    }

    checkHistory() {
        const historyList = document.querySelector("#history-list");
        const historyOptions = historyList.querySelector("#history-options").querySelectorAll("input");
        const ul = historyList.querySelector("ul");
        const loadingDiv = document.createElement("div");
        loadingDiv.classList.add("loading");
        loadingDiv.innerHTML = 'Checking...';
        historyList.insertBefore(loadingDiv, ul);
        /*console.log({open});*/
        fetch('/api/history/menu/last', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            }
        })
            .then(response => response.json())
            /** @var {{ok: boolean, last: number}} data */
            .then(data => {
                const lastWatchedEpisode = data.last;
                const lastEpisodeInHistory = parseInt(historyList.getAttribute("data-last"));
                /*console.log(lastWatchedEpisode, lastEpisodeInHistory);*/
                if (lastEpisodeInHistory !== lastWatchedEpisode) {
                    loadingDiv.innerHTML = 'Reloading...';
                    gThis.reloadHistory({currentTarget: historyOptions[0]});
                }
                loadingDiv.remove();
            });
    }

    reloadHistory(e) {
        /*e.stopPropagation();*/

        const historyList = document.querySelector("#history-list");
        const historyOptions = historyList.querySelector("#history-options").querySelectorAll("input");
        const historyOption = e.currentTarget;
        const optionId = historyOption.id.split('-')[2];
        const ul = historyList.querySelector("ul");
        const lis = ul.querySelectorAll("li");
        const options = {'type': false, 'page': 1, 'count': 20, 'vote': false, 'device': false, 'provider': false};

        historyOptions.forEach(option => {
            if (option.type === 'checkbox') {
                options[option.id.split('-')[2]] = option.checked;
            }
            if (option.type === 'number') {
                options[option.id.split('-')[2]] = option.value;
            }
        });
        /*console.log({options});*/

        if (optionId === 'vote' || optionId === 'device' || optionId === 'provider') {
            lis.forEach((item) => {
                if (historyOption.checked) {
                    item.querySelector('.' + optionId)?.classList.remove('hidden');
                } else {
                    item.querySelector('.' + optionId)?.classList.add('hidden');
                }
            });
            //TODO: save options
            fetch('/api/history/menu/save', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(options)
            })
                .then(response => {
                    console.log(response.ok, 'Options saved');
                })
                .catch((error) => {
                    console.error('Error:', error);
                });
            return;
        }

        fetch('/api/history/menu', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(options)
        })
            .then(response => response.json())
            .then(data => {
                lis.forEach((item) => {
                    item.remove();
                })
                /** @type {HistoryItem} */
                data.list.forEach((item) => {
                    const li = document.createElement("li");
                    li.classList.add("menu-item");
                    li.classList.add("history-item");
                    li.setAttribute("id", item.episodeId);
                    const a = document.createElement("a");
                    a.classList.add("history");
                    a.href = item.url;

                    const poster = document.createElement("div");
                    poster.classList.add("poster");
                    const img = document.createElement("img");
                    img.src = item.posterPath;
                    img.alt = item.name;
                    poster.appendChild(img);

                    a.appendChild(poster);
                    const name = document.createElement("div");
                    name.classList.add("name");
                    name.innerHTML = item.name;
                    a.appendChild(name);

                    const vote = document.createElement("div");
                    vote.classList.add("vote");
                    if (options.vote === false) vote.classList.add('hidden');
                    vote.innerHTML = item.vote;

                    const device = document.createElement("div");
                    device.classList.add("device");
                    if (options.device === false) device.classList.add('hidden');
                    device.innerHTML = gThis.svgs[item.deviceSvg];

                    const provider = document.createElement("div");
                    provider.classList.add("provider");
                    if (options.provider === false) provider.classList.add('hidden');
                    if (item.providerLogoPath) {
                        const imgProvider = document.createElement("img");
                        imgProvider.src = item.providerLogoPath;
                        imgProvider.alt = item.providerName;
                        provider.appendChild(imgProvider);
                    }

                    const number = document.createElement("div");
                    number.classList.add("number");
                    number.innerHTML = 'S' + (item.seasonNumber < 10 ? '0' + item.seasonNumber : item.seasonNumber) + 'E' + (item.episodeNumber < 10 ? '0' + item.episodeNumber : item.episodeNumber);
                    a.appendChild(number);

                    const date = document.createElement("div");
                    date.classList.add("date");
                    date.innerHTML = item.lastWatchAt;
                    a.appendChild(date);

                    a.appendChild(vote);
                    a.appendChild(device);
                    a.appendChild(provider);

                    li.appendChild(a);
                    ul.appendChild(li);
                });
                gThis.adjustHistoryList();
            })
            .catch((error) => {
                console.error('Error:', error);
            });
    }

    adjustHistoryList() {
        const historyList = document.querySelector("#history-list");
        if (!historyList) {
            return;
        }
        const historyListRect = historyList.getBoundingClientRect();
        const bodyRect = document.body.getBoundingClientRect();
        /*console.log(historyListRect);
        console.log(bodyRect);*/
        if (historyListRect.right > bodyRect.width) {
            historyList.style.left = (bodyRect.width - historyListRect.right) + "px";
        }
    }

    checkLog() {
        const logList = document.querySelector("#log-list");
        const ul = logList.querySelector("ul");
        const lastId = logList.getAttribute("data-last");
        const loadingDiv = document.createElement("div");
        loadingDiv.classList.add("menu-item");
        const div = document.createElement("div");
        div.classList.add("loading");
        div.innerHTML = 'Loading...';
        loadingDiv.appendChild(div);
        logList.insertBefore(loadingDiv, ul);
        fetch('/api/log/last', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            }
        })
            .then(response => response.json())
            /** @var {{ok: boolean, last: number}} data */
            .then(data => {
                /*console.log(lastId, data);*/
                if (parseInt(lastId) !== data.last) {
                    loadingDiv.querySelector('div').innerHTML = 'Reloading...';
                    gThis.reloadLog(lastId);
                } else {
                    loadingDiv.remove();
                }
            });
    }

    reloadLog(lastId) {
        // api url : /api/log/load
        const logList = document.querySelector("#log-list");
        const ul = logList.querySelector("ul");
        const loadingDiv = logList.querySelector(".menu-item:has(.loading)");
        fetch('/api/log/load', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({lastId: lastId})
        })
            .then(response => response.json())
            /** @var LogData data */
            .then(data => {
                logList.setAttribute("data-last", data.lastId);
                const countDiv = logList.querySelector("#log-count");
                /** @type {LogItem[]} */
                const logs = data.logs;
                let dateString = "";
                ul.innerHTML = '';
                countDiv.innerHTML = data.count;
                /*console.log(logs);
                console.log(data.dates);*/
                /** @type {LogItem} */
                logs.forEach((item) => {
                    const itemDateString = data.dates[item.dateKey];
                    if (itemDateString !== dateString) {
                        // const now = new Date();
                        const li = document.createElement("li");
                        li.classList.add("menu-item");
                        li.classList.add("log-date");
                        li.innerHTML = itemDateString;
                        ul.appendChild(li);
                        dateString = itemDateString;
                    }

                    const li = document.createElement("li");
                    li.classList.add("menu-item");
                    li.classList.add("log-item");
                    li.setAttribute("data-id", `${item.id}`);

                    const a = document.createElement("a");
                    a.classList.add("log");
                    a.href = item.link;

                    const nameDiv = document.createElement("div");
                    nameDiv.classList.add("name");
                    nameDiv.innerHTML = item.title;
                    const timeDiv = document.createElement("div");
                    timeDiv.classList.add("time");
                    timeDiv.innerHTML = item.time;
                    a.appendChild(nameDiv);
                    a.appendChild(timeDiv);

                    li.appendChild(a);
                    ul.appendChild(li);
                });
                loadingDiv.remove();
            })
            .catch((error) => {
                console.error('Error:', error.message);
            });
    }

    toSlug(str) {
        str = str.replace(/^\s+|\s+$/g, ''); // trim
        str = str.toLowerCase();

        // remove accents, swap ñ for n, etc
        let from = "àáäâèéëêìíïîòóöôùúüûñç·/_,:;";
        let to = "aaaaeeeeiiiioooouuuunc------";
        for (let i = 0, l = from.length; i < l; i++) {
            str = str.replace(new RegExp(from.charAt(i), 'g'), to.charAt(i));
        }

        str = str.replace(/[^a-z0-9 -]/g, '') // remove invalid chars
            .replace(/\s+/g, '-') // collapse whitespace and replace by -
            .replace(/-+/g, '-'); // collapse dashes

        return str;
    }
}