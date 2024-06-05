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
    }

    init() {
        const burger = document.querySelector(".burger");
        const navbar = document.querySelector(".navbar");
        const body = document.querySelector("body");
        const notifications = document.querySelector(".notifications");

        burger.addEventListener("click", () => {
            burger.classList.toggle("open");
            navbar.classList.toggle("active");
            body.classList.toggle("frozen");
        });

        notifications?.addEventListener("click", () => {
            const menu = notifications.querySelector(".menu");
            menu.classList.toggle("show");
        });

        document.addEventListener("click", (e) => {
            if (burger.classList.contains("open") && !navbar.contains(e.target) && !burger.contains(e.target)) {
                burger.classList.remove("open");
                navbar.classList.remove("active");
                body.classList.remove("frozen");
                e.stopPropagation();
                e.preventDefault();
            }
            if (notifications?.querySelector(".menu").classList.contains("show")) {
                const menu = notifications.querySelector(".menu");
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
}