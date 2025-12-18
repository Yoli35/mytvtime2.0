export class UserList {
    constructor(flashMessage) {
        this.flashMessage = flashMessage;
        this.lang = document.querySelector('html').getAttribute('lang');

        this.init = this.init.bind(this);
        this.bookmarkClick = this.bookmarkClick.bind(this);
        this.bookMarkToggle = this.bookMarkToggle.bind(this);
        this.closeSeriesListMenu = this.closeSeriesListMenu.bind(this);

        this.svgBookmark = document.querySelector("#svgs #svg-bookmark");
        this.svgBookmarkOutline = document.querySelector("#svgs #svg-bookmark-outline");

        this.seriesListsMenu = document.querySelector(".series-lists-menu");
        this.createNewList = this.seriesListsMenu.querySelector("li.create-new-list");
        this.userListDialog = document.querySelector("dialog.user-list-dialog");
        this.userListDialogCancel = this.userListDialog.querySelector("button[type=button]");
        this.userListDialogSubmit = this.userListDialog.querySelector("button[type=submit]");

        this.init();
    }

    init() {
        console.log("UserList.js loaded");

        this.initCreateListDialog();
        this.initSeriesToolsContainers();

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

    initSeriesToolsContainers() {
        const seriesToolsContainers = document.querySelectorAll('.series-tools-container');

        seriesToolsContainers.forEach((seriesToolsContainer) => {
            const seriesTools = seriesToolsContainer.querySelector('.series-tools');
            const seriesToolsMenu = seriesToolsContainer.querySelector('.series-tools-menu');
            const bookmark = seriesToolsMenu.querySelector("li.bookmark");

            seriesTools.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
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

            bookmark.addEventListener("click", this.bookmarkClick);
        });
    }

    bookmarkClick(e) {
        e.preventDefault();
        const li = e.currentTarget;
        const seriesToolsContainer = li.closest(".series-tools-container");
        const seriesListsMenu = this.seriesListsMenu;
        const seriesToolsMenu = seriesToolsContainer.querySelector('.series-tools-menu');
        const tmdbId = seriesToolsContainer.getAttribute("data-id");
        const seriesName = seriesToolsContainer.getAttribute("data-name");
        const mouseX = e.clientX;
        const mouseY = e.clientY;

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
            const windowWidth = window.innerWidth;
            const x = Math.min(Math.max(8, mouseX - rect.width / 2), windowWidth - 8);
            const y = mouseY + window.scrollY;
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