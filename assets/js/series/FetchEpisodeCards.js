
let self = null;
export class FetchEpisodeCards {

    constructor(toolTips) {
        self = this;
        this.toolTips = toolTips;

        this.init();
    }

    init() {
        const episodeCardDivs = document.querySelectorAll('.episode__cards');
        this.load(episodeCardDivs, 0, episodeCardDivs.length);
    }

    load(cards, index, length) {
        if (!length) return;
        const episodeCardDiv = cards.item(index);
        const id = episodeCardDiv.getAttribute('data-id');
        const tmdbId = episodeCardDiv.getAttribute('data-tmdb-id');
        const seasonNumber = episodeCardDiv.getAttribute('data-season-number');
        const seriesSlug = episodeCardDiv.getAttribute('data-series-slug');
        fetch('/api/series/season/episode/stills/' + id + '/' + tmdbId + '/' + seasonNumber + '/' + seriesSlug, {method: 'GET'})
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                episodeCardDiv.innerHTML = data['episodeCards'];
                self.toolTips.init(episodeCardDiv);
                index++;
                if (index < length) self.load(cards, index, length);
            })
            .catch(err => console.log(err));
    }
}