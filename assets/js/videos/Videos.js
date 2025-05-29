import {ToolTips} from "ToolTips";

export class Videos {

    /**
     * @typedef Comment
     * @type {Object}
     * @property {string} author
     * @property {string} authorProfileImageUrl
     * @property {string} publishedAt
     * @property {string} text
     * @property {Array<Comment>} replies
     */

    constructor() {
        const globs = JSON.parse(document.querySelector("#global-data").textContent);
        this.app_video_details = globs['app_video_details'];
        this.app_video_comments = globs['app_video_comments'];
        this.app_video_category_add = globs['app_video_category_add'];
        this.app_video_category_delete = globs['app_video_category_delete'];
        this.publishedAt = globs['published_at'];
        this.addedAt = globs['added_at'];
        this.texts = globs['texts'];
        this.commentInfos = {comments: [], nextPageToken: null};
        this.svgs = document.querySelector('#svgs');
        this.tooltips = new ToolTips();
        this.init = this.init.bind(this);
    }

    init() {
        console.log('Videos.js init', this.app_video_details);
        this.initCategories();
        this.getComments();
    }

    initCategories() {
        const videosPage = document.querySelector('.videos-page');
        const videoPage = document.querySelector('.video-page');
        const categorySelect = document.querySelector('select[id="categories"]');

        if (categorySelect) {
            if (videoPage) {
                const videoId = videoPage.getAttribute('data-id');
                const deleteDivs = document.querySelectorAll('.delete-category');
                deleteDivs.forEach((deleteDiv) => {
                    deleteDiv.addEventListener('click', (event) => {
                        event.preventDefault();
                        const categoryDiv = deleteDiv.closest('.category');
                        const categoryId = categoryDiv.getAttribute('data-id');
                        this.deleteCategory(videoId, categoryId, categoryDiv);
                    });
                });
                categorySelect.addEventListener('change', (event) => {
                    const selectedCategory = event.target.value;
                    this.addCategory(videoId, selectedCategory);
                });
            }
            if (videosPage) {
                console.log('Videos page detected, initializing categories select');
            }
        }
    }

    addCategory(videoId, categoryId) {
        if (categoryId === 'all') {
            return; // Do not proceed if "All" is selected
        }
        fetch(this.app_video_category_add, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                videoId: videoId,
                categoryId: categoryId
            })
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                console.log('Category added:', data);
                let categoriesDiv = document.querySelector('.categories');
                if (!categoriesDiv) {
                    categoriesDiv = document.createElement('div');
                    categoriesDiv.classList.add('categories');
                    const h1CategoriesDiv = document.querySelector('.h1-categories');
                    h1CategoriesDiv.appendChild(categoriesDiv);
                }
                const categoryDiv = document.createElement('div');
                categoryDiv.classList.add('category');
                categoryDiv.setAttribute('data-id', data['id']);
                categoryDiv.setAttribute('style', 'background-color: ' + data['color']);
                categoryDiv.innerText = data['name'];
                const deleteDiv = document.createElement('div');
                deleteDiv.classList.add('delete-category');
                const deleteIcon = this.svgs.querySelector('#delete').querySelector('svg').cloneNode(true);
                deleteDiv.appendChild(deleteIcon);
                deleteDiv.addEventListener('click', (event) => {
                    event.preventDefault();
                    this.deleteCategory(videoId, data['id'], categoryDiv);
                });
                categoryDiv.appendChild(deleteDiv);
                categoriesDiv.appendChild(categoryDiv);
                // disable the select option that was just added
                const categorySelect = document.querySelector('select[id="categories"]');
                if (categorySelect) {
                    const option = categorySelect.querySelector(`option[value="${categoryId}"]`);
                    if (option) {
                        option.disabled = true;
                    }
                    const optionAll = categorySelect.querySelector('option[value="all"]');
                    if (optionAll) {
                        optionAll.selected = true; // Select "All" option after deletion
                    }
                }
            })
            .catch(error => {
                console.error('There was a problem with the fetch operation:', error);
            });
    }

    deleteCategory(videoId, categoryId, categoryDiv) {
        fetch(this.app_video_category_delete, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                videoId: videoId,
                categoryId: categoryId
            })
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                console.log('Category deleted:', data);
                categoryDiv.remove();
                // enable the select option that was just deleted
                const categorySelect = document.querySelector('select[id="categories"]');
                if (categorySelect) {
                    const option = categorySelect.querySelector(`option[value="${categoryId}"]`);
                    if (option) {
                        option.disabled = false;
                    }
                    const optionAll = categorySelect.querySelector('option[value="all"]');
                    if (optionAll) {
                        optionAll.selected = true; // Select "All" option after deletion
                    }
                }
            })
            .catch(error => {
                console.error('There was a problem with the fetch operation:', error);
            });
    }

    getComments() {
        fetch(this.app_video_details, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                const infos = this.getVideoInfos(data);
                console.log('Video details:', infos);

                const videoInfosDiv = document.querySelector('.video-infos');
                const headerDiv = document.createElement('div');
                headerDiv.classList.add('header');
                videoInfosDiv.appendChild(headerDiv);
                const contentDiv = document.createElement('div');
                contentDiv.classList.add('content');
                videoInfosDiv.appendChild(contentDiv);

                // header → Channel
                const channel = infos.channel;
                const channelDiv = document.createElement('div');
                channelDiv.classList.add('channel');
                const thumbnailDiv = document.createElement('div');
                thumbnailDiv.classList.add('thumbnail');
                const img = document.createElement('img');
                img.src = "/videos/channels/thumbnails/" + channel["thumbnail"];
                img.alt = channel.title;
                thumbnailDiv.appendChild(img);
                channelDiv.appendChild(thumbnailDiv);
                const channelTitleDiv = document.createElement('div');
                channelTitleDiv.classList.add('title');
                channelTitleDiv.innerText = channel.title;
                channelDiv.appendChild(channelTitleDiv);
                videoInfosDiv.appendChild(channelDiv);
                headerDiv.appendChild(channelDiv);

                // header → Statistics: commentCount, likeCount, viewCount
                const statistics = infos.statistics;
                const statisticsDiv = document.createElement('div');
                statisticsDiv.classList.add('stats');
                if (statistics['viewCount'] > 1)
                    statisticsDiv.appendChild(this.addStats('view-count', '#view', statistics['viewCount'], this.texts['views']));
                if (statistics['likeCount'] > 1)
                    statisticsDiv.appendChild(this.addStats('like-count', '#like', statistics['likeCount'], this.texts['likes']));
                if (statistics['commentCount'] > 1)
                    statisticsDiv.appendChild(this.addStats('comment-count', '#comment', statistics['commentCount'], this.texts['comments']));
                headerDiv.appendChild(statisticsDiv);

                // header → at → Published date
                const atDiv = document.createElement('div');
                atDiv.classList.add('at');
                const publishedAtDiv = document.createElement('div');
                publishedAtDiv.classList.add('published-at');
                publishedAtDiv.innerText = this.publishedAt;
                atDiv.appendChild(publishedAtDiv);
                // header → at → Added date
                const addedAtDiv = document.createElement('div');
                addedAtDiv.classList.add('added-at');
                addedAtDiv.innerText = this.addedAt;
                atDiv.appendChild(addedAtDiv);
                headerDiv.appendChild(atDiv);

                // content → Description
                const descriptionDiv = document.createElement('div');
                descriptionDiv.classList.add('description');
                const descriptionText = document.createElement('div');
                descriptionText.classList.add('description-text');
                const description = infos.snippet.description;
                // Replace all the links in the description with a clickable link
                const regex = /https?:\/\/\S+/g;
                const newDescription = description.replace(regex, (url) => {
                    return `<a href="${url}" target="_blank">${url}</a>`;
                });
                // Replace aa mailto: in the description with a clickable link
                const mailRegex = /mailto:\S+/g;
                const newMailDescription = newDescription.replace(mailRegex, (url) => {
                    return `<a href="${url}" target="_blank">${url}</a>`;
                });
                // Replace \n with <br>
                descriptionText.innerHTML = newMailDescription.replace(/\n/g, '<br>')
                descriptionDiv.appendChild(descriptionText);
                contentDiv.appendChild(descriptionDiv);
                // if div.description-text is too long (height > 10rem), add a "display more" button
                if (descriptionText.offsetHeight > 10 * parseFloat(getComputedStyle(descriptionText).fontSize)) {
                    const displayMoreDiv = document.createElement('div');
                    displayMoreDiv.classList.add('display-more');
                    displayMoreDiv.innerText = this.texts['display_more'];
                    displayMoreDiv.addEventListener('click', () => {
                        descriptionDiv.classList.toggle('expanded');
                        if (descriptionDiv.classList.contains('expanded')) {
                            displayMoreDiv.innerText = this.texts['display_less'];
                        } else {
                            displayMoreDiv.innerText = this.texts['display_more'];
                        }
                    });
                    descriptionDiv.appendChild(displayMoreDiv);
                }

                // content → Tags
                const tags = infos.snippet.tags;
                if (tags) {
                    const tagsDiv = document.createElement('div');
                    tagsDiv.classList.add('tags');
                    tags.forEach(tag => {
                        const tagDiv = document.createElement('div');
                        tagDiv.classList.add('tag');
                        tagDiv.innerText = tag;
                        tagsDiv.appendChild(tagDiv);
                    });
                    contentDiv.appendChild(tagsDiv);
                }

                // content → Comments
                this.commentInfos.comments = infos.comments;
                this.commentInfos.nextPageToken = infos.nextPageToken;

                const comments = infos.comments;
                if (comments) {
                    const commentsDiv = document.createElement('div');
                    commentsDiv.classList.add('comments');
                    const commentsTitleDiv = document.createElement('div');
                    commentsTitleDiv.classList.add('comments-title');
                    commentsTitleDiv.innerText = this.texts['Comments'];
                    commentsDiv.appendChild(commentsTitleDiv);
                    const commentsContentDiv = document.createElement('div');
                    commentsContentDiv.classList.add('comments-content');
                    commentsDiv.appendChild(commentsContentDiv);
                    contentDiv.appendChild(commentsDiv);
                    this.displayComments();
                }

                // If infos.nextPageToken is not null and the bottom of the page is reached, load more comments
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            this.getNextComments(infos, data, observer, entry);
                        }
                    });
                });
                const target = document.querySelector('.comment:last-child');
                observer.observe(target);
            })
            .catch(error => {
                console.error('There was a problem with the fetch operation:', error);
            });
    }

    getNextComments(infos, data, observer, entry) {
        if (this.commentInfos.nextPageToken) {
            fetch(this.app_video_comments, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    link: data.video.id,
                    nextPageToken: this.commentInfos.nextPageToken,
                })
            })
                .then(response => response.json())
                .then(data => {
                    this.commentInfos.comments = data.comments;
                    this.commentInfos.nextPageToken = data.nextPageToken;
                    this.displayComments();
                    observer.unobserve(entry.target);
                    const target = document.querySelector('.comment:last-child');
                    observer.observe(target);
                })
                .catch(error => {
                    console.error('There was a problem with the fetch operation:', error);
                });
        }
    }

    displayComments() {
        const commentsContentDiv = document.querySelector('.comments-content');
        const comments = this.commentInfos.comments;

        comments.forEach((comment) => {
            const commentDiv = document.createElement('div');
            commentDiv.classList.add('comment');
            const commentInfosDiv = document.createElement('div');
            commentInfosDiv.classList.add('comment-infos');
            const thumbnailDiv = document.createElement('div');
            thumbnailDiv.classList.add('author-thumbnail');
            thumbnailDiv.setAttribute('data-title', comment.author);
            const img = document.createElement('img');
            img.src = "/videos/channels/thumbnails/" + comment.authorProfileImageUrl;
            img.alt = comment.author;
            thumbnailDiv.appendChild(img);
            this.tooltips.initElement(thumbnailDiv);
            commentInfosDiv.appendChild(thumbnailDiv);
            const authorNameDiv = document.createElement('div');
            authorNameDiv.classList.add('author-name');
            authorNameDiv.innerText = comment.author;
            commentInfosDiv.appendChild(authorNameDiv);
            const publishedAtDiv = document.createElement('div');
            publishedAtDiv.classList.add('published-at');
            publishedAtDiv.innerText = comment.publishedAt;
            commentInfosDiv.appendChild(publishedAtDiv);
            commentDiv.appendChild(commentInfosDiv);
            const commentText = document.createElement('div');
            commentText.classList.add('comment-text');
            commentText.innerText = comment.text;
            commentDiv.appendChild(commentText);

            const replies = comment.replies;
            if (replies.length > 0) {
                const repliesDiv = document.createElement('div');
                repliesDiv.classList.add('replies');
                replies.forEach((reply) => {
                    const replyDiv = document.createElement('div');
                    replyDiv.classList.add('reply');
                    const replyInfosDiv = document.createElement('div');
                    replyInfosDiv.classList.add('comment-infos');
                    const replyThumbnailDiv = document.createElement('div');
                    replyThumbnailDiv.classList.add('author-thumbnail');
                    replyThumbnailDiv.setAttribute('data-title', reply.author);
                    const replyImg = document.createElement('img');
                    replyImg.src = "/videos/channels/thumbnails/" + reply.authorProfileImageUrl;
                    replyImg.alt = reply.author;
                    replyThumbnailDiv.appendChild(replyImg);
                    this.tooltips.initElement(replyThumbnailDiv);
                    replyInfosDiv.appendChild(replyThumbnailDiv);
                    const replyAuthorNameDiv = document.createElement('div');
                    replyAuthorNameDiv.classList.add('author-name');
                    replyAuthorNameDiv.innerText = reply.author;
                    replyInfosDiv.appendChild(replyAuthorNameDiv);
                    const replyPublishedAtDiv = document.createElement('div');
                    replyPublishedAtDiv.classList.add('published-at');
                    replyPublishedAtDiv.innerText = reply.publishedAt;
                    replyInfosDiv.appendChild(replyPublishedAtDiv);
                    replyDiv.appendChild(replyInfosDiv);
                    const replyText = document.createElement('div');
                    replyText.classList.add('comment-text');
                    replyText.innerText = reply.text;
                    replyDiv.appendChild(replyText);

                    repliesDiv.appendChild(replyDiv);
                });
                commentDiv.appendChild(repliesDiv);
            }
            commentsContentDiv.appendChild(commentDiv);
        });
    }

    getVideoInfos(data) {
        const video = data.video;
        const channel = data.channel;
        return {
            snippet: video.snippet,
            statistics: video.statistics,
            channel: channel,
            comments: data.comments,
            nextPageToken: data.nextPageToken,
        };
    }

    addStats(className, iconID, value, text) {
        const div = document.createElement('div');
        div.classList.add(className);
        const iconDiv = document.createElement('div');
        iconDiv.classList.add('icon');
        const viewSVG = this.svgs.querySelector(iconID).querySelector('svg').cloneNode(true);
        iconDiv.appendChild(viewSVG);
        div.appendChild(iconDiv);
        const textDiv = document.createElement('div');
        textDiv.classList.add('text');
        textDiv.innerText = value + " " + text;
        div.appendChild(textDiv);
        return div;
    }
}