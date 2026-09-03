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

        const lastEpisodeVoteDivs = document.querySelectorAll('.series-tv-time .last-episode-vote');
        lastEpisodeVoteDivs.forEach(div => {
            const yourVoteDiv = div.querySelector('.your-vote');
            const voteDiv = div.querySelector('.vote');
            const stars = voteDiv.querySelectorAll('.vote-star');
            stars.forEach(star => {
                star.addEventListener('click', (e) => {
                    e.preventDefault();
                    const voteValue = parseInt(star.dataset.vote);
                    const starDivs = voteDiv.querySelectorAll('.vote-star');
                    starDivs.forEach((starDiv, index) => {
                        if (index < voteValue) {
                            starDiv.classList.add('active');
                        } else {
                            starDiv.classList.remove('active');
                        }
                    });
                    voteDiv.dataset.vote = voteValue.toString();
                    yourVoteDiv.textContent = voteValue.toString();
                });
            });
            const button = div.querySelector('.submit-vote button');
            button.addEventListener('click', (e) => {
                e.preventDefault();
                if (voteDiv.dataset.vote) {
                    this.addVote(button.dataset.id, parseInt(voteDiv.dataset.vote));
                }
            });
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
                lastId: this.lastId
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
                    if (data['noVoteView']) {
                        const tvTimeDiv = document.querySelector('.series-tv-time');
                        const lastEpisodeVotesDiv = tvTimeDiv.querySelector('.last-episode-votes');
                        const div = document.createElement('div');
                        div.innerHTML = data['noVoteView'];
                        if (lastEpisodeVotesDiv) {
                            lastEpisodeVotesDiv.replaceWith(div.querySelector('.last-episode-votes'));
                        } else {
                            tvTimeDiv.appendChild(div.querySelector('.last-episode-votes'));
                        }
                    }
                }, 10);
            })
            .catch((error) => {
                console.error('Error:', error);
            });
    }

    addVote(id, vote) {
        console.log(id, vote);
        fetch('/api/episode/vote/' + id, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                vote: vote
            })
        })
            .then((response) => response.json())
            .then((data) => {
                console.log(data);
                const seriesCard = document.querySelector('.card[data-prev-id="' + id + '"]');
                const seriesCardVoteDiv = seriesCard.querySelector('.vote');
                seriesCardVoteDiv.innerHTML = vote;
                const voteDiv = document.querySelector('.last-episode-vote[data-id="' + id + '"]');
                voteDiv.classList.add('closing');
                setTimeout(() => {
                    voteDiv.remove();
                }, 300);
            })
            .catch((error) => {
                console.error('Error:', error);
            });
    }
}
