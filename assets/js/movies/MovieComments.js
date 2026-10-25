import {ToolTips} from 'ToolTips';

let self;

export class MovieComments {
    constructor(user, movieId, translations) {
        self = this;
        this.user = user;
        this.movieId = movieId;
        this.translations = translations;
        this.toolTips = new ToolTips();
        this.commentsDiv = document.querySelector(".movie-comments");
        this.answerSVG = document.getElementById('answer-badge').cloneNode(true);
        this.answerSVG.removeAttribute('id');

        this.addMovieComment = this.addMovieComment.bind(this);
    }

    init() {
        if (!this.commentsDiv) {
            return;
        }
        this.commentsDiv.querySelector(".movie-comments-footer").appendChild(self.createCommentForm(0));
        this.getMovieComments();
        this.initCommentImagesDialog();
        console.log("Movie comments initialized")
    }

    getMovieComments() {
        fetch('/api/movie/comments/' + this.movieId, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then((response) => response.json())
            .then(data => {
                const comments = data['comments'];
                const images = data['images'];
                const content = self.commentsDiv.querySelector(".movie-comments-content");
                comments.forEach(comment => {
                    const commentImages = images[comment['id']] ?? [];
                    const replyToCommentDiv = comment['replyTo'] !== null ? content.querySelector('.comment[data-id="' + comment['replyTo'] + '"]') : null;
                    if (replyToCommentDiv) {
                        replyToCommentDiv.querySelector(".message").appendChild(self.createMessage(comment, commentImages));
                    } else {
                        content.appendChild(self.createMessage(comment, commentImages));
                    }
                });
                self.toolTips.init(self.commentsDiv);
            })
            .catch(err => console.log(err));
    }

    addMovieComment(e) {
        const button = e.currentTarget;
        const form = button.closest("form");
        /** @Type {HTMLInputElement} */
        const imageFilesInput = form.querySelector('input[type="file"]');
        const replyToId = form.querySelector('input[name="replyToId"]').value;
        const input = form.querySelector('textarea');
        if (!input.value.trim()) {
            input.focus();
            return;
        }
        const formData = self.getFormData(imageFilesInput, input.value, replyToId);
        fetch('/api/movie/comment/add/' + this.movieId, {
            method: 'POST',
            body: formData
        })
            .then((response) => response.json())
            .then(data => {
                const dialog = document.querySelector("dialog.answer-dialog");
                if (data['ok'] === false) {
                    dialog?.close();
                    return;
                }
                const newMessage = self.createMessage(data['comment'], data['images']);
                self.toolTips.init(newMessage);
                if (replyToId === "0") {
                    input.value = '';
                    input.focus();
                    imageFilesInput.value = '';
                    form.querySelector(".count-badge")?.remove();
                    self.commentsDiv.querySelector(".movie-comments-content").appendChild(newMessage);
                    return;
                }
                dialog.close();
                const commentDiv = document.querySelector('.movie-comments .comment[data-id="' + replyToId + '"]');
                commentDiv.querySelector(".message").appendChild(newMessage);
            })
            .catch(err => console.log(err));
    }

    getFormData(imageFilesInput, message, replyToId) {
        const formData = new FormData();
        formData.append("replyToId", replyToId);
        formData.append("message", message);
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
        const commentForm = e.currentTarget.closest("form");
        const filesInput = commentForm.querySelector('input[type="file"]');
        /** @type HTMLDialogElement */
        const commentImagesDialog = document.querySelector("#commentImagesDialog");
        /** @Type {HTMLInputElement} */
        const commentImageInput = document.getElementById('comment-image-input');

        // Le formulaire en cours d'édition reçoit les fichiers à la fermeture de la boîte de dialogue
        self.currentForm = commentForm;
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
        const commentForm = self.currentForm;
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

    createCommentForm(replyToId) {
        const form = document.createElement("form");
        form.classList.add("comment-form");
        form.classList.add(replyToId === 0 ? "to-movie" : "to-comment");
        const userDiv = self.createUser(self.user);
        const commentInput = document.createElement("textarea");
        commentInput.setAttribute("rows", "1");

        const filesInput = document.createElement("input");
        filesInput.setAttribute("type", "file");
        filesInput.style.display = "none";

        const hiddenInput = document.createElement("input");
        hiddenInput.setAttribute("type", "hidden");
        hiddenInput.setAttribute("name", "replyToId");
        hiddenInput.value = replyToId;

        const imageButton = document.createElement("div");
        imageButton.classList.add("add-image-button");
        imageButton.setAttribute("data-title", self.translations['Add images'] + "…");
        const imgSvg = document.querySelector("#svgs #image-plus").cloneNode(true);
        imgSvg.removeAttribute("id");
        imageButton.appendChild(imgSvg);
        imageButton.addEventListener("click", self.openCommentImagesDialog)

        const sendButton = document.createElement("div");
        sendButton.classList.add("send-button");
        sendButton.setAttribute("data-title", self.translations['Send your comment']);
        sendButton.addEventListener("click", self.addMovieComment);
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
        answerDiv.setAttribute("data-title", self.translations['Add answer']);
        messageDiv.appendChild(answerDiv);

        coreDiv.appendChild(messageDiv);
        answerDiv.addEventListener("click", self.answerEvent);

        if (images.length) {
            const imagesDiv = document.createElement("div");
            imagesDiv.classList.add("comment-images");
            images.forEach(image => {
                const imageDiv = document.createElement("div");
                imageDiv.classList.add("comment-image");
                const img = document.createElement("img");
                img.src = '/images/comments' + image;
                img.alt = comment['user']['username'];
                imageDiv.appendChild(img);
                imagesDiv.appendChild(imageDiv);
            });
            messageDiv.appendChild(imagesDiv);
        }

        return coreDiv;
    }

    answerEvent(e) {
        const commentDiv = e.currentTarget.closest(".comment");
        const replyToId = commentDiv.getAttribute("data-id");
        const form = self.createCommentForm(replyToId);

        const dialog = document.createElement("dialog");
        dialog.classList.add("answer-dialog");
        dialog.appendChild(form);
        dialog.addEventListener("close", () => dialog.remove());
        document.body.appendChild(dialog);
        self.toolTips.init(dialog);
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
}
