
let self = null;
export class FetchEpisodeCards {

    constructor(toolTips) {
        self = this;
        this.toolTips = toolTips;
    }

    init() {
        const episodeCardDivs = document.querySelectorAll('.episode__cards');
        this.load(episodeCardDivs, 0, episodeCardDivs.length);
    }

    load(cards, index, length) {
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
                episodeCardsDiv.replaceWith(newEpisodeCardsDiv);
                newEpisodeCardsDiv.scrollLeft = scrollX;
                self.toolTips.init(newEpisodeCardsDiv);
                index++;
                if (index < length) self.load(cards, index, length);
            })
            .catch(err => console.log(err));
    }
}