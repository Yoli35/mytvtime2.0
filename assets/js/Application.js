let self;

export class Application {
    constructor(menu, toolTips) {
        self = this;
        this.menu = menu;
        this.toolTips = toolTips;

        this.initialize();
    }

    initialize() {

        /******************************************************************************************
         * Scroll to top
         ******************************************************************************************/
        const toTop = document.querySelector(".to-top");
        if (toTop) {
            toTop.addEventListener("click", () => {
                window.scrollTo({
                    top: 0,
                    behavior: "smooth"
                });
            });
            window.addEventListener("scroll", () => {
                if (window.scrollY > 100) {
                    toTop.classList.add("show");
                } else {
                    toTop.classList.remove("show");
                }
            });
        }

        /******************************************************************************************
         * Theme toggler
         ******************************************************************************************/
        const page = document.querySelector(".series-show") || document.querySelector(".episode-show");
        const themeToggler = document.querySelector(".theme-toggler");
        if (page) {
            const html = document.querySelector("html");

            // Restore the saved text-color theme for this series / season / episode.
            // When a setting exists (dark | light) we apply it and mark the toggler
            // active. Its presence also disables the automatic, background-luminosity
            // based text color forcing done in app.js.
            const savedTheme = page.dataset.themeSetting;
            if (savedTheme === 'dark' || savedTheme === 'light') {
                themeToggler.classList.add("active");
                page.dataset.theme = savedTheme;
            }
            // Reflect the current state through the toggler icon
            // (none -> auto, dark -> moon, light -> sun).
            themeToggler.dataset.themeState = page.dataset.theme;

            themeToggler.addEventListener("click", () => {
                if (page.dataset.theme === 'none') {
                    const seasonInfosDivs = document.querySelectorAll(".seasons .season .infos");
                    seasonInfosDivs.forEach(seasonInfo => {
                        seasonInfo.style = '';
                    })
                    page.dataset.theme = 'light';
                    themeToggler.classList.add("active");
                } else if (page.dataset.theme === 'light') {
                    page.dataset.theme = 'dark';
                } else if (page.dataset.theme === 'dark') {
                    themeToggler.classList.remove("active");
                    page.dataset.theme = 'none';
                }
                themeToggler.dataset.themeState = page.dataset.theme;

                // Persist the chosen theme value (series / season / episode).
                const themeType = page.dataset.themeType;
                const themeId = page.dataset.themeId;
                if (themeType && themeId) {
                    fetch(`/api/settings/theme/update/${themeType}/${themeId}`, {
                        method: "POST",
                        headers: {"Content-Type": "application/json"},
                        body: JSON.stringify({theme: page.dataset.theme})
                    })
                        .catch(() => {
                            console.log("Failed to persist theme");
                        });
                }
            })

        } else {
            themeToggler.style.display = "none";
        }
        // const seasonInfosDivs = document.querySelectorAll(".seasons .season .infos");


        /******************************************************************************************
         * Display preview toggler
         ******************************************************************************************/
        const previewToggler = document.querySelector(".preview-toggler");
        const toggler = function () {
            if (self.menu.getPreview()) {
                previewToggler.classList.add("active");
            } else {
                previewToggler.classList.remove("active");
            }
        }
        if (previewToggler) {
            toggler();
            previewToggler.addEventListener("click", (e) => {
                e.preventDefault();
                e.stopPropagation();
                self.menu.togglePreview();
                toggler();
            });
        }

        /******************************************************************************************
         * Get episodes of the day
         ******************************************************************************************/
        const episodesTodayDiv = document.querySelector(".episodes-today");
        if (episodesTodayDiv) {
            this.fetchEpisodesOfTheDay(episodesTodayDiv);
        }
    }

    fetchEpisodesOfTheDay(episodesTodayDiv, show = false) {
        fetch("/api/episode/today", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-Requested-With": "XMLHttpRequest"
            },
            body: JSON.stringify({show: show ? 1 : 0}),
        })
            .then(response => response.json())
            .then(data => {
                console.log('Episodes of the day:', data);
                const body = document.querySelector("body");
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = data['view'];
                const newEpisodesTodayDiv = tempDiv.querySelector(".episodes-today");
                if (newEpisodesTodayDiv) {
                    const togglerDiv = newEpisodesTodayDiv.querySelector(".toggler");
                    const links = newEpisodesTodayDiv.querySelectorAll("a");
                    links.forEach(link => {
                        link.addEventListener("click", (e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            const href = link.getAttribute("href");
                            newEpisodesTodayDiv.classList.remove("show");
                            setTimeout(() => {
                                window.location.href = href;
                            }, 300)
                        });
                    });
                    togglerDiv.addEventListener("click", (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        newEpisodesTodayDiv.classList.toggle("show");
                        if (newEpisodesTodayDiv.classList.contains("show")) {
                            // Date au format Y-m-d
                            const date = new Date().toISOString().split('T')[0];
                            console.log(date);
                            const episodesTodayDivDate = newEpisodesTodayDiv.getAttribute("data-date");
                            if (date !== episodesTodayDivDate) {
                                console.log('Reloading episodes of the day due to date change:', date);
                                self.fetchEpisodesOfTheDay(newEpisodesTodayDiv, true);
                            }
                        }
                    });
                    body.replaceChild(newEpisodesTodayDiv, episodesTodayDiv);
                    self.toolTips.initElement(togglerDiv);
                }
            })
            .catch(error => {
                console.error("Error fetching episodes of the day:", error);
            });
    }

}