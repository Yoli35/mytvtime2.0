export class Videos {

    constructor() {
        const globs = JSON.parse(document.querySelector("#global-data").textContent);
        this.app_video_details = globs['app_video_details'];
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
                const descriptionText = document.createElement('p');
                descriptionText.innerText = infos.snippet.description;
                descriptionDiv.appendChild(descriptionText);
                contentDiv.appendChild(descriptionDiv);
            })
            .catch(error => {
                console.error('There was a problem with the fetch operation:', error);
            });
    }

    getVideoInfos(data) {
        const video = data.video;
        const channel = data.channel;
        return {
            snippet: video.snippet,
            statistics: video.statistics,
            channel: channel,
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
    /*
    [Log] Video details: (Videos-esQ8Q7H.js, line 25)
    {
        video: {
            "contentDetails": {
                "caption": "false",
                "contentRating": {acbRating: null, agcomRating: null, anatelRating: null, bbfcRating: null, bfvcRating: null, …},
                "definition": "hd",
                "dimension": "2d",
                "duration": "PT1H34M32S",
                "hasCustomThumbnail": null,
                "licensedContent": true,
                "projection": "rectangular"
            }
            "etag": "RSpRPMPU_MlnzzL5hWUnBbQm2GU",
            "id": "YNhvj5aqNmc",
            "kind": "youtube#video",
            "snippet": {
                "categoryId": "28",
                "channelId": "UCAbFIrKZCYdKucQYnhxWnrA",
                "channelTitle": "LIMIT",
                "defaultAudioLanguage": "fr",
                "defaultLanguage": null,
                "description": "➙ Cette chaîne vit aussi et surtout grâce à vos dons ! https://linktr.ee/limit.media↵↵Dans cette vidéo de LIMIT, nous abordons la questio…",
                "liveBroadcastContent": "none",
                "localized": {
                    "description": "➙ Cette chaîne vit aussi et surtout grâce à vos dons ! https://linktr.ee/limit.media↵↵Dans cette vidéo de LIMIT, nous abordons la questio…",
                    "title": "L'EAU : LA BOMBE À RETARDEMENT - Charlène Descollonges | LIMIT #partie2"
                },
                "publishedAt": "2025-04-27T16:01:44Z",
                "tags": [
                    "0": "LIMIT",
                    "1": "Actu",
                    "2": "Jancovici",
                    "3": "Tarmac",
                    "4": "Booska P",
                    "5": "Brut",
                    "6": "Le Media",
                    "7": "Thinkerview",
                    "8": "Elucid",
                    "9": "Blast",
                    "10": "Environnement",
                    "11": "Climat",
                    "12": "Ecologie",
                    "13": "Rap",
                    "14": "Finance",
                    "15": "Podcast",
                    "16": "thinkerview",
                    "17": "Histoire",
                    "18": "EAU",
                    "19": "Pluie",
                    "20": "Contaminée"
                ],
                "thumbnails": {
                    "default": {height: 90, url: "https://i.ytimg.com/vi/YNhvj5aqNmc/default.jpg", width: 120},
                    "high": {height: 360, url: "https://i.ytimg.com/vi/YNhvj5aqNmc/hqdefault.jpg", width: 480},
                    "maxres": {height: 720, url: "https://i.ytimg.com/vi/YNhvj5aqNmc/maxresdefault.jpg", width: 1280},
                    "medium": {height: 180, url: "https://i.ytimg.com/vi/YNhvj5aqNmc/mqdefault.jpg", width: 320},
                    "standard": {height: 480, url: "https://i.ytimg.com/vi/YNhvj5aqNmc/sddefault.jpg", width: 640}
                },
                "title": "L'EAU : LA BOMBE À RETARDEMENT - Charlène Descollonges | LIMIT #partie2"
            },
            "statistics": {
                "commentCount": "639",
                "dislikeCount": null,
                "favoriteCount": "0",
                "likeCount": "2188",
                "viewCount": "68997"
            }
        }
    }
*/
}