import {AverageColor} from "AverageColor";
import {ToolTips} from "ToolTips";

let gThis = null;

export class PeopleStar {
    constructor() {
        gThis = this;
        this.lang = document.querySelector("html").getAttribute("lang");
        this.averageColor = new AverageColor();
        this.toolTips = new ToolTips();
        this.theme = this.getTheme();
        console.log("Theme : " + this.theme);
        this.start();
    }

    start() {
        this.setColorPersonCards();

        document.addEventListener("theme-changed", (e) => {
            gThis.theme = gThis.getTheme();
            console.log("Theme changed : " + gThis.theme);
            gThis.setColorPersonCards();
        });
    }

    setColorPersonCards() {
        const personCards = document.querySelectorAll(".person-card");
        personCards.forEach((card) => {
            const img = card.querySelector("img");
            const color = this.averageColor.getColor(img);
            const hsl = this.averageColor.rgbToHsl(color);
            card.style.backgroundColor = "hsl(" + ((hsl.h + 180) % 360) + ", " + (this.theme === 'dark' ? "50%" : "25%") + ", " + (this.theme === 'dark' ? "25%" : "75%") + ")";
        });
    }

    getTheme() {

        // Déterminer le theme (automatique, clair ou sombre)
        const menu = document.querySelector(".menu.user");
        const activeTheme = menu.querySelector(".menu-theme.active");
        let theme = activeTheme.getAttribute("data-theme");
        if (theme === "auto") {
            const isDarkMode = window.matchMedia("(prefers-color-scheme: dark)").matches;
            theme = isDarkMode ? "dark" : "light";
        }
        return theme;
    }
}