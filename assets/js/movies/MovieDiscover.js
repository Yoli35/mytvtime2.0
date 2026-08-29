let self;

export class MovieDiscover {
    constructor(menu) {
        self = this;
        this.lang = document.querySelector("html").lang;
        this.addGenre = this.addGenre.bind(this);
        this.setCountry = this.setCountry.bind(this);
        this.setLanguage = this.setLanguage.bind(this);
        this.fetchKeywords = this.fetchKeywords.bind(this);
        this.switchSeparator = this.switchSeparator.bind(this);

        this.init(menu);
    }

    init(menu) {
        const form = document.querySelector("#discover-form");
        const discoverWithCastInput = document.querySelector("#discover-with-cast");
        discoverWithCastInput.addEventListener("input", menu.searchFetch);
        discoverWithCastInput.addEventListener("keydown", menu.searchMenuNavigate);
        const discoverWithCrewInput = document.querySelector("#discover-with-crew");
        discoverWithCrewInput.addEventListener("input", menu.searchFetch);
        discoverWithCrewInput.addEventListener("keydown", menu.searchMenuNavigate);
        const discoverWithGenresSelect = document.querySelector("#discover-with-genre");
        discoverWithGenresSelect.addEventListener("change", this.addGenre);
        const discoverRegionSelect = document.querySelector("#discover-region");
        discoverRegionSelect.addEventListener("change", this.setRegion);
        const discoverOriginCountrySelect = document.querySelector("#discover-origin-country");
        discoverOriginCountrySelect.addEventListener("change", this.setCountry);
        const discoverOriginLanguageSelect = document.querySelector("#discover-origin-language");
        discoverOriginLanguageSelect.addEventListener("change", this.setLanguage);
        const discoverWithKeywordsInput = document.querySelector("#discover-with-keyword");
        discoverWithKeywordsInput.addEventListener("input", this.fetchKeywords);
        const discoverAndOrSwitches = document.querySelectorAll(".discover-switch-and-or");
        discoverAndOrSwitches.forEach(discoverAndOrSwitch => {
            discoverAndOrSwitch.addEventListener("click", this.switchSeparator);
        });
        const releaseYearInput = document.querySelector("#discover-release-year");
        releaseYearInput.addEventListener("input", this.validateReleaseYear);
        const releaseYearBeforeInput = document.querySelector("#discover-release-year-lte");
        releaseYearBeforeInput.addEventListener("input", this.validateReleaseYear);
        const releaseYearAfterInput = document.querySelector("#discover-release-year-gte");
        releaseYearAfterInput.addEventListener("input", this.validateReleaseYear);
        const discoverSort = document.querySelector("#discover-sort");
        discoverSort.addEventListener("change", this.setSort);

        const discoverFoldButton = form.querySelector("#discover-fold");
        discoverFoldButton.addEventListener("click", () => {
            form.classList.toggle("folded");
        });

        const discoverSubmitButton = form.querySelector("#discover-submit");
        discoverSubmitButton.addEventListener("click", this.submit);
    }

    submit(event) {
        event.preventDefault();
        console.log("Search form submitting…");

        const form = document.querySelector("#discover-form");
        form.classList.toggle("folded");

        const regionItem = document.querySelector(".filters .discover-region .discover-item");
        const region = regionItem ? regionItem.dataset.code : null;
        const releaseYearBefore = document.querySelector("#discover-release-year-lte").value;
        const releaseYearAfter = document.querySelector("#discover-release-year-gte").value;
        const releaseYear = document.querySelector("#discover-release-year").value;
        const originCountryItem = document.querySelector(".filters .discover-origin-country .discover-item");
        const originCountry = originCountryItem ? originCountryItem.dataset.code : null;
        const originLanguageItem = document.querySelector(".filters .discover-origin-language .discover-item");
        const originLanguage = originLanguageItem ? originLanguageItem.dataset.code : null;
        const castItems = document.querySelectorAll(".filters .discover-with-cast-list .discover-item");
        const castSeparator = document.querySelector(".filters .discover-with-cast-list .discover-filter-switch").dataset.separator;
        const cast = [];
        for (const castItem of castItems) {
            cast.push(castItem.dataset.code);
        }
        const crewItems = document.querySelectorAll(".filters .discover-with-crew-list .discover-item");
        const crewSeparator = document.querySelector(".filters .discover-with-crew-list .discover-filter-switch").dataset.separator;
        const crew = [];
        for (const crewItem of crewItems) {
            crew.push(crewItem.dataset.code);
        }
        const keywordItems = document.querySelectorAll(".filters .discover-with-keyword-list .discover-item");
        const keywordSeparator = document.querySelector(".filters .discover-with-keyword-list .discover-filter-switch").dataset.separator;
        const keywords = [];
        for (const keywordItem of keywordItems) {
            keywords.push(keywordItem.dataset.code);
        }
        const genreItems = document.querySelectorAll(".filters .discover-with-genre-list .discover-item");
        const genreSeparator = document.querySelector(".filters .discover-with-genre-list .discover-filter-switch").dataset.separator;
        const genres = [];
        for (const genreItem of genreItems) {
            genres.push(genreItem.dataset.code);
        }
        const discoverSort = document.querySelector(".filters .discover-sort .discover-item").dataset.sort;

        const data = {
            "cast": cast,
            "castSeparator": castSeparator,
            "crew": crew,
            "crewSeparator": crewSeparator,
            "genreSeparator": genreSeparator,
            "genres": genres,
            "keywordSeparator": keywordSeparator,
            "keywords": keywords,
            "originCountry": originCountry,
            "originLanguage": originLanguage,
            "region": region,
            "releaseYear": releaseYear,
            "releaseYearAfter": releaseYearAfter,
            "releaseYearBefore": releaseYearBefore,
            "sort": discoverSort,
        };
        console.log(data);

        fetch('/api/movie/search/advanced', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data),
        })
        .then(response => response.json())
        .then(data => {
            console.log(data)
            const posterUrl = data.posterUrl;
            const results = data.results;
            const page = results.page;
            const totalPages = results.total_pages;
            const totalResults = results.total_results;
            const movies = results.results;
            console.log(page, totalPages, totalResults);
            const wrapper = document.querySelector(".movie-search-result .wrapper");
            wrapper.innerHTML = "";
            movies.forEach(movie => {
                console.log(posterUrl + movie.poster_path);

                const movieCard = document.createElement("div");
                movieCard.classList.add("movie-card");
                const a = document.createElement("a");
                a.href = `/${self.lang}/movie/tmdb/${movie.id}`;
                movieCard.appendChild(a);
                const posterDiv = document.createElement("div");
                posterDiv.classList.add("poster");
                if (!movie.poster_path) {
                    posterDiv.textContent = "No poster";
                } else {
                    const posterImg = document.createElement("img");
                    posterImg.src = posterUrl + movie.poster_path;
                    posterDiv.appendChild(posterImg);
                }
                a.appendChild(posterDiv);
                const infosDiv = document.createElement("div");
                infosDiv.classList.add("infos");
                const titleDiv = document.createElement("div");
                titleDiv.classList.add("title");
                titleDiv.textContent = movie.title;
                infosDiv.appendChild(titleDiv);
                a.appendChild(infosDiv);
                wrapper.appendChild(movieCard);
            });
        })
        .catch(error => console.error(error));
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

    setRegion(event) {
        self.setFilter(event, ".discover-region");
    }

    setCountry(event) {
        self.setFilter(event, ".discover-origin-country");
    }

    setLanguage(event) {
        self.setFilter(event, ".discover-origin-language");
    }

    setFilter(event, filter) {
        const select = event.target;
        // <div class="discover-item" data-code="fr">Français<div class="close">{{ ux_icon('mdi:close') }}</div></div>
        const discoverFilter = document.querySelector(filter);
        const existingItem = discoverFilter.querySelector(".discover-item");
        if (existingItem) existingItem.remove();
        if (select.value === "all") return;

        const discoverItemDiv = document.createElement("div");
        discoverItemDiv.classList.add("discover-item");
        discoverItemDiv.setAttribute("data-code", select.value);
        const option = select.querySelector('option[value="' + select.value + '"]');
        discoverItemDiv.textContent = option.textContent;
        discoverFilter.appendChild(discoverItemDiv);
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
        closeDiv.addEventListener("click", () => {
            discoverWithKeywordListFilters.removeChild(discoverItemDiv);
            discoverWithKeywordList.removeChild(discoverItemWithCloseDiv);
        });
        discoverItemWithCloseDiv.appendChild(closeDiv);
        discoverWithKeywordList.appendChild(discoverItemWithCloseDiv);
    }

    switchSeparator(event) {
        const switchDiv = event.target.parentElement;
        const blockClass = event.target.parentElement.parentElement.getAttribute("for");
        const text = event.target.textContent;
        const filter = document.querySelector('.filters .' + blockClass + '-list .discover-filter-switch');
        let separator = ",";
        console.log(blockClass);
        console.log(text);
        if (event.target.classList.contains("and")) {
            switchDiv.classList.add("and");
            switchDiv.classList.remove("or");
        } else {
            switchDiv.classList.add("or");
            switchDiv.classList.remove("and");
            separator = "|";
        }
        filter.textContent = text;
        filter.dataset.separator = separator;
    }

    validateReleaseYear(event) {
        const releaseYearInput = event.target;
        const releaseYearValue = releaseYearInput.value;
        const filterId = event.target.id;
        const filter = document.querySelector(`.filters .${filterId}`);
        if (releaseYearValue >= 1895) {
            let releaseYearItem = filter.querySelector('.discover-item');
            if (!releaseYearItem) {
                releaseYearItem = document.createElement('div');
                releaseYearItem.classList.add('discover-item');
                filter.appendChild(releaseYearItem);
            }
            releaseYearItem.textContent = releaseYearValue;
            filter.dataset.year = releaseYearValue;
        } else {
            let releaseYearItem = filter.querySelector('.discover-item');
            if (releaseYearItem) {
                releaseYearItem.remove();
                filter.dataset.year = "";
            }
        }
    }

    setSort(event) {
        const discoverSort = event.target;
        const discoverSortValue = discoverSort.value;
        const discoverSortOption = discoverSort.querySelector('option:checked');
        const discoverSortFilter = document.querySelector(".filters .discover-sort .discover-item");
        discoverSortFilter.textContent = discoverSortOption.textContent;
        discoverSortFilter.dataset.sort = discoverSortValue;
    }
}