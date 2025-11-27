import {AverageColor} from "AverageColor";
import {NavBar} from "NavBar";
import {ToolTips} from "ToolTips";

/**
 * @typedef Globs
 * @type {Object}
 * @property {Array} highlightedSeries
 * @property {Array} watchProviders
 * @property {Array} app_home_load_provider_series
 * @property {String} app_series_tmdb
 */

export class HighlightSeries {
    constructor() {
        this.count = 0;
        this.fetchCount = 0;
        this.highlightDiv = null;
        this.highlightProgressDiv = null;
        this.interval = null;
        this.intervalDuration = 20000;
        this.maxDisplayPerPoster = 1;
        this.navBarColor = new NavBar().navBarColor;
        this.posterListDiv = null;
        this.root = null;
        this.series = [];
        this.seriesIndex = 0;
        this.seriesIndexes = [];
        this.timeToChangeSeries = false;
        this.toolTips = new ToolTips();
        this.transition = 300;
    }

    /** @param {Globs} globs */
    init(globs) {
        this.series = globs.highlightedSeries;
        this.app_series_tmdb = globs.app_series_tmdb;
        this.count = this.series.length;
        this.root = document.documentElement;
        this.home = document.querySelector(".home");
        this.highlightDiv = document.querySelector(".highlighted-series");
        this.highlightProgressDiv = document.querySelector(".highlight-progress");
        this.posterListDiv = document.querySelector(".poster-list");
        this.loadingDiv = document.querySelector(".loading");
        /*this.loadingV2Div = null;
        this.loadingV2Rect = null;*/
        this.averageColor = new AverageColor();
        let duration = this.root.style.getPropertyValue("--highlight-duration");
        let transition = this.root.style.getPropertyValue("--highlight-transition");
        if (duration.length) { // s
            this.intervalDuration = duration.slice(0, -1) * 1000;
        }
        if (transition.length) { // ms
            this.transition = transition.slice(0, -2) * 1;
        }

        this.displaySeries();

        /*this.loadingV2();

        new ResizeObserver(() => {
            this.loadingV2Rect = this.loadingV2Div.getBoundingClientRect();
        }).observe(this.highlightDiv);*/
    }

    displaySeries() {
        this.series.forEach((series) => {
            series['thumb'] = this.highlightDiv.querySelector("#thumb-" + series['id']);
            series['countDiv'] = series['thumb'].parentElement.querySelector(".count");
            series['count'] = 0;
        });
        for (let i = 0; i < this.count; i++) {
            this.seriesIndexes.push(i);
        }

        this.seriesIndex = Math.floor(Math.random() * this.count);
        this.setSeries(this.seriesIndex);
        this.interval = setInterval(this.cycle.bind(this), this.intervalDuration);
    }

    cycle() {
        this.highlightDiv.querySelector('.poster').classList.remove('show');
        this.highlightDiv.querySelector('.details').classList.remove('show');
        this.highlightProgressDiv.classList.remove('show');
        if (this.timeToChangeSeries) {
            clearInterval(this.interval);
            this.loadNewSeries();
            return;
        }
        setTimeout(() => {
            this.series[this.seriesIndex]['thumb'].classList.remove('active');
            this.seriesIndex = this.seriesIndexes[Math.floor(Math.random() * this.seriesIndexes.length)];
            this.setSeries(this.seriesIndex);
        }, this.transition);
    }

    setSeries() {
        const poster = this.highlightDiv.querySelector(".poster");
        const posterImg = poster.querySelector("img");
        const aPoster = poster.querySelector("a");
        const nameDiv = this.highlightDiv.querySelector(".name");
        const aDetails = this.highlightDiv.querySelector(".details").querySelector("a");
        const overviewDiv = this.highlightDiv.querySelector(".overview");
        const providerDiv = this.highlightDiv.querySelector(".providers").querySelector(".wrapper");
        const series = this.series[this.seriesIndex];
        const link = this.app_series_tmdb + series['id'] + "-" + series['slug'];

        poster.style.rotate = (5 * (Math.random() - .5)) + "deg";

        posterImg.src = series['poster_path'];
        aPoster.href = link;
        posterImg.alt = series['name'];
        aDetails.href = link;
        posterImg.title = series['name'];
        nameDiv.textContent = series['name'] + (series['year'] ? " (" + series['year'] + ")" : "");
        if (series['date']) nameDiv.setAttribute('data-title', series['date']);
        overviewDiv.textContent = series['overview'];
        providerDiv.innerHTML = "";
        series['watch_providers'].forEach(provider => {
            const div = document.createElement("div");
            const img = document.createElement("img");
            const name = document.createElement("div");
            div.classList.add('provider');
            img.src = provider['logo_path'];
            img.alt = provider['provider_name'];
            img.title = provider['provider_name'];
            name.innerText = provider['provider_name'];
            name.classList.add('name');
            div.appendChild(img);
            div.appendChild(name);
            providerDiv.appendChild(div);
        });
        this.toolTips.init(this.highlightDiv, "highlight");
        posterImg.onload = () => {
            const color = this.averageColor.getColor(posterImg);
            const hsl = this.averageColor.rgbToHsl(color);

            this.navBarColor(hsl);

            const toolTips = document.querySelector(".tool-tips.highlight");
            if (toolTips) {
                this.root.style.setProperty("--tooltips-bg", "hsl(" + hsl.h + ", " + hsl.s + "%, 50%)");
                this.root.style.setProperty("--tooltips-color", "hsl(" + hsl.h + ", " + hsl.s + "%, 80%)");
            }

            this.root.style.setProperty("--highlight-progress", "hsl(" + ((hsl.h + 180) % 360) + ", " + hsl.s + "%, " + (hsl.l < 95 ? hsl.l + 5 : 100) + "%)");
            this.root.style.setProperty("--highlight-head", "hsl(" + ((hsl.h + 180) % 360) + ", " + hsl.s + "%, " + (hsl.l < 75 ? hsl.l + 25 : 100) + "%)");
            this.root.style.setProperty("--highlight-bg", "hsl(" + ((hsl.h + 180) % 360) + ", " + hsl.s + "%, " + hsl.l + "%)");
            this.root.style.setProperty("--highlight-color", "hsl(" + ((hsl.h + 180) % 360) + ", " + hsl.s + "%, " + (hsl.l > 60 ? "10" : "90") + "%)");
            this.highlightDiv.querySelector('.poster').classList.add('show');
            this.highlightDiv.querySelector('.details').classList.add('show');
            this.highlightProgressDiv.classList.add('show');
        };

        this.series[this.seriesIndex]['thumb'].classList.add('active');
        const div = document.createElement("div");
        this.series[this.seriesIndex]['countDiv'].appendChild(div);
        this.series[this.seriesIndex]['count']++;

        if (this.series[this.seriesIndex]['count'] >= this.maxDisplayPerPoster) {
            let index = this.seriesIndexes.indexOf(this.seriesIndex);
            this.seriesIndexes.splice(index, 1);
            // console.log(this.seriesIndexes);
            this.timeToChangeSeries = this.seriesIndexes.length === 0;
        }
    }

    loadNewSeries() {
        this.loadingDiv.classList.add('show');
        fetch('/load-new-series', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            }
        }).then(response => {
            return response.json();
        }).then(data => {
            if (data['status'] === 'success') {
                this.posterListDiv.replaceChildren();
                this.series = data['series'];
                this.count = this.series.length;
                for (let i = 0; i < this.count; i++) {
                    this.seriesIndexes.push(i);
                }
                this.series.forEach((series) => {
                    const a = document.createElement("a");
                    const div = document.createElement("div");
                    const thumb = document.createElement("div");
                    const img = document.createElement("img");
                    const count = document.createElement("div");
                    a.href = this.app_series_tmdb + series['id'] + "-" + series['slug'];
                    div.classList.add('item');
                    thumb.classList.add('poster-item');
                    thumb.id = "thumb-" + series['id'];
                    img.src = series['poster_path'];
                    img.alt = series['name'];
                    count.classList.add('count');
                    thumb.appendChild(img);
                    div.appendChild(thumb);
                    div.appendChild(count);
                    a.appendChild(div);
                    this.posterListDiv.appendChild(a);
                    series['thumb'] = this.highlightDiv.querySelector("#thumb-" + series['id']);
                    series['countDiv'] = series['thumb'].parentElement.querySelector(".count");
                    series['count'] = 0;
                });
                this.fetchCount++;
                const div = document.createElement("div");
                div.classList.add('item');
                div.classList.add('counter');
                const counter = document.createElement("div");
                counter.innerText = this.fetchCount;
                div.appendChild(counter);
                this.posterListDiv.insertBefore(div, this.posterListDiv.firstChild);
                this.loadingDiv.classList.remove('show');

                this.seriesIndex = Math.floor(Math.random() * this.count);
                this.setSeries(this.seriesIndex);
                this.interval = setInterval(this.cycle.bind(this), this.intervalDuration);
            }
        });
    }

    /*loadingV2() {

        const ballSize = 32;
        const ballGap = 16;
        const ballCount = 5 * 5;
        const rowCount = Math.ceil(Math.sqrt(ballCount));
        const rowHalf = Math.floor(rowCount / 2);
        const collisionDistance = 2.5 * ballGap;

        /!*  <div class="loading-v2">
                <div class="ball-container">
                </div>
            </div> *!/
        this.loadingV2Div = document.createElement("div");
        this.loadingV2Div.classList.add("loading-v2");
        const ballContainer = document.createElement("div");
        ballContainer.classList.add("ball-container");
        this.loadingV2Div.appendChild(ballContainer);
        this.highlightDiv.appendChild(this.loadingV2Div);
        this.loadingV2Rect = this.loadingV2Div.getBoundingClientRect();

        // ballContainer = this.loadingV2Div.querySelector(".ball-container");
        for (let i = 0; i < ballCount; i++) {
            const ball = document.createElement("div");
            ball.classList.add("ball");
            ball.style.width = ballSize + "px";
            ball.style.height = ballSize + "px";
            ballContainer.appendChild(ball);
        }
        const balls = this.loadingV2Div.querySelectorAll(".ball");
        let loadingV2Rect = this.loadingV2Rect;
        const centerX = (loadingV2Rect.width + ballSize) / 2;
        const centerY = (loadingV2Rect.height + ballSize) / 2;
        // on met les balles au centre de la div
        balls.forEach((ball, index) => {
            ball.style.left = (((ballGap + ballSize) * ((index % rowCount) - rowHalf)) + centerX - ballSize / 2) + "px";
            ball.style.top = (((ballGap + ballSize) * (Math.floor(index / rowCount) - rowHalf)) + centerY - ballSize / 2) + "px";
            ball.style.backgroundColor = "hsl(" + Math.floor(Math.random() * 360) + ", 50%, 75%)";
        });
        const ballsSpeed = [];
        const ballsDirection = [];
        balls.forEach((ball, index) => {
            ballsSpeed[index] = 1 + 4 * Math.random();
            ballsDirection[index] = {
                x: Math.random() * 2 - 1,
                y: Math.random() * 2 - 1
            };
        });

        const move = () => {

            balls.forEach((ball, index) => {
                ball.collisionTest = [index];
                const left = ball.style.left.slice(0, -2) * 1;
                const top = ball.style.top.slice(0, -2) * 1;
                ball.rect = {
                    bottom: top + ballSize,
                    left: left,
                    right: left + ballSize,
                    top: top,
                    center: {
                        x: left + ballSize / 2,
                        y: top + ballSize / 2
                    }
                };
            });
            balls.forEach((ball, index) => {

                loadingV2Rect = this.loadingV2Rect;

                const maxTop = loadingV2Rect.height - ballSize;
                const maxLeft = loadingV2Rect.width - ballSize;
                const ballRect = balls[index].rect;

                for (let i = 0; i < ballCount; i++) {
                    if (!ball.collisionTest.includes(i)) {
                        ball.collisionTest.push(i);
                        balls[i].collisionTest.push(index);

                        const otherBall = balls[i];
                        const otherBallRect = otherBall.rect;

                        const dx = ballRect.center.x - otherBallRect.center.x;
                        const dy = ballRect.center.y - otherBallRect.center.y;
                        const distance = Math.sqrt(dx * dx + dy * dy);
                        if (distance <= collisionDistance) {
                            const normalX = dx / collisionDistance;
                            const normalY = dy / collisionDistance;
                            const relativeVelocity = {
                                x: ballsSpeed[index] * ballsDirection[index].x - ballsSpeed[i] * ballsDirection[i].x,
                                y: ballsSpeed[index] * ballsDirection[index].y - ballsSpeed[i] * ballsDirection[i].y
                            };
                            const velocityAlongNormal = relativeVelocity.x * normalX + relativeVelocity.y * normalY;
                            const restitution = 1.5;
                            const impulse = velocityAlongNormal / restitution;
                            ballsDirection[index].x -= impulse * normalX;
                            ballsDirection[index].y -= impulse * normalY;
                            ballsDirection[i].x += impulse * normalX;
                            ballsDirection[i].y += impulse * normalY;
                        }
                    }
                }

                const x = Math.abs(ballsDirection[index].x);
                const y = Math.abs(ballsDirection[index].y);
                if (x > 2) {
                    ballsDirection[index].x *= .85;
                } else if (x < 1) {
                    ballsDirection[index].x *= 1.2;
                } else {
                    ballsDirection[index].x *= .99;
                }
                if (y > 2) {
                    ballsDirection[index].y *= .85;
                } else if (y < 1) {
                    ballsDirection[index].y *= 1.2;
                } else {
                    ballsDirection[index].y *= .99;
                }

                const gravity = 0.1;
                ballsDirection[index].y += gravity;

                ballRect.left += ballsSpeed[index] * ballsDirection[index].x;
                ballRect.top += ballsSpeed[index] * ballsDirection[index].y;

                if (ballRect.left <= 0) {
                    ballsDirection[index].x *= -1;
                    ballRect.left = 0;
                }
                if (ballRect.left >= maxLeft) {
                    ballsDirection[index].x *= -1;
                    ballRect.left = maxLeft;
                }
                if (ballRect.top <= 0) {
                    ballsDirection[index].y *= -1.1;
                    ballRect.top = 0;
                }
                if (ballRect.top >= maxTop) {
                    ballsDirection[index].y *= -.9;
                    ballRect.top = maxTop;
                }

                ball.style.left = ballRect.left + "px";
                ball.style.top = ballRect.top + "px";
            });
            window.requestAnimationFrame(move);
        };
        setTimeout(() => {
            window.requestAnimationFrame(move);
        }, 2000);
    }*/
}