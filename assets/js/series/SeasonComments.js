import {ToolTips} from 'ToolTips';

let gThis;

export class SeasonComments {
    constructor(user, seriesId, seasonNumber, translations) {
        gThis = this;
        this.user = user;
        this.seriesId = seriesId;
        this.seasonNumber = seasonNumber;
        this.translations = translations;
        this.toolTips = new ToolTips();

        this.addEpisodeComment = this.addEpisodeComment.bind(this);

        this.init();
        console.log("Season comments initialized")
    }

    init() {
        this.getEpisodeComments();

        const addCommentButtons = document.querySelectorAll(".episode-group-add-comment");
        addCommentButtons.forEach(button => {
            button.addEventListener("click", () => {
            });
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
                /*console.log(data);*/
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
                    const episodeGroupContent = episodeGroup.querySelector(".episode-group-content");

                    if (comment['replyTo'] === null) {
                        episodeGroupContent.appendChild(gThis.createMessage(comment));
                    } else {
                        const replyToCommentDiv = episodeGroupContent.querySelector('.comment[data-id="' + comment['replyTo'] + '"]');
                        const messageDiv = replyToCommentDiv.querySelector(".message");
                        messageDiv.appendChild(gThis.createMessage(comment));
                    }
                    gThis.addUserCommentBadge(comment);
                });
                const episodeArr = data['episodeArr'];
                episodeArr.forEach(ep => {
                    if (!ep['commentCount']) {
                        episodesCommentsDiv.appendChild(gThis.createEpisodeGroup(ep['seasonNumber'], ep['episodeNumber']));
                    }
                });
                gThis.toolTips.init(episodesCommentsDiv);
            })
            .catch(err => console.log(err));
    }

    addEpisodeComment(e) {
        const button = e.currentTarget;
        const episodeNumber = button.getAttribute("data-ep-number");
        const episodeId = button.getAttribute("data-ep-id");
        const input = button.parentNode.querySelector("input");
        fetch('/api/season/comment/add/' + this.seriesId, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                seasonNumber: this.seasonNumber,
                episodeNumber: episodeNumber,
                episodeId: episodeId,
                message: input.value
            })
        })
            .then((response) => response.json())
            .then(data => {
                /*console.log(data);*/
                input.value = '';
                input.focus();
                const footer = button.closest(".episode-group-footer");
                const episodeGroup = footer.closest(".episode-group");
                const content = episodeGroup.querySelector(".episode-group-content");
                const newMessage = this.createMessage(data['comment']);
                gThis.toolTips.init(newMessage);
                content.appendChild(newMessage);
                gThis.addUserCommentBadge(data['comment']);
            })
            .catch(err => console.log(err));
    }

    addUserCommentBadge(comment) {
        const episodeId = comment['tmdbId'];
        const div = document.querySelector('.user-episode .remove-this-episode[data-id="' + episodeId + '"]');
        const userEpisodeDiv = div.closest(".user-episode");
        const selectVote = userEpisodeDiv.querySelector(".select-vote");
        let badge = userEpisodeDiv.querySelector(".comment-badge");
        if (badge) return;
        badge = document.createElement("div");
        badge.classList.add("comment-badge");
        badge.setAttribute("data-id", episodeId);
        badge.setAttribute("data-title", 1);
        const episodeGroup = document.querySelector(".episode-group#episode-comments-" + comment['episodeNumber']);
        const svg = document.querySelector("#svgs #comment-badge").cloneNode(true);
        svg.removeAttribute("id");
        badge.appendChild(svg);
        badge.addEventListener("click", () => {
            episodeGroup.scrollIntoView({behavior: 'smooth', block: 'center'});
        });
        userEpisodeDiv.insertBefore(badge, selectVote);
        console.log(userEpisodeDiv);
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
        /*console.log(episodeArr);*/

        return episodeArr;
    }

    createEpisodeGroup(seasonNumber, episodeNumber) {
        // Episode comment group
        const episodeGroup = document.createElement("div");
        episodeGroup.classList.add("episode-group");
        episodeGroup.style.order = episodeNumber.toString();
        episodeGroup.setAttribute("id", "episode-comments-" + episodeNumber);

        // Header
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
        headerDiv.appendChild(commentButton);
        episodeGroup.appendChild(headerDiv);

        // Content
        const content = document.createElement("div");
        content.classList.add("episode-group-content");
        episodeGroup.appendChild(content);

        // Footer
        const footer = document.createElement("div");
        footer.classList.add("episode-group-footer");
        const userDiv = gThis.createUser(gThis.user);
        const commentInput = document.createElement("input");
        commentInput.setAttribute("type", "text");
        const sendButton = document.createElement("div");
        const episode = document.querySelector('.episodes .episode-wrapper .episode#episode-' + seasonNumber + '-' + episodeNumber);
        const episodeId = episode.getAttribute("data-episode-id");
        sendButton.classList.add("send-button");
        sendButton.setAttribute("data-ep-number", episodeNumber);
        sendButton.setAttribute("data-ep-id", episodeId);
        sendButton.setAttribute("data-title", gThis.translations['Send your comment'])
        sendButton.addEventListener("click", gThis.addEpisodeComment);
        const sendSvg = document.querySelector("#svgs #send").cloneNode(true);
        sendSvg.removeAttribute("id");
        sendButton.appendChild(sendSvg);

        footer.appendChild(userDiv);
        footer.appendChild(commentInput);
        footer.appendChild(sendButton);
        episodeGroup.appendChild(footer);

        return episodeGroup;
    }

    createMessage(comment) {
        const coreDiv = document.createElement("div");
        coreDiv.classList.add("comment");
        coreDiv.setAttribute("data-id", comment['id']);
        coreDiv.setAttribute("data-tmdb-id", comment['tmdbId']);
        const userDiv = gThis.createUser(comment['user']);
        const dateDiv = document.createElement("div");
        dateDiv.classList.add("date");
        dateDiv.innerText = comment['createdAt'].toLocaleString();
        userDiv.appendChild(dateDiv);
        const messageDiv = document.createElement("div");
        messageDiv.classList.add("message");
        messageDiv.innerText = comment['message'];
        coreDiv.appendChild(userDiv);
        coreDiv.appendChild(messageDiv);

        return coreDiv;
    }

    createUser(user) {
        const userDiv = document.createElement("div");
        userDiv.classList.add("user");
        const userAvatarDiv = document.createElement("div");
        userAvatarDiv.classList.add("avatar");
        userAvatarDiv.setAttribute("data-title", user['username']);
        if (user['avatar']) {
            const img = document.createElement("img");
            img.src = "/images/users/avatars/" + user['avatar'];
            img.alt = user['username'];
            userAvatarDiv.appendChild(img);
        } else {
            userAvatarDiv.innerText = user['username'].slice(0, 1).toUpperCase();
        }
        userDiv.appendChild(userAvatarDiv);
        return userDiv;
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