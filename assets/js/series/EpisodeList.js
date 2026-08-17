import {EpisodeActions} from "EpisodeActions";

let self;

export class EpisodeList {
    constructor(fetchEpisodeCards, jsonGlobsObject, flashMessage, toolTips, menu) {
        self = this;
        this.fetchEpisodeCards = fetchEpisodeCards;
        this.jsonGlobsObject = jsonGlobsObject;
        this.flashMessage = flashMessage;
        this.toolTips = toolTips;
        this.menu = menu;
        this.episodeListDivs = document.querySelectorAll('.view-episode-list');
        this.init();
    }

    init() {
        this.episodeListDivs.forEach(episodeListDiv => {
            episodeListDiv.addEventListener('click', () => {
                console.log(episodeListDiv);
                const seasonId = episodeListDiv.dataset.seasonId;
                this.getEpisodes(seasonId);
            });
        });
    }

    getEpisodes(seasonId) {
        fetch("/api/episode/list/get", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify({seasonId: seasonId})
        })
            .then(response => response.json())
            .then(data => {
                console.log(data);
                const body = document.querySelector('body');
                const view = data['view'];
                const episodeListDiv = document.createElement('div');
                episodeListDiv.innerHTML = view;
                const newDialog = episodeListDiv.querySelector('dialog');
                newDialog.addEventListener('close', () => newDialog.remove());
                const close = newDialog.querySelector('.close');
                close.addEventListener('click', (event) => {
                    event.currentTarget.closest('dialog').close();
                });
                const providerSelect = newDialog.querySelector('select#watch-link-select');
                const episodeTogglers = newDialog.querySelectorAll('.ue');
                episodeTogglers.forEach(toggler => {
                    toggler.addEventListener('click', () => {
                        self.toggleWatched(toggler, episodeTogglers, parseInt(providerSelect.value));
                    });
                });
                body.appendChild(newDialog);
                newDialog.showModal();
            })
            .catch(error => {
                console.error('Error fetching episodes:', error);
            });
    }

    toggleWatched(toggler, episodeTogglers, providerId) {
        const episodeId = parseInt(toggler.dataset.ueId);
        const episodeNumber = parseInt(toggler.dataset.episodeNumber);
        const watched = toggler.classList.contains('watched');
        const togglerData = {
            episodeId: episodeId,
            episodeNumber: episodeNumber,
            watched: watched
        };
        let episodeData = [];
        console.log('Toggling watched status for episode:', episodeId, episodeNumber);
        if (!watched) {
            // Y a-t-il des épisodes non vus avant ?
            const previousEpisodes = Array.from(episodeTogglers).filter(toggler => parseInt(toggler.dataset.episodeNumber) < episodeNumber);
            const unseenEpisodes = previousEpisodes.filter(toggler => !toggler.classList.contains('watched'));
            episodeData = unseenEpisodes.map(toggler => {
                return {
                    'episodeId': parseInt(toggler.dataset.ueId),
                    'episodeNumber': parseInt(toggler.dataset.episodeNumber),
                    'watched': false
                };
            });
            unseenEpisodes.forEach(episode => episode.classList.add('watched'));
            toggler.classList.add('watched');
        } else {
            toggler.classList.remove('watched');
        }
        // on ajoute les données de toggler
        episodeData.push(togglerData);
        console.log('episode data:', episodeData);
        fetch("/api/episode/list/toggle", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify({episodeData: episodeData, providerId: providerId})
        })
            .then(response => response.json())
            .then(data => {
                console.log(data);
                if (data['success']) {
                    /******************************************************************************
                     * Fetch episode stills for each season.                                      *
                     ******************************************************************************/
                    self.fetchEpisodeCards.init(-1, false, new EpisodeActions(self.jsonGlobsObject, self.flashMessage, self.toolTips, self.menu, false));

                }
            })
            .catch(error => console.error(error));
    }
}