let self;

export class AdminApi {

    constructor() {
        self = this;
        this.lang = document.documentElement.lang;
        this.apiModules = [];

        this.fetchModules = this.fetchModules.bind(this);
        this.fetchDataGouv = this.fetchDataGouv.bind(this);
        this.initModules = this.initModules.bind(this);

        this.init();
    }

    init() {
        console.log("Api loaded");
        this.fetchModules();
        this.fetchDataGouv();
    }

    fetchModules() {
        fetch("/" + this.lang + "/admin/api/modules", {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            },
        })
            .then(res => res.json())
            .then(data => {
                console.log(data);
                self.apiModules = data['modules'];
                const toolDiv = document.querySelector(".tool.api");
                toolDiv.innerHTML = data['block'];
                self.initModules();
            })
            .catch(err => {
                console.error('Error getting module info ', err);
            });
    }

    initModules() {
        console.log("Api loaded");
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

    fetchDataGouv() {
        fetch("/" + this.lang + "/admin/api/data/gouv", {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            },
        })
            .then(res => res.json())
            .then(data => {
                console.log(data);
                const toolDiv = document.querySelector(".tool.data-gouv");
                const div = document.createElement('div');
                div.innerHTML = data['block'];
                toolDiv.appendChild(div);
                const url = data['url'];
                fetch('/api/admin/external/data/gouv', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({url: url}),
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data['status'] === 'error') {
                            console.log(data);
                            return;
                        }
                        console.log(data);
                        const table = toolDiv.querySelector(".data-gouv-table");
                        const tbody = table.querySelector("tbody");
                        tbody.innerHTML = '';
                        data.forEach(row => {
                            const tr = document.createElement("tr");
                            tr.innerHTML = `
                            <td>${row.nom}</td>
                            <td>${row.description}</td>
                            <td>${row.url}</td>
                        `
                            tbody.appendChild(tr);
                        });
                    })
            })
            .catch(err => {
                console.error('Error getting data.gouv info ', err);
            });
    }
}
