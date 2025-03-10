export class ToolTips {

    tooltipsElement = null;
    bodyElement = null;
    tailElement = null;

    constructor(element = null, className = null) {
        let tooltipsDiv = document.querySelector(".tool-tips");
        if (!tooltipsDiv) {
            tooltipsDiv = this.createTooltips();
        }

        this.tooltipsElement = tooltipsDiv;
        this.bodyElement = this.tooltipsElement.querySelector(".body");
        this.tailElement = this.tooltipsElement.querySelector(".tail");

        this.init(element, className);
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

    initElement(element) {
        element.addEventListener('mousemove', this.move.bind(this));
        element.addEventListener('mouseenter', this.show.bind(this));
        element.addEventListener('mouseleave', this.hide.bind(this));
        // element.addEventListener('mouseover', this.show.bind(this));
        // element.addEventListener('mouseout', this.hide.bind(this));
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
        const tooltips = this.tooltipsElement;
        if (tooltips.classList.contains("show")) {
            return;
        }
        const currentTarget = evt.currentTarget;
        const text = currentTarget.getAttribute("data-title");
        const titleBg = currentTarget.getAttribute("data-title-bg");
        const img = currentTarget.querySelector("img");
        const body = this.bodyElement;
        const tail = this.tailElement;
        if (!img) {
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

        const width = body.offsetWidth;
        const height = body.offsetHeight - 48;
        let style = "translate: " + (evt.pageX - (width / 2)) + "px " + (evt.pageY - height) + "px; max-height: " + height + "px;";
        tooltips.setAttribute("style", style);

        tooltips.classList.add("show");
        // console.log("show - " + text);
        // console.log({x: evt.pageX, y: evt.pageY, width: width, height: height});
    }

    hide() {
        const tooltips = this.tooltipsElement;
        if (!tooltips) {
            return;
        }
        tooltips.classList.remove("show");
        setTimeout(() => {
            tooltips.setAttribute("style", "translate: 0px 0px;");
        }, 300);
        // console.log("hide");
    }

    move(evt) {
        evt.preventDefault();
        evt.stopPropagation();
        const tooltips = this.tooltipsElement;
        const tail = this.tailElement;
        const body = this.bodyElement;
        const bg = "background-color: " + tooltips.getAttribute("bg") + ";";
        const img = body.querySelector("img");
        const width = body.offsetWidth;
        const height = body.offsetHeight - 48;
        const fromTopViewport = evt.clientY;
        const windowWidth = window.innerWidth;
        const visualViewport = window.visualViewport;

        // console.log("move @ " + new Date().toLocaleString());
        // console.log({x: evt.pageX, y: evt.pageY, width: width, height: height, fromTopViewport: fromTopViewport});

        if (evt.pageX === 0 && evt.pageY === 0) {
            return;
        }

        if (img) img.style.maxHeight = (fromTopViewport - 64) + "px";

        const left = evt.pageX - (width / 2);
        if (left < 0) {
            let style = "transform: translate(" + (evt.pageX - (width / 2) + (left * -1)) + "px, " + (evt.pageY - Math.min(height, fromTopViewport)) + "px);";
            tooltips.setAttribute("style", style);
            tail.setAttribute("style", "translate: " + left + "px -.55rem;" + bg);
            return;
        }

        const right = evt.pageX + (width / 2);
        if (right > windowWidth * visualViewport.scale) {
            let style = "transform: translate(" + (evt.pageX - (width / 2) - (right - windowWidth)) + "px, " + (evt.pageY - Math.min(height, fromTopViewport)) + "px);";
            tooltips.setAttribute("style", style);
            tail.setAttribute("style", "translate: " + (right - windowWidth) + "px -.55rem;" + bg);
            return;
        }

        if ((evt.pageX - (width / 2)) === 0 || (evt.pageY - Math.min(height, fromTopViewport)) === 0) {
            console.log("Anomaly detected");
            return;
        }

        let style = "transform: translate(" + (evt.pageX - (width / 2)) + "px, " + (evt.pageY - Math.min(height, fromTopViewport)) + "px);";
        tooltips.setAttribute("style", style);
        tail.setAttribute("style", "translate: 0 -.55rem;" + bg);
        // console.log("move - " + style);
    }
}