import {FlashMessage} from "FlashMessage";
import {UserList} from "UserList";

/**
 *  @typedef Globs
 * @type {Object}
 * @property {Array} tmdbIds
 * @property {String} app_series_tmdb_check
 */

export class Index {
    constructor() {
        this.init = this.init.bind(this);

        this.flashMessage = new FlashMessage();
        this.lang = document.querySelector('html').getAttribute('lang');
        this.translations = {
            'fr': {'more': 'et %d de plus', 'update': 'Mise à jour', 'success': 'Succès', 'check_count': 'Vérifications: %d / %d'},
            'en': {'more': 'and %d more', 'update': 'Update', 'success': 'Success', 'check_count': 'Checks: %d / %d'}
        };
    }

    init(globs, menu) {
        console.log("Index.js loaded");

        this.seriesId = globs.tmdbIds;
        this.app_series_tmdb_check = globs.app_series_tmdb_check;
        this.menu = menu;
        const seriesSearchBlockDiv = document.querySelector('.series-search-block');
        if (seriesSearchBlockDiv) {
            const seriesSearchInput = document.getElementById('series-search');
            seriesSearchInput.focus();
            seriesSearchInput.addEventListener("input", this.menu.searchFetch);
            seriesSearchInput.addEventListener("keydown", this.menu.searchMenuNavigate);
        }

        new UserList(this.flashMessage);

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
            // this.flashMessage.add('success', 'Check count: ' + tmdbCalls + ' / ' + checkCount);
            this.flashMessage.add('success', this.translations[this.lang]['check_count'].replace('%d', tmdbCalls).replace('%d', checkCount));
            updates.forEach((series) => {
                const updates = series['updates'];
                if (updates.length > 0) {
                    // On crée un nouveau flash message
                    console.log('Adding flash message for ', series['name']);
                    let content;
                    if (updates.length === 1) {
                        content = updates[0];
                    } else {
                        // content = updates[0] + ' and ' + (updates.length - 1) + ' more';
                        content = updates[0] + ' ' + this.translations[this.lang]['more'].replace('%d', updates.length - 1) + '<ul>';
                        for (let i = 1; i < updates.length; i++) {
                            content += '<li>' + updates[i] + '</li>';
                        }
                        content += '</ul>';
                    }
                    this.flashMessage.add('update', {
                        name: series['name'],
                        localized_name: series['localized_name'],
                        poster_path: series['poster_path'],
                        content: content,
                    });
                }
            });
        }).catch((error) => {
            console.error("Fetch error:", error);
        });
    }
}