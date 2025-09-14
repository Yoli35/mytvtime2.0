import {Diaporama} from "Diaporama";

let gThis = null;

export class Photos {
    constructor() {
        gThis = this;
        this.diaporama = new Diaporama()

        this.init();
    }

    init() {
        const photosWrapperDiv = document.querySelector('.photos .wrapper');
        const imgs = photosWrapperDiv.querySelectorAll('img');
        this.diaporama.start(imgs)
    }
}