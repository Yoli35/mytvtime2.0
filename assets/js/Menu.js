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
        /*this.avatar = document.querySelector('.avatar');*/
        /*this.userConnected = this.avatar != null;
        this.connexionInterval = null;*/
        this.posterUrl = null;
        this.profileUrl = null;
        this.svgs = {
            "fa6-solid:tv": "<svg viewBox=\"0 0 640 512\" fill=\"currentColor\" height=\"18px\" width=\"18px\" aria-hidden=\"true\"><path fill=\"currentColor\" d=\"M64 64v288h512V64zM0 64C0 28.7 28.7 0 64 0h512c35.3 0 64 28.7 64 64v288c0 35.3-28.7 64-64 64H64c-35.3 0-64-28.7-64-64zm128 384h384c17.7 0 32 14.3 32 32s-14.3 32-32 32H128c-17.7 0-32-14.3-32-32s14.3-32 32-32\"></path></svg>",
            "fa6-solid:mobile-screen-button": "<svg viewBox=\"0 0 384 512\" fill=\"currentColor\" height=\"18px\" width=\"18px\" aria-hidden=\"true\"><path fill=\"currentColor\" d=\"M16 64C16 28.7 44.7 0 80 0h224c35.3 0 64 28.7 64 64v384c0 35.3-28.7 64-64 64H80c-35.3 0-64-28.7-64-64zm208 384a32 32 0 1 0-64 0a32 32 0 1 0 64 0m80-384H80v320h224z\"></path></svg>",
            "fa6-solid:tablet-screen-button": "<svg viewBox=\"0 0 448 512\" fill=\"currentColor\" height=\"18px\" width=\"18px\" aria-hidden=\"true\"><path fill=\"currentColor\" d=\"M0 64C0 28.7 28.7 0 64 0h320c35.3 0 64 28.7 64 64v384c0 35.3-28.7 64-64 64H64c-35.3 0-64-28.7-64-64zm256 384a32 32 0 1 0-64 0a32 32 0 1 0 64 0M384 64H64v320h320z\"></path></svg>",
            "fa6-solid:laptop": "<svg viewBox=\"0 0 640 512\" fill=\"currentColor\" height=\"18px\" width=\"18px\" aria-hidden=\"true\"><path fill=\"currentColor\" d=\"M128 32c-35.3 0-64 28.7-64 64v256h64V96h384v256h64V96c0-35.3-28.7-64-64-64zM19.2 384C8.6 384 0 392.6 0 403.2C0 445.6 34.4 480 76.8 480h486.4c42.4 0 76.8-34.4 76.8-76.8c0-10.6-8.6-19.2-19.2-19.2z\"></path></svg>",
            "fa6-solid:desktop": "<svg viewBox=\"0 0 576 512\" fill=\"currentColor\" height=\"18px\" width=\"18px\" aria-hidden=\"true\"><path fill=\"currentColor\" d=\"M64 0C28.7 0 0 28.7 0 64v288c0 35.3 28.7 64 64 64h176l-10.7 32H160c-17.7 0-32 14.3-32 32s14.3 32 32 32h256c17.7 0 32-14.3 32-32s-14.3-32-32-32h-69.3L336 416h176c35.3 0 64-28.7 64-64V64c0-35.3-28.7-64-64-64zm448 64v224H64V64z\"></path></svg>"
        }
    }

    init() {
        this.reloadOnDayChange();
        this.getTMDBConfig();
        this.initOptions();

        this.tooltips = new ToolTips();

        const navbar = document.querySelector(".navbar");
        const navbarItems = navbar.querySelectorAll(".navbar-item");
        const burger = navbar.querySelector(".burger");
        const avatar = navbar.querySelector(".avatar");
        const mainMenu = navbar.querySelector(".menu.main");
        const userMenu = navbar.querySelector(".menu.user");
        const eotdMenuItems = document.querySelectorAll("a[id^='eotd-menu-item-']");
        const pinnedMenuItems = document.querySelectorAll("a[id^='pinned-menu-item-']");
        const seriesInProgress = document.querySelector("a[id^='sip-menu-item-']");
        const body = document.querySelector("body");
        const notifications = document.querySelector(".notifications");
        /*const detailsElements = document.querySelectorAll("details");*/
        const movieSearch = navbar.querySelector("#movie-search");
        const tvSearch = navbar.querySelector("#tv-search");
        const tvSearchDb = navbar.querySelector("#tv-search-db");
        const personSearch = navbar.querySelector("#person-search");
        const multiSearch = navbar.querySelector("#multi-search");

        const historyNavbarItem = navbar.querySelector("#history-menu");
        //const logMenu = navbar.querySelector("#log-menu");

        /*document.addEventListener("click", (e) => {
            if (notifications?.querySelector(".menu-notifications").classList.contains("show")) {
                const menu = notifications.querySelector(".menu-notifications");
                if (!menu.contains(e.target) && !notifications.contains(e.target)) {
                    menu.classList.remove("show");
                    e.stopPropagation();
                    e.preventDefault();
                }
            }
            document.querySelector(".tooltip")?.classList.remove("show");
        });*/

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

        burger.addEventListener("click", () => {
            burger.classList.toggle("open");
            /*navbar.classList.toggle("active");*/
            body.classList.toggle("frozen");
            if (burger.classList.contains("open")) {
                this.closeMenu(avatar, userMenu);
                mainMenu.classList.add("open");
                setTimeout(() => {
                    mainMenu.classList.add("show");
                }, 0);
            } else {
                setTimeout(() => {
                    mainMenu.classList.remove("show");
                    setTimeout(() => {
                        mainMenu.classList.remove("open");
                    }, 250);
                }, 0);
            }
        });

        avatar.addEventListener("click", () => {
            avatar.classList.toggle("open");
            /*navbar.classList.toggle("active");*/
            body.classList.toggle("frozen");
            if (avatar.classList.contains("open")) {
                this.closeMenu(burger, mainMenu);
                userMenu.classList.add("open");
                setTimeout(() => {
                    userMenu.classList.add("show");
                }, 0);
            } else {
                setTimeout(() => {
                    userMenu.classList.remove("show");
                    setTimeout(() => {
                        userMenu.classList.remove("open");
                    }, 250);
                }, 0);
            }
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
            const menu = notifications.querySelector(".menu-notifications");
            menu.classList.toggle("show");
            if (menu.classList.contains("show")) {
                this.closeMenu(burger, mainMenu);
                this.closeMenu(avatar, userMenu);
            }
            this.markNotificationsAsRead();
        });

        /*detailsElements.forEach((details) => {
            details.addEventListener("toggle", (e) => {
                if (details.open) {
                    this.closeMenu(burger, mainMenu);
                    this.closeMenu(avatar, userMenu);
                }
            });
        });*/

        if (movieSearch) {
            movieSearch.addEventListener("input", (e) => {
                const value = e.target.value;
                console.log({e});
                if (value.length > 2) {
                    const searchResults = movieSearch.closest(".menu-item").querySelector(".search-results");
                    const query = encodeURIComponent(value);
                    const url = 'https://api.themoviedb.org/3/search/movie?query=' + query + '&include_adult=false&language=fr-FR&page=1';
                    const options = {
                        method: 'GET',
                        headers: {
                            accept: 'application/json',
                            Authorization: 'Bearer eyJhbGciOiJIUzI1NiJ9.eyJhdWQiOiJmN2UzYzVmZTc5NGQ1NjViNDcxMzM0YzljNWVjYWY5NiIsIm5iZiI6MTcyMDYxMDA2Ni4zMzk0NzgsInN1YiI6IjYyMDJiZjg2ZTM4YmQ4MDA5MWVjOWIzOSIsInNjb3BlcyI6WyJhcGlfcmVhZCJdLCJ2ZXJzaW9uIjoxfQ.D5XVKmPsIrUKnZjQBXOhsKXzXtrejlHl8KT1dmZ2oyQ'
                        }
                    };

                    /* Object
                           adult: false
                           backdrop_path: "/qSJhQ8WjUcZT3XvHxedFy37KRyc.jpg"
                           genre_ids: [35] (1)
                           id: 643662
                           original_language: "en"
                           original_title: "Call Me by Your Maid"
                           overview: "The Perlman's maid has strong reactions when an exchange student comes to stay with the family."
                           popularity: 0.113
                           poster_path: "/mzpTgeOiL1bFU7p7q4VoKQnEHtB.jpg"
                           release_date: "2018-02-28"
                           title: "Call Me by Your Maid"
                           video: false
                           vote_average: 5
                           vote_count: 3
                       Object
                           adult: false
                           backdrop_path: "/baLoLeFw58xMGSuxaVUinBe4Y3b.jpg"
                           id: 1401402
                           original_language: "en"
                           original_title: "Call Me by Your Name Collection"
                           overview: "The collection of movies about Elio Perlman and Oliver"
                           poster_path: "/94DW591sFWh6kcNyuiHMgRrllFi.jpg"
                           title: "Call Me by Your Name Collection" */

                    fetch(url, options)
                        .then(res => res.json())
                        /** @type {SearchResults} */
                        .then(json => {
                            console.log(json);
                            searchResults.innerHTML = '';
                            const ul = document.createElement("ul");
                            ul.setAttribute("data-type", "movie");
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
                                titleDiv.innerHTML = result.title + (result.release_date ? ' (' + result.release_date.slice(0, 4) + ')': '');
                                a.appendChild(titleDiv);
                                // Si le lien est ouvert dans un autre onglet (bouton du milieu : auxclick), il faut supprimer le menu.
                                a.addEventListener("auxclick", (e) => {
                                    const details = e.currentTarget.closest("details");
                                    ul.remove();
                                    movieSearch.value = '';
                                    details.removeAttribute("open");
                                });
                                li.appendChild(a);
                                ul.appendChild(li);
                            });
                            searchResults.appendChild(ul);
                        })
                        .catch(err => console.error('error:' + err));
                } else {
                    movieSearch.closest(".menu-item").querySelector(".search-results").innerHTML = '';
                }
            });
            movieSearch.addEventListener("keydown", gThis.searchMenuNavigate);

            tvSearch.addEventListener("input", (e) => {
                const value = e.target.value;
                if (value.length > 2) {
                    const searchResults = tvSearch.closest(".menu-item").querySelector(".search-results");
                    const query = encodeURIComponent(value);
                    const url = 'https://api.themoviedb.org/3/search/tv?query=' + query + '&include_adult=false&language=fr-FR&page=1';
                    const options = {
                        method: 'GET',
                        headers: {
                            accept: 'application/json',
                            Authorization: 'Bearer eyJhbGciOiJIUzI1NiJ9.eyJhdWQiOiJmN2UzYzVmZTc5NGQ1NjViNDcxMzM0YzljNWVjYWY5NiIsIm5iZiI6MTcyMDYxMDA2Ni4zMzk0NzgsInN1YiI6IjYyMDJiZjg2ZTM4YmQ4MDA5MWVjOWIzOSIsInNjb3BlcyI6WyJhcGlfcmVhZCJdLCJ2ZXJzaW9uIjoxfQ.D5XVKmPsIrUKnZjQBXOhsKXzXtrejlHl8KT1dmZ2oyQ'
                        }
                    };

                    fetch(url, options)
                        .then(res => res.json())
                        .then(json => {
                            console.log(json);
                            searchResults.innerHTML = '';
                            const ul = document.createElement("ul");
                            ul.setAttribute("data-type", "tv");
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
                                // Si le lien est ouvert dans un autre onglet (bouton du milieu : auxclick), il faut supprimer le menu.
                                a.addEventListener("auxclick", (e) => {
                                    const details = e.currentTarget.closest("details");
                                    ul.remove();
                                    tvSearch.value = '';
                                    details.removeAttribute("open");
                                });
                                li.appendChild(a);
                                ul.appendChild(li);
                            });
                            searchResults.appendChild(ul);
                        })
                        .catch(err => console.error('error:' + err));
                } else {
                    tvSearch.closest(".menu-item").querySelector(".search-results").innerHTML = '';
                }
            });
            tvSearch.addEventListener("keydown", gThis.searchMenuNavigate);

            tvSearchDb.addEventListener("input", (e) => {
                const value = e.target.value;
                if (value.length > 2) {
                    const searchResults = tvSearchDb.closest(".menu-item").querySelector(".search-results");
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
                                // Si le lien est ouvert dans un autre onglet (bouton du milieu : auxclick), il faut supprimer le menu.
                                a.addEventListener("auxclick", (e) => {
                                    const details = e.currentTarget.closest("details");
                                    ul.remove();
                                    tvSearch.value = '';
                                    details.removeAttribute("open");
                                });
                                li.appendChild(a);
                                ul.appendChild(li);
                            });
                            searchResults.appendChild(ul);
                        })
                        .catch(err => console.error('error:' + err));
                } else {
                    tvSearchDb.closest(".menu-item").querySelector(".search-results").innerHTML = '';
                }
            });
            tvSearchDb.addEventListener("keydown", gThis.searchMenuNavigate);

            personSearch.addEventListener("input", (e) => {
                const value = e.target.value;
                if (value.length > 2) {
                    const searchResults = personSearch.closest(".menu-item").querySelector(".search-results");
                    const query = encodeURIComponent(value);
                    const url = 'https://api.themoviedb.org/3/search/person?query=' + query + '&include_adult=false&language=fr-FR&page=1';
                    const options = {
                        method: 'GET',
                        headers: {
                            accept: 'application/json',
                            Authorization: 'Bearer ' + gThis.bearer
                        }
                    };

                    fetch(url, options)
                        .then(res => res.json())
                        .then(json => {
                            console.log(json);
                            searchResults.innerHTML = '';
                            const ul = document.createElement("ul");
                            ul.setAttribute("data-type", "person");
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
                                a.addEventListener("auxclick", (e) => {
                                    const details = e.currentTarget.closest("details");
                                    ul.remove();
                                    personSearch.value = '';
                                    details.removeAttribute("open");
                                });
                                li.appendChild(a);
                                ul.appendChild(li);
                            });
                            searchResults.appendChild(ul);
                        })
                        .catch(err => console.error('error:' + err));
                } else {
                    personSearch.closest(".menu-item").querySelector(".search-results").innerHTML = '';
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
        if (value.length < 3) {
            if (searchResults.innerHTML.length) searchResults.innerHTML = '';
            return;
        }
        const searchType = searchInput.getAttribute("data-type");
        const tmdbAPI = 'https://api.themoviedb.org/3/search/';
        const tvdbAPI = `/${gThis.lang}/series/fetch/search/db/tv`;
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

        let query, url, options;
        if (searchType === 'tvdb') {
            url = tvdbAPI;
            options = {
                method: 'POST',
                headers: {
                    accept: 'application/json'
                },
                body: JSON.stringify({query: value})
            };
        } else {
            query = encodeURIComponent(value);
            url = tmdbAPI + searchType + '?query=' + query + '&include_adult=false&language=fr-FR&page=1';
            options = {
                method: 'GET',
                headers: {
                    accept: 'application/json',
                    Authorization: 'Bearer ' + gThis.bearer
                }
            };
        }

        fetch(url, options)
            .then(res => res.json())
            .then(json => {
                console.log(json);
                searchResults.innerHTML = '';
                const ul = document.createElement("ul");
                ul.setAttribute("data-type", searchType);

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
                    li.setAttribute("data-id", result['id']);
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
                        resultsDiv.innerHTML = '';
                        multiSearchInput.value = '';
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
                const li = ul.querySelector("li.active");
                const multiSearchMenuResultType = (type === 'multi') ? li?.getAttribute("data-type") : null;

                if (!li) {
                    if (type === 'tv') {
                        window.location.href = '/' + gThis.lang + '/series/search/all?q=' + value;
                    }
                    if (type === 'dbtv') {
                        window.location.href = '/' + gThis.lang + '/series/db/search/?name=' + value;
                    }
                    return;
                }

                const id = li.getAttribute("data-id");

                const details = li.closest("details");
                ul.remove();
                e.target.value = '';
                details?.removeAttribute("open");

                if (multiSearchMenuResultType) {
                    type = multiSearchMenuResultType;
                }

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
                }
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
        fetch('/' + gThis.lang + '/movie/tmdb/config', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            },
        })
            .then(response => response.json())
            /**
             *  @type {Object}
             * @property {string} poster_url
             * @property {string} profile_url
             */
            .then(data => {
                gThis.posterUrl = data.body.poster_url;
                gThis.profileUrl = data.body.profile_url;
                gThis.bearer = data.body.bearer;
            })
            .catch((error) => {
                console.error('Error:', error);
            });
    }

    /* checkConnexion() {
         gThis.avatar.classList.add("highlight");
         fetch('/' + gThis.lang + '/user/is-connected', {
             method: 'GET',
             headers: {
                 'Content-Type': 'application/json',
             },
         })
             .then(response => response.json())
             .then(data => {
                 if (!data.body.connected) {
                     clearInterval(gThis.connexionInterval);
                     gThis.avatar.remove();
                     console.log('User disconnected')
                 } else {
                     gThis.avatar.classList.add("connected");
                     setTimeout(() => {
                         gThis.avatar.classList.remove("highlight", "connected");
                     }, 500);
                     console.log('User still connected')
                 }
             })
             .catch((error) => {
                 console.error('Error:', error);
             });
     }*/

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
        document.body.classList.remove("dark", "light");
        if (theme !== 'auto') document.body.classList.add(theme);
        localStorage.setItem("mytvtime_2_theme", theme);
        this.checkTheme(theme);
        // Créer un événement "theme-change" pour que les autres modules puissent l'écouter
        const event = new Event("theme-changed");
        document.dispatchEvent(event);
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
        const firstItem = historyList.querySelector(".history-item");
        const loadingDiv = document.createElement("div");
        loadingDiv.classList.add("loading");
        loadingDiv.innerHTML = 'Checking...';
        historyList.insertBefore(loadingDiv, firstItem);
        console.log({open});
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
                console.log(lastWatchedEpisode, lastEpisodeInHistory);
                if (lastEpisodeInHistory !== lastWatchedEpisode) {
                    loadingDiv.innerHTML = 'Reloading...';
                    gThis.reloadHistory({currentTarget: historyOptions[0]});
                }
                loadingDiv.remove();
            });
    }
    reloadHistory(e) {
        e.stopPropagation();

        const historyList = document.querySelector("#history-list");
        const historyOptions = historyList.querySelector("#history-options").querySelectorAll("input");
        const historyOption = e.currentTarget;
        const optionId = historyOption.id.split('-')[2];
        const historyListItems = historyList.querySelectorAll(".history-item");
        const options = {'type': false, 'page': 1, 'count': 20, 'vote': false, 'device': false, 'provider': false};

        historyOptions.forEach(option => {
            if (option.type === 'checkbox') {
                options[option.id.split('-')[2]] = option.checked;
            }
            if (option.type === 'number') {
                options[option.id.split('-')[2]] = option.value;
            }
        });
        console.log({options});

        if (optionId === 'vote' || optionId === 'device' || optionId === 'provider') {
            historyListItems.forEach((item) => {
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
                historyListItems.forEach((item) => {
                    item.remove();
                });
                /** @type {HistoryItem} */
                data.list.forEach((item) => {
                    const div = document.createElement("div");
                    div.classList.add("menu-item");
                    div.classList.add("history-item");
                    div.setAttribute("id", item.episodeId);
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

                    /*
                        <div class="vote{% if history.vote == 0 %} hidden{% endif %}">{{ h.vote }}</div>
                        <div class="device{% if history.device == 0 %} hidden{% endif %}">{{ ux_icon(h.deviceSvg, {height: "18px", width: "18px"}) }}</div>
                        <div class="provider{% if history.provider == 0 %} hidden{% endif %}"><img src="{{ h.providerLogoPath }}" alt="{{ h.providerName }}"></div>
                     */
                    const vote = document.createElement("div");
                    vote.classList.add("vote");
                    if (options.vote === false) vote.classList.add('hidden');
                    vote.innerHTML = item.vote;
                    a.appendChild(vote);

                    const device = document.createElement("div");
                    device.classList.add("device");
                    if (options.device === false) device.classList.add('hidden');
                    device.innerHTML = gThis.svgs[item.deviceSvg];
                    a.appendChild(device);

                    const provider = document.createElement("div");
                    provider.classList.add("provider");
                    if (options.provider === false) provider.classList.add('hidden');
                    if (item.providerLogoPath) {
                        const imgProvider = document.createElement("img");
                        imgProvider.src = item.providerLogoPath;
                        imgProvider.alt = item.providerName;
                        provider.appendChild(imgProvider);
                    }
                    a.appendChild(provider);

                    const number = document.createElement("div");
                    number.classList.add("number");
                    number.innerHTML = 'S' + (item.seasonNumber < 10 ? '0' + item.seasonNumber : item.seasonNumber) + 'E' + (item.episodeNumber < 10 ? '0' + item.episodeNumber : item.episodeNumber);
                    a.appendChild(number);

                    const date = document.createElement("div");
                    date.classList.add("date");
                    date.innerHTML = item.lastWatchAt;
                    a.appendChild(date);

                    div.appendChild(a);
                    historyList.appendChild(div);
                });
            })
            .catch((error) => {
                console.error('Error:', error);
            });
    }

    checkLog() {
        const logList = document.querySelector("#log-list");
        const firstItem = logList.querySelector(".log-item");
        const lastId = firstItem ? firstItem.getAttribute("data-id") : 0;
        const loadingDiv = document.createElement("div");
        loadingDiv.classList.add("menu-item");
        const div = document.createElement("div");
        div.classList.add("loading");
        div.innerHTML = 'Loading...';
        loadingDiv.appendChild(div);
        logList.insertBefore(loadingDiv, firstItem);
        fetch('/api/log/last', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            }
        })
            .then(response => response.json())
            /** @var {{ok: boolean, last: number}} data */
            .then(data => {
                console.log(lastId, data);
                if (parseInt(lastId) !== data.last) {
                    loadingDiv.querySelector('div').innerHTML = 'Reloading...';
                    gThis.reloadLog();
                } else {
                    loadingDiv.remove();
                }
            });
    }

    reloadLog() {
        // api url : /api/log/load
        const logList = document.querySelector("#log-list");
        const logListItems = logList.querySelectorAll(".log-item, .log-date");
        const lastId = logListItems[0].getAttribute("data-id");
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
                const countDiv = logList.querySelector("#log-count");
                /** @type {LogItem[]} */
                const logs = data.logs;
                let dateString = "";
                logListItems.forEach((item) => {
                    item.remove();
                });
                countDiv.innerHTML = data.count;
                console.log(logs);
                console.log(data.dates);
                /** @type {LogItem} */
                logs.forEach((item) => {
                    const itemDateString = data.dates[item.dateKey];
                    if (itemDateString !== dateString) {
                        // const now = new Date();
                        const div = document.createElement("div");
                        div.classList.add("menu-item");
                        div.classList.add("log-date");
                        div.innerHTML = itemDateString;
                        logList.appendChild(div);
                        dateString = itemDateString;
                    }

                    const div = document.createElement("div");
                    div.classList.add("menu-item");
                    div.classList.add("log-item");
                    div.setAttribute("data-id", `${item.id}`);

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

                    div.appendChild(a);
                    logList.appendChild(div);
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