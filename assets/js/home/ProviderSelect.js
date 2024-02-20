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
            this.setProviderCookie(provider);
        });
    }

    // Save the selected provider in a cookie to keep it on the next visit
    setProviderCookie(provider) {
        const date = new Date();
        date.setTime(date.getTime() + 365 * 24 * 60 * 60 * 1000);
        document.cookie = "mytvtime_2_provider=" + provider + ";expires=" + date.toUTCString() + ";path=/";
    }
}