export class Videos {

    /**
     * @typedef Comment
     * @type {Object}
     * @property {string} author
     * @property {string} publishedAt
     * @property {string} text
     */

    constructor() {
        const globs = JSON.parse(document.querySelector("#global-data").textContent);
        this.app_video_details = globs['app_video_details'];
        this.app_video_comments = globs['app_video_comments'];
        this.publishedAt = globs['published_at'];
        this.addedAt = globs['added_at'];
        this.texts = globs['texts'];
        this.svgs = document.querySelector('#svgs');
        this.init = this.init.bind(this);
    }

    init() {
        console.log('Videos.js init', this.app_video_details);

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
                console.log('Video details:', data);
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
                    this.displayComments(comments);
                }

                // If infos.commentNextPageToken is not null and the bottom of the page is reached, load more comments
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
        if (infos.commentNextPageToken) {
            fetch(this.app_video_comments, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    link: data.video.id,
                    nextPageToken: infos.commentNextPageToken
                })
            })
                .then(response => response.json())
                .then(data => {
                    this.displayComments(data.comments);
                    observer.unobserve(entry.target);
                    const target = document.querySelector('.comment:last-child');
                    observer.observe(target);
                })
                .catch(error => {
                    console.error('There was a problem with the fetch operation:', error);
                });
        }
    }

    displayComments(comments) {
        const commentsContentDiv = document.querySelector('.comments-content');

        comments.forEach((comment, index) => {
            const commentDiv = document.createElement('div');
            commentDiv.classList.add('comment');
            const commentInfosDiv = document.createElement('div');
            commentInfosDiv.classList.add('comment-infos');
            const authorNameDiv = document.createElement('div');
            authorNameDiv.classList.add('author-name');
            authorNameDiv.innerText = "#" + index + " - " + comment.author;
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
            commentNextPageToken: data.commentNextPageToken,
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