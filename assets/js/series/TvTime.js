import JSConfetti from "js-confetti";

let self;

export class TvTime {
    constructor(toolsTips) {
        self = this;
        this.lastId = 0;
        this.toolsTips = toolsTips;

        this.checkForLastId = this.checkForLastId.bind(this);

        this.init();
    }

    init() {
        this.lastId = parseInt(document.querySelector('.series-tv-time').dataset.last);

        const displayList = document.querySelector('.series-tv-time header .display-list');
        const displayGrid = document.querySelector('.series-tv-time header .display-grid');

        displayList?.addEventListener('click', () => {
            const wrapper = document.querySelector('.series-tv-time .series-group .wrapper');
            wrapper.classList.add('list');
            self.saveLayout(1);
        });
        displayGrid?.addEventListener('click', () => {
            const wrapper = document.querySelector('.series-tv-time .series-group .wrapper');
            wrapper.classList.remove('list');
            self.saveLayout(0);
        });

       this.initAddEpisodes();
       this.initVotes();
       this.initCopyWatchLinks();

        document.addEventListener("visibilitychange", () => {
            if (document.visibilityState === 'visible') {
                self.checkForLastId();
            }
        })
    }

    initAddEpisodes() {
        const addBadges = document.querySelectorAll('.series-tv-time .series-group .add-badge');
        addBadges.forEach(badge => {
            badge.addEventListener('click', (e) => {
                e.preventDefault();
                this.addEpisode(badge);
            })
        });
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
                    self.toolsTips.init(tvTimeDiv.querySelector('.last-episode-votes'));
                }
                self.initAddEpisodes();
                self.initVotes();
                self.initCopyWatchLinks();
                self.toolsTips.init(document.querySelector('.series-tv-time .wrapper'));
            })
            .catch((error) => {
                console.error('Error:', error);
            });
    }

    initCopyWatchLinks() {
        const watchLinks = document.querySelectorAll('.series-tv-time .watch-link');
        watchLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                navigator.clipboard.writeText(link.dataset.link).then(() => {
                    self.copied(link);
                });
            });
        });
    }

    copied(element) {
        const elRectBound = element.getBoundingClientRect();
        const copiedDiv = document.createElement('div');
        copiedDiv.classList.add('copied');
        copiedDiv.innerText = 'Copied!';
        copiedDiv.style.left = `${elRectBound.left}px`;
        copiedDiv.style.top = elRectBound.top - 80 + 'px';
        copiedDiv.classList.add('show');
        copiedDiv.classList.add('active');
        document.querySelector('.series-tv-time').appendChild(copiedDiv);
        setTimeout(() => {
            copiedDiv.remove();
        }, 1000);
    }

    initVotes() {
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
                    this.addVote(button.dataset.id, parseInt(voteDiv.dataset.vote), parseInt(button.dataset.isLastEpisode));
                }
            });
        });
    }

    addVote(id, vote, isLastEpisode) {
        console.log(id, vote, isLastEpisode);
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
                if (seriesCard) { // Pour les multiples épisodes, la carte n'apparaitra qu'après leurs lectures
                    const seriesCardVoteDiv = seriesCard.querySelector('.vote');
                    seriesCardVoteDiv.innerHTML = vote;
                }
                if (isLastEpisode > 0) {
                    const jsConfetti = new JSConfetti();
                    jsConfetti.addConfetti({
                        confettiNumber: 500,
                        confettiColors: [
                            'hsl(28deg 100% 48%)',
                            'hsl(34deg 100% 50%)',
                            'hsl(41deg 100% 50%)',
                            'hsl(48deg 100% 50%)',
                            'hsl(55deg 100% 50%)',
                            'hsl(55deg 99% 66%)',
                            'hsl(56deg 98% 75%)',
                            'hsl(56deg 98% 83%)',
                            'hsl(58deg 100% 90%)',
                            'hsl(58deg 100% 93%)',
                            'hsl(58deg 100% 95%)',
                            'hsl(57deg 100% 98%)',
                            'hsl(0deg 0% 100%)',
                        ],
                    }).then(() => {
                        console.log('Vote for a finale!')
                    });
                }
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

    saveLayout(layout) {
        console.log(layout);
        fetch('/api/tv/time/layout', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                layout: layout
            })
        })
            .then((response) => response.json())
            .then((data) => {
                console.log(data);
            })
            .catch((error) => {
                console.error('Error:', error);
            });
    }
}
