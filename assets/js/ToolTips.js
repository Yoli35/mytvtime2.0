export class ToolTips {
    init(element = null, className = null) {
        // <div className="tool-tips">
        //     <div className="body"></div>
        //     <div className="tail"></div>
        // </div>
        let divs;
        if (!element) {
            const tooltips = document.createElement("div");
            tooltips.classList.add("tool-tips");
            if (className) {
                tooltips.classList.add(className);
            }
            const body = document.createElement("div");
            body.classList.add("body");
            const tail = document.createElement("div");
            tail.classList.add("tail");
            tooltips.appendChild(body);
            tooltips.appendChild(tail);
            document.body.appendChild(tooltips);

            divs = document.querySelectorAll("*[data-title]");
        } else {
            if (className) {
                const tooltips = document.querySelector(".tool-tips");
                tooltips?.classList.add(className);
            }
            divs = element.querySelectorAll("*[data-title]");
        }
        divs.forEach(div => {
            div.addEventListener('mousemove', this.move);
            div.addEventListener('mouseover', this.show);
            div.addEventListener('mouseout', this.hide);
        });
    }

    initElement(element) {
        element.addEventListener('mousemove', this.move);
        element.addEventListener('mouseover', this.show);
        element.addEventListener('mouseout', this.hide);
    }

    show(evt) {
        const tooltips = document.querySelector(".tool-tips");
        if (tooltips.classList.contains("show")) {
            return;
        }
        const text = evt.currentTarget.getAttribute("data-title");
        const body = tooltips.querySelector(".body");
        body.innerHTML = text;

        const toolTipsBg = tooltips.style.getPropertyValue("--tooltips-bg");
        const toolTipsColor = tooltips.style.getPropertyValue("--tooltips-color");

        const width = body.offsetWidth;
        let style = "translate: " + (evt.pageX - (width / 2)) + "px " + evt.pageY + "px;";
        tooltips.setAttribute("style", style);
        if (toolTipsBg.length) tooltips.style.setProperty("--tooltips-bg", toolTipsBg);
        if (toolTipsColor.length) tooltips.style.setProperty("--tooltips-color", toolTipsColor);

        tooltips.classList.add("show");
    }

    hide() {
        const tooltips = document.querySelector(".tool-tips");
        tooltips.classList.remove("show");
        tooltips.setAttribute("style", "translate: 0px 0px;");
    }

    move(evt) {
        const tooltips = document.querySelector(".tool-tips");
        const tail = tooltips.querySelector(".tail");
        const body = tooltips.querySelector(".body");
        const width = body.offsetWidth;
        const windowWidth = window.innerWidth;

        const toolTipsBg = tooltips.style.getPropertyValue("--tooltips-bg");
        const toolTipsColor = tooltips.style.getPropertyValue("--tooltips-color");

        const left = evt.pageX - (width / 2);
        if (left < 0) {
            let style  = "translate: " + (evt.pageX - (width / 2) + (left * -1)) + "px " + evt.pageY + "px;";
            tooltips.setAttribute("style", style);
            if (toolTipsBg.length) tooltips.style.setProperty("--tooltips-bg", toolTipsBg);
            if (toolTipsColor.length) tooltips.style.setProperty("--tooltips-color", toolTipsColor);
            tail.setAttribute("style", "translate: " + left + "px -.55em");
            return;
        }

        const right = evt.pageX + (width / 2);
        if (right > windowWidth) {
            let style = "translate: " + (evt.pageX - (width / 2) - (right - windowWidth)) + "px " + evt.pageY + "px;";
            tooltips.setAttribute("style", style);
            if (toolTipsBg.length) tooltips.style.setProperty("--tooltips-bg", toolTipsBg);
            if (toolTipsColor.length) tooltips.style.setProperty("--tooltips-color", toolTipsColor);
            tail.setAttribute("style", "translate: " + (right - windowWidth) + "px -.55em;");
            return;
        }

        let style = "translate: " + (evt.pageX - (width / 2)) + "px " + evt.pageY + "px;";
        tooltips.setAttribute("style", style);
        if (toolTipsBg.length) tooltips.style.setProperty("--tooltips-bg", toolTipsBg);
        if (toolTipsColor.length) tooltips.style.setProperty("--tooltips-color", toolTipsColor);
        tail.setAttribute("style", "translate: 0 -.55em");
    }
}