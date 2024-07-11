let gThis = null;

export class Menu {
    constructor() {
        gThis = this;
        document.addEventListener("DOMContentLoaded", () => {
            this.menuPreview = document.querySelector(".menu-preview");
            this.menuThemes = document.querySelectorAll(".menu-theme");
        });
        this.init = this.init.bind(this);
        this.togglePreview = this.togglePreview.bind(this);
        this.setPreview = this.setPreview.bind(this);
        this.initPreview = this.initPreview.bind(this);
        this.setTheme = this.setTheme.bind(this);
        this.checkTheme = this.checkTheme.bind(this);
        this.lang = document.documentElement.lang;
        this.avatar = document.querySelector('.avatar');
        this.userConnected = this.avatar != null;
        this.connexionInterval = null;
        this.posterUrl = null;
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
        const movieSearch = navbar.querySelector("#movie-search");

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
            const id = item.id.split("-")[3];
            const eotdPreview = document.querySelector(`#eotd-preview-${id}`);
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
        });

        document.addEventListener("DOMContentLoaded", () => {
            this.menuPreview.addEventListener("click", this.togglePreview);
            this.menuThemes.forEach((theme) => {
                theme.addEventListener("click", this.setTheme);
            });
            this.initTheme();
            this.initPreview();
        });

        if (this.userConnected) {
            this.connexionInterval = setInterval(() => {
                this.checkConnexion();
            }, 60000);
        }

        movieSearch.addEventListener("input", (e) => {
            const value = e.target.value;
            if (value.length > 2) {
                const searchResults = movieSearch.closest("li").querySelector(".search-results");
                const query = encodeURIComponent(value);
                const url = 'https://api.themoviedb.org/3/search/movie?query=' + query + '&include_adult=false&language=en-US&page=1';
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
                        json.results.forEach((result) => {
                            const a = document.createElement("a");
                            a.href = '/'+gThis.lang+'/movie/tmdb/' + result.id;
                            const li = document.createElement("li");
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
    }

    getImageConfig() {
        fetch('/' + gThis.lang + '/movie/image/config', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            },
        })
            .then(response => response.json())
            .then(data => {
                gThis.posterUrl = data.body.poster_url;
            })
            .catch((error) => {
                console.error('Error:', error);
            });
    }

    checkConnexion() {
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
}