import {ToolTips} from "ToolTips";

let self = null;
export class VideoList {
    constructor() {
        self = this;
        this.tooltips = new ToolTips();
        this.init = this.init.bind(this);
    }

    init() {
        console.log('VideoList.js init');
        this.tooltips.init();
        this.initPagination();
    }

    initPagination() {
        console.log('VideoList.js pagination');
        // Let the <button class="page active">[n]</button> be visible in "<span class="page-buttons">...</span>"
        const spanPagers = document.querySelectorAll('span.page-buttons');
        // Lorsque le "<span>" est visible ou devient visible, on positionne le bouton actif au centre
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const activePageButton = entry.target.querySelector('button.active.page');
                    if (activePageButton) {
                    activePageButton.scrollIntoViewIfNeeded();
                    // activePageButton.scrollIntoView({behavior: "instant", container: "nearest", inline: "center"});
                    }
                }
            });
        });
        spanPagers.forEach(spanPager => {
            observer.observe(spanPager);
        });


            // const activePageButton = spanPager.querySelector('button.active.page');
            // if (activePageButton) {
                // activePageButton.scrollIntoViewIfNeeded();
                // activePageButton.scrollIntoView({behavior: "instant", container: "nearest", inline: "center"});
            // }
    }
}