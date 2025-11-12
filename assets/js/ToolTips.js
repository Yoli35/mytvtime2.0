let gThis;

export class ToolTips {
    tooltipsElement = null;
    bodyElement = null;
    tailElement = null;

    constructor(element = null, className = null) {
        gThis = this;

        let tooltipsDiv = document.querySelector(".tool-tips");
        if (!tooltipsDiv) {
            tooltipsDiv = this.createTooltips();
        }

        this.tooltipsElement = tooltipsDiv;
        this.bodyElement = this.tooltipsElement.querySelector(".body");
        this.tailElement = this.tooltipsElement.querySelector(".tail");
        this.style = "";

        this.init(element, className);
        this.hideOnLinkClick();
    }

    init(element = null, className = null) {
        let divs;
        if (!element) {
            divs = document.querySelectorAll("*[data-title]");
        } else {
            divs = element.querySelectorAll("*[data-title]");
        }
        divs.forEach(div => {
            this.initElement(div);
        });

        if (className) {
            this.tooltipsElement.classList.add(className);
        }
    }

    hideOnLinkClick() {
        const links = document.querySelectorAll("a");
        links.forEach(link => {
            link.addEventListener("click", () => {
                this.hide();
            });
        });
    }

    initElement(element) {
        element.addEventListener('mousemove', this.move.bind(this));
        element.addEventListener('mouseenter', this.show.bind(this));
        element.addEventListener('mouseleave', this.hide.bind(this));
    }

    createTooltips() {
        const tooltips = document.createElement("div");
        tooltips.classList.add("tool-tips");
        const body = document.createElement("div");
        body.classList.add("body");
        const tail = document.createElement("div");
        tail.classList.add("tail");
        tooltips.appendChild(body);
        tooltips.appendChild(tail);
        document.body.appendChild(tooltips);

        return tooltips;
    }

    show(evt) {
        evt.preventDefault();
        evt.stopPropagation();
        const previewImage = localStorage.getItem("mytvtime_2_preview");
        const tooltips = gThis.tooltipsElement;
        if (tooltips.classList.contains("show")) {
            return;
        }
        const currentTarget = evt.currentTarget;
        const text = currentTarget.getAttribute("data-title");
        const titleBg = currentTarget.getAttribute("data-title-bg");
        const img = currentTarget.querySelector("img");
        const body = this.bodyElement;
        const tail = this.tailElement;
        if (!img || !previewImage) {
            body.innerHTML = text;
        } else {
            const imgClone = img.cloneNode(true);
            body.innerHTML = "";
            body.style.backgroundColor = titleBg || "DarkSlateGray";
            tail.style.backgroundColor = titleBg || "DarkSlateGray";
            tooltips.setAttribute("bg", titleBg || "DarkSlateGray");
            body.appendChild(imgClone);
            if (img.getAttribute("src").includes(".svg")) {
                imgClone.style.width = "20rem";
            }
            const p = document.createElement("p");
            p.innerHTML = text;
            body.appendChild(p);
        }
        gThis.move(evt);

        tooltips.classList.add("show");
    }

    hide() {
        const tooltips = gThis.tooltipsElement;/*document.querySelector(".tool-tips");*/
        if (!tooltips) {
            return;
        }
        tooltips.classList.remove("show");
    }

    move(evt) {
        evt.preventDefault();
        evt.stopPropagation();
        const tooltips = gThis.tooltipsElement;
        const tail = this.tailElement;
        const body = this.bodyElement;
        const bg = "background-color: " + tooltips.getAttribute("bg") + ";";
        const img = body.querySelector("img");
        const width = body.offsetWidth;
        const height = body.offsetHeight - 48;
        const fromTopViewport = evt.clientY;
        const windowWidth = window.innerWidth;
        const visualViewport = window.visualViewport;


        if (evt.pageX === 0 && evt.pageY === 0) {
            return;
        }
        let maxHeight, toolTipsTranslateY, tailTranslateY;
        if (fromTopViewport < (window.innerHeight / 2)) {
            toolTipsTranslateY = (evt.pageY + 96);
            tailTranslateY = body.offsetHeight + 7.2;
            maxHeight = window.innerHeight - fromTopViewport - 64;
        } else {
            toolTipsTranslateY = (evt.pageY - Math.min(height, fromTopViewport));
            tailTranslateY = 8.8;
            maxHeight = fromTopViewport - 64;
        }

        if (img) img.setAttribute("style", "max-height: " + maxHeight + "px");

        const left = evt.pageX - (width / 2);
        if (left < 0) {
            let style = "transform: translate(" + (evt.pageX - (width / 2) + (left * -1)) + "px, " + toolTipsTranslateY + "px);";
            if (style !== gThis.style) {
                gThis.style = style;
                // console.log(style);
                tooltips.setAttribute("style", style);
                tail.setAttribute("style", "translate: " + left + "px -" + tailTranslateY + "px;" + bg);
            }
            return;
        }

        const right = evt.pageX + (width / 2);
        if (right > windowWidth * visualViewport.scale) {
            let style = "transform: translate(" + (evt.pageX - (width / 2) - (right - windowWidth)) + "px, " + toolTipsTranslateY + "px);";
            if (style !== gThis.style) {
                gThis.style = style;
                // console.log(style);
                tooltips.setAttribute("style", style);
                tail.setAttribute("style", "translate: " + (right - windowWidth) + "px -" + tailTranslateY + "px;" + bg);
            }
            return;
        }

        let style = "transform: translate(" + (evt.pageX - (width / 2)) + "px, " + toolTipsTranslateY + "px);";
        if (style !== gThis.style) {
            gThis.style = style;
            // console.log(style);
            tooltips.setAttribute("style", style);
            tail.setAttribute("style", "translate: 0 -" + tailTranslateY + "px;" + bg);
        }
    }
}