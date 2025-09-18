document.addEventListener("DOMContentLoaded", () => {
    init();
});

function init() {
    console.log("Tools loaded");

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

            textarea.value = "find ./public/ -type f -mmin -" + diffMinutes + " -exec zip -n .png:.webp:.jpg:.jpeg:.mp4 public.zip {} \\;";
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

function calcMinutes(value) {
    console.log(value);
    const inputDate = new Date(value);
    const now = new Date();

    const diffMs = now - inputDate;
    const diffMinutes = Math.floor(diffMs / 60000);

    console.log(diffMinutes);

    return diffMinutes;
}