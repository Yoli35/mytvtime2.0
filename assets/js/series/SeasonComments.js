let gThis;

export class SeasonComments {
    constructor(seriesId, seasonNumber, translations) {
        gThis = this;
        this.seriesId = seriesId;
        this.seasonNumber = seasonNumber;
        this.translations = translations;

        this.addCommentButtons = document.querySelectorAll(".episode-group-add-comment");
        console.log("Season comments")
        this.init();
    }

    init() {
        this.getEpisodeComments();
        this.addCommentButtons.forEach(button => {
            button.addEventListener("click", () => {});
        });
    }

    getEpisodeComments() {
        const episodeArr = gThis.getEpisodeArr();
        fetch('/api/season/comments/' + this.seriesId, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                seasonNumber: this.seasonNumber,
                availableEpisodeCount: episodeArr.length,
                episodeArr: JSON.stringify(episodeArr)
            })
        })
            .then((response) => response.json())
            .then(data => {
                console.log(data);
                const comments = data['comments'];
                const episodesCommentsDiv = document.querySelector(".episodes-comments");
                comments.forEach(comment => {
                    const episodeNumber = comment['episodeNumber'];
                    const seasonNumber = comment['seasonNumber'];
                    let episodeGroup = episodesCommentsDiv.querySelector(".episode-group#episode-comments-" + episodeNumber);
                    if (!episodeGroup) {
                        episodeGroup = gThis.createEpisodeGroup(seasonNumber, episodeNumber);
                        episodesCommentsDiv.appendChild(episodeGroup);
                    }
                    const coreDiv = document.createElement("div");
                    coreDiv.classList.add("comment");
                    coreDiv.setAttribute("data-id", comment['id']);
                    coreDiv.setAttribute("data-tmdb-id", comment['tmdbId']);
                    const userDiv = document.createElement("div");
                    userDiv.classList.add("user");
                    const userAvatarDiv = document.createElement("div");
                    userAvatarDiv.classList.add("avatar");
                    userAvatarDiv.setAttribute("data-tile", comment['user']['username']);
                    if (comment['user']['avatar']) {
                        const img = document.createElement("img");
                        img.src = "/images/users/avatars/" + comment['user']['avatar'];
                        img.alt = comment['user']['username'];
                        userAvatarDiv.appendChild(img);
                    } else {
                        userAvatarDiv.innerText = comment['user']['username'].slice(0, 1).toUpperCase();
                    }
                    userDiv.appendChild(userAvatarDiv);
                    const dateDiv = document.createElement("div");
                    dateDiv.classList.add("date");
                    dateDiv.innerText = comment['createdAt'].toLocaleString();
                    userDiv.appendChild(dateDiv);
                    const messageDiv = document.createElement("div");
                    messageDiv.classList.add("message");
                    messageDiv.innerText = comment['message'];
                    coreDiv.appendChild(userDiv);
                    coreDiv.appendChild(messageDiv);
                    if (comment['replyTo'] === null) {
                        episodeGroup.appendChild(coreDiv);
                    } else {
                        const replyToCommentDiv = episodeGroup.querySelector('.comment[data-id="' + comment['replyTo'] + '"]');
                        const messageDiv = replyToCommentDiv.querySelector(".message");
                        messageDiv.appendChild(coreDiv);
                    }
                });
                const episodeArr = data['episodeArr'];
                episodeArr.forEach(ep => {
                    if (!ep['commentCount']) {
                        episodesCommentsDiv.appendChild(gThis.createEpisodeGroup(ep['seasonNumber'], ep['episodeNumber']));
                    }
                });
            })
            .catch(err => console.log(err));
    }

    getEpisodeArr() {
        const availableEpisodes = document.querySelectorAll(".episodes .episode-wrapper .episode");
        const episodeArr = [];
        const seasonNumber = parseInt(gThis.seasonNumber)
        availableEpisodes.forEach(ep => {
            const episodeNumber = parseInt(ep.getAttribute("id").split("-")[2]);
            episodeArr[episodeNumber - 1] = {
                'tmdb': ep.getAttribute("data-episode-id"),
                'episodeNumber': episodeNumber,
                'seasonNumber': seasonNumber,
                'commentCount': 0
            };
        });
        console.log(episodeArr);

        return episodeArr;
    }

    createEpisodeGroup(seasonNumber, episodeNumber) {
        const episodeGroup = document.createElement("div");
        episodeGroup.classList.add("episode-group");
        episodeGroup.style.order = episodeNumber.toString();
        episodeGroup.setAttribute("id", "episode-comments-" + episodeNumber);
        const headerDiv = document.createElement("div");
        headerDiv.classList.add("episode-group-header");
        const titleDiv = document.createElement("div");
        titleDiv.classList.add("episode-group-title");
        titleDiv.innerText = "Episode " + gThis.formatEpisode(seasonNumber, episodeNumber);
        headerDiv.appendChild(titleDiv);
        const commentButton = document.createElement("div");
        commentButton.classList.add("episode-group-add-comment");
        commentButton.innerText = gThis.translations['Add a comment'];
        commentButton.setAttribute("data-episode-number", episodeNumber);
        commentButton.addEventListener("click", () => {
        });
        headerDiv.appendChild(commentButton);
        episodeGroup.appendChild(headerDiv);
        return episodeGroup;
    }

    pad2(value) {
        const n = Number(value);
        if (!Number.isFinite(n) || n < 0) return null;
        return n < 10 ? '0' + n : String(n);
    }

    /**
     * Retourne "SxxExx" ou "" si invalide.
     * @param {number|string|null} season
     * @param {number|string|null} episode
     * @returns {string}
     */
    formatEpisode(season, episode) {
        const s = this.pad2(season);
        const e = this.pad2(episode);
        if (s === null && e === null) return '';
        // Si saison ou épisode manquant, remplacer par "00" (optionnel)
        return `S${s ?? '00'}E${e ?? '00'}`;
    }
}