let self;

export class Diaporama {

    constructor() {
        self = this;
        this.locale = document.querySelector("html").getAttribute("lang");
        this.diaporamaImages = null;
    }

    start(images) {
        if (!images) return;
        if (this.diaporamaImages === null) {
            this.diaporamaImages = Array.from(images);
            images.forEach(image => {
                image.classList.add("pointer");
                image.addEventListener("click", this.openDiaporama);
            });
        } else {
            images.forEach(image => {
                image.classList.add("pointer");
                image.addEventListener("click", this.openDiaporama);
                this.diaporamaImages.push(image);
            });
        }
    }

    enable(image) {
        image.classList.add("pointer");
        image.addEventListener("click", this.openDiaporama);
    }

    openDiaporama(e) {
        const images = self.diaporamaImages;
        const count = images.length;
        let prevDiv, nextDiv;
        const srcArray = Array.from(images, image => {
            return image.getAttribute("src")
        });
        const srcsetArray = Array.from(images, image => {
            return image.getAttribute("srcset")
        });
        const svgs = document.querySelector("#svgs");
        const xmark = svgs.querySelector("#xmark").querySelector("svg").cloneNode(true);
        const arrowLeft = svgs.querySelector("#arrow-left").querySelector("svg").cloneNode(true);
        const arrowRight = svgs.querySelector("#arrow-right").querySelector("svg").cloneNode(true);

        self.diaporamaIndex = 0;
        self.diaporamaCount = count;
        self.diaporamaSrc = srcArray;
        self.diaporamaSrcset = srcsetArray;

        const diaporamaDiv = document.querySelector(".diaporama");

        const backDiapo = document.createElement("div");
        backDiapo.classList.add("back-diapo");
        const wrapperDiv = document.createElement("div");
        wrapperDiv.classList.add("wrapper");
        const imageDiv = document.createElement("div");
        imageDiv.classList.add("image");
        backDiapo.appendChild(wrapperDiv);
        wrapperDiv.appendChild(imageDiv);
        let thumbnails
        if (count > 1) {
            thumbnails = document.createElement("div");
            thumbnails.classList.add("thumbnails");
            wrapperDiv.appendChild(thumbnails);
        }

        const closeDiv = document.createElement("div");
        closeDiv.classList.add("close");
        const crossDiv = document.createElement("div");
        closeDiv.appendChild(crossDiv);
        closeDiv.addEventListener("click", self.closeDiaporama);
        crossDiv.appendChild(xmark);
        if (count > 1) {
            prevDiv = document.createElement("div");
            prevDiv.classList.add("chevron", "left");
            nextDiv = document.createElement("div");
            nextDiv.classList.add("chevron", "right");
            prevDiv.appendChild(arrowLeft);
            nextDiv.appendChild(arrowRight);
        }

        const imageSrc = e.currentTarget.getAttribute("src");
        const imageSrcSet = e.currentTarget.getAttribute("srcset");
        const imgDiv = document.createElement("img");
        imgDiv.setAttribute("id", "diaporamaImg");
        imgDiv.setAttribute("src", imageSrc);
        if (imageSrcSet)
            imgDiv.setAttribute('srcset', imageSrcSet);

        if (count > 1) {
            srcArray.forEach((src, index) => {
                const thumbnail = document.createElement("div");
                thumbnail.classList.add("thumbnail");
                thumbnail.setAttribute("data-index", index.toString());
                if (src === imageSrc) {
                    thumbnail.classList.add("active");
                    self.diaporamaIndex = index;
                }
                const img = document.createElement("img");
                img.setAttribute("src", src);
                if (srcsetArray[index])
                    img.setAttribute("srcset", srcsetArray[index]);
                thumbnail.appendChild(img);
                thumbnail.addEventListener("click", self.gotoImage);
                thumbnails.appendChild(thumbnail);
            });
            prevDiv.addEventListener("click", self.prevImage);
            nextDiv.addEventListener("click", self.nextImage);
        }

        document.addEventListener("keydown", self.getKey);

        backDiapo.appendChild(closeDiv);
        if (count > 1) imageDiv.appendChild(prevDiv);
        imageDiv.appendChild(imgDiv);
        if (count > 1) imageDiv.appendChild(nextDiv);

        diaporamaDiv.appendChild(backDiapo);
        document.body.classList.add("frozen");
        diaporamaDiv.classList.add("show");
        // diaporamaDiv.style.top =  window.scrollY + "px;";
        diaporamaDiv.setAttribute("style", "top: " + window.scrollY + "px;")

        setTimeout(() => {
            imgDiv.classList.add("fade");
        }, 0);
    }

    nextImage() {
        self.thumbnailDeactivate(self.diaporamaIndex);
        self.diaporamaIndex = (self.diaporamaIndex + 1) % self.diaporamaCount;
        self.setImage();
    }

    prevImage() {
        self.thumbnailDeactivate(self.diaporamaIndex);
        self.diaporamaIndex = (self.diaporamaIndex + (self.diaporamaCount - 1)) % self.diaporamaCount;
        self.setImage();
    }

    gotoImage(e) {
        self.thumbnailDeactivate(self.diaporamaIndex);
        self.diaporamaIndex = parseInt(e.currentTarget.getAttribute("data-index"));
        self.setImage();
    }

    setImage() {
        self.thumbnailActivate(self.diaporamaIndex);
        const img = document.querySelector(".back-diapo").querySelector(".image").querySelector("img");
        img.setAttribute("src", self.diaporamaSrc[self.diaporamaIndex]);
        if (self.diaporamaSrcset[self.diaporamaIndex]) {
            img.setAttribute("srcset", self.diaporamaSrcset[self.diaporamaIndex]);
        } else {
            img.removeAttribute("srcset");
        }
    }

    thumbnailDeactivate(index) {
        const selector = '.thumbnail[data-index="' + index + '"]'
        const thumbnail = document.querySelector(selector);
        thumbnail.classList.remove("active");
    }

    thumbnailActivate(index) {
        const selector = '.thumbnail[data-index="' + index + '"]'
        const thumbnail = document.querySelector(selector);
        thumbnail.classList.add("active");
        /*thumbnail.scrollIntoView(false);*/
    }

    closeDiaporama() {
        const diaporamaDiv = document.querySelector(".diaporama");
        const backDiapo = document.querySelector(".back-diapo");
        const imgDiv = backDiapo.querySelector("img");

        document.removeEventListener("keydown", self.getKey);
        document.body.classList.remove("frozen");
        setTimeout(() => {
            imgDiv.classList.remove("fade");
        }, 0);
        setTimeout(() => {
            diaporamaDiv.classList.remove("show");
            backDiapo.remove();
        }, 300);
    }

    getKey(e) {
        if (e.key === "ArrowLeft") {
            if (self.diaporamaCount > 1) self.prevImage();
        } else if (e.key === "ArrowRight") {
            if (self.diaporamaCount > 1) self.nextImage();
        } else if (e.key === "Escape") {
            self.closeDiaporama();
        }
    }
}
