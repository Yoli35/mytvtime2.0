export class UserList {
    constructor(flashMessage, toolTips, buttons) {
        this.flashMessage = flashMessage;
        this.toolTips = toolTips;
        this.lang = document.querySelector('html').getAttribute('lang');

        const globs = document.querySelector("#globs");
        if (globs) {
            const globsJson = JSON.parse(globs.textContent);
            this.translations = globsJson['translations'];
        } else {
            this.translations = [];
        }

        this.init = this.init.bind(this);
        this.bookmarkClick = this.bookmarkClick.bind(this);
        this.bookMarkToggle = this.bookMarkToggle.bind(this);
        this.closeSeriesListMenu = this.closeSeriesListMenu.bind(this);
        this.resetSelect = this.resetSelect.bind(this);
        this.addOption = this.addOption.bind(this);

        this.svgs = document.querySelector("#svgs");
        this.svgBookmark = document.querySelector("#svgs #svg-bookmark");
        this.svgBookmarkOutline = document.querySelector("#svgs #svg-bookmark-outline");

        this.seriesListsMenu = document.querySelector(".series-lists-menu");
        this.createNewList = this.seriesListsMenu.querySelector("li.create-new-list");
        this.userListDialog = document.querySelector("dialog.user-list-dialog");
        this.userListDialogCancel = this.userListDialog.querySelector("button[type=button]");
        this.userListDialogSubmit = this.userListDialog.querySelector("button[type=submit]");

        this.init(buttons);
    }

    init(buttons) {
        console.log("UserList.js loaded");

        this.initCreateListDialog();
        this.initSeriesToolsContainers(buttons);
        this.initBookmarkBadges();
        this.initUserListPage();

        document.addEventListener("click", (e) => {
            if (this.seriesListsMenu.style.length && !this.seriesListsMenu.contains(e.target)) {
                e.preventDefault();
                this.closeSeriesListMenu();
                return;
            }
            const toolsMenu = document.querySelector(".series-tools-menu.visible");
            if (toolsMenu && !toolsMenu.contains(e.target)) {
                e.preventDefault();
                toolsMenu.classList.remove("visible");
            }
        });
    }

    initUserListPage() {
        const userLists = document.querySelector(".user-lists");
        if (!userLists) return;
        this.totalCount = document.querySelectorAll(".cards").length;

        const listItems = document.querySelector(".lists-list").querySelectorAll(".list-item");
        listItems.forEach(item => {
            item.addEventListener("click", e => {
                e.preventDefault()
                if (item.classList.contains("active")) {
                    return;
                }
                const activeItem = document.querySelector(".lists-list .list-item.active");
                activeItem.classList.remove("active");
                item.classList.add("active");

                fetch("/api/series/list/get/list", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                    },
                    body: JSON.stringify({
                        userListId: item.getAttribute("data-id")
                    }),
                }).then((response) => {
                    if (response.ok) {
                        return response.json();
                    }
                    throw new Error("Network response was not ok. " + response.error);
                }).then((data) => {
                    console.log(data);
                    const contentDiv = document.querySelector(".list-container .list-content");
                    const cards = contentDiv.querySelectorAll("div[data-year]");
                    cards.forEach(card => {
                        card.remove();
                    });
                    const infos = data['infos'];
                    const list = data['list'];
                    const years = data['years'];
                    const listCardDiv = document.querySelector(".list-card");
                    const nameDiv = listCardDiv.querySelector(".name");
                    this.totalCount = infos['total_results'];
                    nameDiv.firstChild.remove();
                    nameDiv.appendChild(document.createTextNode(infos['name']));
                    const countDiv = listCardDiv.querySelector(".count");
                    countDiv.lastChild.remove();
                    countDiv.innerHTML = '<span class="sub-count"></span>' + infos['count'];
                    const descriptionDiv = listCardDiv.querySelector(".description");
                    descriptionDiv.firstChild.remove();
                    descriptionDiv.appendChild(document.createTextNode(infos['description']));
                    this.resetSelect(years);
                    const translations = data['translations'];
                    const svgBookmark = this.svgs.querySelector("#svg-bookmark").querySelector("svg");
                    const svgEllipsis = this.svgs.querySelector("#svg-ellipsis").querySelector("svg");
                    list.forEach(item => {
                        const div = document.createElement("div");
                        div.setAttribute("data-year", item['air_year']);
                        const cardDiv = document.createElement("div");
                        cardDiv.classList.add("card");
                        const a = document.createElement("a");
                        a.href = item['url'];
                        const posterDiv = document.createElement("div");
                        posterDiv.classList.add("poster");
                        const img = document.createElement("img");
                        img.src = item['poster_path'];
                        img.alt = item['sln_name'];
                        posterDiv.appendChild(img);
                        const infosDiv = document.createElement("div");
                        infosDiv.classList.add("infos");
                        const nameDiv = document.createElement("div");
                        nameDiv.classList.add("name");
                        const nameText = document.createTextNode(item['sln_name']);
                        nameDiv.appendChild(nameText);
                        const seriesInListDiv = document.createElement("div");
                        seriesInListDiv.classList.add("series-in-list");
                        seriesInListDiv.classList.add("added");
                        seriesInListDiv.setAttribute("data-title", translations['bookmarked']);
                        const bookmarkSVG = svgBookmark.cloneNode(true);
                        seriesInListDiv.appendChild(bookmarkSVG);
                        const seriesToolsContainerDiv = document.createElement("div");
                        seriesToolsContainerDiv.classList.add("series-tools-container");
                        seriesToolsContainerDiv.setAttribute("data-id", item['tmdb_id'].toString());
                        const seriesToolsDiv = document.createElement("div");
                        seriesToolsDiv.classList.add("series-tools");
                        const seriesToolsSVG = svgEllipsis.cloneNode(true);
                        seriesToolsDiv.appendChild(seriesToolsSVG);
                        const ul = this.createLu("series-tools-menu");
                        ul.appendChild(this.createLi("bookmark", "#svg-bookmark", translations['li.add']));
                        ul.appendChild(this.createLi("favorite", "#svg-favorite", translations['li.fav']));
                        ul.appendChild(this.createLi("share", "#svg-share-outline", translations['li.share']));
                        seriesToolsContainerDiv.appendChild(seriesToolsDiv);
                        seriesToolsContainerDiv.appendChild(ul);
                        infosDiv.appendChild(nameDiv);
                        infosDiv.appendChild(seriesInListDiv);
                        infosDiv.appendChild(seriesToolsContainerDiv);
                        a.appendChild(posterDiv);
                        a.appendChild(infosDiv);
                        cardDiv.appendChild(a);
                        div.appendChild(cardDiv);
                        contentDiv.appendChild(div);
                    });
                    this.initSeriesToolsContainers();
                    this.initBookmarkBadges();
                    this.toolTips.init(contentDiv);
                }).catch((error) => {
                    this.flashMessage.add("error", error);
                });
            });
        });

        this.initSelect();
    }

    initSelect() {
        const yearSelect = document.querySelector("#year-filter");
        yearSelect.addEventListener("change", () => {
            const cards = document.querySelectorAll(".list-content > div");
            const newValue = yearSelect.value;
            if (newValue === 'all') {
                cards.forEach(card => {
                    card.classList.remove("d-none")
                });
                const subCountSpan = document.querySelector("span.sub-count");
                subCountSpan.innerText = "";
                return;
            }
            const cardsToShow = document.querySelectorAll(".list-content > div[data-year=\"" + newValue + "\"");
            cards.forEach(card => {
                card.classList.add("d-none")
            });
            cardsToShow.forEach(card => {
                card.classList.remove("d-none")
            });
            const subCountSpan = document.querySelector("span.sub-count");
            const hiddenCount = document.querySelectorAll(".list-content > div.d-none").length;
            if (hiddenCount) {
                subCountSpan.innerText = (this.totalCount - hiddenCount) + " / ";
            } else {
                subCountSpan.innerText = "";
            }
        });
    }

    resetSelect(years) {
        const yearFilter = document.querySelector("#year-filter");
        const options = yearFilter.querySelectorAll("option");
        options.forEach(option => {
            option.remove();
        });
        this.addOption(yearFilter, 'all', this.translations['All'])
        years.forEach(year => {
            this.addOption(yearFilter, year, year.toString())
        });

        const selectYear = yearFilter.closest(".select-year");
        if (years.length < 2) {
            selectYear.classList.add("d-none");
        } else {
            selectYear.classList.remove("d-none");
        }
    }

    addOption(selectElement, value, text) {
        const option = document.createElement("option");
        option.value = value;
        option.appendChild(document.createTextNode(text));
        selectElement.appendChild(option);
    }

    createLu(clasName) {
        const ul = document.createElement("ul");
        ul.classList.add(clasName);

        return ul;
    }

    createLi(className, svgId, text) {
        const li = document.createElement("li");
        li.classList.add(className);
        const svg = this.svgs.querySelector(svgId).cloneNode(true);
        svg.removeAttribute("id");
        const span = document.createElement("span");
        const textNode = document.createTextNode(text);
        span.appendChild(textNode);
        li.appendChild(svg);
        li.appendChild(span);

        return li;
    }

    initCreateListDialog() {
        this.createNewList.addEventListener("click", (e) => {
            e.preventDefault();
            const seriesName = this.createNewList.getAttribute("data-name");
            const tmdbId = this.createNewList.getAttribute("data-tmdb");
            const userLists = this.seriesListsMenu.querySelectorAll("li.user-list");
            userLists.forEach(list => {
                list.remove();
            });
            this.seriesListsMenu.style = "";
            this.createNewList.removeAttribute("data-name");
            const nameInput = this.userListDialog.querySelector("#user-list-name");
            const descriptionTextarea = this.userListDialog.querySelector("#user-list-description");
            const publicCheckbox = this.userListDialog.querySelector("#user-list-public");
            const addSeriesCheckbox = this.userListDialog.querySelector("#user-list-add-series");
            const tmdbInput = this.userListDialog.querySelector("#tmdb-id");
            const span = this.userListDialog.querySelector('label[for="user-list-add-series"] span');
            nameInput.value = "";
            descriptionTextarea.value = "";
            publicCheckbox.checked = true;
            addSeriesCheckbox.checked = true;
            tmdbInput.value = tmdbId;
            span.innerText = seriesName;
            this.userListDialog.showModal();
        });
        this.userListDialogCancel.addEventListener("click", (e) => {
            e.preventDefault();
            this.userListDialog.close();
        });
        this.userListDialogSubmit.addEventListener("click", (e) => {
            e.preventDefault();
            const nameInput = this.userListDialog.querySelector("#user-list-name");
            const descriptionTextarea = this.userListDialog.querySelector("#user-list-description");
            const publicCheckbox = this.userListDialog.querySelector("#user-list-public");
            const addSeriesCheckbox = this.userListDialog.querySelector("#user-list-add-series");
            const tmdbInput = this.userListDialog.querySelector("#tmdb-id");
            fetch("/api/series/list/create", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({
                    name: nameInput.value,
                    description: descriptionTextarea.value,
                    public: publicCheckbox.checked,
                    add: addSeriesCheckbox.checked,
                    tmdbId: tmdbInput.value
                }),
            }).then((response) => {
                if (response.ok) {
                    return response.json();
                }
                const json = response.json();
                throw new Error("Network response was not ok. " + json.error);
            }).then((data) => {
                this.flashMessage.add("success", "List " + nameInput.value + " has been successfully created.");
                const seriesName = nameInput.value;
                const selector = '.series-tools-container[data-id="' + tmdbInput.value + '"]'
                const seriesToolsContainers = document.querySelectorAll(selector);

                if (data['final_state']) {
                    console.log("success", "Series " + seriesName + " added to list " + nameInput.value);
                    this.flashMessage.add("success", "Series " + seriesName + " added to list " + nameInput.value);
                    seriesToolsContainers.forEach(seriesToolsContainer => {
                        const seriesInList = seriesToolsContainer.closest(".infos").querySelector(".series-in-list");
                        seriesInList.classList.add("added");
                    });
                }
                this.userListDialog.close();
            }).catch((error) => {
                this.flashMessage.add("error", error);
            });
        });
    }

    initSeriesToolsContainers(buttons = null) {
        let seriesToolsContainers;
        if (buttons) {
            seriesToolsContainers = buttons;
        } else {
            seriesToolsContainers = document.querySelectorAll('.series-tools-container');
        }
        this.totalCount = seriesToolsContainers.length;

        seriesToolsContainers.forEach((seriesToolsContainer) => {
            const seriesTools = seriesToolsContainer.querySelector('.series-tools');
            const seriesToolsMenu = seriesToolsContainer.querySelector('.series-tools-menu');
            const bookmark = seriesToolsMenu.querySelector("li.bookmark");

            seriesTools.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                seriesToolsMenu.classList.toggle('visible');

                if (seriesToolsMenu.classList.contains('visible')) {
                    if (!seriesToolsMenu.classList.contains('up')) {
                        const visibleMenus = document.querySelectorAll('.series-tools-menu.visible')
                        visibleMenus.forEach((menu) => {
                            if (menu !== seriesToolsMenu) {
                                menu.classList.remove('visible');
                            }
                        });
                        const mouseX = e.clientX;
                        const mouseY = e.clientY;
                        const rect = seriesToolsMenu.getBoundingClientRect();
                        console.log(rect)
                        const windowWidth = window.innerWidth;
                        const windowHeight = window.innerHeight;
                        let dx = 0, dy = 32;
                        if (mouseX - rect.width < 16) {
                            dx = (mouseX - rect.width - 16)
                        } else {
                            if (mouseX > windowWidth - 16) {
                                dx = windowWidth - 16;
                            }
                        }
                        if (windowHeight - (mouseY + dy + rect.height) < 16) {
                            dy = (windowHeight - rect.height - dy - 16)
                        }
                        seriesToolsMenu.setAttribute("style", "right: " + dx + "px; top: " + dy + "px;");
                    }
                }
            });

            bookmark.addEventListener("click", this.bookmarkClick);
        });
    }

    initBookmarkBadges() {
        const bookmarkBadgeDivs = document.querySelectorAll(".series-in-list.added");
        bookmarkBadgeDivs.forEach(div => {
            div.addEventListener("click", (e) => {
                e.preventDefault();
                e.stopPropagation();
            });
        });
    }

    bookmarkClick(e) {
        e.preventDefault();
        const li = e.currentTarget;
        const seriesToolsContainer = li.closest(".series-tools-container") ?? li.closest(".action.toggle-bookmark-series");
        const seriesListsMenu = this.seriesListsMenu;
        const seriesToolsMenu = seriesToolsContainer.querySelector('.series-tools-menu');
        const tmdbId = seriesToolsContainer.getAttribute("data-id");
        const seriesName = seriesToolsContainer.getAttribute("data-name");
        const mouseX = e.clientX + 32;
        const mouseY = e.clientY - 8;

        fetch("/api/series/list/get/lists", {
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
            throw new Error("Network response was not ok. " + response.error);
        }).then((data) => {
            console.log(data);
            const userLists = data['userLists'];
            const seriesListIds = data['seriesListIds'];
            console.log(seriesListIds);
            userLists.forEach((list) => {
                const isListInSeriesList = seriesListIds.some(id => id === list.id);
                const svg = isListInSeriesList ? this.svgBookmark.cloneNode(true) : this.svgBookmarkOutline.cloneNode(true);
                const name = document.createTextNode(list['name']);
                const li = document.createElement("li");
                const span = document.createElement("span");
                span.appendChild(name);
                svg.removeAttribute("id");
                li.classList.add("user-list");
                li.setAttribute("data-list-id", list['id']);
                li.setAttribute("data-tmdb-id", tmdbId);
                li.setAttribute("data-series-name", seriesName);
                li.appendChild(svg);
                li.appendChild(span);
                li.addEventListener("click", this.bookMarkToggle);
                seriesListsMenu.insertBefore(li, this.createNewList);
            });
            this.createNewList.setAttribute("data-name", seriesName);
            this.createNewList.setAttribute("data-tmdb", tmdbId);
            seriesToolsMenu.classList.remove('visible');
            seriesListsMenu.style = "display: block; left: " + mouseX + "px; top: " + mouseY + "px;";
            const rect = seriesListsMenu.getBoundingClientRect();
            /*console.log(rect)*/
            const windowWidth = window.innerWidth;
            const windowHeight = window.innerHeight;
            const x = Math.min(Math.max(16, mouseX - rect.width), windowWidth - 16);
            const y = Math.min(window.scrollY + mouseY, window.scrollY + windowHeight - rect.height - 16);
            seriesListsMenu.style = "display: block; left: " + x + "px; top: " + y + "px;";
        }).catch((error) => {
            this.flashMessage.add("error", error);
        });
    }

    bookMarkToggle(e) {
        e.preventDefault();
        const li = e.currentTarget;
        const listId = li.getAttribute("data-list-id");
        const listName = li.querySelector("span").innerText;
        const tmdbId = li.getAttribute("data-tmdb-id");
        const seriesName = li.getAttribute("data-series-name");

        fetch("/api/series/list/toggle", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify({
                userListId: listId,
                seriesId: tmdbId,
            }),
        }).then((response) => {
            if (response.ok) {
                return response.json();
            }
            throw new Error("Network response was not ok. " + response.error);
        }).then((data) => {
            const selector = '.series-tools-container[data-id="' + tmdbId + '"]'
            const seriesToolsContainers = document.querySelectorAll(selector)
            if (data['final_state']) {
                console.log("success", "Series " + seriesName + " added to list " + listName);
                this.flashMessage.add("success", "Series " + seriesName + " added to list " + listName);
                seriesToolsContainers.forEach(seriesToolsContainer => {
                    const seriesInList = seriesToolsContainer.closest(".infos").querySelector(".series-in-list");
                    seriesInList.classList.add("added");
                });
            } else {
                console.log("success", "Series " + seriesName + " removed from list " + listName);
                this.flashMessage.add("success", "Series " + seriesName + " removed from list " + listName);
                seriesToolsContainers.forEach(seriesToolsContainer => {
                    const seriesInList = seriesToolsContainer.closest(".infos").querySelector(".series-in-list");
                    seriesInList.classList.remove("added");
                });
            }
            this.closeSeriesListMenu();
        }).catch((error) => {
            this.flashMessage.add("error", error);
        });
    }

    closeSeriesListMenu() {
        const userLists = this.seriesListsMenu.querySelectorAll(".user-list");
        userLists.forEach(li => {
            li.remove()
        });
        this.seriesListsMenu.style = "";
    }
}