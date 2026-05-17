
let self;
export class AdminTools {
    constructor(toolTips) {
        self = this;
        self.lang = document.documentElement.lang;
        self.toolTips = toolTips;

        self.seriesArr = [];
        self.updateCount = 0;

        self.init();
    }

    init() {
        console.log("Tools loaded");

        this.initZipCommand();
        this.initCheckPosters();
    }

    initZipCommand() {
        console.log("initZipCommand");
        const findZipForm = document.getElementById("find-zip-command");

        findZipForm.addEventListener("submit", (e) => {
            e.preventDefault();

            const calcButton = findZipForm.querySelector("button[value='calc']");
            const genButton = findZipForm.querySelector("button[value='gen']");
            const copyButton = findZipForm.querySelector("button[value='copy']");
            const dateInput = findZipForm.querySelector("input");
            const textarea = findZipForm.querySelector("textarea");

            calcButton.addEventListener("click", (e) => {
                e.preventDefault();
                const value = dateInput.value;
                const diffMinutes = self.calcMinutes(value);

                console.log(diffMinutes);

                textarea.value = diffMinutes.toString();
            });

            genButton.addEventListener("click", (e) => {
                e.preventDefault();
                const value = dateInput.value;
                const diffMinutes = self.calcMinutes(value);

                textarea.value = "find './public/' -type f -mmin -" + diffMinutes + " -exec zip -q -n .png:.webp:.jpg:.jpeg:.mp4 public.zip {} \\;";
            });

            copyButton.addEventListener("click", (e) => {
                e.preventDefault();

                navigator.clipboard.writeText(textarea.value).then(() => {
                    copyButton.classList.add("success");
                    setTimeout(() => {
                        copyButton.classList.remove("success");
                    }, 2000);
                }).catch(err => {
                    console.error('Error copying text: ', err);
                });
            });
        });
    }

    initCheckPosters() {
        console.log("initCheckPosters");
        const checkPostersForm = document.getElementById("check-posters");
        const startInput = checkPostersForm.querySelector("input[name='start']");

        self.fetchSeries(parseInt(startInput.value) || 0);

        checkPostersForm.addEventListener("submit", (e) => {
            e.preventDefault();

            const adjustButton = checkPostersForm.querySelector("button[value='adjust']");
            const checkButton = checkPostersForm.querySelector("button[value='check']");

            adjustButton.addEventListener("click", (e) => {
                e.preventDefault();
                self.fetchSeries(parseInt(e.target.value) || 0);
            });

            checkButton.addEventListener("click", (e) => {
                e.preventDefault();
                self.checkSeriesPosters(0);
            })
        });
    }

    checkSeriesPosters(index) {
        const posterUpdateDiv = document.querySelector('.form-results.poster-update');
        if (index >= self.seriesArr.length) {
            console.log("All series checked");
            const newItem = document.createElement('div');
            const count = self.updateCount;
            newItem.classList.add('item');
            newItem.innerHTML = `
                <span>All series checked, ${count} series updated.</span>
                <span class="${count ? 'updated' : 'no-change'}"></span>
            `;
            posterUpdateDiv.appendChild(newItem);
            newItem.scrollIntoView({behavior: 'smooth', inline: 'end'});
            return;
        }
        if (!posterUpdateDiv) {
            console.error("Poster update div not found");
            return;
        }

        if (index === 0) self.updateCount = 0;

        const series = self.seriesArr[index];
        fetch('/' + self.lang + '/admin/tools/check/posters/check', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                series: series,
            }),
        })
            .then(response => response.json())
            .then(data => {
                console.log(data);
                self.updateCount += data.update ? 1 : 0;
                const newItem = document.createElement('div');
                newItem.classList.add('item');
                newItem.innerHTML = `
                <div id="series-${series['tmdb_id']}" data-title="${series['localized_name'] ?? series['name']}">
                    <img src="${data.poster_path}" alt="${series['localized_name'] ?? series['name']} Poster">
                </div>
                <span>${series['localized_name'] ?? series['name']}</span>
                <span class="${data.update ? 'updated' : 'no-change'}"></span>
            `;
                posterUpdateDiv.appendChild(newItem);
                self.toolTips.initElement(posterUpdateDiv.querySelector('#series-' + series['tmdb_id']));
                newItem.scrollIntoView({behavior: 'smooth', inline: 'end'});
                self.checkSeriesPosters(index + 1);
            })
            .catch(error => {
                console.error(`Error checking series ${self.seriesArr[index].id}:`, error);
                self.checkSeriesPosters(index + 1);
            });
    }

    fetchSeries(startDay) {
        fetch('/' + self.lang + '/admin/tools/check/posters/adjust', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                startDay: startDay,
            }),
        })
            .then(response => response.json())
            .then(data => {
                console.log(data);
                document.querySelector('.series-count').textContent = data.seriesArr.length.toString();
                self.seriesArr = data.seriesArr;
            })
            .catch(error => {
                console.error('Error fetching series:', error);
            });
    }

    calcMinutes(value) {
        console.log(value);
        const inputDate = new Date(value);
        const now = new Date();

        const diffMs = now - inputDate;
        const diffMinutes = Math.floor(diffMs / 60000);

        console.log(diffMinutes);

        return diffMinutes;
    }
}