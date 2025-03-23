import {AverageColor} from "AverageColor";
import {ToolTips} from "ToolTips";

let gThis = null;

export class PeopleStar {
    constructor() {
        gThis = this;
        this.lang = document.querySelector("html").getAttribute("lang");
        this.averageColor = new AverageColor();
        this.toolTips = new ToolTips();
        this.start();
    }

    start() {
        // Déterminer le theme (automatique, clair ou sombre)
        const menu = document.querySelector(".menu.user");
        const activeTheme = menu.querySelector(".menu-theme.active");
        let theme = activeTheme.getAttribute("data-theme");
        if (theme === "auto") {
            const isDarkMode = window.matchMedia("(prefers-color-scheme: dark)").matches;
            theme = isDarkMode ? "dark" : "light";
        }
        console.log("Theme : " + theme);

        const personCards = document.querySelectorAll(".person-card");
        // Obtenir la couleur moyenne de la photo
        personCards.forEach((card) => {
            const img = card.querySelector("img");
            const color = this.averageColor.getColor(img);
            const hsl = this.averageColor.rgbToHsl(color);
            card.style.backgroundColor = "hsl(" + ((hsl.h + 180) % 360) + ", " + (theme === 'dark' ? "50%" : "25%") + ", " + (theme === 'dark' ? "25%" : "75%") + ")";
        });
    }
}