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
     * @property {number} episodeNumber
     * @property {number} id
     * @property {number} seasonNumber
     * @property {string} name
     * @property {string} posterPath
     * @property {string} url
     */
    constructor() {
        gThis = this;
        this.menuPreview = document.querySelector(".menu-preview");
        this.menuThemes = document.querySelectorAll(".menu-theme");
        this.init = this.init.bind(this);
        this.togglePreview = this.togglePreview.bind(this);
        this.setPreview = this.setPreview.bind(this);
        this.initPreview = this.initPreview.bind(this);
        this.setTheme = this.setTheme.bind(this);
        this.checkTheme = this.checkTheme.bind(this);
        this.lang = document.documentElement.lang;
        this.avatar = document.querySelector('.avatar');
        /*this.userConnected = this.avatar != null;
        this.connexionInterval = null;*/
        this.posterUrl = null;
        this.profileUrl = null;
    }

    init() {
        this.getImageConfig();
        const burger = document.querySelector(".burger");
        const navbar = document.querySelector(".navbar");
        const mainMenu = navbar.querySelector(".menu");
        const eotdMenuItems = document.querySelectorAll("a[id^='eotd-menu-item-']");
        const pinnedMenuItems = document.querySelectorAll("a[id^='pinned-menu-item-']");
        const body = document.querySelector("body");
        const notifications = document.querySelector(".notifications");
        const detailsElements = document.querySelectorAll("details");
        const movieSearch = navbar.querySelector("#movie-search");
        const tvSearch = navbar.querySelector("#tv-search");
        const tvSearchDb = navbar.querySelector("#tv-search-db");
        const personSearch = navbar.querySelector("#person-search");

        const historyList = navbar.querySelector("#history-list");
        const historyOption = historyList.querySelector("#history-option").querySelector("input[type='checkbox']");

        burger.addEventListener("click", () => {
            burger.classList.toggle("open");
            navbar.classList.toggle("active");
            body.classList.toggle("frozen");
            if (burger.classList.contains("open")) {
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

        notifications?.addEventListener("click", () => {
            const menu = notifications.querySelector(".menu-notifications");
            menu.classList.toggle("show");
            this.markNotificationsAsRead();
        });

        document.addEventListener("click", (e) => {
            if (burger.classList.contains("open") && !navbar.contains(e.target) && !burger.contains(e.target)) {
                burger.classList.remove("open");
                navbar.classList.remove("active");
                body.classList.remove("frozen");

                setTimeout(() => {
                    mainMenu.classList.remove("show");
                    setTimeout(() => {
                        mainMenu.classList.remove("open");
                    }, 250);
                }, 0);
                e.stopPropagation();
                e.preventDefault();
            }
            if (notifications?.querySelector(".menu-notifications").classList.contains("show")) {
                const menu = notifications.querySelector(".menu-notifications");
                if (!menu.contains(e.target) && !notifications.contains(e.target)) {
                    menu.classList.remove("show");
                    e.stopPropagation();
                    e.preventDefault();
                }
            }
            detailsElements.forEach((details) => {
                if (details.open && !details.contains(e.target)) {
                    details.removeAttribute("open");
                    e.stopPropagation();
                    e.preventDefault();
                }
            });
            document.querySelector(".tooltip")?.classList.remove("show");
        });

        this.menuPreview.addEventListener("click", this.togglePreview);
        this.menuThemes.forEach((theme) => {
            theme.addEventListener("click", this.setTheme);
        });
        this.initTheme();
        this.initPreview();

        movieSearch.addEventListener("input", (e) => {
            const value = e.target.value;
            console.log({e});
            if (value.length > 2) {
                const searchResults = movieSearch.closest("li").querySelector(".search-results");
                const query = encodeURIComponent(value);
                const url = 'https://api.themoviedb.org/3/search/movie?query=' + query + '&include_adult=false&language=fr-FR&page=1';
                const options = {
                    method: 'GET',
                    headers: {
                        accept: 'application/json',
                        Authorization: 'Bearer eyJhbGciOiJIUzI1NiJ9.eyJhdWQiOiJmN2UzYzVmZTc5NGQ1NjViNDcxMzM0YzljNWVjYWY5NiIsIm5iZiI6MTcyMDYxMDA2Ni4zMzk0NzgsInN1YiI6IjYyMDJiZjg2ZTM4YmQ4MDA5MWVjOWIzOSIsInNjb3BlcyI6WyJhcGlfcmVhZCJdLCJ2ZXJzaW9uIjoxfQ.D5XVKmPsIrUKnZjQBXOhsKXzXtrejlHl8KT1dmZ2oyQ'
                    }
                };

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
                            titleDiv.innerHTML = result.title;
                            a.appendChild(titleDiv);
                            // Si le lien est ouvert dans un autre onglet (bouton du milieu : auxclick), il faut supprimer le menu
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
                movieSearch.closest("li").querySelector(".search-results").innerHTML = '';
            }
        });
        movieSearch.addEventListener("keydown", gThis.searchMenuNavigate);

        tvSearch.addEventListener("input", (e) => {
            const value = e.target.value;
            if (value.length > 2) {
                const searchResults = tvSearch.closest("li").querySelector(".search-results");
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
                            // Si le lien est ouvert dans un autre onglet (bouton du milieu : auxclick), il faut supprimer le menu
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
                tvSearch.closest("li").querySelector(".search-results").innerHTML = '';
            }
        });
        tvSearch.addEventListener("keydown", gThis.searchMenuNavigate);

        tvSearchDb.addEventListener("input", (e) => {
            const value = e.target.value;
            if (value.length > 2) {
                const searchResults = tvSearchDb.closest("li").querySelector(".search-results");
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
                            // Si le lien est ouvert dans un autre onglet (bouton du milieu : auxclick), il faut supprimer le menu
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
                tvSearch.closest("li").querySelector(".search-results").innerHTML = '';
            }
        });
        tvSearchDb.addEventListener("keydown", gThis.searchMenuNavigate);

        personSearch.addEventListener("input", (e) => {
            const value = e.target.value;
            if (value.length > 2) {
                const searchResults = personSearch.closest("li").querySelector(".search-results");
                const query = encodeURIComponent(value);
                const url = 'https://api.themoviedb.org/3/search/person?query=' + query + '&include_adult=false&language=fr-FR&page=1';
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
                personSearch.closest("li").querySelector(".search-results").innerHTML = '';
            }
        });
        personSearch.addEventListener("keydown", gThis.searchMenuNavigate);

        historyOption.addEventListener("change", this.reloadHistory);
    }

    searchMenuNavigate(e) {
        // movieSearch, tvSearch, tvSearchDb or personSearch
        const searchMenu = e.target;
        // console.log({e});
        const value = e.target.value;
        if (value.length > 2) {
            const ul = searchMenu.closest("li").querySelector(".search-results ul");
            if (!ul) return;
            const type = ul.getAttribute("data-type");
            // console.log({ul});
            // console.log(e.key);
            if (e.key === 'Enter') {
                e.preventDefault();
                const li = ul.querySelector("li.active") || ul.querySelector("li");
                const id = li.getAttribute("data-id");

                const details = li.closest("details");
                ul.remove();
                e.target.value = '';
                details.removeAttribute("open");

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

    getImageConfig() {
        fetch('/' + gThis.lang + '/movie/image/config', {
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

    reloadHistory(e) {
        const historyList = document.querySelector("#history-list");
        const historyOption = e.currentTarget;
        const historyListItems = historyList.querySelectorAll("li.history-item");
        // if checked, fetch last viewed episodes else fetch last viewed series
        const type = historyOption.checked ? 'episode' : 'series';

        fetch('/api/history/menu', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({type: type})
        })
            .then(response => response.json())
            .then(data => {
                historyListItems.forEach((item) => {
                    item.remove();
                });
                /** @type {HistoryItem} */
                data.list.forEach((item) => {
                    const li = document.createElement("li");
                    li.classList.add("history-item");
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
                    const number = document.createElement("div");
                    number.classList.add("number");
                    number.innerHTML = 'S' + (item.seasonNumber < 10 ? '0' + item.seasonNumber : item.seasonNumber) + 'E' + (item.episodeNumber < 10 ? '0' + item.episodeNumber : item.episodeNumber);
                    a.appendChild(number);
                    const date = document.createElement("div");
                    date.classList.add("date");
                    date.innerHTML = item.lastWatchAt;
                    a.appendChild(date);
                    li.appendChild(a);
                    historyList.appendChild(li);
                });
            })
            .catch((error) => {
                console.error('Error:', error);
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