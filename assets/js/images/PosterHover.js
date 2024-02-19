
export class PosterHover {
    constructor() {
        this.showPoster = this.showPoster.bind(this);
        this.hidePoster = this.hidePoster.bind(this);
    }

    init() {
        const posters = document.querySelectorAll(".poster");
        posters.forEach(poster => {
            poster.addEventListener("mouseover", this.showPoster);
            poster.addEventListener("mousemove", this.showPoster)
            poster.addEventListener("mouseout", this.hidePoster);
        });
    }

    showPoster(evt) {
        const poster = evt.currentTarget;
        const img = poster.querySelector("img");

        if (img === null) {
            return;
        }
        let flyingPoster = document.querySelector(".flying-poster");
        // Flying poster : 480 x 704 px
        const screenW = window.innerWidth;
        const screenH = window.innerHeight;
        let left = evt.clientX - 240;
        let top = evt.clientY + 16;
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
        // Select div with class name beginning with "container"
        const container = document.querySelector("div[class|=container]");
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