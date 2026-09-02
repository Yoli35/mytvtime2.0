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

        document.addEventListener("visibilitychange", () => {
            if (document.visibilityState === 'visible') {
                self.checkForLastId();
            }
        })
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
                wrapper.outerHTML = data['view'];
            })
            .catch((error) => {
                console.error('Error:', error);
            });
    }
}
