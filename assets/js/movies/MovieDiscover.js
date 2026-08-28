let self;

export class MovieDiscover {
    constructor(menu) {
        self = this;
        this.addGenre = this.addGenre.bind(this);
        this.setCountry = this.setCountry.bind(this);
        this.setLanguage = this.setLanguage.bind(this);
        this.fetchKeywords = this.fetchKeywords.bind(this);

        this.init(menu);
    }

    init(menu) {
        const discoverWithCastInput = document.querySelector("#discover-with-cast");
        discoverWithCastInput.addEventListener("input", menu.searchFetch);
        discoverWithCastInput.addEventListener("keydown", menu.searchMenuNavigate);
        const discoverWithCrewInput = document.querySelector("#discover-with-crew");
        discoverWithCrewInput.addEventListener("input", menu.searchFetch);
        discoverWithCrewInput.addEventListener("keydown", menu.searchMenuNavigate);
        const discoverWithGenresSelect = document.querySelector("#discover-with-genres");
        discoverWithGenresSelect.addEventListener("change", this.addGenre);
        const discoverOriginCountrySelect = document.querySelector("#discover-origin-country");
        discoverOriginCountrySelect.addEventListener("change", this.setCountry);
        const discoverOriginLanguageSelect = document.querySelector("#discover-origin-language");
        discoverOriginLanguageSelect.addEventListener("change", this.setLanguage);
        const discoverWithKeywordsInput = document.querySelector("#discover-with-keywords");
        discoverWithKeywordsInput.addEventListener("input", this.fetchKeywords);

    }

    addGenre(event) {
        const select = event.target;
        console.log(select.value);

        // <div class="discover-item" data-code="1190668">Timothée Chalamet</div>
        const discoverItemDiv = document.createElement("div");
        discoverItemDiv.classList.add("discover-item");
        discoverItemDiv.setAttribute("data-code", select.value);
        const option = select.querySelector('option[value="' + select.value + '"]');
        discoverItemDiv.textContent = option.textContent;
        const discoverWithGenreListFilters = document.querySelector(".discover-with-genre-list");
        discoverWithGenreListFilters.appendChild(discoverItemDiv);
        // <div class="discover-item" data-code="1190668">Timothée Chalamet<div class="close">{{ ux_icon('mdi:close') }}</div></div>
        const discoverWithGenreList = select.parentElement.parentElement.querySelector(".discover-with-genre-list");
        const discoverItemWithCloseDiv = discoverItemDiv.cloneNode(true);
        const closeDiv = document.createElement("div");
        closeDiv.classList.add("close");
        const closeSVG = document.querySelector("#svgs .svg-close svg").cloneNode(true);
        closeDiv.appendChild(closeSVG);
        closeDiv.addEventListener("click", () => {
            discoverWithGenreListFilters.removeChild(discoverItemDiv);
            discoverWithGenreList.removeChild(discoverItemWithCloseDiv);
        });
        discoverItemWithCloseDiv.appendChild(closeDiv);
        discoverWithGenreList.appendChild(discoverItemWithCloseDiv);

        select.value = "all";
    }

    setCountry(event) {
        const select = event.target;
        // <div class="discover-item" data-code="FR">France<div class="close">{{ ux_icon('mdi:close') }}</div></div>
        const discoverWithCountryFilters = document.querySelector(".discover-origin-country");
        const existingItem = discoverWithCountryFilters.querySelector(".discover-item");
        if (existingItem) existingItem.remove();
        if (select.value === "all") return;

        const discoverItemDiv = document.createElement("div");
        discoverItemDiv.classList.add("discover-item");
        discoverItemDiv.setAttribute("data-code", select.value);
        const option = select.querySelector('option[value="' + select.value + '"]');
        discoverItemDiv.textContent = option.textContent;
        discoverWithCountryFilters.appendChild(discoverItemDiv);
    }

    setLanguage(event) {
        const select = event.target;
        // <div class="discover-item" data-code="fr">Français<div class="close">{{ ux_icon('mdi:close') }}</div></div>
        const discoverWithCountryFilters = document.querySelector(".discover-origin-language");
        const existingItem = discoverWithCountryFilters.querySelector(".discover-item");
        if (existingItem) existingItem.remove();
        if (select.value === "all") return;

        const discoverItemDiv = document.createElement("div");
        discoverItemDiv.classList.add("discover-item");
        discoverItemDiv.setAttribute("data-code", select.value);
        const option = select.querySelector('option[value="' + select.value + '"]');
        discoverItemDiv.textContent = option.textContent;
        discoverWithCountryFilters.appendChild(discoverItemDiv);
    }

    fetchKeywords(event) {
        const input = event.target;
        const query = input.value;

        fetch("/api/keywords/search",{
                method: 'POST',
                headers: {
                    accept: 'application/json'
                },
                body: JSON.stringify({query: query})
            })
            .then(response => response.json())
            .then(data => {
                console.log(data);
                const ul = input.closest(".form-field").querySelector("ul");
                const lis = ul.querySelectorAll("li");
                lis.forEach(li => li.remove());

                data['keywords'].forEach(keyword => {
                    const li = document.createElement("li");
                    li.classList.add("keyword");
                    li.textContent = keyword.name;
                    li.setAttribute("data-id", keyword.id);
                    li.addEventListener("click", () => {
                        input.value = "";
                        input.focus();
                        const lis = ul.querySelectorAll("li");
                        lis.forEach(li => li.remove());
                        self.addKeyword(input, keyword);
                    });
                    ul.appendChild(li);
                });
            })
            .catch(error => {
                console.error('Error:', error);
            });
    }

    addKeyword(input, {id, name}) {
        // <div class="discover-item" data-code="13141">based on manga</div>
        const discoverItemDiv = document.createElement("div");
        discoverItemDiv.classList.add("discover-item");
        discoverItemDiv.setAttribute("data-code", id.toString());
        discoverItemDiv.textContent = name.toString();
        const discoverWithKeywordListFilters = document.querySelector(".discover-with-keyword-list");
        discoverWithKeywordListFilters.appendChild(discoverItemDiv);
        // <div class="discover-item" data-code="13141">based on manga<div class="close">{{ ux_icon('mdi:close') }}</div></div>
        const discoverWithKeywordList = input.parentElement.parentElement.querySelector(".discover-with-keyword-list");
        const discoverItemWithCloseDiv = discoverItemDiv.cloneNode(true);
        const closeDiv = document.createElement("div");
        closeDiv.classList.add("close");
        const closeSVG = document.querySelector("#svgs .svg-close svg").cloneNode(true);
        closeDiv.appendChild(closeSVG);
        closeDiv.addEventListener("click", e => {
            discoverWithKeywordListFilters.removeChild(discoverItemDiv);
            discoverWithKeywordList.removeChild(discoverItemWithCloseDiv);
        });
        discoverItemWithCloseDiv.appendChild(closeDiv);
        discoverWithKeywordList.appendChild(discoverItemWithCloseDiv);
    }
}