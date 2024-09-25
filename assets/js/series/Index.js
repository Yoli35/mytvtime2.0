

export class Index {
    constructor() {
        this.init = this.init.bind(this);
        this.startDate = new Date();
        this.init();
    }

    init() {
        console.log("Index.js loaded");
        setInterval(() => {
            const now = new Date();
            console.log("Index.js has been running for " + ((now - this.startDate) / 60000).toFixed(0) + " minutes");
            if (now.getDate() !== this.startDate.getDate()) {
                location.reload();
            }
        }, 1000 * 60 *10); // 10 minutes
        // Si la fenêtre redevient active et si la date à changée, on recharge la page
        document.addEventListener("visibilitychange", () => {
            if (document.visibilityState === "visible" && new Date().getDate() !== this.startDate.getDate()) {
                location.reload();
            }
        });
    }
}