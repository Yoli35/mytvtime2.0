import {ToolTips} from 'ToolTips';

let self;

export class SeasonComments {
    constructor(user, seriesId, seasonNumber, translations) {
        self = this;
        this.user = user;
        this.seriesId = seriesId;
        this.seasonNumber = seasonNumber;
        this.translations = translations;
        this.toolTips = new ToolTips();
        this.answerSVG = document.getElementById('answer-badge').cloneNode(true);
        this.answerSVG.removeAttribute('id');

        this.addEpisodeComment = this.addEpisodeComment.bind(this);

        // this.init();
    }

    init() {
        this.getEpisodeComments();
        this.initCommentImagesDialog();
        console.log("Season comments initialized")
    }

    getEpisodeComments() {
        const episodeArr = self.getEpisodeArr();
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
                const images = data['images'];
                const episodesCommentsDiv = document.querySelector(".episodes-comments");
                comments.forEach(comment => {
                    const commentImages = images[comment['id']] ?? [];
                    const episodeNumber = comment['episodeNumber'];
                    const seasonNumber = comment['seasonNumber'];
                    let episodeGroup = episodesCommentsDiv.querySelector(".episode-group#episode-comments-" + episodeNumber);
                    if (!episodeGroup) {
                        episodeGroup = self.createEpisodeGroup(seasonNumber, episodeNumber);
                        episodesCommentsDiv.appendChild(episodeGroup);
                    }
                    const episodeGroupContent = episodeGroup.querySelector(".episode-group-content");

                    if (comment['replyTo'] === null) {
                        episodeGroupContent.appendChild(self.createMessage(comment, commentImages));
                    } else {
                        const replyToCommentDiv = episodeGroupContent.querySelector('.comment[data-id="' + comment['replyTo'] + '"]');
                        const messageDiv = replyToCommentDiv.querySelector(".message");
                        messageDiv.appendChild(self.createMessage(comment, commentImages));
                    }
                    self.adjustCommentBadge(comment['tmdbId']);
                });
                const episodeArr = data['episodeArr'];
                episodeArr.forEach(ep => {
                    if (!ep['commentCount']) {
                        episodesCommentsDiv.appendChild(self.createEpisodeGroup(ep['seasonNumber'], ep['episodeNumber']));
                    }
                });
                self.toolTips.init(episodesCommentsDiv);
            })
            .catch(err => console.log(err));
    }

    addEpisodeComment(e) {
        const button = e.currentTarget;
        const form = button.closest("form");
        /** @Type {HTMLInputElement} */
        const imageFilesInput = form.querySelector('input[type="file"]');
        const replyToId = form.querySelector('input[name="replyToId"]').value;
        const episodeNumber = button.getAttribute("data-ep-number");
        const episodeId = button.getAttribute("data-ep-id");
        const input = form.querySelector('textarea');
        const formData = self.getFormData(imageFilesInput, input.value, episodeId, episodeNumber, replyToId);
        fetch('/api/season/comment/add/' + this.seriesId, {
            method: 'POST',
            body: formData
        })
            .then((response) => response.json())
            .then(data => {
                /*console.log(data);*/
                const dialog = document.querySelector("dialog.answer-dialog");
                if (data['ok'] === false) {
                    dialog.close();
                    return;
                }
                const comment = data['comment'];
                const newMessage = self.createMessage(comment, data['images']);
                self.adjustCommentBadge(comment['tmdbId']);
                self.toolTips.init(newMessage);
                if (replyToId === "0") {
                    input.value = '';
                    input.focus();
                    const footer = button.closest(".episode-group-footer");
                    const episodeGroup = footer.closest(".episode-group");
                    const content = episodeGroup.querySelector(".episode-group-content");
                    content.appendChild(newMessage);
                    return;
                }
                dialog.close();
                const commentDiv = document.querySelector('.comment[data-id="' + replyToId + '"]');
                const messageDiv = commentDiv.querySelector(".message");
                messageDiv.appendChild(newMessage);

            })
            .catch(err => console.log(err));
    }

    getFormData(imageFilesInput, message, episodeId, episodeNumber, replyToId) {
        const formData = new FormData();
        formData.append("seasonNumber", self.seasonNumber);
        formData.append("replyToId", replyToId);
        formData.append("episodeNumber", episodeNumber);
        formData.append("episodeId", episodeId);
        formData.append("message", message);
        // Array.from(imageFilesInput.files).forEach(function (file, index) {
        //     formData.append('additional-image-' + index, file);
        // });
        const files = imageFilesInput.files;
        if (!files) {
            return formData;
        }

        let index = 0;
        for (const file of files) {
            formData.append(`additional-image-${index}`, file, file.name);
            index += 1;
        }

        return formData;
    }

    initCommentImagesDialog() {
        /** @type HTMLDialogElement */
        const commentImagesDialog = document.querySelector("#commentImagesDialog");
        /** @Type {HTMLInputElement} */
        const commentImageInput = document.getElementById('comment-image-input');
        const submitButton = commentImagesDialog.querySelector("button[type=submit]");
        const cancelButton = commentImagesDialog.querySelector("button[type=button]");

        commentImageInput.addEventListener('change', this.displayFiles);

        cancelButton.addEventListener("click", () => {
            document.removeEventListener("keydown", self.keyEventHandlerCommentImagesDialog);
            commentImagesDialog.close();
        });
        submitButton.addEventListener("click", () => {
            // Store files
            self.closeCommentImagesDialog();
        });
    }

    displayFiles(e) {
        /** @Type HTMLInputElement */
        const commentImageInput = e.currentTarget;
        const previewImageFiles = commentImageInput.closest(".form-field").querySelector(".preview-image-files");
        previewImageFiles.innerHTML = '';
        const ol = document.createElement('ol');
        previewImageFiles.appendChild(ol);
        /** @Type File file */
        Array.from(commentImageInput.files).forEach(file => {
            const reader = new FileReader();
            reader.onload = () => {
                const result = reader.result;
                if (typeof result !== "string") {
                    return; // sécurité: ne devrait pas arriver avec readAsDataURL
                }
                const li = document.createElement('li');
                const div = document.createElement('div');
                div.innerText = file.name + ' (' + self.fileSize(file.size) + ')';
                const img = document.createElement('img');
                img.src = result;
                img.alt = file.name;
                li.appendChild(img);
                li.appendChild(div);
                ol.appendChild(li);
            };
            reader.readAsDataURL(file);
        });
    }

    openCommentImagesDialog(e) {
        const imageButton = e.currentTarget;
        const episodeId = imageButton.getAttribute("data-ep-id");
        const commentForm = imageButton.closest("form");
        const filesInput = commentForm.querySelector('input[type="file"]');
        /** @type HTMLDialogElement */
        const commentImagesDialog = document.querySelector("#commentImagesDialog");
        /** @Type {HTMLInputElement} */
        const commentImageInput = document.getElementById('comment-image-input');
        const episodeIdInput = commentImagesDialog.querySelector("#comment-image-episode-id");

        episodeIdInput.value = episodeId;
        commentImageInput.files = filesInput.files;

        self.displayFiles({currentTarget: commentImageInput});

        document.addEventListener("keydown", self.keyEventHandlerCommentImagesDialog);
        commentImagesDialog.showModal();
    }

    closeCommentImagesDialog() {
        /** @type HTMLDialogElement */
        const commentImagesDialog = document.querySelector("#commentImagesDialog");
        /** @Type {HTMLInputElement} */
        const commentImageInput = document.getElementById('comment-image-input');
        const episodeId = commentImagesDialog.querySelector("#comment-image-episode-id").value;
        const commentForm = document.querySelector('.add-image-button[data-ep-id="' + episodeId + '"]').closest("form");
        const filesInput = commentForm.querySelector('input[type="file"]');

        filesInput.files = commentImageInput.files;

        const imageCount = filesInput.files.length;
        const addImageButton = commentForm.querySelector(".add-image-button");
        let countBadge = commentForm.querySelector(".count-badge");
        if (imageCount) {
            if (!countBadge) {
                countBadge = document.createElement("div");
                countBadge.classList.add("count-badge");
                addImageButton.appendChild(countBadge);
            }
            countBadge.innerText = imageCount.toString();
        } else {
            if (countBadge) {
                countBadge.remove()
            }
        }

        document.removeEventListener("keydown", self.keyEventHandlerCommentImagesDialog);
        commentImagesDialog.close();
    }

    fileSize(size) {
        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
        let unitIndex = 0;
        while (size >= 1024 && unitIndex < units.length - 1) {
            size /= 1024;
            unitIndex++;
        }
        return `${size.toFixed(2)} ${units[unitIndex]}`;
    }

    keyEventHandlerCommentImagesDialog(event) {
        /** @type HTMLDialogElement */
        const commentImagesDialog = document.querySelector("#commentImagesDialog");
        const submitButton = commentImagesDialog.querySelector("button[type=submit]");
        const cancelButton = commentImagesDialog.querySelector("button[type=button]");

        if (event.key === "Escape" && commentImagesDialog.open) {
            cancelButton.click();
        }
        if (event.key === "Enter" && commentImagesDialog.open) {
            submitButton.click();
        }
    }

    adjustCommentBadge(episodeId) {
        const badge = document.querySelector('.user-episode .comment-badge[data-id="' + episodeId + '"]');
        if (!badge) {
            return;
        }
        const count = parseInt(badge.getAttribute("data-count"));
        let countBadge;
        if (!count) {
            const svg = document.querySelector("#svgs #comment-badge").cloneNode(true);
            svg.removeAttribute("id");
            badge.querySelector("svg").remove();
            badge.appendChild(svg);
            countBadge = document.createElement("div");
            countBadge.classList.add("count-badge");
            badge.appendChild(countBadge);
        } else {
            countBadge = badge.querySelector(".count-badge");
        }
        badge.setAttribute("data-count", (count + 1).toString());
        countBadge.innerText = (count + 1).toString();
    }

    getEpisodeArr() {
        const availableEpisodes = document.querySelectorAll(".episodes .episode-wrapper .episode");
        const episodeArr = [];
        const seasonNumber = parseInt(self.seasonNumber)
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
        let episodeGroup = document.querySelector('.episodes-comments .episode-group[id="episode-comments-' + episodeNumber + '"]');
        if (episodeGroup) {
            return episodeGroup;
        }
        // Episode comment group
        episodeGroup = document.createElement("div");
        episodeGroup.classList.add("episode-group");
        episodeGroup.style.order = episodeNumber.toString();
        episodeGroup.setAttribute("id", "episode-comments-" + episodeNumber);

        // Header
        const headerDiv = document.createElement("div");
        headerDiv.classList.add("episode-group-header");
        const titleDiv = document.createElement("div");
        titleDiv.classList.add("episode-group-title");
        titleDiv.innerText = "Episode " + self.formatEpisode(seasonNumber, episodeNumber);
        headerDiv.appendChild(titleDiv);
        episodeGroup.appendChild(headerDiv);

        // Content
        const content = document.createElement("div");
        content.classList.add("episode-group-content");
        episodeGroup.appendChild(content);

        // Footer
        const footer = document.createElement("div");
        footer.classList.add("episode-group-footer");
        footer.appendChild(self.createCommentForm(seasonNumber, episodeNumber, 0));
        episodeGroup.appendChild(footer);

        // Add event click to the comment badge
        const episode = document.querySelector('.episodes .episode-wrapper .episode#episode-' + seasonNumber + '-' + episodeNumber);
        const episodeId = episode.getAttribute("data-episode-id");
        const badge = document.querySelector('.comment-badge[data-id="' + episodeId + '"]');
        badge?.addEventListener("click", () => {
            episodeGroup.classList.add("force-show");
            episodeGroup.scrollIntoView({behavior: 'smooth', block: 'center'});
            setTimeout(() => {
                episodeGroup.classList.remove("force-show");
            }, 600000); // 10 minutes
        });

        return episodeGroup;
    }

    createCommentForm(seasonNumber, episodeNumber, replyToId) {
        const form = document.createElement("form");
        form.classList.add("comment-form");
        form.classList.add(replyToId === 0 ? "to-episode" : "to-comment");
        const userDiv = self.createUser(self.user);
        const commentInput = document.createElement("textarea");
        commentInput.setAttribute("row", "1");
        const episode = document.querySelector('.episodes .episode-wrapper .episode#episode-' + seasonNumber + '-' + episodeNumber);
        const episodeId = episode.getAttribute("data-episode-id");

        const filesInput = document.createElement("input");
        filesInput.setAttribute("type", "file");
        filesInput.style.display = "none";

        const hiddenInput = document.createElement("input");
        hiddenInput.setAttribute("type", "hidden");
        hiddenInput.setAttribute("name", "replyToId");
        hiddenInput.value = replyToId;

        const imageButton = document.createElement("div");
        imageButton.classList.add("add-image-button");
        imageButton.setAttribute("data-ep-number", episodeNumber);
        imageButton.setAttribute("data-ep-id", episodeId);
        imageButton.setAttribute("data-title", self.translations['Add images'] + "…");
        const imgSvg = document.querySelector("#svgs #image-plus").cloneNode(true);
        imgSvg.removeAttribute("id");
        imageButton.appendChild(imgSvg);
        imageButton.addEventListener("click", self.openCommentImagesDialog)

        const sendButton = document.createElement("div");
        sendButton.classList.add("send-button");
        sendButton.setAttribute("data-ep-number", episodeNumber);
        sendButton.setAttribute("data-ep-id", episodeId);
        sendButton.setAttribute("data-title", self.translations['Send your comment']);
        sendButton.addEventListener("click", self.addEpisodeComment);
        const sendSvg = document.querySelector("#svgs #send").cloneNode(true);
        sendSvg.removeAttribute("id");
        sendButton.appendChild(sendSvg);

        form.appendChild(userDiv);
        form.appendChild(filesInput);
        form.appendChild(hiddenInput);
        form.appendChild(commentInput);
        form.appendChild(imageButton);
        form.appendChild(sendButton);

        form.addEventListener("submit", (e) => {
            e.preventDefault();
            sendButton.click();
        })

        return form;
    }

    createMessage(comment, images) {
        const coreDiv = document.createElement("div");
        coreDiv.classList.add("comment");
        coreDiv.setAttribute("data-id", comment['id']);
        coreDiv.setAttribute("data-tmdb-id", comment['tmdbId']);

        const userDiv = self.createUser(comment['user']);

        const dateDiv = document.createElement("div");
        dateDiv.classList.add("date");
        dateDiv.innerText = comment['createdAt'].toLocaleString();
        userDiv.appendChild(dateDiv);

        const messageDiv = document.createElement("div");
        messageDiv.classList.add("message");
        messageDiv.appendChild(userDiv);
        const span = document.createElement("span");
        span.innerText = comment['message'];
        messageDiv.appendChild(span);
        const answerDiv = document.createElement("div");
        answerDiv.classList.add("answer-button");
        const svg = self.answerSVG.cloneNode(true);
        answerDiv.appendChild(svg);
        answerDiv.setAttribute("data-ep-number", comment['episodeNumber'].toString());
        answerDiv.setAttribute("data-title", self.translations['Add answer']);
        messageDiv.appendChild(answerDiv);

        coreDiv.appendChild(messageDiv);
        answerDiv.addEventListener("click", self.answerEvent);

        if (images.length) {
            const imagesDiv = document.createElement("div");
            imagesDiv.classList.add("comment-images");
            images.forEach(image => {
                const imageName = 'Image ' + self.formatEpisode(comment['seasonNumber'], comment['episodeNumber']);
                const imageDiv = document.createElement("div");
                imageDiv.classList.add("comment-image");
                imageDiv.setAttribute("data-title", imageName)
                const img = document.createElement("img");
                img.src = '/images/comments' + image;
                img.alt = imageName;
                imageDiv.appendChild(img);
                imagesDiv.appendChild(imageDiv);
            });
            coreDiv.appendChild(imagesDiv);
        }

        return coreDiv;
    }

    answerEvent(e) {
        const answerButton = e.currentTarget;
        const episodeNumber = answerButton.getAttribute("data-ep-number");
        const commentDiv = answerButton.closest(".comment");
        const replyToId = commentDiv.getAttribute("data-id");
        const form = self.createCommentForm(self.seasonNumber, episodeNumber, replyToId);

        const dialog = document.createElement("dialog");
        dialog.classList.add("answer-dialog");
        dialog.appendChild(form);
        document.body.appendChild(dialog);
        dialog.showModal();
    }

    createUser(user) {
        const userDiv = document.createElement("div");
        userDiv.classList.add("comment-user");
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