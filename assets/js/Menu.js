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
        this.root = document.documentElement;
        this.userMenu = document.querySelector(".menu.user");
        this.userConnected = this.userMenu.getAttribute("data-user-connected") === "true";
        this.accentColor = this.userMenu.querySelector(".accent-color-settings");
        this.scheduleRange = this.userMenu.querySelector(".schedule-range-settings");
        this.whatNext = this.userMenu.querySelector(".what-next-settings");
        this.menuPreview = this.userMenu.querySelector(".menu-preview-settings");
        this.menuThemes = this.userMenu.querySelectorAll(".menu-theme");
        this.clientHeight = window.innerHeight;
        this.init = this.init.bind(this);
        this.togglePreview = this.togglePreview.bind(this);
        this.setPreview = this.setPreview.bind(this);
        this.initPreview = this.initPreview.bind(this);
        this.setTheme = this.setTheme.bind(this);
        this.checkTheme = this.checkTheme.bind(this);
        this.getAccentColor = this.getAccentColor.bind(this);
        this.getScheduleRange = this.getScheduleRange.bind(this);
        this.lang = document.documentElement.lang;
        this.initialPreviewSetting = null;
        this.posterUrl = null;
        this.profileUrl = null;
        this.svgs = {
            "mdi:tv": "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"1em\" height=\"1em\" viewBox=\"0 0 24 24\"><path fill=\"currentColor\" d=\"M21 17H3V5h18m0-2H3a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h5v2h8v-2h5a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2\"/></svg>",
            "mdi:mobile-phone": "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"1em\" height=\"1em\" viewBox=\"0 0 24 24\"><path fill=\"currentColor\" d=\"M17 19H7V5h10m0-4H7c-1.11 0-2 .89-2 2v18a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2\"/></svg>",
            "mdi:tablet": "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"1em\" height=\"1em\" viewBox=\"0 0 24 24\"><path fill=\"currentColor\" d=\"M19 18H5V6h14m2-2H3c-1.11 0-2 .89-2 2v12a2 2 0 0 0 2 2h18a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2\"/></svg>",
            "mdi:laptop": "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"1em\" height=\"1em\" viewBox=\"0 0 24 24\"><path fill=\"currentColor\" d=\"M4 6h16v10H4m16 2a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2H4c-1.11 0-2 .89-2 2v10a2 2 0 0 0 2 2H0v2h24v-2z\"/></svg>",
            "mdi:desktop-windows": "<svg viewBox=\"0 0 576 512\" height=\"18px\" width=\"18px\" aria-hidden=\"true\"><path fill=\"currentColor\" d=\"M64 0C28.7 0 0 28.7 0 64v288c0 35.3 28.7 64 64 64h176l-10.7 32H160c-17.7 0-32 14.3-32 32s14.3 32 32 32h256c17.7 0 32-14.3 32-32s-14.3-32-32-32h-69.3L336 416h176c35.3 0 64-28.7 64-64V64c0-35.3-28.7-64-64-64zm448 64v224H64V64z\"></path></svg>"
        }

        this.apiEndPoints = {
            "multi": "/api/search/multi",
            "movie": "/api/search/tmdb/movie",
            "movie_id": "/api/search/tmdb/movie/",
            "dbmovie": "/api/search/db/movie",
            "tv": "/api/search/tmdb/tv",
            "tv_id": "/api/search/tmdb/tv/",
            "dbtv": "/api/search/db/tv",
            "people": "/api/search/people"
        }
        this.resultNames = {
            'movie': 'title',
            'movie_id': 'title',
            'dbmovie': 'display_title',
            'collection': 'title',
            'tv': 'name',
            'tv_id': 'name',
            'dbtv': 'display_name',
            'people': 'name'
        }
        this.hRefs = {
            'movie': 'movie/tmdb/',
            'movie_id': 'movie/tmdb/',
            'dbmovie': 'movie/show/',
            'collection': 'movie/collection/',
            'tv': 'series/tmdb/',
            'tv_id': 'series/tmdb/',
            'dbtv': 'series/show/',
            'people': 'people/show/',
            'multi': 'search/all?q='
        };
        this.resultPaths = {
            'movie': 'poster_path',
            'movie_id': 'poster_path',
            'dbmovie': 'poster_path',
            'collection': 'poster_path',
            'tv': 'poster_path',
            'tv_id': 'poster_path',
            'dbtv': 'poster_path',
            'people': 'profile_path'
        };
    }

    init() {
        const navbar = document.querySelector(".navbar");
        if (!navbar) {
            console.log("Navbar not found");
            return;
        }

        window.addEventListener("resize", () => {
            this.clientHeight = window.innerHeight;
        });

        this.reloadOnDayChange();
        this.getTMDBConfig();
        this.initOptions();

        this.getAccentColor();

        this.tooltips = new ToolTips();

        const navbarItems = navbar.querySelectorAll(".navbar-item");
        const historyNavbarItem = navbar.querySelector("#history-menu");
        this.adjustHistoryList();

        setInterval(() => {
            const mainMenuIsOpen = navbar.querySelector(".menu.main.open.show");
            if (!mainMenuIsOpen) {
                return;
            }
            this.updateMainMenu();
        }, 60000);

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
                gThis.tooltips.hide()
                if (menu.classList.contains("history")) {
                    gThis.checkHistory();
                }
                if (menu.classList.contains("log")) {
                    gThis.checkLog();
                }
                if (menu.classList.contains("main")) {
                    gThis.updateMainMenu();
                }
            });
        });

        this.posterPreview();
        this.initMultiSearch(navbar);

        if (historyNavbarItem) {
            const historyMenu = historyNavbarItem.querySelector(".menu");
            const historyOptions = historyMenu.querySelector("#history-options").querySelectorAll("input");
            historyOptions.forEach((historyOption) => {
                historyOption.addEventListener("change", this.reloadHistory);
            });
        }
    }


    initMultiSearch(navbar) {
        if (!this.userConnected) {
            return;
        }
        const multiSearch = navbar.querySelector("#multi-search");
        const multiSearchDiv = navbar.querySelector(".multi-search");
        const magnifyingGlassSpan = multiSearchDiv.querySelector(".magnifying-glass");
        const multiSearchOptionsButton = multiSearchDiv.querySelector(".multi-search-options-button");
        const multiSearchOptionsMenu = multiSearchDiv.querySelector(".multi-search-options-menu");
        const multiSearchOptions = multiSearchOptionsMenu.querySelectorAll(".multi-search-option");
        const displayPosterToggler = multiSearchOptionsMenu.querySelector("#display-poster-toggler");
        const openInNewTabToggler = multiSearchOptionsMenu.querySelector("#new-tab-toggler");
        magnifyingGlassSpan.addEventListener("click", () => {
            multiSearchDiv.classList.toggle("active");
            if (multiSearchDiv.classList.contains("active")) {
                multiSearch.focus();
                gThis.initialPreviewSetting = gThis.getPreview();
                gThis.forcePreview(displayPosterToggler.checked);
            } else {
                gThis.forcePreview(gThis.initialPreviewSetting);
                multiSearchOptionsMenu.classList.remove("active");
                multiSearch.value = "";
                gThis.closeMultiSearchMenu(multiSearch);
            }
        });
        displayPosterToggler.addEventListener("change", () => {
            gThis.forcePreview(displayPosterToggler.checked);
        });
        openInNewTabToggler.addEventListener("change", () => {
            const as = multiSearchDiv.querySelectorAll(".search-results ul li a");
            const openInNewTab = openInNewTabToggler.checked;
            console.log(as);
            console.log(openInNewTab);
            if (openInNewTab) {
                as.forEach(a => {
                    a.target = "_blank"
                });
            } else {
                as.forEach(a => {
                    a.target = "_self"
                });
            }
        });

        multiSearchOptionsButton.addEventListener("click", () => {
            multiSearchOptionsMenu.classList.toggle("active");
        });
        multiSearchOptions.forEach(option => {
            option.addEventListener("click", () => {
                if (option.classList.contains("active")) {
                    return;
                }
                const newValue = option.getAttribute("data-value");
                // On assigne la nouvelle option
                multiSearchDiv.querySelector("ul").setAttribute("data-sub-type", newValue);
                // On modifie le placeholder
                multiSearch.setAttribute("placeholder", option.innerText);
                // On active la nouvelle option
                multiSearchOptions.forEach(option => {
                    option.classList.remove("active");
                });
                option.classList.add("active");
                // On ferme le menu
                multiSearchOptionsMenu.classList.toggle("active");
                // On met le focus sur le champ de recherche
                multiSearch.focus();
                // On sauve l'option dans un cookie
                gThis.setMultiSearchOptionCookie(newValue);
                // On relance la recherche
                gThis.searchFetch({currentTarget: multiSearch});
            });
        });

        const cookie = document.cookie;
        let initialValue = "multi";
        if (cookie) {
            const re = new RegExp(/mytvtime_2_multi_search_sub_type=(\w+);/);
            const result = re.exec(cookie);
            if (result) {
                initialValue = result[1];
            }
        }
        if (initialValue !== "multi") {
            // On assigne la nouvelle option
            multiSearchDiv.querySelector("ul").setAttribute("data-sub-type", initialValue);
            const option = multiSearchOptionsMenu.querySelector("div[data-value=\"" + initialValue + "\"]")
            // On modifie le placeholder
            multiSearch.setAttribute("placeholder", option.innerText);
            // On active la nouvelle option
            multiSearchOptions.forEach(option => {
                option.classList.remove("active");
            });
            option.classList.add("active");
        }

        multiSearch.addEventListener("input", gThis.searchFetch);
        multiSearch.addEventListener("keydown", gThis.searchMenuNavigate);
    }

    setMultiSearchOptionCookie(multiSearchSubType) {
        const date = new Date();
        date.setTime(date.getTime() + 365 * 24 * 60 * 60 * 1000);
        document.cookie = "mytvtime_2_multi_search_sub_type=" + multiSearchSubType + ";expires=" + date.toUTCString() + ";path=/";
    }

    posterPreview() {
        if (!this.userConnected) {
            return;
        }
        const eotdMenuItems = document.querySelectorAll("a[id^='eotd-menu-item-']");
        eotdMenuItems.forEach((item) => {
            const group = item.id.split("-")[3]; // eotd-menu-item-{group}-{id}
            const id = item.id.split("-")[4];
            const eotdPreview = document.querySelector(`#eotd-preview-${group}-${id}`);
            item.addEventListener("mouseenter", (e) => {
                if (e.clientY > gThis.clientHeight / 2)
                    eotdPreview.classList.add("up");
                else
                    eotdPreview.classList.remove("up");
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

        const pinnedMenuItems = document.querySelectorAll("a[id^='pinned-menu-item-']");
        const seriesInProgress = document.querySelector("a[id^='sip-menu-item-']");
        // On ajoute seriesInProgress aux pinnedMenuItems dans un nouveau tableau
        const arr = Array.from(pinnedMenuItems);
        arr.push(seriesInProgress);

        arr.forEach((item) => {
            const id = item.id.split("-")[3];
            const preview = document.querySelector(`div[id$="preview-${id}"]`);
            item.addEventListener("mouseenter", (e) => {
                if (e.clientY > gThis.clientHeight / 2)
                    preview.classList.add("up");
                else
                    preview.classList.remove("up");
                preview.classList.add("open");
                setTimeout(() => {
                    preview.classList.add("show");
                }, 0);
            });
            item.addEventListener("mouseleave", () => {
                setTimeout(() => {
                    preview.classList.remove("show");
                    setTimeout(() => {
                        preview.classList.remove("open");
                    }, 250);
                }, 0);
            });
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
        if (this.userConnected) {
            this.accentColor.addEventListener("click", this.setAccentColor);
            this.scheduleRange.addEventListener("click", this.setScheduleRange);
            this.whatNext.addEventListener("click", this.setWhatNext);
            this.menuPreview.addEventListener("click", this.togglePreview);
            this.initPreview();
        }
        this.menuThemes.forEach((theme) => {
            theme.addEventListener("click", this.setTheme);
        });
        this.initTheme();
    }

    updateMainMenu() {
        const scheduleMenuDiv = document.querySelector(".schedule-menu");
        const lastEpisodeId = scheduleMenuDiv.getAttribute("data-id") || "-1";
        const locale = this.lang;
        const apiUrl = '/api/main/menu/update';
        const options = {
            method: 'POST',
            headers: {
                accept: 'application/json'
            },
            body: JSON.stringify({locale: locale, lastViewedEpisodeId: localStorage.getItem("schedule_range_updated") ? -1 : lastEpisodeId}),
        };

        fetch(apiUrl, options)
            .then(res => res.json())
            .then(res => {
                localStorage.removeItem("schedule_range_updated");
                if (res['update'] === false) return;
                const scheduleMenuDiv = document.querySelector(".schedule-menu");
                const block = res['block'];
                const blockDiv = document.createElement('div');
                blockDiv.innerHTML = block;
                const newScheduleMenuDiv = blockDiv.querySelector(".schedule-menu");
                scheduleMenuDiv.innerHTML = newScheduleMenuDiv.innerHTML;
                scheduleMenuDiv.setAttribute("data-id", newScheduleMenuDiv.getAttribute("data-id"));

                gThis.posterPreview();
            })
            .catch(err => {
                console.log(err);
            });

        if (document.querySelector(".suggestions")) {
            return;
        }
        fetch("/api/main/menu/suggestions")
            .then(res => res.json())
            .then(res => {
                const list = res['suggestions'];
                const suggestionsDiv = document.createElement("div");
                suggestionsDiv.classList.add("suggestions");
                const wrapperDiv = document.createElement("div");
                wrapperDiv.classList.add("wrapper");
                suggestionsDiv.appendChild(wrapperDiv);
                list.forEach(item => {
                    const a = document.createElement("a");
                    a.href = item['href'];
                    const suggestionDiv = document.createElement("div");
                    suggestionDiv.classList.add("suggestion");
                    suggestionDiv.setAttribute("data-title", item['name']);
                    const img = document.createElement("img");
                    img.src = "/series/posters" + item['poster_path'];
                    img.alt = item['name'];
                    a.appendChild(img);
                    suggestionDiv.appendChild(a);
                    wrapperDiv.appendChild(suggestionDiv);
                });
                const mainMenu = document.querySelector(".menu.main");
                const separation = mainMenu.querySelector("#menu-main-pinned-series");
                const suggestionSeparation = document.createElement("div");
                suggestionSeparation.classList.add("separation");
                suggestionSeparation.innerText = res['label'];
                if (separation) {
                    mainMenu.insertBefore(suggestionSeparation, separation);
                    mainMenu.insertBefore(suggestionsDiv, separation);
                } else {
                    mainMenu.appendChild(suggestionSeparation);
                    mainMenu.appendChild(suggestionsDiv);
                }
                wrapperDiv.classList.add("ready");
                gThis.tooltips.init(suggestionsDiv);
            })
            .catch(err => {
                console.log(err);
            });
    }

    searchFetch(e) {
        const openMultiSearchOptionsMenu = document.querySelector(".multi-search-options-menu.active");
        if (openMultiSearchOptionsMenu) {
            openMultiSearchOptionsMenu.classList.remove("active");
        }

        const searchInput = e.currentTarget;
        const value = searchInput.value;
        const searchResults = searchInput.parentElement.parentElement.querySelector(".search-results");
        const ul = searchResults.querySelector('ul');//document.createElement("ul");
        const lis = ul?.querySelectorAll('li');
        if (value.length < 3) {
            lis.forEach(item => {
                item.remove();
            });
            searchResults.classList.remove("showing-something");
            return;
        }
        const searchType = ul.getAttribute("data-type");
        const searchSubType = ul.getAttribute("data-sub-type");
        const baseHref = "/" + gThis.lang + "/";
        let url, options;

        if (searchSubType === 'tv_id' || searchSubType === 'movie_id')
        {
            url = gThis.apiEndPoints[searchSubType] + value;
            options = {
                method: 'GET',
                headers: {
                    accept: 'application/json'
                }
            };
        } else {
            url = gThis.apiEndPoints[searchSubType || searchType];
            options = {
                method: 'POST',
                headers: {
                    accept: 'application/json'
                },
                body: JSON.stringify({query: value})
            };
        }

        fetch(url, options)
            .then(res => res.json())
            .then(json => {
                // console.log(json);
                const openInNewTab = document.querySelector(".navbar .multi-search .multi-search-options-menu #new-tab-toggler").checked;
                console.log(openInNewTab);
                const addCastBlock = searchInput.closest('.cast-search-block');
                const isAddCastInput = searchType === 'people' && addCastBlock !== null;
                const lis = ul.querySelectorAll('li');
                lis.forEach(item => {
                    item.remove();
                });

                if (json.results.length) {
                    searchResults.classList.add("showing-something");
                }

                json.results.forEach((result, index) => {
                    const type = result['media_type'] || searchSubType || searchType; // Pour les résultats de recherche multi
                    if (type === 'collection') {
                        console.log({index});
                        console.log({result});
                        //return; // On ne veut pas de collection
                    }
                    let url;
                    if (isAddCastInput) {
                        url = null;
                    } else {
                        url = baseHref + gThis.hRefs[type] + result['id'];
                        if (type !== 'movie' && type !== 'dbmovie' && type !== 'collection') url += '-' + gThis.toSlug(result[gThis.resultNames[type]]);
                    }
                    const aDiv = document.createElement(url ? "a" : "div");
                    if (url) {
                        aDiv.href = url;
                        aDiv.target = openInNewTab ? "_blank" : "_self";
                    } else {
                        const hiddenInputPersonId = addCastBlock.querySelector('#cast-search-person-id');
                        const castNameInput = addCastBlock.querySelector('#cast-search');
                        aDiv.setAttribute("person-id", result['id'].toString());
                        aDiv.setAttribute("name", result['name'].toString());
                        aDiv.addEventListener("click", e => {
                            castNameInput.removeEventListener("input", gThis.searchFetch);
                            castNameInput.addEventListener("input", () => {
                                castNameInput.addEventListener("input", gThis.searchFetch); // The same type & listener do not add event listener
                            });
                            hiddenInputPersonId.value = aDiv.getAttribute("person-id");
                            castNameInput.value = aDiv.getAttribute("name");
                            const thisUl = e.target.closest("ul");
                            const lis = thisUl.querySelectorAll('li');
                            lis.forEach(item => {
                                item.remove();
                            });
                        })
                    }
                    const li = document.createElement("li");
                    li.setAttribute('data-title', result[gThis.resultNames[type]]);
                    if (!index) li.classList.add("active");
                    const posterDiv = document.createElement("div");
                    posterDiv.classList.add("poster");
                    const imageResult = gThis.resultPaths[type];
                    if (result[imageResult]) {
                        const img = document.createElement("img");
                        img.src = gThis.imagePaths[type] + result[imageResult];
                        img.alt = result[gThis.resultNames[type]];
                        posterDiv.appendChild(img);
                    } else {
                        posterDiv.innerHTML = '<div>No poster</div>';
                    }
                    aDiv.appendChild(posterDiv);
                    const titleDiv = document.createElement("div");
                    titleDiv.classList.add("title");
                    titleDiv.innerHTML = result[gThis.resultNames[type]];
                    aDiv.appendChild(titleDiv);
                    li.appendChild(aDiv);
                    ul.appendChild(li);
                });
                gThis.tooltips.init(ul);
                searchResults.appendChild(ul);
            })
            .catch(err => console.error('error:' + err));
    }

    closeMultiSearchMenu(input) {
        const searchResults = input.parentElement.parentElement.querySelector(".search-results");
        const ul = searchResults.querySelector('ul');//document.createElement("ul");
        const lis = ul?.querySelectorAll('li');

        lis.forEach(item => {
            item.remove();
        });
        searchResults.classList.remove("showing-something");
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
            if (e.key === 'Enter') {
                e.preventDefault();
                const li = ul.querySelector("li.active") ?? ul.querySelector("li");
                if (type === 'multi') {
                    li.querySelector("a").click();
                    return;
                }

                if (!li) {
                    if (type === 'movie') {
                        window.location.href = '/' + gThis.lang + '/movie/search/all?q=' + value;
                    }
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
                const lis = ul.querySelectorAll("li");
                lis.forEach(item => {
                    item.remove();
                });
                e.target.value = '';
                searchResults.classList.remove("showing-something");
                const menuDiv = searchResults.closest(".menu");
                const navbarItem = menuDiv.closest(".navbar-item");
                gThis.closeMenu(navbarItem, menuDiv);
                /* << */
                a.click();
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
        fetch('/api/search/multi', {
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
                gThis.imagePaths = {
                    'movie': gThis.posterUrl,
                    'movie_id': gThis.posterUrl,
                    'dbmovie': '/movies/posters',
                    'collection': gThis.posterUrl,
                    'tv': gThis.posterUrl,
                    'tv_id': gThis.posterUrl,
                    'dbtv': '/series/posters',
                    'people': gThis.profileUrl
                };
            })
            .catch((error) => {
                console.error({error});
            });
    }

    initPreview() {
        this.setPreview(localStorage.getItem("mytvtime_2_preview"));
    }

    setAccentColor() {
        document.documentElement.click();
        /** @type HTMLDialogElement */
        const accentColorDialog = document.querySelector("#accentColorDialog");
        const submitButton = accentColorDialog.querySelector("button[type=submit]");
        const resetButton = accentColorDialog.querySelector("button[type=reset]");
        const cancelButton = accentColorDialog.querySelector("button[type=button]");
        const accentColorInput = accentColorDialog.querySelector("input[type=color]");

        accentColorInput.value = gThis.accentColorValue;
        cancelButton.addEventListener("click", () => {
            gThis.root.style.setProperty("--accent-color", gThis.accentColorValue);
            accentColorDialog.close();
        });
        resetButton.addEventListener("click", () => {
            accentColorInput.value = gThis.defaultColorValue;
            gThis.root.style.setProperty("--accent-color", gThis.defaultColorValue);
            gThis.updateAccentColor(gThis.defaultColorValue);
        });
        submitButton.addEventListener("click", () => {
            gThis.updateAccentColor(accentColorInput.value);
            accentColorDialog.close();
        });
        accentColorInput.addEventListener("input", () => {
            console.log(accentColorInput.value);
            gThis.root.style.setProperty("--accent-color", accentColorInput.value);
        });
        accentColorDialog.showModal();
    }

    getAccentColor() {
        fetch("/api/settings/accent-color/read")
            .then(response => response.json())
            .then(data => {
                /*console.log(data);*/
                this.accentColorValue = data['value'];
                this.defaultColorValue = data['default'];
                this.root.style.setProperty("--accent-color", this.accentColorValue);
            })
            .catch((error) => {
                console.log(error);
            });
    }

    updateAccentColor(value) {
        fetch("/api/settings/accent-color/update", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify({accentColor: value})
        })
            .then(response => response.json())
            .then(data => {
                console.log(data);
            })
            .catch((error) => {
                console.log(error);
            });
    }

    setScheduleRange() {
        document.documentElement.click();
        /** @type HTMLDialogElement */
        const scheduleRangeDialog = document.querySelector("#scheduleRangeDialog");
        const submitButton = scheduleRangeDialog.querySelector("button[type=submit]");
        const resetButton = scheduleRangeDialog.querySelector("button[type=reset]");
        const cancelButton = scheduleRangeDialog.querySelector("button[type=button]");
        const startInput = scheduleRangeDialog.querySelector("input[id=schedule-menu-range-start]");
        const endInput = scheduleRangeDialog.querySelector("input[id=schedule-menu-range-end]");

        gThis.getScheduleRange('values');

        cancelButton.addEventListener("click", () => {
            scheduleRangeDialog.close();
        });
        resetButton.addEventListener("click", () => {
            gThis.getScheduleRange('default');
            localStorage.setItem("schedule_range_updated", "true")
        });
        submitButton.addEventListener("click", () => {
            gThis.updateScheduleRange(startInput.value, endInput.value);
            localStorage.setItem("schedule_range_updated", "true")
            scheduleRangeDialog.close();
        });
        scheduleRangeDialog.showModal();
    }

    getScheduleRange(type) {
        fetch("/api/settings/schedule-range/read?t=" + type)
            .then(response => response.json())
            .then(data => {
                /*console.log(data);*/
                const scheduleRangeDialog = document.querySelector("#scheduleRangeDialog");
                const startInput = scheduleRangeDialog.querySelector("input[id=schedule-menu-range-start]");
                const endInput = scheduleRangeDialog.querySelector("input[id=schedule-menu-range-end]");

                startInput.value = data['start'];
                endInput.value = data['end'];
            })
            .catch((error) => {
                console.log(error);
            });
    }

    updateScheduleRange(start, end) {
        fetch("/api/settings/schedule-range/update", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify({start: start, end: end})
        })
            .then(response => response.json())
            .then(data => {
                console.log(data);
            })
            .catch((error) => {
                console.log(error);
            });
    }

    setWhatNext() {
        document.documentElement.click();
        /** @type HTMLDialogElement */
        const whatNextDialog = document.querySelector("#whatNextDialog");
        const submitButton = whatNextDialog.querySelector("button[type=submit]");
        const resetButton = whatNextDialog.querySelector("button[type=reset]");
        const cancelButton = whatNextDialog.querySelector("button[type=button]");

        gThis.getWhatNextSettings('values');

        cancelButton.addEventListener("click", () => {
            whatNextDialog.close();
        });
        resetButton.addEventListener("click", () => {
            gThis.getWhatNextSettings('defaut');
        });
        submitButton.addEventListener("click", gThis.updateWhatNextSettings);
        whatNextDialog.showModal();
    }

    getWhatNextSettings(type) {
        fetch("/api/settings/what/next/read?t=" + type)
            .then(response => response.json())
            .then(data => {
                const whatNextDialog = document.querySelector("#whatNextDialog");
                const sortSelect = whatNextDialog.querySelector("#what-next-select-sort");
                const orderSelect = whatNextDialog.querySelector("#what-next-select-order");
                const limitSelect = whatNextDialog.querySelector("#what-next-select-limit");
                const linkToSelect = whatNextDialog.querySelector("#what-next-select-link-to");
                console.log(data);
                sortSelect.value = data['sort'];
                orderSelect.value = data['order'];
                limitSelect.value = data['limit'];
                linkToSelect.value = data['link_to'];
            })
            .catch((error) => {
                console.log(error);
            });
    }

    updateWhatNextSettings() {
        const whatNextDialog = document.querySelector("#whatNextDialog");
        const sortSelect = whatNextDialog.querySelector("#what-next-select-sort");
        const orderSelect = whatNextDialog.querySelector("#what-next-select-order");
        const limitSelect = whatNextDialog.querySelector("#what-next-select-limit");
        const linkToSelect = whatNextDialog.querySelector("#what-next-select-link-to");
        fetch("/api/settings/what/next/update", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify({sort: sortSelect.value, order: orderSelect.value, limit: limitSelect.value, link_to: linkToSelect.value})
        })
            .then(response => response.json())
            .then(data => {
                console.log(data);
                whatNextDialog.close();
            })
            .catch((error) => {
                console.log(error);
            });
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

    forcePreview(preview) {
        if (preview) {
            localStorage.setItem("mytvtime_2_preview", "true");
        } else {
            localStorage.removeItem("mytvtime_2_preview");
        }
        this.setPreview(localStorage.getItem("mytvtime_2_preview"));
    }

    getPreview() {
        return !!localStorage.getItem("mytvtime_2_preview");
    }

    setPreview(preview) {
        if (preview) {
            this.menuPreview.innerHTML = this.menuPreview.getAttribute("data-on");
        } else {
            this.menuPreview.innerHTML = this.menuPreview.getAttribute("data-off");
        }
    }

    initTheme() {
        let theme = localStorage.getItem("mytvtime_2_theme");
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

        // if (!document.startViewTransition) {
        gThis.updateTheme(theme);
        // } else {
        //     document.startViewTransition(() => {
        //         gThis.updateTheme(theme);
        //     });
        // }

        localStorage.setItem("mytvtime_2_theme", theme);
        this.checkTheme(theme);
        // Créer un événement "theme-change" pour que les autres modules puissent l'écouter
        const event = new Event("theme-changed");
        document.dispatchEvent(event);
    }

    updateTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
    }

    checkTheme(theme) {
        this.menuThemes.forEach((t) => {
            t.classList.remove("active");
        });
        const newTheme = document.querySelector(`.menu-theme[data-theme="${theme}"]`);
        newTheme.classList.add("active");
        document.documentElement.setAttribute('data-theme', theme);
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

                    const userPart = document.createElement("div");
                    userPart.classList.add("user-part");

                    const vote = document.createElement("div");
                    vote.classList.add("vote");
                    /*if (options.vote === false) vote.classList.add('hidden');*/
                    vote.innerHTML = item.vote;

                    const device = document.createElement("div");
                    device.classList.add("device");
                    /*if (options.device === false) device.classList.add('hidden');*/
                    if (item.deviceSvg) device.innerHTML = gThis.svgs[item.deviceSvg];

                    const provider = document.createElement("div");
                    provider.classList.add("provider");
                    /*if (options.provider === false) provider.classList.add('hidden');*/
                    if (item.providerLogoPath) {
                        const imgProvider = document.createElement("img");
                        imgProvider.src = item.providerLogoPath;
                        imgProvider.alt = item.providerName;
                        provider.appendChild(imgProvider);
                    }

                    const date = document.createElement("div");
                    date.classList.add("date");
                    date.innerHTML = item.lastWatchAt;
                    a.appendChild(date);

                    const number = document.createElement("div");
                    number.classList.add("number");
                    number.innerHTML = 'S' + (item.seasonNumber < 10 ? '0' + item.seasonNumber : item.seasonNumber) + 'E' + (item.episodeNumber < 10 ? '0' + item.episodeNumber : item.episodeNumber);
                    a.appendChild(number);

                    userPart.appendChild(vote);
                    userPart.appendChild(device);
                    userPart.appendChild(provider);

                    a.appendChild(userPart);

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

        // remove accents, swap ñ for n, etc.
        let from = "àáäâèéëêìíïîòóöôùúüûñç·/_,:;";
        let to = "aaaaeeeeiiiioooouuuunc------";
        for (let i = 0, l = from.length; i < l; i++) {
            str = str.replace(new RegExp(from.charAt(i), 'g'), to.charAt(i));
        }

        str = str.replace(/[^a-z0-9 -]/g, '') // remove invalid chars
            .replace(/\s+/g, '-') // collapse whitespace and replace by '-'
            .replace(/-+/g, '-'); // collapse dashes

        if (!str || str === '') str = 'no-slug';
        /*console.log(str);*/
        return str;
    }
}