import {ToolTips} from "ToolTips";

let gThis;

export class AdminSeriesUpdates {

    /** @typedef Update
     * @type {Object}
     * @property {string} field
     * @property {string} label
     * @property {string|null} valueBefore
     * @property {string|null} valueAfter
     */

    constructor() {
        gThis = this;
        this.toolTip = new ToolTips();
        const globs = document.querySelector("#globs");
        this.globs = JSON.parse(globs.textContent);
        this.ids = this.globs['ids'];
        this.api_series_update_series = this.globs['api_series_update_series'];
        const svgs = document.querySelector("#svgs");
        this.rightArrow = svgs.querySelector("#right-arrow");
        this.init();
    }

    init() {
        /*console.log(gThis.ids);*/
        const updatesButton = document.querySelector("#admin-series-updates");

        updatesButton.addEventListener("click", () => {
            gThis.newLine([{'className': 'header', 'label': 'Starting updates...'}]);
            gThis.newLine([]);

            gThis.startIndex = 0;
            gThis.startDate = new Date();
            gThis.updates();
        });
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
                gThis.posterUrl = data['posterUrl'];
                gThis.backdropUrl = data['backdropUrl'];
                data['results'].forEach(item => {
                    gThis.newLine([
                        {'className': gThis.kebabCase(item['check']), 'label': item['check']},
                        {'className': 'id', 'label': item['id']},
                        {'className': 'name', 'label': item['name']},
                        {'className': 'localized-name', 'label': item['localizedName']},
                        {'className': 'date', 'label': item['lastUpdate']}
                    ]);
                    gThis.displayUpdates(item['updates']);
                });
                if (data['results'].length + gThis.startIndex >= count) {
                    gThis.newLine([]);
                    gThis.endDate = new Date();
                    const elapsed = (gThis.endDate.getTime() - gThis.startDate.getTime()) / 1000;
                    gThis.newLine([{'className': 'footer', 'label': 'Updates completed in ' + elapsed +  ' seconds.'}]);
                } else {
                    gThis.startIndex += limit;
                    gThis.updates();
                }
            }
        });
    }

    newLine(arr) {
        const updatesDiv = document.querySelector(".admin__series__updates");
        const lineDiv = document.createElement("div");
        lineDiv.classList.add("admin__series__updates__line");
        arr.forEach(item => {
            const itemDiv = document.createElement("div");
            itemDiv.classList.add(item.className);
            itemDiv.appendChild(document.createTextNode(item.label));
            lineDiv.appendChild(itemDiv);
        })
        updatesDiv.appendChild(lineDiv);
        lineDiv.scrollIntoView({ behavior: 'instant', block: 'end' });
    }

    displayUpdates(updates) {
        // items :
        //      ['field' => 'name', 'before' => $series->getName(), 'after' => $name];
        //      ['field' => 'overview', 'before' => $series->getOverview(), 'after' => $overview];
        //      ['field' => 'backdrop_path', 'before' => $series->getBackdropPath(), 'after' => $backdropPath];
        //      ['field' => 'poster_path', 'before' => $series->getPosterPath(), 'after' => $posterPath];
        //      ['field' => 'status', 'before' => $series->getStatus(), 'after' => $status];
        //      ['field' => 'number of seasons', 'before' => $series->getNumberOfSeason(), 'after' => $seasonNUmber];
        //      ['field' => 'number of episodes', 'before' => $series->getNumberOfEpisode(), 'after' => $episodeNumber];
        if (updates.length === 0) {
            return;
        }
        const posterUrl = gThis.posterUrl;
        const backdropUrl = gThis.backdropUrl;
        const updatesDiv = document.querySelector(".admin__series__updates");
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
            switch (item['field']) {
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
                        img.src = posterUrl + posterPath;
                        beforeDiv.appendChild(img);
                    } else {
                        beforeDiv.innerText = 'No poster';
                    }

                    posterPath = item.valueAfter;
                    afterDiv.classList.add("poster");
                    if (posterPath) {
                        const img = document.createElement("img");
                        img.src = posterUrl + posterPath;
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
                        img.src = backdropUrl + backdropPath;
                        beforeDiv.appendChild(img);
                    } else {
                        beforeDiv.innerText = 'No poster';
                    }

                    backdropPath = item.valueAfter;
                    afterDiv.classList.add("backdrop");
                    if (backdropPath) {
                        const img = document.createElement("img");
                        img.src = backdropUrl + backdropPath;
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

        updatesDiv.appendChild(detailsDiv);
    }

    kebabCase(string) {
        return string
            .replace(/([a-z])([A-Z])/g, "$1-$2")
            .replace(/[\s_]+/g, '-')
            .toLowerCase();
    }
}