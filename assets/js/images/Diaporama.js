let gThis;

export class Diaporama {

    constructor() {
        gThis = this;
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
        const images = gThis.diaporamaImages;
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

        gThis.diaporamaIndex = 0;
        gThis.diaporamaCount = count;
        gThis.diaporamaSrc = srcArray;
        gThis.diaporamaSrcset = srcsetArray;

        const diaporamaDiv = document.createElement("div");
        diaporamaDiv.classList.add("diaporama");

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
        closeDiv.addEventListener("click", gThis.closeDiaporama);
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
                    gThis.diaporamaIndex = index;
                }
                const img = document.createElement("img");
                img.setAttribute("src", src);
                if (srcsetArray[index])
                    img.setAttribute("srcset", srcsetArray[index]);
                thumbnail.appendChild(img);
                thumbnail.addEventListener("click", gThis.gotoImage);
                thumbnails.appendChild(thumbnail);
            });
            prevDiv.addEventListener("click", gThis.prevImage);
            nextDiv.addEventListener("click", gThis.nextImage);
        }

        document.addEventListener("keydown", gThis.getKey);

        backDiapo.appendChild(closeDiv);
        if (count > 1) imageDiv.appendChild(prevDiv);
        imageDiv.appendChild(imgDiv);
        if (count > 1) imageDiv.appendChild(nextDiv);

        diaporamaDiv.appendChild(backDiapo);
        diaporamaDiv.addEventListener("click", gThis.fullScreen);
        document.querySelector("body").appendChild(diaporamaDiv);

        diaporamaDiv.click();
    }

    fullScreen(e) {
        const diaporamaDiv = e.currentTarget;
        const imgDiv = diaporamaDiv.querySelector("#diaporamaImg");

        diaporamaDiv.requestFullscreen().then(() => {
            setTimeout(() => {
                imgDiv.classList.add("fade");
            }, 0);
        });
    }

    nextImage() {
        gThis.thumbnailDeactivate(gThis.diaporamaIndex);
        gThis.diaporamaIndex = (gThis.diaporamaIndex + 1) % gThis.diaporamaCount;
        gThis.setImage();
    }

    prevImage() {
        gThis.thumbnailDeactivate(gThis.diaporamaIndex);
        gThis.diaporamaIndex = (gThis.diaporamaIndex + (gThis.diaporamaCount - 1)) % gThis.diaporamaCount;
        gThis.setImage();
    }

    gotoImage(e) {
        gThis.thumbnailDeactivate(gThis.diaporamaIndex);
        gThis.diaporamaIndex = parseInt(e.currentTarget.getAttribute("data-index"));
        gThis.setImage();
    }

    setImage() {
        gThis.thumbnailActivate(gThis.diaporamaIndex);
        const img = document.querySelector(".back-diapo").querySelector(".image").querySelector("img");
        img.setAttribute("src", gThis.diaporamaSrc[gThis.diaporamaIndex]);
        if (gThis.diaporamaSrcset[gThis.diaporamaIndex]){
            img.setAttribute("srcset", gThis.diaporamaSrcset[gThis.diaporamaIndex]);
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
        thumbnail.scrollIntoView(false);
    }

    closeDiaporama() {
        const diaporamaDiv = document.querySelector(".diaporama");
        const backDiapo = document.querySelector(".back-diapo");
        const imgDiv = backDiapo.querySelector("img");

        document.exitFullscreen().then(() => {
            document.removeEventListener("keydown", gThis.getKey);
            document.body.style.overflow = 'unset';
            setTimeout(() => {
                imgDiv.classList.remove("fade");
            }, 0);
            setTimeout(() => {
                document.querySelector("body").removeChild(diaporamaDiv);
            }, 300);
        });
    }

    getKey(e) {
        if (e.key === "ArrowLeft") {
            if (gThis.diaporamaCount > 1) gThis.prevImage();
        } else if (e.key === "ArrowRight") {
            if (gThis.diaporamaCount > 1) gThis.nextImage();
        } else if (e.key === "Escape") {
            gThis.closeDiaporama();
        }
    }
}
