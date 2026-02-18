import {UserList} from "UserList";

let self;
export class WhatNext {
    constructor(element, flashMessage, toolTips) {
        self = this;
        this.element = element;
        this.flashMessage = flashMessage;
        this.toolTips = toolTips;

        this.init();
    }

    init() {
        const whatToWatchNextDiv = document.querySelector('.what-to-watch-next');
        this.element.addEventListener('click', () => {
            this.element.classList.add('disabled');
            const id = this.element.getAttribute('data-id');
            const language = this.element.getAttribute('data-language');

            fetch("/api/series/what/next?id=" + id + "&language=" + language,
                {
                    'method': 'GET',
                    'headers': {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(res => res.json())
                .then(data => {
                    const blocks = data['blocks'];
                    let containerDiv = whatToWatchNextDiv.querySelector('.series-to-watch');
                    let wrapperDiv, infosDiv;
                    if (!containerDiv) {
                        infosDiv = document.createElement('div');
                        infosDiv.classList.add('series-to-watch-infos');
                        whatToWatchNextDiv.appendChild(infosDiv);
                        containerDiv = document.createElement('div');
                        containerDiv.classList.add('series-to-watch');
                        wrapperDiv = document.createElement('div');
                        wrapperDiv.classList.add('wrapper');
                        containerDiv.appendChild(wrapperDiv);
                        whatToWatchNextDiv.appendChild(containerDiv);
                    } else {
                        infosDiv = whatToWatchNextDiv.querySelector(".series-to-watch-infos");
                        wrapperDiv = containerDiv.querySelector('.wrapper')
                        wrapperDiv.innerHTML = '';
                    }
                    infosDiv.innerText = data['sortOption'] + " / " + data['orderOption'] + " x " + data['limitOption'];
                    blocks.forEach((block, index) => {
                        wrapperDiv.insertAdjacentHTML('beforeend', block);
                        const posterDiv = wrapperDiv.querySelector(".card:last-child").querySelector(".poster");
                        const numberDiv = document.createElement("div");
                        numberDiv.classList.add("number");
                        numberDiv.innerText = (index + 1).toString()
                        posterDiv.appendChild(numberDiv);
                    });
                    new UserList(self.flashMessage, self.toolTips);
                    this.element.classList.remove('disabled');
                });
        });
    }
}