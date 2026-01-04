export class AddCast {
    constructor() {
        this.lang = document.documentElement.lang;
    }

    init(menu, toolTips, flashMessage) {
        const peopleSearchBlockDiv = document.querySelector('.cast-search-block');
        if (peopleSearchBlockDiv) {
            const addCastButton = document.querySelector('.add-cast-button');
            const castSearchClose = peopleSearchBlockDiv.querySelector(".cast-search-close");
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

            castSearchClose.addEventListener('click', () => {
                peopleSearchBlockDiv.classList.remove('active');
            });

            form.addEventListener('submit', (e) => {
                e.preventDefault();
                toolTips.hide()
                const characterName = characterNameInput.value;
                const seriesId = form.querySelector("#cast-search-series-id").value;
                const seasonNumber = form.querySelector("#cast-search-season-number").value;
                const peopleId = form.querySelector("#cast-search-person-id").value;
                const url = "/" + this.lang + "/series/cast/add";

                fetch(url, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                    },
                    body: JSON.stringify({
                        id: seriesId,
                        seasonNumber: seasonNumber,
                        peopleId: peopleId,
                        characterName: characterName
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        const wrapperDiv = document.querySelector(".cast-and-name .cast .wrapper")
                        wrapperDiv.insertAdjacentHTML('beforeend', data['block']);
                        const lastAdded = wrapperDiv.querySelector("a:last-child");
                        lastAdded.scrollIntoView({behavior: 'smooth', inline: 'end'});
                        flashMessage.add('success', data['message']);
                        characterNameInput.value = '';
                        peopleSearchInput.value = '';
                        characterNameInput.focus();
                    })
                    .catch((error) => {
                        console.log(error);
                    });
            });
        }
    }
}