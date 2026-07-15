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
        const width = episodeCardsDiv.clientWidth;
        const id = episodeCardsDiv.getAttribute('data-id');
        const tmdbId = episodeCardsDiv.getAttribute('data-tmdb-id');
        const seasonNumber = episodeCardsDiv.getAttribute('data-season-number');
        const seriesSlug = episodeCardsDiv.getAttribute('data-series-slug');
        fetch('/api/series/season/episode/stills/' + id + '/' + tmdbId + '/' + seasonNumber + '/' + seriesSlug,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    targetId: targetId,
                    simpleUpdate: simpleUpdate
                })
            })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                const newEpisodeCardsDiv = document.createElement('div');
                newEpisodeCardsDiv.classList.add('episode__cards', 'replacing-episodes');
                newEpisodeCardsDiv.setAttribute('data-id', id);
                newEpisodeCardsDiv.setAttribute('data-tmdb-id', tmdbId);
                newEpisodeCardsDiv.setAttribute('data-season-number', seasonNumber);
                newEpisodeCardsDiv.setAttribute('data-series-slug', seriesSlug);
                newEpisodeCardsDiv.innerHTML = data['episodeCards'];

                const aside = episodeCardsDiv.closest('aside[id="episode-cards"]');
                if (aside) { // episode page
                    aside.appendChild(newEpisodeCardsDiv);
                    const targetEpisodeCard = newEpisodeCardsDiv.querySelector(`[data-episode-id="${targetId}"]`);
                    const cardLeft = targetEpisodeCard.offsetLeft;
                    const cardWidth = targetEpisodeCard.offsetWidth;
                    newEpisodeCardsDiv.scrollLeft = Math.max(0, cardLeft - (width - cardWidth) / 2);
                    setTimeout(function () {
                        newEpisodeCardsDiv.style.opacity = "1";
                        episodeCardsDiv.style.opacity = "0";
                    }, 0);
                    setTimeout(function () {
                        episodeCardsDiv.remove();
                        aside.querySelector('script').remove();
                        newEpisodeCardsDiv.classList.remove('replacing-episodes');
                    }, 600)
                } else // series page
                {
                    episodeCardsDiv.replaceWith(newEpisodeCardsDiv);
                }

                self.toolTips.init(newEpisodeCardsDiv);

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
