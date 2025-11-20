// import {Menu} from "Menu";

export class AddCast {
    constructor() {
        this.lang = document.documentElement.lang;
    }

    init(menu) {
        const peopleSearchBlockDiv = document.querySelector('.cast-search-block');
        if (peopleSearchBlockDiv) {
            const addCastButton = document.querySelector('.add-cast-button');
            const characterNameInput = peopleSearchBlockDiv.querySelector('#character-name');
            const peopleSearchInput = peopleSearchBlockDiv.querySelector('#cast-search');
            const form = peopleSearchBlockDiv.querySelector("#cast-search-form");
            // const submitButton = peopleSearchInput.querySelector('button[name="submit"]');

            peopleSearchInput.addEventListener("input", menu.searchFetch);
            peopleSearchInput.addEventListener("keydown", menu.searchMenuNavigate);

            addCastButton.addEventListener('click', () => {
                peopleSearchBlockDiv.classList.toggle('active');
                if (peopleSearchBlockDiv.classList.contains('active')) {
                    characterNameInput.focus();
                }
            });

            form.addEventListener('submit', (e) => {
                e.preventDefault();
                const characterName = form.querySelector('#character-name').value;
                const seriesId = form.querySelector("#cast-search-series-id").value;
                const seasonNumber = form.querySelector("#cast-search-season-number").value;
                const personId = form.querySelector("#cast-search-person-id").value;
                const baseHref = "/" + this.lang + "/";
                window.location.href = baseHref + 'series/cast/add/' + seriesId + '/' + seasonNumber + '/' + personId + '?name=' + encodeURIComponent(characterName);
            });
        }
    }
}