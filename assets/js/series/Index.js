import {FlashMessage} from "FlashMessage";

/**
 *  @typedef Globs
 * @type {Object}
 * @property {Array} tmdbIds
 * @property {String} app_series_tmdb_check
 */

export class Index {
    constructor() {
        this.init = this.init.bind(this);
        this.startDate = new Date();
        this.flashMessage = new FlashMessage();
    }

    init(globs) {
        console.log("Index.js loaded");
        console.log(globs)
        this.seriesId = globs.tmdbIds;
        this.app_series_tmdb_check = globs.app_series_tmdb_check;
        console.log(this.seriesId);
        console.log(this.app_series_tmdb_check);

        setInterval(() => {
            const now = new Date();
            console.log("Index.js has been running for " + ((now - this.startDate) / 60000).toFixed(0) + " minutes");
            if (now.getDate() !== this.startDate.getDate()) {
                location.reload();
            }
        }, 1000 * 60 * 10); // 10 minutes
        // Si la fenêtre redevient active et si la date a changé, on recharge la page
        document.addEventListener("visibilitychange", () => {
            if (document.visibilityState === "visible" && new Date().getDate() !== this.startDate.getDate()) {
                location.reload();
            }
        });

        fetch(this.app_series_tmdb_check, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify({
                tmdbIds: this.seriesId,
            }),
        }).then((response) => {
            if (response.ok) {
                return response.json();
            }
            throw new Error("Network response was not ok.");
        }).then((data) => {
            console.log(data);
            const updates = data['updates'];
            const checkCount = data['dbSeriesCount'];
            const tmdbCalls = data['tmdbCalls'];
            this.flashMessage.add('success', 'Check count: ' + tmdbCalls + ' / ' + checkCount);
            updates.forEach((series) => {
                const updates = series['updates'];
                if (updates.length > 0) {
                    // On crée un nouveau flash message
                    console.log('Adding flash message for ', series['name']);
                    this.flashMessage.add('update', {
                        name: series['name'],
                        localized_name: series['localized_name'],
                        poster_path: series['poster_path'],
                        content: updates.map((update) => update).join('<br>'),
                    });
                }
            });
        }).catch((error) => {
            console.error("Fetch error:", error);
        });
    }
}