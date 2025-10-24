document.addEventListener("DOMContentLoaded", () => {
    const globsContent = document.querySelector(".globs").textContent;
    console.log(globsContent);
    const globs = JSON.parse(globsContent);
    const apiModules = globs['api_modules'];
    console.log(apiModules);
    init();
});

function init() {
    console.log("Api loaded");

    const modules = ""

    const apiForm = document.getElementById("api-form");
    const moduleSelect = apiForm.querySelector("#module-select");
    const formTabs = apiForm.querySelectorAll(".form-tab");
    const textarea = apiForm.querySelector("#results");
    const copyButton = apiForm.querySelector("button[value='copy']");

    moduleSelect.addEventListener("change", (e) => {
        e.preventDefault();
        const value = e.target.value;
        const selector = ".form-tab[data-for='" + value + "']";
        const newActiveTav = apiForm.querySelector(selector);
        formTabs.forEach(tab => {
            tab.classList.remove("active");
        });
        newActiveTav.classList.add("active");
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

    apiForm.addEventListener("submit", (e) => {
        e.preventDefault();

        const execButton = apiForm.querySelector("button[value='exec']");

        execButton.addEventListener("click", (e) => {
            e.preventDefault();
            const moduleValue = moduleSelect.value;

            console.log(moduleValue);

            textarea.value = 'Module value: ' + moduleValue;
        });
    });
}
