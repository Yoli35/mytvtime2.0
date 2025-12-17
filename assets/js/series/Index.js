import {FlashMessage} from "FlashMessage";

/**
 *  @typedef Globs
 * @type {Object}
 * @property {Array} tmdbIds
 * @property {String} app_series_tmdb_check
 */

export class Index {
    constructor() {
        this.init = this.init.bind(this);
        this.clampDisplay = this.clampDisplay.bind(this);
        this.clampCalc = this.clampCalc.bind(this);
        this.toFix = this.toFix.bind(this);
        // this.startDate = new Date();
        this.flashMessage = new FlashMessage();
        this.lang = document.querySelector('html').getAttribute('lang');
        this.translations = {
            'fr': {'more': 'et %d de plus', 'update': 'Mise à jour', 'success': 'Succès', 'check_count': 'Vérifications: %d / %d'},
            'en': {'more': 'and %d more', 'update': 'Update', 'success': 'Success', 'check_count': 'Checks: %d / %d'}
        };
    }

    init(globs, menu) {
        console.log("Index.js loaded");
        // this.clampDisplay();

        this.seriesId = globs.tmdbIds;
        this.app_series_tmdb_check = globs.app_series_tmdb_check;
        this.menu = menu;
        const seriesSearchBlockDiv = document.querySelector('.series-search-block');
        if (seriesSearchBlockDiv) {
            const seriesSearchInput = document.getElementById('series-search');
            seriesSearchInput.focus();
            seriesSearchInput.addEventListener("input", this.menu.searchFetch);
            seriesSearchInput.addEventListener("keydown", this.menu.searchMenuNavigate);
        }

        const seriesToolsContainers = document.querySelectorAll('.series-tools-container');
        const seriesListsMenu = document.querySelector(".series-lists-menu");
        const createNewList = seriesListsMenu.querySelector("li.create-new-list");
        const userListDialog = document.querySelector("dialog.user-list-dialog");
        const userListDialogCancel = userListDialog.querySelector("button[type=button]");
        const userListDialogSubmit = userListDialog.querySelector("button[type=submit]");
        const svgBookmark = document.querySelector("#svgs #svg-bookmark");
        const svgBookmarkOutline = document.querySelector("#svgs #svg-bookmark-outline");

        createNewList.addEventListener("click", (e) => {
            e.preventDefault();
            const seriesName = createNewList.getAttribute("data-name");
            const userLists = seriesListsMenu.querySelectorAll("li.user-list");
            userLists.forEach(list => {list.remove();});
            seriesListsMenu.style = "";
            createNewList.removeAttribute("data-name");
            const span = userListDialog.querySelector('label[for="user-list-add-series"] span');
            span.innerText = seriesName;
            userListDialog.showModal();
        });
        userListDialogCancel.addEventListener("click", (e) => {
            userListDialog.close();
        });
        userListDialogSubmit.addEventListener("click", (e) => {
            userListDialog.close();
        });

        seriesToolsContainers.forEach((seriesToolsContainer) => {
            const seriesTools = seriesToolsContainer.querySelector('.series-tools');
            const seriesToolsMenu = seriesToolsContainer.querySelector('.series-tools-menu');
            const tmdbId = seriesToolsContainer.getAttribute("data-id");
            const seriesName = seriesToolsContainer.getAttribute("data-name");
            const bookmark = seriesToolsMenu.querySelector("li.bookmark");

            seriesTools.addEventListener('click', (e) => {
                e.preventDefault();
                seriesToolsMenu.classList.toggle('visible');

                if (seriesToolsMenu.classList.contains('visible')) {
                    const visibleMenus = document.querySelectorAll('.series-tools-menu.visible')
                    visibleMenus.forEach((menu) => {
                        if (menu !== seriesToolsMenu) {
                            menu.classList.remove('visible');
                        }
                    });
                }
            });

            bookmark.addEventListener("click", (e) => {
                e.preventDefault();
                const mouseX = e.clientX;
                const mouseY = e.clientY;

                fetch("/api/series/list/get", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                    },
                    body: JSON.stringify({
                        tmdbId: tmdbId,
                    }),
                }).then((response) => {
                    if (response.ok) {
                        return response.json();
                    }
                    throw new Error("Network response was not ok.");
                }).then((data) => {
                    console.log(data);
                    const userLists = data['userLists'];
                    const seriesListIds = data['seriesListIds'];
                    console.log(seriesListIds);
                    userLists.forEach((list) => {
                        const isListInSeriesList = seriesListIds.some(id => id === list.id);
                        const svg = isListInSeriesList ? svgBookmark.cloneNode(true) : svgBookmarkOutline.cloneNode(true);
                        const name = document.createTextNode(list['name']);
                        const li = document.createElement("li");
                        svg.removeAttribute("id");
                        li.classList.add("user-list");
                        li.setAttribute("data-id", list['id']);
                        li.appendChild(svg);
                        li.appendChild(name);
                        seriesListsMenu.insertBefore(li, createNewList);
                        createNewList.setAttribute("data-name", seriesName);
                    });
                    seriesToolsMenu.classList.remove('visible');
                    seriesListsMenu.style = "display: block; left: " + mouseX + "px; top: " + mouseY + "px;";
                    const rect = seriesListsMenu.getBoundingClientRect();
                    const windowWidth = window.innerWidth;
                    const x = Math.min(Math.max(8, mouseX - rect.width / 2), windowWidth - 8);
                    const y = mouseY + window.scrollY;
                    seriesListsMenu.style = "display: block; left: " + x + "px; top: " + y + "px;";
                }).catch((error) => {
                    console.error("Fetch error:", error);
                });
            });
        });

        fetch(this.app_series_tmdb_check, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify({
                tmdbIds: this.seriesId,
            }),
        }).then((response) => {
            if (response.ok) {
                return response.json();
            }
            throw new Error("Network response was not ok.");
        }).then((data) => {
            console.log(data);
            const updates = data['updates'];
            const checkCount = data['dbSeriesCount'];
            const tmdbCalls = data['tmdbCalls'];
            // this.flashMessage.add('success', 'Check count: ' + tmdbCalls + ' / ' + checkCount);
            this.flashMessage.add('success', this.translations[this.lang]['check_count'].replace('%d', tmdbCalls).replace('%d', checkCount));
            updates.forEach((series) => {
                const updates = series['updates'];
                if (updates.length > 0) {
                    // On crée un nouveau flash message
                    console.log('Adding flash message for ', series['name']);
                    let content;
                    if (updates.length === 1) {
                        content = updates[0];
                    } else {
                        // content = updates[0] + ' and ' + (updates.length - 1) + ' more';
                        content = updates[0] + ' ' + this.translations[this.lang]['more'].replace('%d', updates.length - 1) + '<ul>';
                        for (let i = 1; i < updates.length; i++) {
                            content += '<li>' + updates[i] + '</li>';
                        }
                        content += '</ul>';
                    }
                    this.flashMessage.add('update', {
                        name: series['name'],
                        localized_name: series['localized_name'],
                        poster_path: series['poster_path'],
                        content: content,
                    });
                }
            });
        }).catch((error) => {
            console.error("Fetch error:", error);
        });
    }

    // clamp(-8rem, calc(-0.1 * max(18rem, 24vmax)), -11rem)
    // Display the 3 parameters of clamp CSS function
    clampDisplay() {
        let displayDiv = document.querySelector('.clamp-display');
        if (!displayDiv) {
            const h2 = document.querySelector('h2');
            displayDiv = document.createElement('div');
            displayDiv.classList.add('clamp-display');
            h2.appendChild(displayDiv);
        }

        this.clampCalc();
        window.addEventListener('resize', this.clampCalc);
    }

    clampCalc() {
        const displayDiv = document.querySelector('.clamp-display');
        const windowWidth = window.innerWidth;
        const windowHeight = window.innerHeight;
        let temp = windowWidth > windowWidth ? windowWidth : window.innerWidth;
        const vmax = this.toFix(temp);
        temp = .24 * vmax;
        const calc_24 = this.toFix(temp);
        temp = -.1 * Math.max(288, .24 * vmax);
        const calc_0_1 = this.toFix(temp);

        displayDiv.innerHTML = "<em>" + windowWidth + "</em>x<em>" + windowHeight + "</em>px - clamp(-128px, calc(-0.1 * max(288px, 24vmax<em>" + calc_24 + "</em>px))<em>" + calc_0_1 + "</em>, -176px)";
    }

    toFix(x) {
        return Number.parseFloat(x).toFixed(1);
    }
}