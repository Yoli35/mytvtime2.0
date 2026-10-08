import JSConfetti from "js-confetti";

let self;

export class TvTime {
    constructor(toolsTips) {
        self = this;
        const globs = JSON.parse(document.querySelector("#global-data").textContent);
        this.tab = globs.tab;
        this.sub = globs.sub;
        /*this.episodesAvailable = globs.episodesAvailable;*/
        this.remainingSecondes = globs.remainingSecondes;
        this.intervalId = -1;
        this.toolsTips = toolsTips;
        this.dividerStates = new Map();
        this.initializedDividers = new WeakSet();

        this.getEpisodes = this.getEpisodes.bind(this);
        this.initComponents = this.initComponents.bind(this);

        /*console.log(this.episodesAvailable);*/

        this.init();
    }

    init() {
        const filterSeriesInput = document.querySelector('#filter-series-input');
        filterSeriesInput?.addEventListener('input', () => {
            this.filterSeries(filterSeriesInput.value.toLowerCase());
        });

        const specialsInput = document.querySelector("#filter-series-specials");
        specialsInput.addEventListener('click', () => {
            self.saveSpecials(specialsInput.checked ? 1 : 0);
            if (self.tab === 0) {
                self.getEpisodes();
            }
        });

        this.initComponents();
        if (this.tab === 0) {
            self.fixDetailsDivs(document.querySelector('main .series-tab .wrapper'));
            this.getEpisodeNames();
        }

        document.addEventListener("visibilitychange", () => {
            if (document.visibilityState === 'visible') {
                self.getEpisodes();
            }
        });

        this.intervalId = setInterval(() => {
            self.getEpisodes();
        }, this.remainingSecondes * 1000);
        this.startingTimestamp = Date.now();
        this.displayRemainingTime();
    }

    initComponents() {
        this.initTab();
        this.initWeekCount();
        this.initSortSwitch();
        this.initLayoutSwitch();
        this.initAddEpisodes();
        this.initVotes();
        this.initCopyWatchLinks();
        this.initDividers();
    }

    initTab() {
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
    }

    /*initNameFilter() {}*/

    initLayoutSwitch() {
        const displayList = document.querySelector('.series-tv-time header .display-list');
        const displayGrid = document.querySelector('.series-tv-time header .display-grid');

        displayList?.addEventListener('click', () => {
            const wrapper = document.querySelector('.series-tv-time .active .wrapper');
            wrapper.classList.add('list');
            self.saveLayout(1);
            self.fixDetailsDivs(wrapper);
            /*self.resetDividers();*/
        });
        displayGrid?.addEventListener('click', () => {
            const wrapper = document.querySelector('.series-tv-time .active .wrapper');
            wrapper.classList.remove('list');
            self.saveLayout(0);
            self.fixDetailsDivs(wrapper);
            /*self.resetDividers();*/
        });
    }

    initSortSwitch() {
        const sortDivs = document.querySelectorAll('.series-tv-time header .sort-by');
        const sortR = document.querySelector("#series-sort-menu-remaining-days");
        sortR.addEventListener('click', () => {
            if (sortR.classList.contains('active')) return;
            self.getEpisodes('0');
            sortDivs.forEach((sortDiv) => {
                sortDiv.classList.remove('active');
            });
            sortR.classList.add('active');
        });
        const sortL = document.querySelector("#series-sort-menu-last-watch-at");
        sortL.addEventListener('click', () => {
            if (sortL.classList.contains('active')) return;
            self.getEpisodes('1');
            sortDivs.forEach((sortDiv) => {
                sortDiv.classList.remove('active');
            });
            sortL.classList.add('active');
        });
        const sortA = document.querySelector("#series-sort-menu-air-date");
        sortA.addEventListener('click', () => {
            if (sortA.classList.contains('active')) return;
            self.getEpisodes('2');
            sortDivs.forEach((sortDiv) => {
                sortDiv.classList.remove('active');
            });
            sortA.classList.add('active');
        });
    }

    initWeekCount() {
        const weekInput = document.querySelector("#filter-series-specials-week");
        weekInput.addEventListener('keyup', (e) => {
            if (e.key === 'Enter') {
                self.saveWeek(weekInput.value);
                if (self.tab === 0) {
                    self.getEpisodes();
                }
            }
        });
    }

    initDividers() {
        const dividerDivs = document.querySelectorAll('.series-tv-time main .content-tab .wrapper .divider');
        dividerDivs.forEach(dividerDiv => {
            const contentDiv = dividerDiv.nextElementSibling;
            if (!contentDiv?.classList.contains('content') || this.initializedDividers.has(dividerDiv)) return;

            const tab = dividerDiv.closest('.content-tab').dataset.tabIndex;
            const storageKey = dividerDiv.dataset.content
                ? `mytvtime_2_divider_${tab}_${dividerDiv.dataset.content}`
                : null;
            if (storageKey && !this.dividerStates.has(storageKey)) {
                try {
                    this.dividerStates.set(storageKey, localStorage.getItem(storageKey) === 'folded');
                } catch {
                    // Le stockage peut être indisponible : conserver l'état en mémoire.
                    this.dividerStates.set(storageKey, contentDiv.classList.contains('folded'));
                }
            }
            if (storageKey) {
                contentDiv.classList.toggle('folded', this.dividerStates.get(storageKey));
            }
            this.initializedDividers.add(dividerDiv);

            dividerDiv.addEventListener('click', () => {
                const folded = !contentDiv.classList.contains('folded');
                if (folded) {
                    contentDiv.style.height = `${contentDiv.getBoundingClientRect().height}px`;
                    // Appliquer la hauteur avant de démarrer la transition vers zéro.
                    // Force le navigateur à recalculer immédiatement la mise en page du bloc avant de poursuivre
                    void contentDiv.offsetHeight;
                    contentDiv.classList.add('folded');
                    setTimeout(() => {
                        if (contentDiv.isConnected && contentDiv.classList.contains('folded')) {
                            contentDiv.removeAttribute('style');
                        }
                    }, 500);

                } else {
                    contentDiv.style.setProperty('height', `${contentDiv.scrollHeight}px`);
                    contentDiv.classList.remove('folded');
                    setTimeout(() => {
                        if (contentDiv.isConnected && !contentDiv.classList.contains('folded')) {
                            contentDiv.removeAttribute('style');
                            contentDiv.scrollIntoView({behavior: 'smooth'});
                        }
                    }, 500);
                }
                if (storageKey) {
                    this.dividerStates.set(storageKey, folded);
                    try {
                        localStorage.setItem(storageKey, folded ? 'folded' : 'expanded');
                    } catch {
                        // L'état en mémoire reste disponible lors des rafraîchissements AJAX.
                    }
                }
            });
        });
    }

    /*resetDividers() {
        const dividerDivs = document.querySelectorAll('.series-tv-time .active .divider');
        dividerDivs.forEach(dividerDiv => {
            dividerDiv.removeAttribute('style');
        });
    }*/

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
                const wrapper = document.querySelector('.series-tv-time .series-tab .wrapper');

                const div = document.createElement('div');
                div.innerHTML = data['view'];
                const newWrapper = div.querySelector('.wrapper');
                wrapper.replaceWith(newWrapper);
                self.fixDetailsDivs(document.querySelector('.series-tv-time .series-tab .wrapper'));
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
                self.initComponents();
                self.toolsTips.init(document.querySelector('.series-tv-time .wrapper'));
                /*self.episodesAvailable = data['data']['series']['episodesAvailable'];*/
                self.getEpisodeNames();
                self.resetReload(data['data']['remainingSecondes']);
            })
            .catch((error) => {
                console.error('Error:', error);
            });
    }

    fixDetailsDivs(wrapper) {
        const infosDivs = wrapper.querySelectorAll('.infos');
        const isListLayout = wrapper.classList.contains('list');
        if (isListLayout) {
            infosDivs.forEach(infosDiv => {
                const watchLinksDid = infosDiv.querySelector('.watch-links');
                if (watchLinksDid) {
                    const voteDiv = infosDiv.querySelector('.vote');
                    const extraWidth = watchLinksDid.getBoundingClientRect().width + 72 + (voteDiv ? 72 : 0);
                    const detailsDiv = infosDiv.querySelector('.details');
                    detailsDiv.style.width = 'calc(100% - ' + extraWidth + 'px)';
                }
            });
        } else {
            infosDivs.forEach(infosDiv => {
                const detailsDiv = infosDiv.querySelector('.details');
                detailsDiv.style.width = '100%';
            });
        }
    }

    resetReload(remainingSecondes) {
        this.remainingSecondes = remainingSecondes;
        this.startingTimestamp = Date.now();
        clearInterval(this.intervalId);
        this.intervalId = setInterval(() => {
            this.getEpisodes();
        }, this.remainingSecondes * 1000);
    }

    displayRemainingTime() {
        const remainingTimeElement = document.querySelector('.series-tv-time .remaining-time');
        let n = 0;
        setInterval(() => {
            const currentTimestamp = Date.now();
            const elapsedTime = currentTimestamp - this.startingTimestamp;
            // Display format: hh:mm::ss
            const remainingSeconds = this.remainingSecondes - Math.floor(elapsedTime / 1000);
            const hours = Math.floor(remainingSeconds / 3600);
            const minutes = Math.floor((remainingSeconds % 3600) / 60);
            const seconds = remainingSeconds % 60;
            if (n % 2)
                remainingTimeElement.textContent = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            else
                remainingTimeElement.textContent = `${hours.toString().padStart(2, '0')} ${minutes.toString().padStart(2, '0')} ${seconds.toString().padStart(2, '0')}`;
            n++;
        }, 1000);
    }

    getEpisodeNames() {
        const firstContentDiv = document.querySelector('.content');
        if (!firstContentDiv)
            return;
        const cards = firstContentDiv.querySelectorAll('.card');
        if (!cards)
            return;
        const refArray = Array.from(cards).map(card => card.getAttribute('data-ref'));
        fetch('/api/tv/time/episode/check', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                episodeRefs: refArray
            })
        })
            .then(response => response.json())
            .then(data => {
                /** @type {Array<{episode_id: number, content: {status: string, name?: string, runtime?: number|null}}>} */
                const updates = data.updates;
                console.log(updates);
                let updatesCount = 0;
                updates.forEach(update => {
                    if (update.content.status !== 'success') {
                        return;
                    }
                    updatesCount++;
                    const episodeCard = document.querySelector(`.series-tv-time .card[data-episode-id="${update.episode_id}"]`);
                    if (episodeCard) {
                        const esnNameDiv = episodeCard.querySelector('.esn-name');
                        if (esnNameDiv) {
                            const nameDiv = esnNameDiv.querySelector('.name');
                            const durationDiv = esnNameDiv.querySelector('.duration');
                            nameDiv.innerText = update.content.name;
                            durationDiv.innerText = update.content.runtime ? '(' + update.content.runtime + ' minutes)' : '';
                            esnNameDiv.classList.add('updated');
                            esnNameDiv.setAttribute('data-title', update.content.name);
                        }
                    }
                });
                if (updatesCount > 0) {
                    // Juste le premier bloc 'content'
                    self.toolsTips.init(document.querySelector('.series-tv-time .wrapper .content'));
                }
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

    saveSpecials(specials) {
        console.log(specials);
        fetch('/api/tv/time/specials', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                specials: specials
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

    saveWeek(week) {
        console.log(week);
        fetch('/api/tv/time/week', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                week: week
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
