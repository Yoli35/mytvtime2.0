import {ToolTips} from "ToolTips";

let gThis;

export class AdminSeriesUpdates {
    constructor() {
        gThis = this;
        this.toolTip = new ToolTips();
        const globs = document.querySelector("#globs");
        this.globs = JSON.parse(globs.textContent);
        this.ids = this.globs['ids'];
        this.api_series_update_series = this.globs['api_series_update_series'];
        this.init();
    }

    init() {
        console.log(gThis.ids);
        const updatesButton = document.querySelector("#admin-series-updates");

        updatesButton.addEventListener("click", () => {
            gThis.newLine([{'className': 'header', 'label': 'Starting updates...'}]);
            gThis.newLine([]);

            gThis.startIndex = 0;
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
            console.log({data});
            if (data.status === 'success') {
                data['results'].forEach(item => {
                    gThis.newLine([
                        {'className': item['check'] ? 'checked' : 'not-checked', 'label': item['check'] ? '✓' : 'x'},
                        {'className': 'id', 'label': item['id']},
                        {'className': 'name', 'label': item['name']},
                        {'className': 'localized-name', 'label': item['localizedName']},
                        {'className': 'date', 'label': item['lastUpdate']}
                    ]);
                });
                if (data['results'].length + gThis.startIndex >= count) {
                    gThis.newLine([]);
                    gThis.newLine([{'className': 'footer', 'label': 'Updates completed...'}]);
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
        lineDiv.scrollIntoView({ behavior: 'smooth', block: 'end' });
    }
}