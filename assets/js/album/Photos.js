import {Diaporama} from "Diaporama";

let self = null;

export class Photos {
    constructor() {
        self = this;
        this.diaporama = new Diaporama()

        this.init();
    }

    init() {
        const photosWrapperDiv = document.querySelector('.photos .wrapper');
        const imgs = photosWrapperDiv.querySelectorAll('img');
        this.diaporama.start(imgs)
    }
}