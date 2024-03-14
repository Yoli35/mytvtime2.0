export class Menu {
    constructor() {
        document.addEventListener("DOMContentLoaded", () => {
            this.menuPreview = document.querySelector(".menu-preview");
            this.menuThemes = document.querySelector(".menu-theme");
            this.switchPreview = document.querySelector("#switch-preview");
            this.switchThemes = document.querySelectorAll(".theme");
            this.switchLanguage = document.querySelector(".switch-language");
        });
        this.init = this.init.bind(this);
        this.togglePreview = this.togglePreview.bind(this);
        this.setPreview = this.setPreview.bind(this);
        this.initPreview = this.initPreview.bind(this);
        this.setTheme = this.setTheme.bind(this);
        this.iconTheme = this.iconTheme.bind(this);
    }

    init() {
        document.addEventListener("DOMContentLoaded", () => {
            this.switchPreview.addEventListener("click", this.togglePreview);
            this.switchThemes.forEach((theme) => {
                theme.addEventListener("click", this.setTheme);
            });
            this.initTheme();
            this.initPreview();
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
        this.menuPreview.querySelector('i').classList.remove('fa-eye-slash', 'fa-eye');
        if (preview !== null) {
            this.switchPreview.innerHTML = this.switchPreview.getAttribute("data-on");
            this.menuPreview.querySelector('i').classList.add('fa-eye');
        } else {
            this.switchPreview.innerHTML = this.switchPreview.getAttribute("data-off");
            this.menuPreview.querySelector('i').classList.add('fa-eye-slash');
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
        this.iconTheme(theme);
    }

    setTheme(e) {
        const theme = e.currentTarget.getAttribute("data-theme");
        document.body.classList.remove("dark", "light");
        if (theme !== 'auto') document.body.classList.add(theme);
        localStorage.setItem("mytvtime_2_theme", theme);
        this.iconTheme(theme);
    }

    iconTheme(theme) {
        const i = this.menuThemes.querySelector('i');
        i.classList.remove('fa-palette', 'fa-moon', 'fa-sun');
        if (theme === 'auto') {
            i.classList.add('fa-palette');
        }
        if (theme === 'dark') {
            i.classList.add('fa-moon');
        }
        if (theme === 'light') {
            i.classList.add('fa-sun');
        }
    }
}