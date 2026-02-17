import {Diaporama} from "Diaporama";
import {ToolTips} from "ToolTips";
import {TranslationsForms} from "TranslationsForms";

let self = null;

export class PeopleShow {
    constructor() {
        self = this;
        this.lang = document.querySelector("html").getAttribute("lang");
        this.globs = JSON.parse(document.querySelector(".global-data").textContent);

        this.app_series_get_overview = this.globs.app_series_get_overview;
        this.app_people_rating = this.globs.app_people_rating;
        this.app_people_preferred_name = this.globs.app_people_preferred_name;
        this.imgUrl = this.globs.imgUrl;
        this.translations = this.globs.translations;
        this.toolTips = new ToolTips();
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

        const images = document.querySelector(".person").querySelector(".images")?.querySelectorAll("img");
        this.diaporama.start(images);

        const media = document.querySelector(".person").querySelector(".known-for").querySelectorAll(".poster");
        media.forEach(m => {
            m.addEventListener("mouseenter", this.showPoster);
            m.addEventListener("mousemove", this.showPoster)
            m.addEventListener("mouseleave", this.hidePoster);
        });
        document.addEventListener("click", this.hidePoster);

        const stars = document.querySelector(".rating.user").querySelectorAll(".star");
        stars.forEach(star => {
            star.addEventListener("click", this.rate);
        });

        const preferredNameForm = document.querySelector(".preferred-name");
        const preferredNameSubmit = preferredNameForm.querySelector("button[type=submit]");
        preferredNameSubmit.addEventListener("click", this.preferredName);

        /******************************************************************************
         * Menu to add a localized name or an overview and additional overview        *
         ******************************************************************************/
        const id = document.querySelector(".person").getAttribute("data-id");
        new TranslationsForms(id, 'people', this.translations);
    }

    initInfos() {
        const popInfos = document.querySelector(".pop-infos");
        const close = popInfos.querySelector(".close");

        close.addEventListener("click", self.hideInfos);
    }

    showInfos(evt) {
        const popInfos = document.querySelector(".pop-infos");
        let wasOpen = false;
        if (popInfos.classList.contains("show")) {
            self.hideInfos(evt);
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
                img.setAttribute("src", self.imgUrl + poster);
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
            xhr.open("GET", self.app_series_get_overview + id + "?type=" + type);
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
        if (poster.classList.contains("show") && e.type === "click") {
            e.preventDefault();
        }
        poster.classList.remove("show");
    }

    rate(evt) {
        const rating = evt.currentTarget.getAttribute("data-rating");
        const stars = document.querySelector(".rating.user").querySelectorAll(".star");
        stars.forEach(star => {
            star.classList.remove("active");
        });
        for (let i = 0; i < rating; i++) {
            stars[i].classList.add("active");
        }
        const id = document.querySelector(".person").getAttribute("data-id");
        const data = {
            "id": id,
            "rating": rating
        };
        fetch(self.app_people_rating,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                console.log(data);
                const leftColumn = document.querySelector(".left-column");
                const ratingInfos = leftColumn.querySelector(".rating-infos");
                const newBlockDiv = document.createElement("div");
                newBlockDiv.classList.add("temp");
                newBlockDiv.innerHTML = data['block'];
                const newBlock = newBlockDiv.querySelector(".rating-infos");
                ratingInfos.innerHTML = newBlock.innerHTML;
                newBlockDiv.remove();
                const stars = leftColumn.querySelector(".rating.user").querySelectorAll(".star");
                stars.forEach(star => {
                    star.addEventListener("click", self.rate);
                });
                self.toolTips.init(ratingInfos);
            });
    }

    preferredName(e) {
        e.preventDefault();
        const preferredNameForm = document.querySelector(".preferred-name");
        const id = preferredNameForm.querySelector("input[name=id]").value;
        const formData = new FormData(preferredNameForm);
        const preferredName = formData.get("also_known_as");
        const newName = formData.get("new_name");
        const data = {
            "id": id,
            "name": preferredName,
            "new": newName
        };
        fetch(self.globs.app_people_preferred_name,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                const leftColumn = document.querySelector(".left-column");
                const preferredNameInfos = leftColumn.querySelector(".preferred-name-infos");
                const newBlockDiv = document.createElement("div");
                newBlockDiv.classList.add("temp");
                newBlockDiv.innerHTML = data['block'];
                const newBlock = newBlockDiv.querySelector(".preferred-name-infos");
                preferredNameInfos.innerHTML = newBlock.innerHTML;
                newBlockDiv.remove();

                // form and submit button have been replaced
                const preferredNameForm = document.querySelector(".preferred-name");
                const preferredNameSubmit = preferredNameForm.querySelector("button[type=submit]");
                preferredNameSubmit.addEventListener("click", self.preferredName);

                // display the new preferred name in the h1
                const h1 = document.querySelector("h1");
                let preferredNameSpan = h1.querySelector("span.preferred-name");
                if (!preferredNameSpan) {
                    preferredNameSpan = document.createElement("span");
                    preferredNameSpan.classList.add("preferred-name");
                    h1.appendChild(preferredNameSpan);
                }
                preferredNameSpan.innerHTML = ' - ' + data['preferred-name'];
            });
    }
}