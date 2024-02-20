export class Menu {
    constructor() {
        this.switchPreview = document.querySelector("#switch-preview");
        this.switchThemes = document.querySelectorAll(".theme");
        this.switchLanguage = document.querySelector(".switch-language");
    }

    init() {
        this.switchPreview.addEventListener("click", this.togglePreview);
        this.switchThemes.forEach((theme) => {
        theme.addEventListener("click", this.setTheme);
        });
    }

    togglePreview(e) {
        const preview = localStorage.getItem("preview");
        const switchPreview = e.currentTarget;
        if (preview === null) {
            localStorage.setItem("preview", "true");
            switchPreview.innerHTML = switchPreview.getAttribute("data-on")
        } else {
            localStorage.removeItem("preview");
            switchPreview.innerHTML = switchPreview.getAttribute("data-off");
        }
    }

    setTheme(e) {
        const theme = e.currentTarget.getAttribute("data-theme");
        document.body.classList.remove("dark", "light");
        if (theme !== 'auto') document.body.classList.add(theme);
    }
}