import {Diaporama} from "Diaporama";

let gThis = null;

export class PeopleShow {
    constructor() {
        gThis = this;
        this.lang = document.querySelector("html").getAttribute("lang");
        this.globs = JSON.parse(document.querySelector(".global-data").textContent);

        this.app_series_get_overview = this.globs.app_series_get_overview;
        this.imgUrl = this.globs.imgUrl;
        this.diaporama = new Diaporama();
        this.start();
    }

    start() {

        const infos = document.querySelector(".credits").querySelectorAll(".info");
        infos.forEach(info => {
            info.addEventListener("click", this.showInfos);
        });
        document.addEventListener("click", this.hideInfos);
        this.initInfos();

        const images = document.querySelector(".person").querySelector(".images").querySelectorAll("img");
        this.diaporama.start(images);

        const media = document.querySelector(".person").querySelector(".known-for").querySelectorAll(".poster");
        media.forEach(m => {
            m.addEventListener("mouseenter", this.showPoster);
            m.addEventListener("mousemove", this.showPoster)
            m.addEventListener("mouseleave", this.hidePoster);
        });
        document.addEventListener("click", this.hidePoster);
    }

    initInfos() {
        const popInfos = document.querySelector(".pop-infos");
        const close = popInfos.querySelector(".close");

        close.addEventListener("click", gThis.hideInfos);
    }

    showInfos(evt) {
        const popInfos = document.querySelector(".pop-infos");
        let wasOpen = false;
        if (popInfos.classList.contains("show")) {
            gThis.hideInfos(evt);
            wasOpen = true;
        }

        setTimeout(() => {
            const id = evt.target.closest(".info").getAttribute("data-id");
            const type = evt.target.closest(".info").getAttribute("data-type");
            const title = evt.target.closest(".info").getAttribute("data-title");
            const poster = evt.target.closest(".info").getAttribute("data-poster");
            const screenW = window.innerWidth;

            const x = evt.clientX, y = evt.clientY + evt.view.scrollY - 16;

            let img = document.createElement("img");
            if (poster.length) {
                img.setAttribute("src", gThis.imgUrl + poster);
            } else {
                img.setAttribute("src", '/images/default/no_poster_dark.png');
            }
            popInfos.querySelector(".poster").innerHTML = "";
            popInfos.querySelector(".poster").appendChild(img);
            popInfos.querySelector(".title").appendChild(document.createTextNode(title));
            popInfos.querySelector(".spinner").setAttribute("style", "display: block;");
            if (screenW >= 576)
                popInfos.setAttribute("style", "left: calc(" + x + "px - 16em); top: calc(" + y + "px - 10.85em);");
            popInfos.classList.add("show");
            setTimeout(() => {
                popInfos.classList.add("fade");
            }, 0);

            const xhr = new XMLHttpRequest();
            xhr.onload = function () {
                /** @type {{overview: string, media_type: string}} */
                let result = JSON.parse(this.response);

                popInfos.querySelector(".title").innerHTML = "";
                popInfos.querySelector(".overview").innerHTML = "";
                popInfos.querySelector(".title").appendChild(document.createTextNode(title + " (" + result.media_type + ")"));
                popInfos.querySelector(".overview").appendChild(document.createTextNode(result.overview));
                popInfos.querySelector(".spinner").setAttribute("style", "display: none;");
            }
            xhr.open("GET", gThis.app_series_get_overview + id + "?type=" + type);
            xhr.send();
        }, wasOpen ? 200 : 0);
    }

    hideInfos(evt) {
        const popInfos = document.querySelector(".pop-infos");
        if (popInfos.classList.contains("fade")) {
            evt.preventDefault();
            popInfos.classList.remove("fade");
            setTimeout(() => {
                popInfos.classList.remove("show");
                popInfos.querySelector(".poster").innerHTML = "";
                popInfos.querySelector(".title").innerHTML = "";
                popInfos.querySelector(".overview").innerHTML = "";
            }, 150);
        }
    }

    showPoster(evt) {
        const poster = document.querySelector(".person").querySelector(".poster-hover");
        const pageHeight = window.innerHeight;
        const pageWidth = window.innerWidth;
        const top = evt.pageY + 16;
        const imageHeight = Math.min(.75 * pageHeight, pageHeight - top - 32);
        const imageWidth = imageHeight * 78 / 117;
        const posterHeight = imageHeight + 32;
        const posterWidth = imageWidth + 32;
        const left = Math.min(evt.pageX - 144, pageWidth - posterWidth - 32);

        poster.setAttribute("style", "left: " + left + "px; top: " + top + "px; height: " + posterHeight + "px; width: " + posterWidth + "px;");

        if (poster.classList.contains("show")) return;

        const img = poster.querySelector("img");
        const imgSrc = evt.currentTarget.querySelector("img").getAttribute("src");
        const title = evt.currentTarget.querySelector("img").getAttribute("alt");
        img.setAttribute("src", imgSrc);
        img.setAttribute("alt", title);
        poster.classList.add("show");
    }

    hidePoster(e) {
        const poster = document.querySelector(".person").querySelector(".poster-hover");
        // poster.classList.remove("show");
        console.log(e);
        if (poster.classList.contains("show") && e.type === "click") {
            e.preventDefault();
        }
        poster.classList.remove("show");
    }
}