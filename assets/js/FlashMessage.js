let gThis
export class FlashMessage {
    constructor() {
        this.closeAll = this.closeAll.bind(this);
        this.dismisses = ['hide-scale', 'hide-to-left', 'hide-to-right', 'hide-to-top', 'hide-to-bottom'];
        this.dismissesCount = this.dismisses.length;
        if (!gThis) {
            this.start();
        }
    }

    start() {
        gThis = this;
        console.log('FlashMessage init');
        const flashMessagesDiv = document.querySelector('.flash-messages');
        flashMessagesDiv.addEventListener('click', this.closeAll);
        // Flash messages
        const flashes = document.querySelectorAll(".flash-message");

        flashes.forEach(flashMessageDiv => {
            this.setupCountdown(flashMessageDiv, this.dismisses[Math.floor(Math.random() * this.dismissesCount)]);
        });
    }

    closeAll(e) {
        e.currentTarget.removeEventListener('click', this.closeAll);
        const flashMessageDivs = document.querySelectorAll('.flash-message');
        flashMessageDivs.forEach((flashMessageDiv) => {
            const closeDiv = flashMessageDiv.querySelector('.closure-countdown, .close');
            closeDiv.click();
        });
        // Add vanishing class to flashMessagesDiv (currentTarget) after 200ms
        setTimeout(() => {
            e.currentTarget.classList.remove('vanishing');
        }, 200);
        // Wait a bit to allow the clicks to be processed
        setTimeout(() => {
            e.currentTarget.addEventListener('click', this.closeAll);
        }, 1500);
    }

    /**
     * @typedef Update
     * @type {object}
     * @property {string} name
     * @property {string} localized_name
     * @property {string} poster_path
     * @property {string} content
     */

    /**
     * Add a flash message
     * @param {string} status
     * @param {string|Update} message
     * @param {string} subMessage
     */
    add(status, message, subMessage = '') {
        const flashMessagesDiv = document.querySelector('.flash-messages');
        const svgXmark = document.querySelector('#svg-xmark').querySelector('svg').cloneNode(true);
        const dismissClass = this.dismisses[Math.floor(Math.random() * this.dismissesCount)];

        const flashMessageDiv = document.createElement('div');
        flashMessageDiv.classList.add('flash-message');
        flashMessageDiv.classList.add(status);

        if (status ==='update') {
            const posterDiv = document.createElement('div');
            posterDiv.classList.add('poster');
            if (message.poster_path) {
                const img = document.createElement('img');
                img.src = '/series/posters' + message.poster_path;
                img.alt = message.name;
                posterDiv.appendChild(img);
            } else {
                posterDiv.innerText = "No image";
            }
            flashMessageDiv.appendChild(posterDiv);
            const infosDiv = document.createElement('div');
            infosDiv.classList.add('infos');
            const nameDiv = document.createElement('div');
            nameDiv.innerHTML = message.name;
            if (message.localized_name) {
                nameDiv.innerHTML += ' | ' + message.localized_name;
            }
            infosDiv.appendChild(nameDiv);
            const contentDiv = document.createElement('div');
            contentDiv.classList.add('content');
            contentDiv.innerHTML = message.content;
            infosDiv.appendChild(contentDiv);
            flashMessageDiv.appendChild(infosDiv);
        } else {
            flashMessageDiv.innerHTML = '[' + new Date().toLocaleString() + '] ' + message + (subMessage.length ? ('<br>' + subMessage) : '');
        }
        flashMessagesDiv.appendChild(flashMessageDiv);
        // <div class="closure-countdown">
        //     <div>
        //         <i class="fa-solid fa-xmark"></i>
        //     </div>
        //     <div class="circle-start"></div>
        //     <div class="circle-end"></div>
        // </div>
        const closureCountdownDiv = document.createElement('div');
        closureCountdownDiv.classList.add('closure-countdown');
        const div1 = document.createElement('div');
        div1.innerHTML = svgXmark.outerHTML;
        closureCountdownDiv.appendChild(div1);
        const div2 = document.createElement('div');
        div2.classList.add('circle-start');
        closureCountdownDiv.appendChild(div2);
        const div3 = document.createElement('div');
        div3.classList.add('circle-end');
        closureCountdownDiv.appendChild(div3);
        flashMessageDiv.appendChild(closureCountdownDiv);

        this.setupCountdown(flashMessageDiv, dismissClass);

    }

    setupCountdown(flashMessageDiv, dismissClass) {
        const closure = flashMessageDiv.querySelector('.closure-countdown');
        const closureEnd = flashMessageDiv.querySelector('.circle-end');
        closure.addEventListener('click', () => {
            this.close(flashMessageDiv, dismissClass);
        });
        const start = new Date();
        const i = setInterval(() => {
            const now = new Date();
            const progress = 360 * (1 - ((now - start) / 30000) % 1);
            closure.style.backgroundImage = `conic-gradient(var(--clr) 0deg, var(--clr) ${progress}deg, var(--cd) ${progress}deg, var(--cd) 360deg)`;
            closureEnd.style.transform = `rotate(${progress}deg) translateY(-.6875rem)`;
        }, 100);
        setTimeout(() => {
            clearInterval(i);
            this.close(flashMessageDiv, dismissClass);
        }, 30000);
    }

    close(flash, dismissClass) {
        setTimeout(() => {
            flash.classList.add(dismissClass);
        }, 0);
        setTimeout(() => {
            flash.classList.add("d-none");
            flash.remove();
        }, 500);
    }
}