let self;

export class TvTime {
    constructor() {
        self = this;
        this.lastId = 0;

        this.checkForLastId = this.checkForLastId.bind(this);

        this.init();
    }

    init() {
        this.lastId = parseInt(document.querySelector('.series-tv-time').dataset.last);

        const wrapper = document.querySelector('.series-tv-time .series-group .wrapper');
        const displayList = document.querySelector('.series-tv-time header .display-list');
        const displayGrid = document.querySelector('.series-tv-time header .display-grid');

        displayList?.addEventListener('click', () => {
            wrapper.classList.add('list');
        });
        displayGrid?.addEventListener('click', () => {
            wrapper.classList.remove('list');
        });

        const addBadges = document.querySelectorAll('.series-tv-time .series-group .add-badge');
        addBadges.forEach(badge => {
            badge.addEventListener('click', (e) => {
                e.preventDefault();
                this.addEpisode(badge);
            })
        });

        document.addEventListener("visibilitychange", () => {
            if (document.visibilityState === 'visible') {
                self.checkForLastId();
            }
        })
    }

    addEpisode(badge) {
        const seriesId = badge.dataset.seriesId;
        const tmdbId = badge.dataset.tmdbId;
        const episodeId = badge.dataset.episodeId;
        const userEpisodeId = badge.dataset.userEpisodeId;
        const seasonNumber = badge.dataset.seasonNumber;
        const episodeNumber = badge.dataset.episodeNumber;
        const lastEpisode = badge.dataset.lastEpisode;

        fetch('/api/episode/add/' + episodeId, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                episodeNumber: episodeNumber,
                isTvTimePage: true,
                lastEpisode: lastEpisode,
                seasonNumber: seasonNumber,
                seriesId: seriesId,
                showId: tmdbId,
                timezone: 'Europe/Paris',
                userEpisodeId: userEpisodeId,
            })
        })
        .then(response => response.json())
        .then(data => {
            console.log(data);
            self.checkForLastId();
        });
    }

    checkForLastId() {
        fetch('/api/tv/time/check', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                lastId: this.lastId,
            })
        })
            .then(response => response.json())
            .then(data => {
                console.log(data);
                if (data['new_episode'] === false) {
                    return;
                }
                self.lastId = data['lastWatchedEpisodeId'];
                document.querySelector('.series-tv-time').dataset.last = self.lastId;
                const wrapper = document.querySelector('.series-tv-time .series-group .wrapper');
                setTimeout(() => {
                    const div = document.createElement('div');
                    div.innerHTML = data['view'];
                    const newWrapper = div.querySelector('.wrapper');
                    wrapper.replaceWith(newWrapper);
                }, 10);
            })
            .catch((error) => {
                console.error('Error:', error);
            });
    }
}
