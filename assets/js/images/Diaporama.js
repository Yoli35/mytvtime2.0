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
        const svgs = document.querySelector("#svgs");
        const xmark = svgs.querySelector("#xmark").querySelector("svg").cloneNode(true);
        const arrowLeft = svgs.querySelector("#arrow-left").querySelector("svg").cloneNode(true);
        const arrowRight = svgs.querySelector("#arrow-right").querySelector("svg").cloneNode(true);

        gThis.diaporamaIndex = 0;
        gThis.diaporamaCount = count;
        gThis.diaporamaSrc = srcArray;

        const dialog = document.createElement("dialog");
        dialog.classList.add("diaporama");

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
        // document.querySelector("body").appendChild(backDiapo);
        // document.body.style.overflow = 'hidden';

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
        const imgDiv = document.createElement("img");
        imgDiv.setAttribute("src", imageSrc);

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

        dialog.appendChild(backDiapo);
        document.querySelector("body").appendChild(dialog);
        dialog.showModal();

        setTimeout(() => {
            imgDiv.classList.add("fade");
        }, 0);
        // backDiapo.style.bottom = -window.scrollY + "px";
        // backDiapo.style.top = window.scrollY + "px";

        /*gThis.initShortcutsHelp(backDiapo, count);*/
    }

    nextImage() {
        gThis.thumbnailDeactivate(gThis.diaporamaIndex);
        gThis.diaporamaIndex = (gThis.diaporamaIndex + 1) % gThis.diaporamaCount;
        gThis.thumbnailActivate(gThis.diaporamaIndex);
        const img = document.querySelector(".back-diapo").querySelector(".image").querySelector("img");
        img.setAttribute("src", gThis.diaporamaSrc[gThis.diaporamaIndex]);
    }

    /*initShortcutsHelp(back, count) {
        const txt = {
            'title': {
                'fr': 'Raccourcis clavier',
                'en': 'Keyboard shortcuts',
                'de': 'Tastaturkürzel',
                'es': 'Atajos de teclado',
            },
            'left': {
                'fr': 'Image précédente',
                'en': 'Previous image',
                'de': 'Vorheriges Bild',
                'es': 'Imagen anterior',
            }
            ,
            'right': {
                'fr': 'Image suivante',
                'en': 'Next image',
                'de': 'Nächstes Bild',
                'es': 'Imagen siguiente',
            }
            ,
            'escape': {
                'fr': 'Quitter le diaporama',
                'en': 'Quit diaporama',
                'de': 'Diashow beenden',
                'es': 'Salir de la presentación',
            }
        };
        const navigation = document.createElement("div");
        navigation.classList.add("navigation");
        const help = document.createElement("div");
        help.classList.add("help");
        navigation.appendChild(help);
        const mini = document.createElement("div");
        mini.classList.add("mini");
        mini.innerHTML = '<i class="fa-solid fa-circle-question"></i>';
        mini.addEventListener("click", gThis.maximiseHelp);
        help.appendChild(mini);
        const maxi = document.createElement("div");
        maxi.classList.add("maxi");
        help.appendChild(maxi);
        const title = document.createElement("div");
        title.classList.add("title");
        title.innerHTML = txt.title[gThis.locale];
        maxi.appendChild(title);
        if (count) {
            gThis.addKey(maxi, "left", txt.left[gThis.locale]);
            gThis.addKey(maxi, "right", txt.right[gThis.locale]);
        }
        gThis.addKey(maxi, "escape", txt.escape[gThis.locale]);
        const close = document.createElement("div");
        close.classList.add("close");
        close.innerHTML = '<i class="fa-solid fa-circle-chevron-down"></i>';
        close.addEventListener("click", gThis.minimiseHelp);
        maxi.appendChild(close);
        back.appendChild(navigation);
    }*/

    prevImage() {
        gThis.thumbnailDeactivate(gThis.diaporamaIndex);
        gThis.diaporamaIndex = (gThis.diaporamaIndex + (gThis.diaporamaCount - 1)) % gThis.diaporamaCount;
        gThis.thumbnailActivate(gThis.diaporamaIndex);
        const img = document.querySelector(".back-diapo").querySelector(".image").querySelector("img");
        img.setAttribute("src", gThis.diaporamaSrc[gThis.diaporamaIndex]);
    }

    gotoImage(e) {
        gThis.thumbnailDeactivate(gThis.diaporamaIndex);
        gThis.diaporamaIndex = parseInt(e.currentTarget.getAttribute("data-index"));
        gThis.thumbnailActivate(gThis.diaporamaIndex);
        const img = document.querySelector(".back-diapo").querySelector(".image").querySelector("img");
        img.setAttribute("src", gThis.diaporamaSrc[gThis.diaporamaIndex]);
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
        const dialog = document.querySelector("dialog.diaporama");
        const backDiapo = document.querySelector(".back-diapo");
        const imgDiv = backDiapo.querySelector("img");

        if (gThis.diaporamaCount > 1) {
            for (let i = 0; i < gThis.diaporamaCount; i++) {
                const selector = '.thumbnail[data-index="' + i + '"]'
                const thumbnail = document.querySelector(selector);
                thumbnail.removeEventListener("click", gThis.gotoImage);
            }
            const prevDiv = document.querySelector(".back-diapo").querySelector(".chevron.left").parentElement;
            const nextDiv = document.querySelector(".back-diapo").querySelector(".chevron.right").parentElement;
            prevDiv.removeEventListener("click", gThis.prevImage);
            nextDiv.removeEventListener("click", gThis.nextImage);
        }
        document.removeEventListener("keydown", gThis.getKey);
        document.body.style.overflow = 'unset';
        setTimeout(() => {
            imgDiv.classList.remove("fade");
        }, 0);
        setTimeout(() => {
            dialog.close();
            document.querySelector("body").removeChild(dialog);
            // document.querySelector("body").removeChild(backDiapo);
        }, 300);
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

    minimiseHelp() {
        const help = document.querySelector(".back-diapo").querySelector(".navigation").querySelector(".help");
        help.classList.add("minimise");
    }

    maximiseHelp() {
        const help = document.querySelector(".back-diapo").querySelector(".navigation").querySelector(".help");
        help.classList.remove("minimise");
    }

    addKey(parent, name, txt) {
        const key = document.createElement("div");
        key.classList.add("key")
        const img = document.createElement("img");
        img.setAttribute("src", "/images/interface/key-" + name + ".png");
        key.appendChild(img);
        const text = document.createElement("div");
        text.appendChild(document.createTextNode(txt));
        key.appendChild(text);
        parent.appendChild(key);
    }
}
