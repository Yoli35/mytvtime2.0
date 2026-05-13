let seriesArr = [];
let updateCount = 0;
document.addEventListener("DOMContentLoaded", () => {
    init();
});

function init() {
    console.log("Tools loaded");

    initZipCommand();
    initCheckPosters();
}

function initZipCommand() {
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
            const diffMinutes = calcMinutes(value);

            console.log(diffMinutes);

            textarea.value = diffMinutes.toString();
        });

        genButton.addEventListener("click", (e) => {
            e.preventDefault();
            const value = dateInput.value;
            const diffMinutes = calcMinutes(value);

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

function initCheckPosters() {
    console.log("initCheckPosters");
    const checkPostersForm = document.getElementById("check-posters");
    const startInput = checkPostersForm.querySelector("input[name='start']");

    fetchSeries(parseInt(startInput.value) || 0);

    checkPostersForm.addEventListener("submit", (e) => {
        e.preventDefault();

        const adjustButton = checkPostersForm.querySelector("button[value='adjust']");
        const checkButton = checkPostersForm.querySelector("button[value='check']");

        adjustButton.addEventListener("click", (e) => {
            e.preventDefault();
            fetchSeries(parseInt(e.target.value) || 0);
        });

        checkButton.addEventListener("click", (e) => {
            e.preventDefault();
            checkSeriesPosters(0);
        })
    });
}

function checkSeriesPosters(index) {
    const posterUpdateDiv = document.querySelector('.form-results.poster-update');
    if (index >= seriesArr.length) {
        console.log("All series checked");
        const newItem = document.createElement('div');
        newItem.classList.add('item');
        newItem.innerHTML = `
                <span>All series checked, ${updateCount} series updated.</span>
                <span class="${updateCount ? 'updated' : 'no-change'}"></span>
            `;
        posterUpdateDiv.appendChild(newItem);
        newItem.scrollIntoView({behavior: 'smooth', inline: 'end'});
        return;
    }
    if (!posterUpdateDiv) {
        console.error("Poster update div not found");
        return;
    }
    const lang = document.querySelector("html").getAttribute("lang");
    const series = seriesArr[index];
    fetch('/' + lang + '/admin/tools/check/posters/check', {
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
            updateCount += data.update ? 1 : 0;
            const newItem = document.createElement('div');
            newItem.classList.add('item');
            newItem.innerHTML = `
                <img src="${data.poster_path}" alt="${series['localized_name'] ?? series['name']} Poster">
                <span>${series['localized_name'] ?? series['name']}</span>
                <span class="${data.update ? 'updated' : 'no-change'}"></span>
            `;
            posterUpdateDiv.appendChild(newItem);
            newItem.scrollIntoView({behavior: 'smooth', inline: 'end'});
            checkSeriesPosters(index + 1);
        })
        .catch(error => {
            console.error(`Error checking series ${seriesArr[index].id}:`, error);
            checkSeriesPosters(index + 1);
        });
}
function fetchSeries(startDay) {
    const lang = document.querySelector("html").getAttribute("lang");
    fetch('/' + lang + '/admin/tools/check/posters/adjust', {
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
            document.querySelector('.series-count').textContent = data.seriesArr.length;
            seriesArr = data.seriesArr;
        })
        .catch(error => {
            console.error('Error fetching series:', error);
        });
}

function calcMinutes(value) {
    console.log(value);
    const inputDate = new Date(value);
    const now = new Date();

    const diffMs = now - inputDate;
    const diffMinutes = Math.floor(diffMs / 60000);

    console.log(diffMinutes);

    return diffMinutes;
}