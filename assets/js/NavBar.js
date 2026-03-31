let self;

export class NavBar {
    constructor() {
        self = this;
        this.root = document.documentElement;
        // this.debugDiv = null;
        this.navBarColor = this.navBarColor.bind(this);
        this.setOpacity = this.setOpacity.bind(this);
        this.mapScroll = this.mapScroll.bind(this);
        this.init = this.init.bind(this);

        this.init();
    }

    init() {
        const navbar = document.querySelector(".navbar");
        // const navBarBounds = navbar.getBoundingClientRect();
        // const body = document.querySelector("body");
        // this.debugDiv = document.createElement("div");
        // this.debugDiv.classList.add("debug");
        // body.appendChild(this.debugDiv);

        // this.setOpacity();
        // window.addEventListener("scroll", this.setOpacity);
        // window.addEventListener("resize", this.setOpacity);
        /*window.addEventListener("mousemove", (e) => {
            const distance = e.clientY - (navBarBounds.top + navBarBounds.height);
            // Si distance > 200 → opacity = 0
            if (distance > 200) {
                this.root.style.setProperty("--navbar-opacity", "0");
            } else {
                const opacity = 1 - distance / 200;
                this.root.style.setProperty("--navbar-opacity", opacity.toString());
            }
        });*/
        const multiSearchDiv = navbar.querySelector('.multi-search');
        if (multiSearchDiv) {
            const labels = {
                'en': 'Summer',
                'fr': 'Été',
                'ko': '여름'
            }
            const label = labels[document.documentElement.lang];
            let countDownDiv = document.querySelector('.count-down')
            if (!countDownDiv) {
                countDownDiv = document.createElement('div');
                countDownDiv.classList.add('count-down');
                navbar.insertBefore(countDownDiv, multiSearchDiv);
                const initialDate = new Date('2026-06-21T08:24:30.000Z');
                const countDownInterval = setInterval(() => {
                    const currentDate = new Date();
                    const timeDifference = initialDate - currentDate;
                    if (timeDifference < 0) {
                        clearInterval(countDownInterval);
                        countDownDiv.remove();
                        return;
                    }
                    const days = Math.floor(timeDifference / (1000 * 60 * 60 * 24));
                    const hours = Math.floor((timeDifference % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const minutes = Math.floor((timeDifference % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((timeDifference % (1000 * 60)) / 1000);
                    // Affiche le temps restant au format DD:HH:MM:SS
                    countDownDiv.textContent = label + ' → ' + `${days.toString().padStart(2, '0')}:${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                }, 1000);
            }

            let screenSizeDiv = document.querySelector('.screen-size');
            if (screenSizeDiv) {
                screenSizeDiv.textContent = `${window.innerWidth}x${window.innerHeight}`;
                navbar.insertBefore(screenSizeDiv, countDownDiv);
                window.addEventListener('resize', () => {
                    screenSizeDiv.textContent = `${window.innerWidth}x${window.innerHeight}`;
                });
            }
        }
    }

    navBarColor(hsl) {
        this.adjustThemeColorMeta(hsl);
        this.root.style.setProperty("--navbar-bg", "hsl(" + hsl.h + ", " + hsl.s + "%, 38%)");
        this.root.style.setProperty("--navbar-border", "hsl(" + hsl.h + ", " + hsl.s + "%, 60%)");
        this.root.style.setProperty("--navbar-bg-50", "hsla(" + hsl.h + ", " + hsl.s + "%, 28%, .5)");
        this.root.style.setProperty("--navbar-bg-75", "hsla(" + hsl.h + ", " + hsl.s + "%, 28%, .75)");
        // this.root.style.setProperty("--navbar-bg", "hsl(" + hsl.h + ", " + hsl.s + "%, " + (hsl.l - 10) + "%)");
        // this.root.style.setProperty("--navbar-bg-50", "hsla(" + hsl.h + ", " + hsl.s + "%, " + hsl.l + "%, .5)");
        // this.root.style.setProperty("--navbar-bg-75", "hsla(" + hsl.h + ", " + hsl.s + "%, " + hsl.l + "%, .75)");

        const navbarLinks = document.querySelectorAll(".navbar a");
        const footer = document.querySelector(".home-footer");
        if (hsl.l > 50) {
            navbarLinks?.forEach(link => {
                link.classList.add("dark");
            });
            footer?.classList.add("dark");
        }
    }

    adjustThemeColorMeta(hsl) {
        const hexColor = this.hslToHex(hsl);
        this.updateThemeColor(hexColor);
    }

    hslToHex(hsl) {
        const h = hsl.h;
        const s = hsl.s / 100;
        const l = .38;//hsl.l / 100;

        const k = n => (n + h / 30) % 12;
        const a = s * Math.min(l, 1 - l);
        const f = n => Math.round(255 * (l - a * Math.max(-1, Math.min(k(n) - 3, 9 - k(n), 1))));

        const toHex = x => x.toString(16).padStart(2, '0');
        return `#${toHex(f(0))}${toHex(f(8))}${toHex(f(4))}`;
    }

    updateThemeColor(hexColor) {
        const metaThemeColor = document.querySelector('meta[name="theme-color"]');
        if (metaThemeColor) {
            metaThemeColor.setAttribute('content', hexColor);
        }
    }

    setOpacity() {
        // Window width and main menu opacity - commented out - see app.css and @media (width >= 1920px)
        // const width = window.innerWidth;
        // if (width >= 1920) {
        //     this.root.style.setProperty("--navbar-opacity", "1");
        //     return;
        // }
        const opacity = 1;//this.mapScroll(window.scrollY);
        // this.debugDiv.textContent = opacity.toFixed(2).toString() + " / " + window.scrollY.toFixed(2);
        this.root.style.setProperty("--navbar-opacity", opacity.toString());
    }

    mapScroll(amount) {
        const start = 100;
        const end = 800;
        const interval = end - start;

        let scroll = 1;
        if (amount > start && amount < end) {
            scroll = 1 - .9 * Math.pow((amount - start) / interval, 3);
        } else if (amount >= end) {
            scroll = .1;
        }
        return scroll;
    }
}