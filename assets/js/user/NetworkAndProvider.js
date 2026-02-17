let self;

export class NetworkAndProvider {
    constructor(isNetwork) {
        self = this;
        const locale = document.querySelector('html').lang;
        if (isNetwork) {
            this.url = `/${locale}/user/network/toggle/`;
        } else {
            this.url = `/${locale}/user/provider/toggle/`;
        }
        this.init();
    }

    init() {
        this.filter();
        this.seeSelected();
        this.toggleProvider();
    }

    filter() {
        /** @type {HTMLInputElement} */
        const search = document.getElementById('provider-filter-search');
        const checkboxes = document.querySelectorAll('.provider__label');
        search.addEventListener('input', () => {
            const value = search.value.toLowerCase();
            console.log(value);
            checkboxes.forEach(checkbox => {
                const label = checkbox.textContent.trim().toLowerCase();
                if (label.includes(value)) {
                    checkbox.parentElement.style.display = 'flex';
                } else {
                    checkbox.parentElement.style.display = 'none';
                }
            });
        });
    }

    seeSelected() {
        /** @type {HTMLInputElement} */
        const selected = document.getElementById('provider-selected');
        /** @type {HTMLInputElement} */
        const search = document.getElementById('provider-filter-search');
        const providers = document.querySelector('.providers');
        const checkboxes = providers.querySelectorAll('input[type="checkbox"]');
        selected.addEventListener('change', () => {
            if (selected.checked) {
                search.value = '';
                checkboxes.forEach(checkbox => {
                    if (checkbox.checked) {
                        checkbox.parentElement.parentElement.style.display = 'flex';
                    } else {
                        checkbox.parentElement.parentElement.style.display = 'none';
                    }
                });
            } else {
                checkboxes.forEach(checkbox => {
                    checkbox.parentElement.parentElement.style.display = 'flex';
                });
            }
        });
    }

    toggleProvider() {
        const providers = document.querySelector('.providers');
        const checkboxes = providers.querySelectorAll('input[type="checkbox"]');
        const checked = providers.querySelectorAll('input[type="checkbox"]:checked');
        const selectedCount = document.getElementById('provider-selected-count');
        selectedCount.textContent = `(${checked.length})`;
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', () => {
                const providerId = checkbox.id.split('-')[1];
                fetch(self.url + providerId, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({}),
                }).then(response => {
                    if (response.ok) {
                        return response.json();
                    }
                    throw new Error('Network response was not ok.');
                }).then(data => {
                    const checked = providers.querySelectorAll('input[type="checkbox"]:checked');
                    selectedCount.textContent = `(${checked.length})`;
                    console.log(data);
                }).catch(error => {
                    console.error('There has been a problem with your fetch operation:', error);
                })
            });
        });
    }
}