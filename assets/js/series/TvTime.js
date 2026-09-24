import JSConfetti from "js-confetti";

let self;

export class TvTime {
    constructor(toolsTips) {
        self = this;
        const globs = JSON.parse(document.querySelector("#global-data").textContent);
        this.tab = globs.tab;
        this.sub = globs.sub;
        this.lastId = 0;
        this.toolsTips = toolsTips;

        this.getEpisodes = this.getEpisodes.bind(this);

        this.init();
    }

    init() {
        this.lastId = parseInt(document.querySelector('.series-tv-time').dataset.last);

        this.changeTab(this.tab, this.sub, false);

        const seriesTabNameDiv = document.querySelector('.series-tv-time header .series-tab-name');
        const moviesTabNameDiv = document.querySelector('.series-tv-time header .movies-tab-name');
        const comingTabNameDiv = document.querySelector('.series-tv-time header .coming-tab-name');

        seriesTabNameDiv?.addEventListener('click', () => {
            this.changeTab(0);
        });
        moviesTabNameDiv?.addEventListener('click', () => {
            this.changeTab(1);
        });
        comingTabNameDiv?.addEventListener('click', () => {
            this.changeTab(2);
        });

        const filterSeriesInput = document.querySelector('#filter-series-input');
        filterSeriesInput?.addEventListener('input', () => {
            this.filterSeries(filterSeriesInput.value.toLowerCase());
        });
        const sortDivs = document.querySelectorAll('.series-tv-time header .sort-by');
        const sortR = document.querySelector("#sort-by-remaining-days");
        sortR.addEventListener('click', () => {
            if (sortR.classList.contains('active')) return;
            self.getEpisodes('0');
            sortDivs.forEach((sortDiv) => {
                sortDiv.classList.remove('active');
            });
            sortR.classList.add('active');
        });
        const sortL = document.querySelector("#sort-by-last-watch-at");
        sortL.addEventListener('click', () => {
            if (sortL.classList.contains('active')) return;
            self.getEpisodes('1');
            sortDivs.forEach((sortDiv) => {
                sortDiv.classList.remove('active');
            });
            sortL.classList.add('active');
        });
        const sortA = document.querySelector("#sort-by-air-date");
        sortA.addEventListener('click', () => {
            if (sortA.classList.contains('active')) return;
            self.getEpisodes('2');
            sortDivs.forEach((sortDiv) => {
                sortDiv.classList.remove('active');
            });
            sortA.classList.add('active');
        });

        const displayList = document.querySelector('.series-tv-time header .display-list');
        const displayGrid = document.querySelector('.series-tv-time header .display-grid');

        displayList?.addEventListener('click', () => {
            const wrapper = document.querySelector('.series-tv-time .active .wrapper');
            wrapper.classList.add('list');
            self.saveLayout(1);
        });
        displayGrid?.addEventListener('click', () => {
            const wrapper = document.querySelector('.series-tv-time .active .wrapper');
            wrapper.classList.remove('list');
            self.saveLayout(0);
        });

       this.initAddEpisodes();
       this.initVotes();
       this.initCopyWatchLinks();

        document.addEventListener("visibilitychange", () => {
            if (document.visibilityState === 'visible') {
                self.getEpisodes();
            }
        })
    }

    changeTab(index, sub = 0, save = true) {
        if (save) self.saveTab(index);

        const seriesTvTimeDiv = document.querySelector('.series-tv-time');
        const seriesTabNameDiv = seriesTvTimeDiv.querySelector('header .series-tab-name');
        const moviesTabNameDiv = seriesTvTimeDiv.querySelector('header .movies-tab-name');
        const comingTabNameDiv = seriesTvTimeDiv.querySelector('header .coming-tab-name');
        const seriesTabHeaderDiv = seriesTvTimeDiv.querySelector('header .series-tab-header');
        const moviesTabHeaderDiv = seriesTvTimeDiv.querySelector('header .movies-tab-header');
        const comingTabHeaderDiv = seriesTvTimeDiv.querySelector('header .coming-tab-header');

        seriesTabNameDiv.classList.remove('active');
        moviesTabNameDiv.classList.remove('active');
        comingTabNameDiv.classList.remove('active');

        seriesTabHeaderDiv.classList.remove('active');
        moviesTabHeaderDiv.classList.remove('active');
        comingTabHeaderDiv.classList.remove('active');

        const tabHeaderDiv = seriesTvTimeDiv.querySelector(`header .tab-headers div[data-tab-index="${index}"]`);
        tabHeaderDiv.classList.add('active');

        const tabNameDiv = seriesTvTimeDiv.querySelector(`header .tab-names div[data-tab-index="${index}"]`);
        tabNameDiv.classList.add('active');

        seriesTvTimeDiv.style.setProperty('--active-tab', index);

        const h1Spans = seriesTvTimeDiv.querySelectorAll('h1 span');
        h1Spans.forEach(span => span.classList.remove("active"));
        h1Spans[index].classList.add("active");
    }

    initAddEpisodes() {
        const addBadges = document.querySelectorAll('.series-tv-time .series-tab .add-badge');
        addBadges.forEach(badge => {
            badge.addEventListener('click', (e) => {
                e.preventDefault();
                this.addEpisode(badge);
            })
        });
    }

    filterSeries(needle) {
        const cards = document.querySelectorAll('.series-tv-time .series-tab.active .wrapper .content .card');
        if (needle.length === 0) {
            cards.forEach(card => {
                card.removeAttribute('style');
            });
            return;
        }
        cards.forEach(card => {
            const name = card.querySelector('.name').textContent.toLowerCase();
            if (name.includes(needle)) {
                card.removeAttribute('style');
            } else {
                card.style.display = 'none';
            }
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
                self.getEpisodes();
            });
    }

    getEpisodes(sort = null) {
        fetch('/api/tv/time/check', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                sort: sort
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
                const wrapper = document.querySelector('.series-tv-time .series-tab .wrapper');

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

    saveTab(tabIndex) {
        console.log(tabIndex);
        fetch('/api/tv/time/tab', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                tabIndex: tabIndex
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
