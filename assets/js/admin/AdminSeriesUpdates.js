import {ToolTips} from "ToolTips";

let gThis;

export class AdminSeriesUpdates {

    /** @typedef Update
     * @type {Object}
     * @property {string} field
     * @property {string} label
     * @property {string|null} valueBefore
     * @property {string|null} valueAfter
     * @property {string|null} previous
     * @property {string|null} since
     */

    constructor() {
        gThis = this;
        this.toolTip = new ToolTips();
        const globs = document.querySelector("#globs");
        this.globs = JSON.parse(globs.textContent);
        this.ids = this.globs['ids'];
        this.urls = this.globs['urls'];
        this.api_series_update_series = this.globs['api_series_update_series'];
        const svgs = document.querySelector("#svgs");
        this.rightArrow = svgs.querySelector("#right-arrow");
        this.init();
    }

    init() {
        const updatesButton = document.querySelector("#admin-series-updates");
        const goToTopButton = document.querySelector('.go-to-top');
        const goToBottomButton = document.querySelector('.go-to-bottom');

        updatesButton.addEventListener("click", () => {
            const updateDiv1 = gThis.newUpdateDiv("updates header");
            gThis.newLine([{'className': 'header', 'label': 'Starting updates...'}], updateDiv1);
            /*const updateDiv2 = gThis.newUpdateDiv("updates after header");
            gThis.newLine([], updateDiv2);*/
            updatesButton.setAttribute("disabled", "");

            gThis.startIndex = 0;
            gThis.startDate = new Date();
            gThis.updates();
        });

        goToBottomButton.addEventListener("click", () => {
            const updatesWrapperDiv = document.querySelector(".admin__series__updates__wrapper");
            const lastChild = updatesWrapperDiv.lastChild;
            lastChild.scrollIntoView({behavior: 'smooth', block: 'end'});
        });

        goToTopButton.addEventListener("click", () => {
            const updatesWrapperDiv = document.querySelector(".admin__series__updates__wrapper");
            const firstChild = updatesWrapperDiv.firstChild;
            firstChild.scrollIntoView({behavior: 'smooth', block: 'start'});
        })
    }

    updates() {
        const count = gThis.ids.length;
        const limit = 5;
        let arr = gThis.ids.slice(gThis.startIndex, gThis.startIndex + limit);
        const data = {
            ids: arr
        };
        fetch(gThis.api_series_update_series,
            {
                method: 'POST',
                body: JSON.stringify(data)
            }
        ).then(async function (response) {
            const data = await response.json();
            /*console.log({data});*/
            if (data.status === 'success') {
                data['results'].forEach(item => {
                    const updateDiv = gThis.newUpdateDiv(item.id);
                    gThis.newLine([
                            {'className': gThis.kebabCase(item['check']), 'label': item['check']},
                            {'className': 'id', 'label': item['id']},
                            {'className': 'name', 'label': item['name']},
                            {'className': 'localized-name', 'label': item['localizedName']},
                            {'className': 'date', 'label': item['lastUpdate']}
                        ],
                        updateDiv);
                    gThis.displayUpdates(item['updates'], updateDiv);
                });
                if (data['results'].length + gThis.startIndex >= count) {
                    /*const updateDiv1 = gThis.newUpdateDiv("updates before footer");
                    gThis.newLine([], updateDiv1);*/
                    gThis.endDate = new Date();
                    const elapsed = (gThis.endDate.getTime() - gThis.startDate.getTime()) / 1000;
                    const updateDiv2 = gThis.newUpdateDiv("updates footer");
                    gThis.newLine([{'className': 'footer', 'label': 'Updates completed in ' + elapsed + ' seconds.'}], updateDiv2);
                    const updatesButton = document.querySelector("#admin-series-updates");
                    updatesButton.removeAttribute("disabled");
                } else {
                    gThis.startIndex += limit;
                    gThis.updates();
                }
            }
        });
    }

    newUpdateDiv(id) {
        const updatesWrapperDiv = document.querySelector(".admin__series__updates__wrapper");
        const updateDiv = document.createElement('div');
        updateDiv.id = id;
        updateDiv.classList.add('admin__series__update');
        updatesWrapperDiv.appendChild(updateDiv);
        return updateDiv;
    }

    newLine(arr, div) {
        const lineDiv = document.createElement("div");
        lineDiv.classList.add("admin__series__updates__line");
        arr.forEach(item => {
            const itemDiv = document.createElement("div");
            itemDiv.classList.add(item.className);
            itemDiv.appendChild(document.createTextNode(item.label));
            lineDiv.appendChild(itemDiv);
        })
        div.appendChild(lineDiv);
        div.scrollIntoView({behavior: 'instant', block: 'end'});
    }

    displayUpdates(updates, div) {
        if (updates.length === 0) {
            return;
        }
        const posterUrls = gThis.urls['posterUrl'];
        const backdropUrls = gThis.urls['backdropUrl'];
        const detailsDiv = document.createElement('div');
        detailsDiv.classList.add("admin__series__updates__details");

        /** @type Update item */
        updates.forEach((item) => {
            const detailDiv = document.createElement('div');
            detailDiv.classList.add("admin__series__updates__detail");
            const className = gThis.kebabCase(item.field);
            const fieldDiv = document.createElement("div");
            fieldDiv.classList.add("admin__update__field");
            fieldDiv.classList.add(className);
            const beforeDiv = document.createElement("div");
            beforeDiv.classList.add("admin__update__before");
            beforeDiv.classList.add(className);
            const rightArrowSvg = gThis.rightArrow.cloneNode(true);
            rightArrowSvg.removeAttribute("id");
            rightArrowSvg.classList.add("admin__update__arrow");
            const afterDiv = document.createElement("div");
            afterDiv.classList.add("admin__update__after");
            afterDiv.classList.add(className);
            switch (item.field) {
                case 'since':
                    fieldDiv.innerText = item.label;
                    beforeDiv.innerText = item.previous;
                    afterDiv.innerText = item.since;
                    break;
                case 'first_air_date':
                case 'name':
                case 'overview':
                case 'status':
                case 'number':
                case 'number_of_seasons':
                case 'number_of_episodes':
                    fieldDiv.innerText = item.label;
                    beforeDiv.innerText = item.valueBefore;
                    afterDiv.innerText = item.valueAfter;
                    break;
                case 'poster_path':
                    fieldDiv.innerText = item.label;
                    let posterPath = item.valueBefore
                    beforeDiv.classList.add("poster");
                    if (posterPath) {
                        const img = document.createElement("img");
                        img.alt = "poster";
                        img.src = posterUrls['low'] + posterPath;
                        img.srcset = posterUrls['low'] + posterPath + " 185w, " + posterUrls['high'] + posterPath + " 700w";
                        beforeDiv.appendChild(img);
                    } else {
                        beforeDiv.innerText = 'No poster';
                    }

                    posterPath = item.valueAfter;
                    afterDiv.classList.add("poster");
                    if (posterPath) {
                        const img = document.createElement("img");
                        img.alt = "poster";
                        img.src = posterUrls['low'] + posterPath;
                        img.srcset = posterUrls['low'] + posterPath + " 185w, " + posterUrls['high'] + posterPath + " 700w";
                        afterDiv.appendChild(img);
                    } else {
                        afterDiv.innerText = 'No poster';
                    }
                    break;

                case 'backdrop_path':
                    fieldDiv.innerText = item.label;
                    let backdropPath = item.valueBefore
                    beforeDiv.classList.add("backdrop");
                    if (backdropPath) {
                        const img = document.createElement("img");
                        img.alt = "backdrop";
                        img.src = backdropUrls['low'] + backdropPath;
                        img.srcset = backdropUrls['low'] + backdropPath + " 300w, " + backdropUrls['high'] + backdropPath + " 1200w";
                        beforeDiv.appendChild(img);
                    } else {
                        beforeDiv.innerText = 'No poster';
                    }

                    backdropPath = item.valueAfter;
                    afterDiv.classList.add("backdrop");
                    if (backdropPath) {
                        const img = document.createElement("img");
                        img.alt = "backdrop";
                        img.src = backdropUrls['low'] + backdropPath;
                        img.srcset = backdropUrls['low'] + backdropPath + " 300w, " + backdropUrls['high'] + backdropPath + " 1200w";
                        afterDiv.appendChild(img);
                    } else {
                        afterDiv.innerText = 'No backdrop';
                    }
                    break;
            }
            detailDiv.append(fieldDiv);
            detailDiv.appendChild(beforeDiv);
            detailDiv.appendChild(rightArrowSvg);
            detailDiv.appendChild(afterDiv);
            detailsDiv.appendChild(detailDiv);
        });

        div.appendChild(detailsDiv);
        div.scrollIntoView({behavior: 'instant', block: 'end'});
    }

    kebabCase(string) {
        return string
            .replace(/([a-z])([A-Z])/g, "$1-$2")
            .replace(/[\s_]+/g, '-')
            .toLowerCase();
    }
}