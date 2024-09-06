let gThis;
export class NavBar {
    constructor() {
        gThis = this;
        this.root = document.documentElement;
        this.debugDiv = null;
        this.navBarColor = this.navBarColor.bind(this);
        this.setOpacity = this.setOpacity.bind(this);
        this.mapScroll = this.mapScroll.bind(this);
        this.init = this.init.bind(this);

        this.init();
    }

    init() {
        const body = document.querySelector("body");
        this.debugDiv = document.createElement("div");
        this.debugDiv.classList.add("debug");
        body.appendChild(this.debugDiv);

        this.setOpacity();
        window.addEventListener("scroll", this.setOpacity);
    }

    navBarColor(hsl) {

        this.root.style.setProperty("--navbar-bg", "hsl(" + hsl.h + ", " + hsl.s + "%, " + (hsl.l - 10) + "%)");
        this.root.style.setProperty("--navbar-bg-50", "hsla(" + hsl.h + ", " + hsl.s + "%, " + hsl.l + "%, .5)");
        this.root.style.setProperty("--navbar-bg-75", "hsla(" + hsl.h + ", " + hsl.s + "%, " + hsl.l + "%, .75)");

        const navbarLinks = document.querySelectorAll(".navbar a");
        const footer = document.querySelector(".home-footer");
        if (hsl.l > 50) {
            navbarLinks?.forEach(link => {
                link.classList.add("dark");
            });
            footer?.classList.add("dark");
        }
    }

    setOpacity() {
        const opacity = this.mapScroll(window.scrollY);
        this.debugDiv.textContent = opacity.toFixed(2).toString() + " / " + window.scrollY.toFixed(2);
        this.root.style.setProperty("--navbar-opacity", opacity.toString());
    }

    mapScroll(amount)
    {
        const start = 100;
        const end = 400;
        const interval = end - start;

        let scroll = 1;
        if (amount > start && amount < end) {
            scroll = 1 - .8 *  Math.pow((amount - start) / interval, 3);
        } else if (amount >= end) {
            scroll = .2;
        }
        return scroll;
    }
}