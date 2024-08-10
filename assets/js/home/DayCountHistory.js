export class DayCountHistory {
    constructor() {
        this.select = document.querySelector("#day-count");
    }

    init() {
        this.select?.addEventListener("change", () => {
            const dayCount = this.select.value;
            // const url = new URL(window.location.href);
            this.setDayCountCookie(dayCount);
            // url.searchParams.set("daycount", dayCount);
            // window.location.href = url.href;
            window.location.reload();
        });
    }

    setDayCountCookie(dayCount) {
        const date = new Date();
        date.setTime(date.getTime() + 365 * 24 * 60 * 60 * 1000);
        document.cookie = "mytvtime_2_day_count=" + dayCount + ";expires=" + date.toUTCString() + ";path=/";
    }
}