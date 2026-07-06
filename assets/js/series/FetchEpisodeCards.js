let self = null;

export class FetchEpisodeCards {

    constructor(toolTips) {
        self = this;
        this.toolTips = toolTips;
    }

    init(targetId = -1, simpleUpdate = false, episodeActions = null) {
        const episodeCardDivs = document.querySelectorAll('.episode__cards');
        this.load(episodeCardDivs, 0, episodeCardDivs.length, targetId, simpleUpdate, episodeActions);
    }

    load(cards, index, length, targetId, simpleUpdate, episodeActions = null) {
        if (!length) return;
        const episodeCardsDiv = cards.item(index);
        const scrollX = episodeCardsDiv.scrollLeft;
        const id = episodeCardsDiv.getAttribute('data-id');
        const tmdbId = episodeCardsDiv.getAttribute('data-tmdb-id');
        const seasonNumber = episodeCardsDiv.getAttribute('data-season-number');
        const seriesSlug = episodeCardsDiv.getAttribute('data-series-slug');
        fetch('/api/series/season/episode/stills/' + id + '/' + tmdbId + '/' + seasonNumber + '/' + seriesSlug, {method: 'GET'})
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                const newEpisodeCardsDiv = document.createElement('div');
                newEpisodeCardsDiv.classList.add('episode__cards');
                newEpisodeCardsDiv.setAttribute('data-id', id);
                newEpisodeCardsDiv.setAttribute('data-tmdb-id', tmdbId);
                newEpisodeCardsDiv.setAttribute('data-season-number', seasonNumber);
                newEpisodeCardsDiv.setAttribute('data-series-slug', seriesSlug);
                newEpisodeCardsDiv.innerHTML = data['episodeCards'];
                if (simpleUpdate === false) newEpisodeCardsDiv.style.opacity = "0";
                episodeCardsDiv.replaceWith(newEpisodeCardsDiv);
                newEpisodeCardsDiv.scrollLeft = scrollX;
                self.toolTips.init(newEpisodeCardsDiv);
                if (targetId !== -1) {
                    const targetEpisodeCard = newEpisodeCardsDiv.querySelector(`[data-episode-id="${targetId}"]`);
                    if (targetEpisodeCard) {
                        setTimeout(function () {
                            targetEpisodeCard.classList.add('this-is-my-page');
                            const containerWidth = newEpisodeCardsDiv.clientWidth;
                            const cardLeft = targetEpisodeCard.offsetLeft;
                            const cardWidth = targetEpisodeCard.offsetWidth;
                            const nextScrollLeft = Math.max(0, cardLeft - (containerWidth - cardWidth) / 2);

                            newEpisodeCardsDiv.scrollTo({
                                left: nextScrollLeft,
                                behavior: 'auto'
                            });
                        }, 0);
                    }
                }

                self.initVoteBlock(newEpisodeCardsDiv.querySelectorAll('.episode-vote-block'), episodeActions);
                episodeActions.initEpisodeCards();

                if (simpleUpdate) return;
                setTimeout(function () {
                    newEpisodeCardsDiv.style.opacity = "1";
                }, 100);
                index++;
                if (index < length) self.load(cards, index, length, targetId, simpleUpdate, episodeActions);
            })
            .catch(err => console.log(err));
    }

    initVoteBlock(episodeVoteBlocks, episodeActions = null) {
        if (!episodeActions) return;
        episodeVoteBlocks.forEach(block => {
            const episodeId = block.getAttribute('data-id');
            const selectVote = block.querySelector('select');
            selectVote.addEventListener('change', () => {
                const selectVoteDiv = document.querySelector('header .user-episode .select-vote[data-ue-id="' + episodeId + '"]');
                episodeActions.saveVote(episodeId, selectVote.value, selectVoteDiv);
                // const episodeVoteValue = block.closest('.episode-vote').querySelector('.episode-vote-value');
                // episodeVoteValue.textContent = selectVote.value;
            });
        });
    }
}
