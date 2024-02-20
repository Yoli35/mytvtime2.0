export class ProviderSelect {
    constructor() {
        this.select = document.querySelector("#watch-providers");
    }

    init() {
        this.select.addEventListener("change", () => {
            const provider = this.select.value;
            const url = new URL(window.location.href);
            url.searchParams.set("provider", provider);
            window.location.href = url.href;
        });
    }
}