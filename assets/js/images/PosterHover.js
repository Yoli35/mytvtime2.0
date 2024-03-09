export class PosterHover {
    constructor() {
        this.showPoster = this.showPoster.bind(this);
        this.hidePoster = this.hidePoster.bind(this);
    }

    init() {
        const posters = document.querySelectorAll(".poster");
        posters.forEach(poster => {
            poster.addEventListener("mouseover", this.showPoster);
            poster.addEventListener("mousemove", this.showPoster);
            poster.addEventListener("mouseout", this.hidePoster);
        });
        const stills = document.querySelectorAll(".still");
        stills.forEach(still => {
            still.addEventListener("mouseover", this.showPoster);
            still.addEventListener("mousemove", this.showPoster);
            still.addEventListener("mouseout", this.hidePoster);
        });
        const cast = document.querySelector(".cast");
        if (cast) {
            const people = cast.querySelectorAll(".people");
            people.forEach(person => {
                person.addEventListener("mouseover", this.showPoster);
                person.addEventListener("mousemove", this.showPoster);
                person.addEventListener("mouseout", this.hidePoster);
            });
        }
        const crew = document.querySelector(".crew");
        if (crew) {
            const people = crew.querySelectorAll(".people");
            people.forEach(person => {
                person.addEventListener("mouseover", this.showPoster);
                person.addEventListener("mousemove", this.showPoster);
                // person.addEventListener("mousewheel", this.showPoster);
                person.addEventListener("mouseout", this.hidePoster);
            });
        }
    }

    showPoster(evt) {
        const preview = localStorage.getItem("mytvtime_2_preview");
        if (preview === null) {
            return;
        }
        const poster = evt.currentTarget;
        const img = poster.querySelector("img");

        if (img === null) {
            return;
        }
        let flyingPoster = document.querySelector(".flying-poster");
        // Flying poster : 480 x 704 px
        const screenW = window.innerWidth;
        const screenH = window.innerHeight;
        const homePage = document.querySelector(".home");
        const marginBlockStart = homePage ? 64 : 0;
        let left = evt.clientX - 240;
        // Ajouter le décalage du scroll
        let top = evt.clientY + 16 + window.scrollY - marginBlockStart;
        if (left < 0) {
            left = 0;
        } else if (left + 480 > screenW) {
            left = screenW - 480;
        }
        if (top + 704 + 16 > screenH) {
            top -= 732;
        }

        if (flyingPoster) {
            flyingPoster.setAttribute("style", "left: " + left + "px; top: " + top + "px;");
            return;
        }
        const container = document.querySelector("div[class|='container']");
        const src = img.getAttribute("src");
        const alt = img.getAttribute("alt");
        let flyingPosterImg;

        flyingPoster = document.createElement("div");
        flyingPoster.classList.add("flying-poster");
        flyingPosterImg = document.createElement("img");
        flyingPosterImg.setAttribute("src", src);
        flyingPosterImg.setAttribute("alt", alt);
        flyingPoster.appendChild(flyingPosterImg);
        flyingPoster.setAttribute("style", "left: " + left + "px; top: " + top + "px;");
        container.appendChild(flyingPoster);
        flyingPoster.classList.add('show');
    }

    hidePoster() {
        const flyingPoster = document.querySelector(".flying-poster");
        if (flyingPoster) {
            flyingPoster.classList.remove('show');
            setTimeout(() => {
                flyingPoster.remove();
            }, 150);
        }
    }
}